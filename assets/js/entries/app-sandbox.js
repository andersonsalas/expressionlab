/**
 * Expression Lab (Sandbox wrapper)
 * (c) Anderson Salas <github@andersonsalas.com>
 * @license GPL-2.0+
 **/
import '../../scss/expressionlab-app-sandbox.scss';
import { sendToSandbox } from '../lib/sandbox-communication.js';
import { debugLog } from '../lib/helpers.js';

document.addEventListener('DOMContentLoaded', function () {
  const iframe = document.getElementById('expressionlab-sandbox');

  if (!iframe) {
    debugLog('Sandbox iframe not found.', null, 'error');
    return;
  }

  debugLog('Sandbox script loaded.');

  /**
   * Validate that a message comes from the sandbox iframe.
   */
  const isValidSandboxMessage = (event) => {
    if (event.source !== iframe.contentWindow) {
      debugLog('Message source does not match sandbox iframe. Ignoring.', null, 'warn');
      return false;
    }
    if (event.origin !== 'null') {
      debugLog('Message origin is not null (sandbox). Ignoring.', null, 'warn');
      return false;
    }
    return true;
  };

  /**
   * Handle AJAX proxy requests from the sandbox.
   */
  const handleAjax = (msg) => {
    debugLog('Processing AJAX request from sandbox:', msg);
    const payload = msg.payload;
    const targetUrl = payload.url.split('?')[0];
    const allowedUrl = window.el_settings.ajax_url.split('?')[0];

    if (targetUrl !== allowedUrl) {
      debugLog('Blocked unauthorized fetch request to:', payload.url, 'error');
      return;
    }

    fetch(payload.url, payload.options)
      .then((res) => res.json())
      .then((data) => {
        debugLog('AJAX request successful. Sending response back to sandbox.', data);
        sendToSandbox(iframe, 'response', { response_to: msg.message_id, data });
      })
      .catch((err) => {
        debugLog('AJAX request failed. Sending error back to sandbox.', err, 'error');
        sendToSandbox(iframe, 'response', { response_to: msg.message_id, error: err.message })
      });
  };

  /**
   * Handle clipboard copy requests from the sandbox.
   */
  const handleClipboard = (msg) => {
    debugLog('Processing clipboard copy request from sandbox:', msg);
    const { text, html } = msg.payload;

    if (navigator.clipboard && window.isSecureContext) {
      if (html) {
        const blobHtml = new Blob([html], { type: 'text/html' });
        const blobText = new Blob([text], { type: 'text/plain' });
        const clipboardItem = new ClipboardItem({
          'text/html': blobHtml,
          'text/plain': blobText,
        });
        navigator.clipboard.write([clipboardItem]).catch((err) => {
          debugLog('Clipboard write failed', err, 'error');
          navigator.clipboard.writeText(text);
        });
      } else {
        navigator.clipboard.writeText(text);
      }
    } else {
      if (html) {
        const div = document.createElement('div');
        div.innerHTML = html;
        div.style.position = 'fixed';
        div.style.left = '-9999px';
        document.body.appendChild(div);

        const range = document.createRange();
        range.selectNode(div);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);

        try {
          document.execCommand('copy');
        } catch (error) {
          debugLog('Copy failed', error, 'error');
        }
        div.remove();
        selection.removeAllRanges();
      } else {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
          document.execCommand('copy');
        } catch (error) {
          debugLog('Copy failed', error, 'error');
        }
        textArea.remove();
      }
    }
  };

  /**
   * Handle download requests from the sandbox.
   */
  const handleDownload = (msg) => {
    debugLog('Processing download request from sandbox:', msg);
    const { content, filename = 'download.txt', mimeType = 'text/plain;charset=utf-8;' } = msg.payload;

    // Sanitize filename: extract basename, strip directory traversal, and remove unsafe characters.
    let safeFilename = String(filename || 'download.txt').replace(/^.*[\\/]/, '').replace(/[^a-zA-Z0-9._-]/g, '_');
    if (!safeFilename || safeFilename === '.' || safeFilename === '..') {
      safeFilename = 'download.txt';
    }

    // Neutralize dangerous executable extensions
    const dangerousExtensions = /\.(html?|php\d?|phtml|phar|sh|bash|exe|bat|cmd|vbs|js|mjs)$/i;
    if (dangerousExtensions.test(safeFilename)) {
      safeFilename = safeFilename.replace(/\.[^.]+$/, '.txt');
    }

    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', safeFilename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  };

  /**
   * Handle open_url requests from the sandbox.
   */
  const handleOpenUrl = (msg) => {
    debugLog('Processing open_url request from sandbox:', msg);
    const { url } = msg.payload || {};

    if (!url || typeof url !== 'string') {
      debugLog('Invalid or missing URL in open_url payload.', msg, 'error');
      return;
    }

    try {
      const parsedUrl = new URL(url, window.location.origin);
      if (['http:', 'https:', 'mailto:'].includes(parsedUrl.protocol)) {
        window.open(parsedUrl.href, '_blank', 'noopener,noreferrer');
      } else {
        debugLog('Blocked opening URL with disallowed protocol:', parsedUrl.protocol, 'warn');
      }
    } catch (err) {
      debugLog('Failed to parse URL for open_url:', err, 'error');
    }
  };

  /**
   * Handle open_tab requests from the sandbox.
   */
  const handleOpenTab = (msg) => {
    debugLog('Processing open_tab request from sandbox:', msg);
    if (msg.payload?.url) {
      handleOpenUrl(msg);
      return;
    }

    const { content, mimeType = 'text/plain;charset=utf-8;' } = msg.payload || {};

    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const newWindow = window.open(url, '_blank');

    if (newWindow) {
      newWindow.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
      setTimeout(() => URL.revokeObjectURL(url), 10000);
    } else {
      URL.revokeObjectURL(url);
    }
  };

  /**
   * Handle localStorage requests from the sandbox.
   */
  const handleLocalStorage = (msg) => {
    debugLog('Processing localStorage request from sandbox:', msg);
    const { method, key, value } = msg.payload;
    let result = null;

    try {
      if (typeof localStorage !== 'undefined') {
        if (method === 'getItem') {
          result = localStorage.getItem(key);
        } else if (method === 'setItem') {
          localStorage.setItem(key, value);
        } else if (method === 'removeItem') {
          localStorage.removeItem(key);
        }
      }
    } catch (e) {
      debugLog('localStorage operation failed', e, 'error');
    }

    sendToSandbox(iframe, 'response', { response_to: msg.message_id, value: result });
  };

  /**
   * File System Access API state & IndexedDB persistence.
   */
  let activeDirHandle = null;

  const FS_DB_NAME = 'expressionlab_fs_db';
  const FS_DB_VERSION = 1;
  const FS_STORE_NAME = 'handles';
  const FS_HANDLE_KEY = 'snippets_dir_handle';

  const openHandleDB = () => {
    return new Promise((resolve) => {
      if (typeof indexedDB === 'undefined') {
        resolve(null);
        return;
      }
      try {
        const request = indexedDB.open(FS_DB_NAME, FS_DB_VERSION);
        request.onupgradeneeded = (e) => {
          const db = e.target.result;
          if (!db.objectStoreNames.contains(FS_STORE_NAME)) {
            db.createObjectStore(FS_STORE_NAME);
          }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => resolve(null);
      } catch (e) {
        resolve(null);
      }
    });
  };

  const saveDirHandleToDB = async (handle) => {
    try {
      const db = await openHandleDB();
      if (!db) return;
      return new Promise((resolve) => {
        const tx = db.transaction(FS_STORE_NAME, 'readwrite');
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => resolve(false);
        tx.objectStore(FS_STORE_NAME).put(handle, FS_HANDLE_KEY);
      });
    } catch (e) {
      /* Ignore */
    }
  };

  const getDirHandleFromDB = async () => {
    try {
      const db = await openHandleDB();
      if (!db) return null;
      return new Promise((resolve) => {
        const tx = db.transaction(FS_STORE_NAME, 'readonly');
        tx.onerror = () => resolve(null);
        const req = tx.objectStore(FS_STORE_NAME).get(FS_HANDLE_KEY);
        req.onsuccess = () => resolve(req.result || null);
        req.onerror = () => resolve(null);
      });
    } catch (e) {
      return null;
    }
  };

  const removeDirHandleFromDB = async () => {
    try {
      const db = await openHandleDB();
      if (!db) return;
      return new Promise((resolve) => {
        const tx = db.transaction(FS_STORE_NAME, 'readwrite');
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => resolve(false);
        tx.objectStore(FS_STORE_NAME).delete(FS_HANDLE_KEY);
      });
    } catch (e) {
      /* Ignore */
    }
  };

  /**
   * Handle File System directory selection.
   */
  const handleFsSelectDirectory = async (msg) => {
    debugLog('Processing fs_select_directory request from sandbox:', msg);
    if (typeof window.showDirectoryPicker !== 'function') {
      const unsupportedErr = {
        response_to: msg.message_id,
        error: 'unsupported',
        message: 'File System Access API is not supported in this browser.',
        details: {
          userAgent: typeof navigator !== 'undefined' ? navigator.userAgent : 'unknown',
          hasShowDirectoryPicker: false,
        },
      };
      debugLog('File System Access API (showDirectoryPicker) is not supported in this browser.', unsupportedErr, 'warn');
      sendToSandbox(iframe, 'response', unsupportedErr);
      return;
    }

    try {
      const dirHandle = await window.showDirectoryPicker({ mode: 'readwrite' });
      activeDirHandle = dirHandle;
      await saveDirHandleToDB(dirHandle);

      let fileHandle;
      let isNew = false;
      try {
        fileHandle = await dirHandle.getFileHandle('snippets.json', { create: false });
      } catch (e) {
        fileHandle = await dirHandle.getFileHandle('snippets.json', { create: true });
        isNew = true;
      }

      let content = '';
      if (isNew) {
        const writable = await fileHandle.createWritable();
        await writable.write(JSON.stringify([], null, 2));
        await writable.close();
        content = '[]';
      } else {
        const file = await fileHandle.getFile();
        content = await file.text();
        if (!content.trim()) {
          const writable = await fileHandle.createWritable();
          await writable.write(JSON.stringify([], null, 2));
          await writable.close();
          content = '[]';
        }
      }

      let snippets = [];
      try {
        snippets = JSON.parse(content);
        if (!Array.isArray(snippets)) snippets = [];
      } catch (e) {
        snippets = [];
      }

      sendToSandbox(iframe, 'response', {
        response_to: msg.message_id,
        success: true,
        snippets,
        dirName: dirHandle.name,
      });
    } catch (err) {
      if (err.name === 'AbortError') {
        sendToSandbox(iframe, 'response', {
          response_to: msg.message_id,
          cancelled: true,
        });
      } else {
        debugLog('fs_select_directory error:', err, 'error');
        sendToSandbox(iframe, 'response', {
          response_to: msg.message_id,
          error: err.name || 'error',
          message: err.message,
        });
      }
    }
  };

  /**
   * Handle reading snippets.json from active or restored directory handle.
   */
  const handleFsReadSnippets = async (msg) => {
    debugLog('Processing fs_read_snippets request from sandbox:', msg);
    if (!activeDirHandle) {
      const stored = await getDirHandleFromDB();
      if (stored) {
        try {
          const perm = await stored.queryPermission({ mode: 'readwrite' });
          if (perm === 'granted') {
            activeDirHandle = stored;
          } else {
            sendToSandbox(iframe, 'response', {
              response_to: msg.message_id,
              error: 'permission_required',
              permission: perm,
              dirName: stored.name,
              message: 'Permission required to access directory.',
            });
            return;
          }
        } catch (e) {
          /* Fall through */
        }
      }
    }

    if (!activeDirHandle) {
      sendToSandbox(iframe, 'response', {
        response_to: msg.message_id,
        error: 'no_directory',
        message: 'No local directory connected.',
      });
      return;
    }

    try {
      const fileHandle = await activeDirHandle.getFileHandle('snippets.json', { create: false });
      const file = await fileHandle.getFile();
      const content = await file.text();
      let snippets = [];
      try {
        snippets = JSON.parse(content);
        if (!Array.isArray(snippets)) snippets = [];
      } catch (e) {
        snippets = [];
      }
      sendToSandbox(iframe, 'response', {
        response_to: msg.message_id,
        success: true,
        snippets,
        dirName: activeDirHandle.name,
      });
    } catch (err) {
      debugLog('fs_read_snippets error:', err, 'error');
      sendToSandbox(iframe, 'response', {
        response_to: msg.message_id,
        error: err.name || 'error',
        message: err.message,
      });
    }
  };

  /**
   * Handle requesting permission for stored directory handle.
   */
  const handleFsRequestPermission = async (msg) => {
    debugLog('Processing fs_request_permission request from sandbox:', msg);
    let handle = activeDirHandle;
    if (!handle) {
      handle = await getDirHandleFromDB();
    }

    if (!handle) {
      // No stored handle, prompt directory picker
      return handleFsSelectDirectory(msg);
    }

    try {
      const perm = await handle.requestPermission({ mode: 'readwrite' });
      if (perm === 'granted') {
        activeDirHandle = handle;
        await saveDirHandleToDB(handle);

        const fileHandle = await activeDirHandle.getFileHandle('snippets.json', { create: true });
        const file = await fileHandle.getFile();
        const content = await file.text();
        let snippets = [];
        try {
          snippets = JSON.parse(content);
          if (!Array.isArray(snippets)) snippets = [];
        } catch (e) {
          snippets = [];
        }

        sendToSandbox(iframe, 'response', {
          response_to: msg.message_id,
          success: true,
          permission: 'granted',
          snippets,
          dirName: handle.name,
        });
      } else {
        sendToSandbox(iframe, 'response', {
          response_to: msg.message_id,
          success: false,
          permission: perm,
          dirName: handle.name,
        });
      }
    } catch (err) {
      if (err.name === 'AbortError') {
        sendToSandbox(iframe, 'response', {
          response_to: msg.message_id,
          cancelled: true,
        });
      } else {
        sendToSandbox(iframe, 'response', {
          response_to: msg.message_id,
          error: err.name || 'error',
          message: err.message,
        });
      }
    }
  };

  /**
   * Handle writing snippets.json to active directory handle.
   */
  const handleFsWriteSnippets = async (msg) => {
    debugLog('Processing fs_write_snippets request from sandbox:', msg);
    if (!activeDirHandle) {
      const stored = await getDirHandleFromDB();
      if (stored) {
        try {
          const perm = await stored.queryPermission({ mode: 'readwrite' });
          if (perm === 'granted') {
            activeDirHandle = stored;
          }
        } catch (e) {
          /* Fall through */
        }
      }
    }

    if (!activeDirHandle) {
      sendToSandbox(iframe, 'response', {
        response_to: msg.message_id,
        error: 'no_directory',
        message: 'No local directory connected.',
      });
      return;
    }

    try {
      const { content } = msg.payload || {};
      const fileHandle = await activeDirHandle.getFileHandle('snippets.json', { create: true });
      const writable = await fileHandle.createWritable();
      const textToWrite = typeof content === 'string' ? content : JSON.stringify(content, null, 2);
      await writable.write(textToWrite);
      await writable.close();
      sendToSandbox(iframe, 'response', {
        response_to: msg.message_id,
        success: true,
      });
    } catch (err) {
      debugLog('fs_write_snippets error:', err, 'error');
      sendToSandbox(iframe, 'response', {
        response_to: msg.message_id,
        error: err.name || 'error',
        message: err.message,
      });
    }
  };

  /**
   * Handle open file picker for importing JSON file.
   */
  const handleFsImportFile = async (msg) => {
    debugLog('Processing fs_import_file request from sandbox:', msg);
    if (typeof window.showOpenFilePicker === 'function') {
      try {
        const [fileHandle] = await window.showOpenFilePicker({
          types: [
            {
              description: 'JSON Files',
              accept: {
                'application/json': ['.json'],
              },
            },
          ],
          multiple: false,
        });
        const file = await fileHandle.getFile();
        const content = await file.text();
        sendToSandbox(iframe, 'response', {
          response_to: msg.message_id,
          success: true,
          content,
          filename: file.name,
        });
      } catch (err) {
        if (err.name === 'AbortError') {
          sendToSandbox(iframe, 'response', {
            response_to: msg.message_id,
            cancelled: true,
          });
        } else {
          debugLog('fs_import_file error:', err, 'error');
          sendToSandbox(iframe, 'response', {
            response_to: msg.message_id,
            error: err.name || 'error',
            message: err.message,
          });
        }
      }
    } else {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = '.json,application/json';
      input.style.display = 'none';
      document.body.appendChild(input);

      input.onchange = (e) => {
        const file = e.target.files?.[0];
        if (!file) {
          sendToSandbox(iframe, 'response', {
            response_to: msg.message_id,
            cancelled: true,
          });
          document.body.removeChild(input);
          return;
        }
        const reader = new FileReader();
        reader.onload = (event) => {
          sendToSandbox(iframe, 'response', {
            response_to: msg.message_id,
            success: true,
            content: event.target.result,
            filename: file.name,
          });
          document.body.removeChild(input);
        };
        reader.onerror = (err) => {
          sendToSandbox(iframe, 'response', {
            response_to: msg.message_id,
            error: 'read_error',
            message: err.message || 'Failed to read file',
          });
          document.body.removeChild(input);
        };
        reader.readAsText(file);
      };

      input.click();
    }
  };

  /**
   * Handle checking status of active directory.
   */
  const handleFsStatus = async (msg) => {
    debugLog('Processing fs_status request from sandbox:', msg);
    let handle = activeDirHandle;
    if (!handle) {
      handle = await getDirHandleFromDB();
      if (handle) {
        try {
          const perm = await handle.queryPermission({ mode: 'readwrite' });
          if (perm === 'granted') {
            activeDirHandle = handle;
          }
          sendToSandbox(iframe, 'response', {
            response_to: msg.message_id,
            connected: true,
            hasStoredHandle: true,
            permission: perm,
            dirName: handle.name,
          });
          return;
        } catch (e) {
          /* Fall through */
        }
      }
    }

    if (handle) {
      let perm = 'granted';
      try {
        perm = await handle.queryPermission({ mode: 'readwrite' });
      } catch (e) {
        /* Ignore */
      }
      sendToSandbox(iframe, 'response', {
        response_to: msg.message_id,
        connected: true,
        hasStoredHandle: true,
        permission: perm,
        dirName: handle.name,
      });
    } else {
      sendToSandbox(iframe, 'response', {
        response_to: msg.message_id,
        connected: false,
        hasStoredHandle: false,
        dirName: null,
      });
    }
  };

  /**
   * Handle disconnecting active directory.
   */
  const handleFsDisconnect = async (msg) => {
    debugLog('Processing fs_disconnect request from sandbox:', msg);
    activeDirHandle = null;
    await removeDirHandleFromDB();
    sendToSandbox(iframe, 'response', {
      response_to: msg.message_id,
      success: true,
    });
  };

  /**
   * Message type to handler mapping.
   */
  const handlers = {
    ajax: handleAjax,
    clipboard: handleClipboard,
    download: handleDownload,
    open_tab: handleOpenTab,
    open_url: handleOpenUrl,
    localStorage: handleLocalStorage,
    fs_select_directory: handleFsSelectDirectory,
    fs_read_snippets: handleFsReadSnippets,
    fs_write_snippets: handleFsWriteSnippets,
    fs_import_file: handleFsImportFile,
    fs_status: handleFsStatus,
    fs_disconnect: handleFsDisconnect,
    fs_request_permission: handleFsRequestPermission,
  };

  /**
   * Main message handler.
   */
  window.addEventListener('message', (event) => {
    if (!isValidSandboxMessage(event)) return;

    debugLog('Received message from sandbox:', event.data);

    const msg = event.data;
    if (!msg || !msg.message_id) {
      debugLog('Invalid message format received. Missing message_id.', msg, 'error');
      return;
    }

    const handler = handlers[msg.type];
    if (handler) {
      handler(msg);
    } else {
      debugLog('Unknown message type received from sandbox. No handler implemented for this type.', msg, 'warn');
    }
  });
});