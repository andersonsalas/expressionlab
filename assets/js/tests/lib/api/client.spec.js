import { setActivePinia, createPinia } from 'pinia';
import { useUiStore } from '../../../stores/ui.js';
import { handleFetch, requestChallenge, handleLocalStorage, handleDownload, handleClipboardCopy, handleOpenUrl, handleOpenTab, handleOpenFilePicker, isLocalFsSupported } from '../../../lib/api/client.js';

describe('lib/api/client.js', () => {
  let originalFetch;

  beforeEach(() => {
    setActivePinia(createPinia());
    originalFetch = global.fetch;
    global.fetch = jest.fn();

    window.el_settings = {
      ajax_url: 'http://example.com/wp-admin/admin-ajax.php',
      nonce: 'api_test_nonce',
      settings: {
        enable_sandbox: false, // Testing direct fetch mode
        debug_mode: true,
        signature_duration: 30,
      },
    };
  });

  afterEach(() => {
    global.fetch = originalFetch;
    delete window.el_settings;
    jest.clearAllMocks();
  });

  describe('handleFetch - Query & Body Parameter Injection', () => {
    it('appends challenge_nonce and timestamp to GET query parameters', async () => {
      const uiStore = useUiStore();
      uiStore.setChallenge({ nonce: 'nonce_123', timestamp: 1700000000 });

      global.fetch.mockResolvedValueOnce({
        clone: () => ({ json: () => Promise.resolve({ success: true }) }),
        json: () => Promise.resolve({ success: true }),
      });

      await handleFetch('http://example.com/api?action=test', { method: 'GET' });

      expect(global.fetch).toHaveBeenCalledTimes(1);
      const calledUrl = global.fetch.mock.calls[0][0];
      const urlObj = new URL(calledUrl);
      expect(urlObj.searchParams.get('challenge_nonce')).toBe('nonce_123');
      expect(urlObj.searchParams.get('timestamp')).toBe('1700000000');
      expect(urlObj.searchParams.get('action')).toBe('test');
    });

    it('appends challenge_nonce and timestamp to POST body (URLSearchParams)', async () => {
      const uiStore = useUiStore();
      uiStore.setChallenge({ nonce: 'nonce_post', timestamp: 1700000001 });

      global.fetch.mockResolvedValueOnce({
        clone: () => ({ json: () => Promise.resolve({ success: true }) }),
        json: () => Promise.resolve({ success: true }),
      });

      const body = new URLSearchParams();
      body.set('code', '2 + 2');

      await handleFetch('http://example.com/api', { method: 'POST', body });

      expect(global.fetch).toHaveBeenCalledTimes(1);
      const options = global.fetch.mock.calls[0][1];
      expect(options.body).toContain('challenge_nonce=nonce_post');
      expect(options.body).toContain('timestamp=1700000001');
      expect(options.body).toContain('code=2+%2B+2');
    });
  });

  describe('handleFetch - Cryptographic Signing', () => {
    it('generates and attaches X-ExpressionLab-Signature header when privateKey is present', async () => {
      const uiStore = useUiStore();
      const mockKey = new Uint8Array(32).fill(5);
      uiStore.unlock(mockKey);

      global.fetch.mockResolvedValueOnce({
        clone: () => ({ json: () => Promise.resolve({ success: true }) }),
        json: () => Promise.resolve({ success: true }),
      });

      await handleFetch('http://example.com/api?query=1', { method: 'GET' });

      expect(global.fetch).toHaveBeenCalledTimes(1);
      const options = global.fetch.mock.calls[0][1];
      expect(options.headers).toBeDefined();
      expect(options.headers['X-ExpressionLab-Signature']).toBeTruthy();
      expect(typeof options.headers['X-ExpressionLab-Signature']).toBe('string');
    });
  });

  describe('handleFetch - Challenge Negotiation & Retry Flow', () => {
    it('retries request automatically when server returns challenge_required', async () => {
      // First fetch returns challenge_required
      global.fetch
        .mockResolvedValueOnce({
          clone: () => ({
            json: () => Promise.resolve({
              success: false,
              data: { code: 'challenge_required' },
            }),
          }),
          json: () => Promise.resolve({
            success: false,
            data: { code: 'challenge_required' },
          }),
        })
        // Second fetch is for requestChallenge
        .mockResolvedValueOnce({
          clone: () => ({
            json: () => Promise.resolve({
              success: true,
              data: { challenge: { nonce: 'new_nonce_456', ts: 1700000050 } },
            }),
          }),
          json: () => Promise.resolve({
            success: true,
            data: { challenge: { nonce: 'new_nonce_456', ts: 1700000050 } },
          }),
        })
        // Third fetch is the retried original request with new challenge
        .mockResolvedValueOnce({
          clone: () => ({
            json: () => Promise.resolve({
              success: true,
              data: { evaluated: 42 },
            }),
          }),
          json: () => Promise.resolve({
            success: true,
            data: { evaluated: 42 },
          }),
        });

      const response = await handleFetch('http://example.com/api?action=eval');
      const data = await response.json();

      expect(data.success).toBe(true);
      expect(data.data.evaluated).toBe(42);
      expect(global.fetch).toHaveBeenCalledTimes(3);
    });

    it('updates uiStore with new challenge when response payload contains one', async () => {
      const uiStore = useUiStore();

      global.fetch.mockResolvedValueOnce({
        clone: () => ({
          json: () => Promise.resolve({
            success: true,
            data: {
              challenge: { nonce: 'renewed_nonce', timestamp: 1700000999 },
            },
          }),
        }),
        json: () => Promise.resolve({
          success: true,
          data: {
            challenge: { nonce: 'renewed_nonce', timestamp: 1700000999 },
          },
        }),
      });

      await handleFetch('http://example.com/api');

      expect(uiStore.currentChallenge).toEqual({
        nonce: 'renewed_nonce',
        timestamp: 1700000999,
      });
    });
  });

  describe('handleLocalStorage', () => {
    it('delegates getItem, setItem, and removeItem correctly', async () => {
      const setSpy = jest.spyOn(Storage.prototype, 'setItem');
      const getSpy = jest.spyOn(Storage.prototype, 'getItem').mockReturnValue('stored_value');
      const removeSpy = jest.spyOn(Storage.prototype, 'removeItem');

      await handleLocalStorage('setItem', 'test_key', 'test_val');
      expect(setSpy).toHaveBeenCalledWith('test_key', 'test_val');

      const val = await handleLocalStorage('getItem', 'test_key');
      expect(val).toBe('stored_value');
      expect(getSpy).toHaveBeenCalledWith('test_key');

      await handleLocalStorage('removeItem', 'test_key');
      expect(removeSpy).toHaveBeenCalledWith('test_key');

      setSpy.mockRestore();
      getSpy.mockRestore();
      removeSpy.mockRestore();
    });
  });

  describe('handleDownload', () => {
    it('creates an anchor, sets download attributes, and triggers click', () => {
      const origCreateObjectURL = URL.createObjectURL;
      URL.createObjectURL = jest.fn(() => 'blob:http://example.com/test-uuid');

      const clickMock = jest.fn();
      const origCreateElement = document.createElement.bind(document);
      jest.spyOn(document, 'createElement').mockImplementation((tag) => {
        const el = origCreateElement(tag);
        if (tag === 'a') {
          el.click = clickMock;
        }
        return el;
      });

      handleDownload('{"snippet": 1}', 'snippets.json', 'application/json');

      expect(URL.createObjectURL).toHaveBeenCalled();
      expect(clickMock).toHaveBeenCalled();

      URL.createObjectURL = origCreateObjectURL;
      document.createElement.mockRestore();
    });
  });

  describe('handleClipboardCopy', () => {
    it('copies text via navigator.clipboard when available in secure context', async () => {
      const writeTextMock = jest.fn().mockResolvedValue(undefined);
      window.isSecureContext = true;
      Object.assign(navigator, {
        clipboard: { writeText: writeTextMock },
      });

      handleClipboardCopy('sample text to copy');
      expect(writeTextMock).toHaveBeenCalledWith('sample text to copy');
    });
  });

  describe('handleOpenUrl', () => {
    it('opens URL directly via window.open when sandbox is disabled', () => {
      const origOpen = window.open;
      window.open = jest.fn();

      handleOpenUrl('https://example.com/docs');

      expect(window.open).toHaveBeenCalledWith('https://example.com/docs', '_blank', 'noopener,noreferrer');

      window.open = origOpen;
    });

    it('ignores invalid or empty URLs', () => {
      const origOpen = window.open;
      window.open = jest.fn();

      handleOpenUrl('');
      handleOpenUrl(null);
      handleOpenUrl(undefined);

      expect(window.open).not.toHaveBeenCalled();

      window.open = origOpen;
    });

    it('sends postMessage to window.parent when sandbox is enabled', () => {
      window.el_settings.settings.enable_sandbox = true;
      window.el_settings.settings.debug_mode = false;

      const postMessageSpy = jest.spyOn(window.parent, 'postMessage').mockImplementation(() => {});

      handleOpenUrl('https://developer.wordpress.org/reference/');

      expect(postMessageSpy).toHaveBeenCalledTimes(1);
      expect(postMessageSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          type: 'open_url',
          payload: { url: 'https://developer.wordpress.org/reference/' },
        }),
        'http://example.com'
      );

      postMessageSpy.mockRestore();
    });
  });

  describe('handleOpenTab', () => {
    it('delegates to handleOpenUrl when passed an HTTP/HTTPS URL string', () => {
      const origOpen = window.open;
      window.open = jest.fn();

      handleOpenTab('https://expressionlab.io');

      expect(window.open).toHaveBeenCalledWith('https://expressionlab.io', '_blank', 'noopener,noreferrer');

      window.open = origOpen;
    });

    it('delegates to handleOpenUrl when mimeType is "url"', () => {
      const origOpen = window.open;
      window.open = jest.fn();

      handleOpenTab('/local-path', 'url');

      expect(window.open).toHaveBeenCalledWith('/local-path', '_blank', 'noopener,noreferrer');

      window.open = origOpen;
    });

    it('opens blob in new window when passed raw content and sandbox is disabled', () => {
      const origCreateObjectURL = URL.createObjectURL;
      URL.createObjectURL = jest.fn(() => 'blob:http://example.com/tab-uuid');
      const origOpen = window.open;
      window.open = jest.fn();

      handleOpenTab('{"test": 123}', 'application/json');

      expect(URL.createObjectURL).toHaveBeenCalled();
      expect(window.open).toHaveBeenCalledWith('blob:http://example.com/tab-uuid', '_blank');

      URL.createObjectURL = origCreateObjectURL;
      window.open = origOpen;
    });

    it('sends postMessage to parent when passed raw content and sandbox is enabled', () => {
      window.el_settings.settings.enable_sandbox = true;
      window.el_settings.settings.debug_mode = false;

      const postMessageSpy = jest.spyOn(window.parent, 'postMessage').mockImplementation(() => {});

      handleOpenTab('{"test": 456}', 'application/json');

      expect(postMessageSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          type: 'open_tab',
          payload: { content: '{"test": 456}', mimeType: 'application/json' },
        }),
        'http://example.com'
      );

      postMessageSpy.mockRestore();
    });
  });

  describe('handleOpenFilePicker', () => {
    let originalShowOpenFilePicker;

    beforeEach(() => {
      originalShowOpenFilePicker = window.showOpenFilePicker;
    });

    afterEach(() => {
      window.showOpenFilePicker = originalShowOpenFilePicker;
    });

    it('reads file content using native window.showOpenFilePicker when available in direct mode', async () => {
      const mockFile = {
        name: 'snippets.json',
        text: jest.fn().mockResolvedValue('{"snippets": [{"id": 1}]}'),
      };
      const mockFileHandle = {
        getFile: jest.fn().mockResolvedValue(mockFile),
      };
      window.showOpenFilePicker = jest.fn().mockResolvedValue([mockFileHandle]);

      const result = await handleOpenFilePicker();

      expect(window.showOpenFilePicker).toHaveBeenCalledTimes(1);
      expect(result).toEqual({
        success: true,
        content: '{"snippets": [{"id": 1}]}',
        filename: 'snippets.json',
      });
    });

    it('returns cancelled: true when user aborts native window.showOpenFilePicker', async () => {
      const abortError = new DOMException('The user aborted a request.', 'AbortError');
      window.showOpenFilePicker = jest.fn().mockRejectedValue(abortError);

      const result = await handleOpenFilePicker();

      expect(result).toEqual({ cancelled: true });
    });

    it('returns error when native window.showOpenFilePicker throws an unhandled error', async () => {
      const genericError = new DOMException('Operation failed', 'UnknownError');
      window.showOpenFilePicker = jest.fn().mockRejectedValue(genericError);

      const result = await handleOpenFilePicker();

      expect(result).toEqual({
        error: 'UnknownError',
        message: 'Operation failed',
      });
    });

    it('falls back to input[type=file] when native showOpenFilePicker throws SecurityError (Playground / cross-origin iframe)', async () => {
      const securityError = new DOMException(
        'Failed to execute \'showOpenFilePicker\' on \'Window\': Cross origin sub frames aren\'t allowed to show a file picker.',
        'SecurityError'
      );
      window.showOpenFilePicker = jest.fn().mockRejectedValue(securityError);

      const origCreateElement = document.createElement.bind(document);
      jest.spyOn(document, 'createElement').mockImplementation((tag) => {
        const el = origCreateElement(tag);
        if (tag === 'input') {
          el.click = jest.fn().mockImplementation(() => {
            const mockFile = new Blob(['{"imported": true}'], { type: 'application/json' });
            mockFile.name = 'imported.json';
            Object.defineProperty(el, 'files', {
              value: [mockFile],
              configurable: true,
            });
            if (typeof el.onchange === 'function') {
              el.onchange({ target: el });
            }
          });
        }
        return el;
      });

      const result = await handleOpenFilePicker();

      expect(window.showOpenFilePicker).toHaveBeenCalledTimes(1);
      expect(result.success).toBe(true);
      expect(result.content).toBe('{"imported": true}');
      expect(result.filename).toBe('imported.json');

      document.createElement.mockRestore();
    });

    it('falls back to input[type=file] when native showOpenFilePicker throws NotAllowedError', async () => {
      const notAllowedError = new DOMException('Permission denied', 'NotAllowedError');
      window.showOpenFilePicker = jest.fn().mockRejectedValue(notAllowedError);

      const origCreateElement = document.createElement.bind(document);
      jest.spyOn(document, 'createElement').mockImplementation((tag) => {
        const el = origCreateElement(tag);
        if (tag === 'input') {
          el.click = jest.fn().mockImplementation(() => {
            if (typeof el.oncancel === 'function') {
              el.oncancel();
            }
          });
        }
        return el;
      });

      const result = await handleOpenFilePicker();

      expect(window.showOpenFilePicker).toHaveBeenCalledTimes(1);
      expect(result).toEqual({ cancelled: true });

      document.createElement.mockRestore();
    });

    it('uses input[type=file] directly when window.showOpenFilePicker is undefined (e.g. Firefox/Safari)', async () => {
      delete window.showOpenFilePicker;

      const origCreateElement = document.createElement.bind(document);
      jest.spyOn(document, 'createElement').mockImplementation((tag) => {
        const el = origCreateElement(tag);
        if (tag === 'input') {
          el.click = jest.fn().mockImplementation(() => {
            const mockFile = new Blob(['{"browser": "safari"}'], { type: 'application/json' });
            mockFile.name = 'safari-export.json';
            Object.defineProperty(el, 'files', {
              value: [mockFile],
              configurable: true,
            });
            if (typeof el.onchange === 'function') {
              el.onchange({ target: el });
            }
          });
        }
        return el;
      });

      const result = await handleOpenFilePicker();

      expect(result.success).toBe(true);
      expect(result.content).toBe('{"browser": "safari"}');
      expect(result.filename).toBe('safari-export.json');

      document.createElement.mockRestore();
    });

    it('sends postMessage to window.parent when sandbox is enabled', async () => {
      window.el_settings.settings.enable_sandbox = true;
      window.el_settings.settings.debug_mode = false;

      const postMessageSpy = jest.spyOn(window.parent, 'postMessage').mockImplementation(() => {});

      handleOpenFilePicker();

      expect(postMessageSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          type: 'fs_import_file',
          message_id: expect.any(String),
        }),
        'http://example.com'
      );

      postMessageSpy.mockRestore();
    });
  });

  describe('isLocalFsSupported', () => {
    let originalShowDirectoryPicker;

    beforeEach(() => {
      originalShowDirectoryPicker = window.showDirectoryPicker;
    });

    afterEach(() => {
      window.showDirectoryPicker = originalShowDirectoryPicker;
      if (window.el_settings?.settings) {
        delete window.el_settings.settings.is_playground;
      }
      if (window.el_settings?.server) {
        delete window.el_settings.server.is_playground;
      }
    });

    it('returns true when direct mode, top-level window, not in playground, and showDirectoryPicker exists', () => {
      window.showDirectoryPicker = jest.fn();
      expect(isLocalFsSupported()).toBe(true);
    });

    it('returns false when in WordPress Playground via settings flag', () => {
      window.showDirectoryPicker = jest.fn();
      window.el_settings.settings.is_playground = true;
      expect(isLocalFsSupported()).toBe(false);
    });

    it('returns false when in WordPress Playground via server flag', () => {
      window.showDirectoryPicker = jest.fn();
      window.el_settings.server = { is_playground: true };
      expect(isLocalFsSupported()).toBe(false);
    });

    it('returns false when showDirectoryPicker is undefined (Firefox / Safari)', () => {
      delete window.showDirectoryPicker;
      expect(isLocalFsSupported()).toBe(false);
    });
  });
});
