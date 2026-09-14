import { setActivePinia, createPinia } from 'pinia';
import { useOutlineStore } from '../../stores/outline.js';
import { handleFetch, handleLocalStorage } from '../../lib/api/client.js';

jest.mock('../../lib/api/client.js', () => ({
  handleFetch: jest.fn(),
  handleLocalStorage: jest.fn(),
}));

const mockRawOutlineData = {
  objects: {
    Users: {
      summary: 'Users helper',
      methods: {
        find: {
          summary: 'Find user',
          signature: 'Users.find(id)',
          return: { type: 'User' }
        }
      }
    }
  },
  functions: {
    rand: {
      summary: 'Random number',
      signature: 'rand(min, max)'
    }
  },
  constants: {
    PHP_VERSION: {
      summary: 'PHP Version string',
      type: 'string',
      value: '8.2.0'
    }
  },
  typeRegistry: {
    Users: {
      find: { type: 'method', returnType: 'User' }
    }
  }
};

describe('stores/outline.js', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    jest.clearAllMocks();
    window.el_settings = {
      ajax_url: 'http://example.com/wp-admin/admin-ajax.php',
      nonce: 'outline_nonce',
      settings: {
        enable_cache: false,
      }
    };
  });

  afterEach(() => {
    delete window.el_settings;
  });

  it('loads and processes outline data via API when not cached', async () => {
    handleFetch.mockResolvedValueOnce({
      json: () => Promise.resolve({
        success: true,
        data: { data: mockRawOutlineData }
      })
    });

    const store = useOutlineStore();
    expect(store.loaded).toBe(false);
    expect(store.loading).toBe(true);

    await store.loadOutline();

    expect(handleFetch).toHaveBeenCalledWith(
      'http://example.com/wp-admin/admin-ajax.php?action=expressionlab_get_outline&nonce=outline_nonce',
      { method: 'GET' }
    );
    expect(store.loaded).toBe(true);
    expect(store.loading).toBe(false);
    expect(store.outlineFlat.length).toBeGreaterThan(0);
    expect(store.outlineTree.length).toBeGreaterThan(0);
    expect(store.outlineChained).not.toBeNull();
  });

  it('does not re-fetch if already loaded and force is false', async () => {
    handleFetch.mockResolvedValueOnce({
      json: () => Promise.resolve({
        success: true,
        data: { data: mockRawOutlineData }
      })
    });

    const store = useOutlineStore();
    await store.loadOutline();
    expect(handleFetch).toHaveBeenCalledTimes(1);

    await store.loadOutline();
    expect(handleFetch).toHaveBeenCalledTimes(1);
  });

  it('uses localStorage cached outline data when cache is enabled', async () => {
    window.el_settings.settings.enable_cache = true;
    handleLocalStorage.mockResolvedValueOnce(JSON.stringify(mockRawOutlineData));

    const store = useOutlineStore();
    await store.loadOutline();

    expect(handleLocalStorage).toHaveBeenCalledWith('getItem', 'el_outline_cache');
    expect(handleFetch).not.toHaveBeenCalled();
    expect(store.loaded).toBe(true);
    expect(store.loading).toBe(false);
    expect(store.outlineFlat.length).toBeGreaterThan(0);
  });

  it('saves outline data to localStorage when cache is enabled and data is fetched', async () => {
    window.el_settings.settings.enable_cache = true;
    handleLocalStorage.mockResolvedValueOnce(null);
    handleFetch.mockResolvedValueOnce({
      json: () => Promise.resolve({
        success: true,
        data: { data: mockRawOutlineData }
      })
    });

    const store = useOutlineStore();
    await store.loadOutline();

    expect(handleFetch).toHaveBeenCalled();
    expect(handleLocalStorage).toHaveBeenCalledWith(
      'setItem',
      'el_outline_cache',
      JSON.stringify(mockRawOutlineData)
    );
    expect(store.loaded).toBe(true);
  });

  it('handles API failure gracefully with empty outline', async () => {
    handleFetch.mockResolvedValueOnce({
      json: () => Promise.resolve({
        success: false
      })
    });

    const store = useOutlineStore();
    await store.loadOutline();

    expect(store.loaded).toBe(false);
    expect(store.loading).toBe(false);
    expect(store.outlineFlat).toEqual([]);
    expect(store.outlineTree).toEqual([]);
    expect(store.outlineChained).toBeNull();
  });
});
