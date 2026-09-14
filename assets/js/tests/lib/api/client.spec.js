import { setActivePinia, createPinia } from 'pinia';
import { useUiStore } from '../../../stores/ui.js';
import { handleFetch, requestChallenge, handleLocalStorage, handleDownload, handleClipboardCopy, handleOpenUrl, handleOpenTab } from '../../../lib/api/client.js';

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
});

