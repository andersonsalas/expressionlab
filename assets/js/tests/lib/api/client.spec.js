import { setActivePinia, createPinia } from 'pinia';
import { useUiStore } from '../../../stores/ui.js';
import {
  handleFetch,
  requestChallenge,
  handleLocalStorage,
  handleDownload,
  handleClipboardCopy,
  handleOpenUrl,
  handleOpenTab,
  handleOpenFilePicker,
  handleOpenBinaryFilePicker,
  handleSaveBinaryFile,
  handleSaveBinaryFileAs,
  isLocalFsSupported,
} from '../../../lib/api/client.js';

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

  describe('Binary File Bridge Handlers (.elab)', () => {
    const dispatchParentMessage = (data, origin = 'http://example.com') => {
      const event = new Event('message');
      Object.defineProperties(event, {
        data: { value: data },
        origin: { value: origin },
        source: { value: window.parent },
      });
      window.dispatchEvent(event);
    };

    afterEach(() => {
      delete window.showOpenFilePicker;
      delete window.showSaveFilePicker;
      document.querySelectorAll('input[type="file"]').forEach((el) => el.remove());
    });

    it('handleOpenBinaryFilePicker reads ArrayBuffer and registers handleId in direct mode', async () => {
      const sampleBytes = new Uint8Array([0x45, 0x4c, 0x41, 0x42, 0x01]).buffer;
      const mockFile = {
        name: 'report.elab',
        size: 5,
        arrayBuffer: jest.fn().mockResolvedValue(sampleBytes),
      };
      const mockWritable = {
        write: jest.fn().mockResolvedValue(undefined),
        close: jest.fn().mockResolvedValue(undefined),
      };
      const mockFileHandle = {
        name: 'report.elab',
        getFile: jest.fn().mockResolvedValue(mockFile),
        createWritable: jest.fn().mockResolvedValue(mockWritable),
      };
      window.showOpenFilePicker = jest.fn().mockResolvedValue([mockFileHandle]);

      const opened = await handleOpenBinaryFilePicker({ description: 'ELAB Notebook' });

      expect(opened.success).toBe(true);
      expect(opened.filename).toBe('report.elab');
      expect(opened.size).toBe(5);
      expect(opened.fileHandleId).toBeTruthy();
      expect(opened.buffer).toBe(sampleBytes);

      const newBytes = new Uint8Array([0x45, 0x4c, 0x41, 0x42, 0x02]);
      const saved = await handleSaveBinaryFile({
        fileHandleId: opened.fileHandleId,
        buffer: newBytes,
      });

      expect(saved).toEqual({
        success: true,
        fileHandleId: opened.fileHandleId,
        filename: 'report.elab',
      });
      expect(mockFileHandle.createWritable).toHaveBeenCalledTimes(1);
      expect(mockWritable.write).toHaveBeenCalledTimes(1);
      expect(mockWritable.close).toHaveBeenCalledTimes(1);
    });

    it('handleSaveBinaryFileAs opens save picker and writes ArrayBuffer in direct mode', async () => {
      const mockWritable = {
        write: jest.fn().mockResolvedValue(undefined),
        close: jest.fn().mockResolvedValue(undefined),
      };
      const mockFileHandle = {
        name: 'saved-notebook.elab',
        createWritable: jest.fn().mockResolvedValue(mockWritable),
      };
      window.showSaveFilePicker = jest.fn().mockResolvedValue(mockFileHandle);

      const payload = new Uint8Array([1, 2, 3, 4]).buffer;
      const result = await handleSaveBinaryFileAs({
        buffer: payload,
        suggestedName: 'saved-notebook.elab',
      });

      expect(window.showSaveFilePicker).toHaveBeenCalledTimes(1);
      expect(result.success).toBe(true);
      expect(result.filename).toBe('saved-notebook.elab');
      expect(result.fileHandleId).toBeTruthy();
      expect(mockWritable.write).toHaveBeenCalledWith(payload);
      expect(mockWritable.close).toHaveBeenCalledTimes(1);
    });

    it('handleSaveBinaryFileAs supports getBuffer callback to defer buffer generation until after picker selection', async () => {
      const mockWritable = {
        write: jest.fn().mockResolvedValue(undefined),
        close: jest.fn().mockResolvedValue(undefined),
      };
      const mockFileHandle = {
        name: 'picked-file.elab',
        createWritable: jest.fn().mockResolvedValue(mockWritable),
      };
      window.showSaveFilePicker = jest.fn().mockResolvedValue(mockFileHandle);

      const generatedBuffer = new Uint8Array([9, 8, 7, 6]).buffer;
      const getBufferSpy = jest.fn().mockResolvedValue(generatedBuffer);

      const result = await handleSaveBinaryFileAs({
        getBuffer: getBufferSpy,
        suggestedName: 'initial.elab',
      });

      expect(window.showSaveFilePicker).toHaveBeenCalledTimes(1);
      expect(getBufferSpy).toHaveBeenCalledWith('picked-file.elab');
      expect(result.success).toBe(true);
      expect(result.filename).toBe('picked-file.elab');
      expect(mockWritable.write).toHaveBeenCalledWith(generatedBuffer);
    });

    it('transfers ArrayBuffer via postMessage when sandbox is enabled', async () => {
      window.el_settings.settings.enable_sandbox = true;
      window.el_settings.settings.debug_mode = false;

      const postMessageSpy = jest.spyOn(window.parent, 'postMessage').mockImplementation(() => {});
      const rawBuffer = new Uint8Array([10, 20, 30, 40]).buffer;

      const pending = handleSaveBinaryFile({ fileHandleId: 'bin_handle_1', buffer: rawBuffer });

      expect(postMessageSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          type: 'fs_save_file_binary',
          payload: expect.objectContaining({
            fileHandleId: 'bin_handle_1',
            buffer: rawBuffer,
          }),
          message_id: expect.any(String),
        }),
        'http://example.com',
        [rawBuffer]
      );

      const messageId = postMessageSpy.mock.calls[0][0].message_id;
      dispatchParentMessage({ response_to: messageId, success: true, fileHandleId: 'bin_handle_1', filename: 'a.elab' });

      await expect(pending).resolves.toEqual(expect.objectContaining({ success: true, fileHandleId: 'bin_handle_1' }));
      postMessageSpy.mockRestore();
    });

    it('ignores bridge responses from a foreign origin or with a different message id', async () => {
      jest.useFakeTimers();
      window.el_settings.settings.enable_sandbox = true;
      window.el_settings.settings.debug_mode = false;

      const postMessageSpy = jest.spyOn(window.parent, 'postMessage').mockImplementation(() => {});
      const pending = handleSaveBinaryFileAs({ buffer: new Uint8Array([1]).buffer });
      const messageId = postMessageSpy.mock.calls[0][0].message_id;

      dispatchParentMessage({ response_to: messageId, success: true }, 'http://evil.example');
      dispatchParentMessage({ response_to: 'other_id', success: true });
      jest.advanceTimersByTime(120000);

      await expect(pending).resolves.toEqual({ error: 'timeout', message: 'Binary file save timed out' });
      postMessageSpy.mockRestore();
      jest.useRealTimers();
    });

    it('opens binary files through the parent bridge without a transfer list in sandbox mode', async () => {
      window.el_settings.settings.enable_sandbox = true;
      window.el_settings.settings.debug_mode = false;

      const postMessageSpy = jest.spyOn(window.parent, 'postMessage').mockImplementation(() => {});
      const pending = handleOpenBinaryFilePicker({ extensions: ['.elab', '.exe'] });

      const [message, origin, transfer] = postMessageSpy.mock.calls[0];
      expect(message.type).toBe('fs_open_file_binary');
      expect(message.payload.extensions).toEqual(['.elab']);
      expect(origin).toBe('http://example.com');
      expect(transfer).toEqual([]);

      const received = new Uint8Array([7, 8, 9]).buffer;
      dispatchParentMessage({ response_to: message.message_id, success: true, buffer: received, filename: 'n.elab', fileHandleId: 'elab_fh_1' });

      const opened = await pending;
      expect(opened.success).toBe(true);
      expect(opened.buffer).toBe(received);
      expect(opened.fileHandleId).toBe('elab_fh_1');
      postMessageSpy.mockRestore();
    });

    it('copies only the viewed bytes of a partial TypedArray before transferring in sandbox mode', () => {
      window.el_settings.settings.enable_sandbox = true;
      window.el_settings.settings.debug_mode = false;

      const postMessageSpy = jest.spyOn(window.parent, 'postMessage').mockImplementation(() => {});
      const backing = new Uint8Array([0, 1, 2, 3, 4, 5, 6, 7]);
      const view = backing.subarray(2, 5);

      handleSaveBinaryFileAs({ buffer: view, suggestedName: '../../etc/report.exe' });

      const [message, , transfer] = postMessageSpy.mock.calls[0];
      expect(message.type).toBe('fs_save_file_as_binary');
      expect(message.payload.suggestedName).toBe('report.exe.elab');
      expect(transfer).toHaveLength(1);
      expect(transfer[0]).toBe(message.payload.buffer);
      expect(transfer[0]).not.toBe(backing.buffer);
      expect(Array.from(new Uint8Array(transfer[0]))).toEqual([2, 3, 4]);
      expect(backing.byteLength).toBe(8);
      postMessageSpy.mockRestore();
    });

    it('resolves with an error and cleans up when postMessage throws a DataCloneError', async () => {
      window.el_settings.settings.enable_sandbox = true;
      window.el_settings.settings.debug_mode = false;

      const cloneError = new Error('ArrayBuffer is not detachable');
      cloneError.name = 'DataCloneError';
      const postMessageSpy = jest.spyOn(window.parent, 'postMessage').mockImplementation(() => {
        throw cloneError;
      });
      const removeSpy = jest.spyOn(window, 'removeEventListener');

      const result = await handleSaveBinaryFile({ buffer: new Uint8Array([1, 2]).buffer });

      expect(result).toEqual({ error: 'DataCloneError', message: 'ArrayBuffer is not detachable' });
      expect(removeSpy).toHaveBeenCalledWith('message', expect.any(Function));
      postMessageSpy.mockRestore();
      removeSpy.mockRestore();
    });

    it.each([
      ['missing buffer', undefined],
      ['string payload', 'not-a-buffer'],
      ['plain object', { byteLength: 4 }],
    ])('rejects invalid buffers (%s) without prompting', async (_label, buffer) => {
      window.showSaveFilePicker = jest.fn();

      await expect(handleSaveBinaryFile({ buffer })).resolves.toEqual({
        error: 'invalid_buffer',
        message: 'Binary buffer is required to save file.',
      });
      await expect(handleSaveBinaryFileAs({ buffer })).resolves.toEqual({
        error: 'invalid_buffer',
        message: 'Binary buffer is required to save file.',
      });
      expect(window.showSaveFilePicker).not.toHaveBeenCalled();
    });

    it('rejects detached buffers instead of failing inside postMessage', async () => {
      const detached = new ArrayBuffer(4);
      Object.defineProperty(detached, 'detached', { value: true });

      await expect(handleSaveBinaryFileAs({ buffer: detached })).resolves.toEqual({
        error: 'invalid_buffer',
        message: 'Binary buffer is required to save file.',
      });
    });

    it('returns cancelled when the open picker is dismissed (AbortError)', async () => {
      const abort = new Error('The user aborted a request.');
      abort.name = 'AbortError';
      window.showOpenFilePicker = jest.fn().mockRejectedValue(abort);

      await expect(handleOpenBinaryFilePicker()).resolves.toEqual({ cancelled: true });
    });

    it('returns cancelled when the save picker is dismissed (AbortError)', async () => {
      const abort = new Error('The user aborted a request.');
      abort.name = 'AbortError';
      window.showSaveFilePicker = jest.fn().mockRejectedValue(abort);

      await expect(handleSaveBinaryFileAs({ buffer: new Uint8Array([1]).buffer })).resolves.toEqual({ cancelled: true });
    });

    it('falls back to <input type="file"> when showOpenFilePicker raises SecurityError', async () => {
      const securityError = new Error('Cross origin sub frames are not allowed');
      securityError.name = 'SecurityError';
      window.showOpenFilePicker = jest.fn().mockRejectedValue(securityError);

      const pending = handleOpenBinaryFilePicker();
      await new Promise((resolve) => setTimeout(resolve, 0));

      const input = document.querySelector('input[type="file"]');
      expect(input).not.toBeNull();
      expect(input.accept).toBe('.elab');

      const bytes = new Uint8Array([1, 2, 3]).buffer;
      const file = { name: 'fallback.elab', arrayBuffer: jest.fn().mockResolvedValue(bytes) };
      Object.defineProperty(input, 'files', { value: [file] });
      await input.onchange({ target: input });

      await expect(pending).resolves.toEqual({
        success: true,
        buffer: bytes,
        size: 3,
        filename: 'fallback.elab',
        fileHandleId: null,
      });
      expect(document.querySelector('input[type="file"]')).toBeNull();
    });

    it('falls back to a sanitized blob download when showSaveFilePicker raises SecurityError', async () => {
      const securityError = new Error('Blocked');
      securityError.name = 'SecurityError';
      window.showSaveFilePicker = jest.fn().mockRejectedValue(securityError);

      const origCreateObjectURL = URL.createObjectURL;
      URL.createObjectURL = jest.fn(() => 'blob:http://example.com/elab-uuid');
      const clickMock = jest.fn();
      const origCreateElement = document.createElement.bind(document);
      let anchor = null;
      jest.spyOn(document, 'createElement').mockImplementation((tag) => {
        const el = origCreateElement(tag);
        if (tag === 'a') {
          el.click = clickMock;
          anchor = el;
        }
        return el;
      });

      const result = await handleSaveBinaryFileAs({ buffer: new Uint8Array([1]).buffer, suggestedName: 'evil.html' });

      expect(result).toEqual({ success: true, filename: 'evil.html.elab', fileHandleId: null, fallbackDownload: true });
      expect(clickMock).toHaveBeenCalled();
      expect(anchor.getAttribute('download')).toBe('evil.html.elab');

      URL.createObjectURL = origCreateObjectURL;
      document.createElement.mockRestore();
    });

    it('evicts a stale handle and falls back to Save As when the direct write fails', async () => {
      const opened = { name: 'stale.elab', arrayBuffer: jest.fn().mockResolvedValue(new ArrayBuffer(1)) };
      const staleHandle = {
        name: 'stale.elab',
        getFile: jest.fn().mockResolvedValue(opened),
        createWritable: jest.fn().mockRejectedValue(Object.assign(new Error('Revoked'), { name: 'NotAllowedError' })),
      };
      window.showOpenFilePicker = jest.fn().mockResolvedValue([staleHandle]);
      const { fileHandleId } = await handleOpenBinaryFilePicker();

      const saveWritable = { write: jest.fn().mockResolvedValue(undefined), close: jest.fn().mockResolvedValue(undefined) };
      const newHandle = { name: 'renamed.elab', createWritable: jest.fn().mockResolvedValue(saveWritable) };
      window.showSaveFilePicker = jest.fn().mockResolvedValue(newHandle);

      const first = await handleSaveBinaryFile({ fileHandleId, buffer: new Uint8Array([1]).buffer });
      expect(first.success).toBe(true);
      expect(first.filename).toBe('renamed.elab');
      expect(first.fileHandleId).not.toBe(fileHandleId);

      await handleSaveBinaryFile({ fileHandleId, buffer: new Uint8Array([2]).buffer });
      expect(staleHandle.createWritable).toHaveBeenCalledTimes(1);
      expect(window.showSaveFilePicker).toHaveBeenCalledTimes(2);
    });

    it('aborts the writable stream when writing fails', async () => {
      const writeError = Object.assign(new Error('Disk full'), { name: 'QuotaExceededError' });
      const writable = {
        write: jest.fn().mockRejectedValue(writeError),
        close: jest.fn(),
        abort: jest.fn().mockResolvedValue(undefined),
      };
      window.showSaveFilePicker = jest.fn().mockResolvedValue({ name: 'x.elab', createWritable: jest.fn().mockResolvedValue(writable) });

      const result = await handleSaveBinaryFileAs({ buffer: new Uint8Array([1]).buffer });

      expect(result).toEqual({ error: 'QuotaExceededError', message: 'Disk full' });
      expect(writable.abort).toHaveBeenCalledTimes(1);
      expect(writable.close).not.toHaveBeenCalled();
    });

    it('fails closed when getBuffer throws an Error or plain engine string in handleSaveBinaryFile without evicting fileHandleId or falling back to Save As (Directive D3-3)', async () => {
      const opened = { name: 'existing.elab', arrayBuffer: jest.fn().mockResolvedValue(new ArrayBuffer(1)) };
      const writable = { write: jest.fn().mockResolvedValue(undefined), close: jest.fn().mockResolvedValue(undefined) };
      const existingHandle = {
        name: 'existing.elab',
        getFile: jest.fn().mockResolvedValue(opened),
        createWritable: jest.fn().mockResolvedValue(writable),
      };
      window.showOpenFilePicker = jest.fn().mockResolvedValue([existingHandle]);
      const { fileHandleId } = await handleOpenBinaryFilePicker();

      window.showSaveFilePicker = jest.fn();

      // 1. Plain string thrown by the engine (e.g. size-limit error)
      const failRes = await handleSaveBinaryFile({
        fileHandleId,
        getBuffer: () => {
          throw 'Notebook too large to export: SQLite image exceeds 256 MiB limit';
        },
      });

      expect(failRes).toEqual({
        error: 'export_error',
        message: 'Notebook too large to export: SQLite image exceeds 256 MiB limit',
      });
      // Must NOT fall back to Save As picker or write anything
      expect(window.showSaveFilePicker).not.toHaveBeenCalled();
      expect(existingHandle.createWritable).not.toHaveBeenCalled();

      // 2. Subsequent save with valid buffer succeeds using the preserved fileHandleId
      const okRes = await handleSaveBinaryFile({
        fileHandleId,
        getBuffer: () => new Uint8Array([9, 8, 7]),
      });
      expect(okRes.success).toBe(true);
      expect(okRes.fileHandleId).toBe(fileHandleId);
      expect(existingHandle.createWritable).toHaveBeenCalledTimes(1);
      expect(window.showSaveFilePicker).not.toHaveBeenCalled();
    });

    it('fails closed when getBuffer throws in handleSaveBinaryFileAs without writing an empty buffer', async () => {
      const writable = { write: jest.fn(), close: jest.fn() };
      const saveHandle = { name: 'new.elab', createWritable: jest.fn().mockResolvedValue(writable) };
      window.showSaveFilePicker = jest.fn().mockResolvedValue(saveHandle);

      const res = await handleSaveBinaryFileAs({
        getBuffer: () => {
          throw new Error('Password cannot be empty');
        },
      });

      expect(res).toEqual({
        error: 'Error',
        message: 'Password cannot be empty',
      });
      expect(saveHandle.createWritable).not.toHaveBeenCalled();
      expect(writable.write).not.toHaveBeenCalled();
    });
  });
});
