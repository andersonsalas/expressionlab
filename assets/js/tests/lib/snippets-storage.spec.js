import {
  loadSavedSnippets,
  persistSavedSnippets,
  saveSnippetToLibrary,
  SNIPPETS_STORAGE_KEY,
  USE_LOCAL_FS_KEY,
} from '../../lib/snippets-storage.js';
import * as client from '../../lib/api/client.js';

jest.mock('../../lib/api/client.js', () => ({
  handleLocalStorage: jest.fn(),
  handleReadLocalSnippets: jest.fn(),
  handleWriteLocalSnippets: jest.fn(),
}));

describe('snippets-storage.js', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('loadSavedSnippets', () => {
    it('returns snippets from localStorage when local FS is disabled', async () => {
      client.handleLocalStorage.mockImplementation(async (action, key) => {
        if (key === USE_LOCAL_FS_KEY) return 'false';
        if (key === SNIPPETS_STORAGE_KEY) {
          return JSON.stringify([{ id: 'test', name: 'test', code: '1+1', tags: [] }]);
        }
        return null;
      });

      const res = await loadSavedSnippets();
      expect(res.useLocalFs).toBe(false);
      expect(res.snippets).toHaveLength(1);
      expect(res.snippets[0].name).toBe('test');
    });

    it('returns snippets from local FS when enabled', async () => {
      client.handleLocalStorage.mockImplementation(async (action, key) => {
        if (key === USE_LOCAL_FS_KEY) return 'true';
        return null;
      });
      client.handleReadLocalSnippets.mockResolvedValueOnce({
        success: true,
        snippets: [{ id: 'fs-snippet', name: 'fs-snippet', code: '2+2', tags: [] }],
      });

      const res = await loadSavedSnippets();
      expect(res.useLocalFs).toBe(true);
      expect(res.snippets).toHaveLength(1);
      expect(res.snippets[0].name).toBe('fs-snippet');
    });

    it('falls back to empty array if localStorage is empty or corrupt', async () => {
      client.handleLocalStorage.mockImplementation(async (action, key) => {
        if (key === USE_LOCAL_FS_KEY) return 'false';
        if (key === SNIPPETS_STORAGE_KEY) return 'invalid-json{{{';
        return null;
      });

      const res = await loadSavedSnippets();
      expect(res.useLocalFs).toBe(false);
      expect(res.snippets).toEqual([]);
    });
  });

  describe('persistSavedSnippets', () => {
    it('persists to localStorage when useLocalFs is false', async () => {
      const snippets = [{ id: 's1', name: 's1', code: 'a', tags: [] }];
      const success = await persistSavedSnippets(snippets, false);

      expect(success).toBe(true);
      expect(client.handleLocalStorage).toHaveBeenCalledWith(
        'setItem',
        SNIPPETS_STORAGE_KEY,
        JSON.stringify(snippets, null, 2)
      );
    });

    it('persists to local FS when useLocalFs is true', async () => {
      client.handleWriteLocalSnippets.mockResolvedValueOnce({ success: true });
      const snippets = [{ id: 's1', name: 's1', code: 'a', tags: [] }];
      const success = await persistSavedSnippets(snippets, true);

      expect(success).toBe(true);
      expect(client.handleWriteLocalSnippets).toHaveBeenCalledWith(snippets);
    });
  });

  describe('saveSnippetToLibrary', () => {
    it('creates a new snippet and saves it', async () => {
      client.handleLocalStorage.mockImplementation(async (action, key) => {
        if (key === USE_LOCAL_FS_KEY) return 'false';
        if (key === SNIPPETS_STORAGE_KEY) return '[]';
        return null;
      });

      const code = 'Users.all()';
      const snippet = await saveSnippetToLibrary(code);

      expect(snippet.code).toBe(code);
      expect(snippet.tags).toContain('scratchpad');
      expect(snippet.name).toBe('Scratchpad Snippet');
      expect(client.handleLocalStorage).toHaveBeenCalledWith(
        'setItem',
        SNIPPETS_STORAGE_KEY,
        expect.stringContaining('Scratchpad Snippet')
      );
    });

    it('handles unique names when a snippet already exists', async () => {
      client.handleLocalStorage.mockImplementation(async (action, key) => {
        if (key === USE_LOCAL_FS_KEY) return 'false';
        if (key === SNIPPETS_STORAGE_KEY) {
          return JSON.stringify([{ id: 'Scratchpad Snippet', name: 'Scratchpad Snippet', code: '', tags: [] }]);
        }
        return null;
      });

      const snippet = await saveSnippetToLibrary('1+1');
      expect(snippet.name).toBe('Scratchpad Snippet (1)');
    });
  });
});
