import { useUiStore } from '../../stores/ui';
import { ed25519 } from '@noble/curves/ed25519.js';
import { arrayBufferToBase64, debugLog } from '../helpers.js';
import { sendToSandbox, requestFromSandbox } from '../sandbox-communication.js';

let activeChallengeRequest = null;
export function isSandboxEnabled() {
  return true === window.el_settings?.settings?.enable_sandbox && false === window.el_settings?.settings?.debug_mode;
}

/**
 * Generate a unique message ID for sandbox communication.
 * @returns {string}
 */
function generateMessageId() {
  return Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
}

/**
 * Get the WordPress origin for postMessage target.
 * @returns {string}
 */
function getWpOrigin() {
  return new URL(window.el_settings.ajax_url).origin;
}

/**
 * Handle API requests with cryptographic signing and challenge management.
 *
 * @param {string} url
 * @param {object} options
 * @returns {Promise<Response>}
 */
export async function handleFetch(url, options = {}) {
  const uiStore = useUiStore();
  
  if (!options._isRetry && uiStore.currentChallenge && uiStore.challengeFetchedAt) {
      const signatureDuration = window.el_settings.settings.signature_duration ?? 30;
      const elapsed = Math.floor((Date.now() - uiStore.challengeFetchedAt) / 1000);

      if (elapsed >= signatureDuration) {
          if ( isSandboxEnabled() ) {
            debugLog('Current challenge expired based on elapsed time. Requesting new challenge before proceeding with fetch.', { elapsed, signatureDuration });
            uiStore.setRequestingChallenge(true);
            await requestChallenge();
          } else {
            debugLog('Sandbox is disabled. Skipping challenge.', { elapsed, signatureDuration });
          }
      }
  }

  let serializedBody = options.body || '';

  // Challenge nonce and timestamp.
  if (uiStore.currentChallenge) {
      if (options.method === 'GET' || !options.method) {
          const urlObj = new URL(url, window.location.origin);
          urlObj.searchParams.set('challenge_nonce', uiStore.currentChallenge.nonce);
          urlObj.searchParams.set('timestamp', uiStore.currentChallenge.timestamp);
          url = urlObj.toString();
      } else if (options.body instanceof URLSearchParams) {
          options.body.set('challenge_nonce', uiStore.currentChallenge.nonce);
          options.body.set('timestamp', uiStore.currentChallenge.timestamp);
          serializedBody = options.body.toString();
      } else if (typeof options.body === 'string') {
           const tempParams = new URLSearchParams(options.body);
           tempParams.set('challenge_nonce', uiStore.currentChallenge.nonce);
           tempParams.set('timestamp', uiStore.currentChallenge.timestamp);
           serializedBody = tempParams.toString();
           options.body = serializedBody;
      }
  }

  // Cryptographic signing.
  if (uiStore.privateKey) {
      // Create headers object if it doesn't exist
      options.headers = options.headers || {};
      
      let dataToSignString = '';
      const isGet = !options.method || options.method.toUpperCase() === 'GET';

      if (isGet) {
          // Extract the search parameters (query string) including the '?'
          const urlObj = new URL(url, window.location.origin);
          dataToSignString = urlObj.search;
      } else {
          dataToSignString = serializedBody;
      }
      
      const encoder = new TextEncoder();
      const dataToSign = encoder.encode(dataToSignString);
      const signature = await ed25519.sign(dataToSign, uiStore.privateKey);
      
      options.headers['X-ExpressionLab-Signature'] = arrayBufferToBase64(signature);
  }

  let response;

  if ( ! isSandboxEnabled() ) {
    // Simulated.
    const fetchOptions = { ...options };
    if (options.method !== 'GET' && options.method !== 'HEAD' && options.method) {
        fetchOptions.body = serializedBody;
    }

    debugLog('Making direct AJAX request:', { url, options: fetchOptions });

    response = await fetch(url, fetchOptions);
  } else {
    // Actual postMessage-based communication with parent window.
    response = await new Promise((resolve, reject) => {
        const messageId = generateMessageId();
        const wpOrigin = getWpOrigin();
        const listener = (event) => {
          if (event.source !== window.parent) {
            debugLog('Message source does not match parent window. Ignoring.', null, 'warn');
            return;
          };
          if (event.origin !== wpOrigin) {
            debugLog('Message origin does not match expected WordPress origin. Ignoring.', null, 'warn');
            return;
          };
          if (event.data && event.data.response_to === messageId) {
            window.removeEventListener('message', listener);
            if (event.data.error) {
              reject(event.data.error);
            } else {
              const mockResponse = {
                ok: true,
                status: 200,
                json: () => Promise.resolve(event.data.data),
                text: () => Promise.resolve(JSON.stringify(event.data.data)),
                clone: () => mockResponse,
              };
              resolve(mockResponse);
            }
          }
        };

        const safeOptions = {
          method: options.method || 'GET',
          headers: {},
        };

        if (safeOptions.method !== 'GET' && safeOptions.method !== 'HEAD' && safeOptions.method) {
          safeOptions.body = serializedBody.toString();
        }

        if (options.headers) {
          safeOptions.headers = { ...options.headers };
        }

        debugLog('Sending AJAX request to sandbox via postMessage:', { url, options: safeOptions });

        window.addEventListener('message', listener);
        window.parent.postMessage(
          { message_id: messageId, type: 'ajax', payload: { url, options: safeOptions } },
          wpOrigin
        );
    });

  }

  let data;
  
  try {
      const clone = response.clone ? response.clone() : { json: response.json }; 
      data = await clone.json();
  } catch (e) {
      return response;
  }

  if ( !data.success && data.data && data.data.code === 'challenge_required' ) {
      debugLog('Challenge expired, requesting new challenge before retrying fetch', { responseData: data, options });
      
      // Renegotiate and retry.
      if ( ! options._isRetry ) {
          await requestChallenge();
          options._isRetry = true;
          return handleFetch(url, options);
      }
  }

  // Update challenge.
  if (data.success && data.data && data.data.challenge) {
      const challenge = data.data.challenge;
      // Normalize timestamp key
      if (challenge.ts && !challenge.timestamp) {
          challenge.timestamp = challenge.ts;
      }
      uiStore.setChallenge(challenge);
  }

  return response;
}

/**
 * Request a new challenge from the server.
 * 
 * @returns {Promise<boolean>}
 */
export async function requestChallenge() {
    if (activeChallengeRequest) {
      return activeChallengeRequest;
    }

    activeChallengeRequest = (async () => {
        const uiStore = useUiStore();
        uiStore.setRequestingChallenge(true);
        const startTime = Date.now();
        const url = window.el_settings.ajax_url; 
        const body = new URLSearchParams();
        body.append('action', 'expressionlab_challenge');
        body.append('nonce', window.el_settings.nonce);

        const response = await handleFetch(url, { 
            method: 'POST', 
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body,
            _isRetry: true 
        });
        
        try {
            const clone = response.clone ? response.clone() : { json: response.json };
            const json = await clone.json();

            if (json.success && json.data && json.data.nonce) {
                const elapsed = Date.now() - startTime;
                if (elapsed < 600) {
                    await new Promise(r => setTimeout(r, 600 - elapsed));
                }
                uiStore.setChallenge({
                    nonce: json.data.nonce, 
                    timestamp: json.data.timestamp
                });
                uiStore.setRequestingChallenge(false);
                return true;
            }
        } catch (e) {
            console.error('Challenge parsing failed', e);
        }
        uiStore.setRequestingChallenge(false);
        return false;
    })();

    try {
        return await activeChallengeRequest;
    } finally {
        activeChallengeRequest = null;
    }
}

/**
 * Handle clipboard copying.
 * 
 * @param {string} text 
 * @param {string|null} html 
 */
export function handleClipboardCopy(text, html = null) {
  if ( ! isSandboxEnabled() ) {
    if (navigator.clipboard && window.isSecureContext) {
      if (html) {
        const blobHtml = new Blob([html], { type: 'text/html' });
        const blobText = new Blob([text], { type: 'text/plain' });
        const clipboardItem = new ClipboardItem({
            'text/html': blobHtml,
            'text/plain': blobText
        });
        navigator.clipboard.write([clipboardItem]).catch(err => {
           console.error('Clipboard write failed', err);
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
          console.error('Copy failed', error);
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
          console.error('Copy failed', error);
        }
        textArea.remove();
      }
    }
  } else {
    window.parent.postMessage(
      { message_id: generateMessageId(), type: 'clipboard', payload: { text, html } },
      getWpOrigin()
    );
  }

}

/**
 * Handle file downloading.
 * 
 * @param {string} content 
 * @param {string} filename 
 * @param {string} mimeType 
 */
export function handleDownload(content, filename, mimeType = 'text/plain;charset=utf-8;') {
  if ( ! isSandboxEnabled() ) {
    const blob = new Blob([content], { type: mimeType });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  } else {
    window.parent.postMessage(
      { message_id: generateMessageId(), type: 'download', payload: { content, filename, mimeType } },
      getWpOrigin()
    );
  }

}

/**
 * Handle opening a URL in a new tab.
 * 
 * @param {string} url 
 */
export function handleOpenUrl(url) {
  if (!url || typeof url !== 'string') return;

  if ( ! isSandboxEnabled() ) {
    window.open(url, '_blank', 'noopener,noreferrer');
  } else {
    window.parent.postMessage(
      { message_id: generateMessageId(), type: 'open_url', payload: { url } },
      getWpOrigin()
    );
  }
}

/**
 * Handle opening content in a new tab.
 * 
 * @param {string|ArrayBuffer} content 
 * @param {string} mimeType 
 */
export function handleOpenTab(content, mimeType = 'application/json') {
  if (typeof content === 'string' && (/^https?:\/\//i.test(content) || mimeType === 'url')) {
    handleOpenUrl(content);
    return;
  }

  if ( ! isSandboxEnabled() ) {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank');
  } else {
    window.parent.postMessage(
      { message_id: generateMessageId(), type: 'open_tab', payload: { content, mimeType } },
      getWpOrigin()
    );
  }
}


/**
 * Handle localStorage operations, proxying through the parent window when sandboxed.
 * 
 * @param {'getItem'|'setItem'|'removeItem'} method 
 * @param {string} key 
 * @param {string|null} value - Required for 'setItem'
 * @returns {Promise<string|null>}
 */
export function handleLocalStorage(method, key, value = null) {
  if ( ! isSandboxEnabled() ) {
    try {
      if (typeof localStorage === 'undefined') return Promise.resolve(null);
      if (method === 'getItem') return Promise.resolve(localStorage.getItem(key));
      if (method === 'setItem') { localStorage.setItem(key, value); return Promise.resolve(null); }
      if (method === 'removeItem') { localStorage.removeItem(key); return Promise.resolve(null); }
      return Promise.resolve(null);
    } catch (e) {
      return Promise.resolve(null);
    }
  } else {
    return new Promise((resolve) => {
      const messageId = generateMessageId();
      const wpOrigin = getWpOrigin();

      const timeout = setTimeout(() => {
        window.removeEventListener('message', listener);
        resolve(null);
      }, 5000);

      const listener = (event) => {
        if (event.source !== window.parent) return;
        if (event.origin !== wpOrigin) return;
        if (event.data && event.data.response_to === messageId) {
          clearTimeout(timeout);
          window.removeEventListener('message', listener);
          resolve(event.data.value ?? null);
        }
      };

      window.addEventListener('message', listener);
      window.parent.postMessage(
        { message_id: messageId, type: 'localStorage', payload: { method, key, value } },
        wpOrigin
      );
    });
  }

}

let directDirHandle = null;

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
 * Prompts user to pick a folder, initializes/reads snippets.json.
 * @returns {Promise<{ success?: boolean, snippets?: Array, dirName?: string, cancelled?: boolean, error?: string, message?: string }>}
 */
export async function handleSelectDirectory() {
  const isDirect = !isSandboxEnabled();
  debugLog('handleSelectDirectory invoked', { route: isDirect ? 'direct' : 'sandbox-postMessage' });
  if (isDirect) {
    if (typeof window.showDirectoryPicker !== 'function') {
      const unsupportedErr = {
        error: 'unsupported',
        message: 'File System Access API is not supported in this browser.',
        details: {
          userAgent: typeof navigator !== 'undefined' ? navigator.userAgent : 'unknown',
          hasShowDirectoryPicker: false,
        },
      };
      debugLog('File System Access API (showDirectoryPicker) is not supported in this browser.', unsupportedErr, 'warn');
      return unsupportedErr;
    }
    try {
      debugLog('Direct FS: Prompting window.showDirectoryPicker({ mode: "readwrite" })...');
      const dirHandle = await window.showDirectoryPicker({ mode: 'readwrite' });
      directDirHandle = dirHandle;
      await saveDirHandleToDB(dirHandle);
      debugLog('Direct FS: Acquired and saved directory handle:', { name: dirHandle.name });

      let fileHandle;
      let isNew = false;
      try {
        fileHandle = await dirHandle.getFileHandle('snippets.json', { create: false });
      } catch (e) {
        debugLog('Direct FS: snippets.json not found, creating new file...', e);
        fileHandle = await dirHandle.getFileHandle('snippets.json', { create: true });
        isNew = true;
      }

      let content = '';
      if (isNew) {
        const writable = await fileHandle.createWritable();
        await writable.write(JSON.stringify([], null, 2));
        await writable.close();
        content = '[]';
        debugLog('Direct FS: Created initial empty snippets.json');
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
        debugLog('Direct FS: Failed to parse snippets.json content:', e, 'error');
        snippets = [];
      }

      debugLog('Direct FS: Directory selected successfully with snippets:', { count: snippets.length, dirName: dirHandle.name });
      return { success: true, snippets, dirName: dirHandle.name };
    } catch (err) {
      if (err.name === 'AbortError') {
        debugLog('Direct FS: Directory selection cancelled by user (AbortError)');
        return { cancelled: true };
      }
      debugLog('Direct FS: Error during directory selection:', { name: err.name, message: err.message, stack: err.stack }, 'error');
      return { error: err.name || 'error', message: err.message };
    }
  } else {
    debugLog('Sandbox FS: Sending fs_select_directory postMessage to host window...');
    return new Promise((resolve) => {
      const messageId = generateMessageId();
      const wpOrigin = getWpOrigin();

      const timeout = setTimeout(() => {
        window.removeEventListener('message', listener);
        debugLog('Sandbox FS: Directory selection timed out (120s)', null, 'error');
        resolve({ error: 'timeout', message: 'Directory selection timed out' });
      }, 120000);

      const listener = (event) => {
        if (event.source !== window.parent) return;
        if (event.origin !== wpOrigin) return;
        if (event.data && event.data.response_to === messageId) {
          clearTimeout(timeout);
          window.removeEventListener('message', listener);
          debugLog('Sandbox FS: Received fs_select_directory response from host:', event.data);
          resolve(event.data);
        }
      };

      window.addEventListener('message', listener);
      window.parent.postMessage(
        { message_id: messageId, type: 'fs_select_directory' },
        wpOrigin
      );
    });
  }
}

/**
 * Handle reading snippets from snippets.json in active directory.
 * @returns {Promise<{ success?: boolean, snippets?: Array, error?: string, message?: string }>}
 */
export async function handleReadLocalSnippets() {
  const isDirect = !isSandboxEnabled();
  debugLog('handleReadLocalSnippets invoked', { route: isDirect ? 'direct' : 'sandbox-postMessage' });
  if (isDirect) {
    if (!directDirHandle) {
      const stored = await getDirHandleFromDB();
      if (stored) {
        try {
          const perm = await stored.queryPermission({ mode: 'readwrite' });
          if (perm === 'granted') {
            directDirHandle = stored;
            debugLog('Direct FS: Restored stored directory handle from DB with granted permission', { name: stored.name });
          } else {
            debugLog('Direct FS: Stored handle requires permission query/prompt', { permission: perm, name: stored.name });
            return { error: 'permission_required', permission: perm, dirName: stored.name };
          }
        } catch (e) {
          debugLog('Direct FS: Error checking permission on stored handle', e, 'warn');
        }
      }
    }

    if (!directDirHandle) {
      debugLog('Direct FS: No local directory connected when attempting read');
      return { error: 'no_directory', message: 'No local directory connected.' };
    }
    try {
      debugLog('Direct FS: Reading snippets.json from active directory...', { name: directDirHandle.name });
      const fileHandle = await directDirHandle.getFileHandle('snippets.json', { create: false });
      const file = await fileHandle.getFile();
      const content = await file.text();
      let snippets = [];
      try {
        snippets = JSON.parse(content);
        if (!Array.isArray(snippets)) snippets = [];
      } catch (e) {
        debugLog('Direct FS: JSON parse error in snippets.json', e, 'error');
        snippets = [];
      }
      debugLog('Direct FS: Successfully read snippets from local disk', { count: snippets.length });
      return { success: true, snippets, dirName: directDirHandle.name };
    } catch (err) {
      debugLog('Direct FS: Error reading snippets.json', { name: err.name, message: err.message }, 'error');
      return { error: err.name || 'error', message: err.message };
    }
  } else {
    debugLog('Sandbox FS: Sending fs_read_snippets postMessage...');
    return new Promise((resolve) => {
      const messageId = generateMessageId();
      const wpOrigin = getWpOrigin();

      const timeout = setTimeout(() => {
        window.removeEventListener('message', listener);
        debugLog('Sandbox FS: Read snippets timed out (10s)', null, 'error');
        resolve({ error: 'timeout', message: 'Read snippets timed out' });
      }, 10000);

      const listener = (event) => {
        if (event.source !== window.parent) return;
        if (event.origin !== wpOrigin) return;
        if (event.data && event.data.response_to === messageId) {
          clearTimeout(timeout);
          window.removeEventListener('message', listener);
          debugLog('Sandbox FS: Received fs_read_snippets response:', event.data);
          resolve(event.data);
        }
      };

      window.addEventListener('message', listener);
      window.parent.postMessage(
        { message_id: messageId, type: 'fs_read_snippets' },
        wpOrigin
      );
    });
  }
}

/**
 * Handle requesting permission to access stored local directory.
 * @returns {Promise<{ success?: boolean, permission?: string, snippets?: Array, dirName?: string }>}
 */
export async function handleRequestFsPermission() {
  const isDirect = !isSandboxEnabled();
  debugLog('handleRequestFsPermission invoked', { route: isDirect ? 'direct' : 'sandbox-postMessage' });
  if (isDirect) {
    let handle = directDirHandle;
    if (!handle) {
      handle = await getDirHandleFromDB();
    }
    if (!handle) {
      debugLog('Direct FS: No stored handle found, falling back to handleSelectDirectory');
      return handleSelectDirectory();
    }
    try {
      debugLog('Direct FS: Requesting readwrite permission for handle:', { name: handle.name });
      const perm = await handle.requestPermission({ mode: 'readwrite' });
      debugLog('Direct FS: Permission request result:', { permission: perm, name: handle.name });
      if (perm === 'granted') {
        directDirHandle = handle;
        await saveDirHandleToDB(handle);
        const res = await handleReadLocalSnippets();
        return { success: true, permission: 'granted', snippets: res.snippets, dirName: handle.name };
      }
      return { success: false, permission: perm, dirName: handle.name };
    } catch (err) {
      if (err.name === 'AbortError') {
        debugLog('Direct FS: Permission prompt dismissed/aborted by user');
        return { cancelled: true };
      }
      debugLog('Direct FS: Permission request error:', { name: err.name, message: err.message }, 'error');
      return { error: err.name || 'error', message: err.message };
    }
  } else {
    debugLog('Sandbox FS: Sending fs_request_permission postMessage...');
    return new Promise((resolve) => {
      const messageId = generateMessageId();
      const wpOrigin = getWpOrigin();

      const timeout = setTimeout(() => {
        window.removeEventListener('message', listener);
        debugLog('Sandbox FS: Permission request timed out (120s)', null, 'error');
        resolve({ error: 'timeout', message: 'Permission request timed out' });
      }, 120000);

      const listener = (event) => {
        if (event.source !== window.parent) return;
        if (event.origin !== wpOrigin) return;
        if (event.data && event.data.response_to === messageId) {
          clearTimeout(timeout);
          window.removeEventListener('message', listener);
          debugLog('Sandbox FS: Received fs_request_permission response:', event.data);
          resolve(event.data);
        }
      };

      window.addEventListener('message', listener);
      window.parent.postMessage(
        { message_id: messageId, type: 'fs_request_permission' },
        wpOrigin
      );
    });
  }
}

/**
 * Handle writing snippets to snippets.json in active directory.
 * @param {Array|string} snippets 
 * @returns {Promise<{ success?: boolean, error?: string, message?: string }>}
 */
export async function handleWriteLocalSnippets(snippets) {
  const isDirect = !isSandboxEnabled();
  debugLog('handleWriteLocalSnippets invoked', { route: isDirect ? 'direct' : 'sandbox-postMessage' });
  const content = typeof snippets === 'string' ? snippets : JSON.parse(JSON.stringify(snippets));
  if (isDirect) {
    if (!directDirHandle) {
      const stored = await getDirHandleFromDB();
      if (stored) {
        try {
          const perm = await stored.queryPermission({ mode: 'readwrite' });
          if (perm === 'granted') {
            directDirHandle = stored;
          }
        } catch (e) {
          debugLog('Direct FS: Error checking permission before write', e, 'warn');
        }
      }
    }

    if (!directDirHandle) {
      debugLog('Direct FS: Cannot write, no directory connected');
      return { error: 'no_directory', message: 'No local directory connected.' };
    }
    try {
      debugLog('Direct FS: Writing snippets to snippets.json...');
      const fileHandle = await directDirHandle.getFileHandle('snippets.json', { create: true });
      const writable = await fileHandle.createWritable();
      const textToWrite = typeof content === 'string' ? content : JSON.stringify(content, null, 2);
      await writable.write(textToWrite);
      await writable.close();
      debugLog('Direct FS: Successfully wrote snippets to disk');
      return { success: true };
    } catch (err) {
      debugLog('Direct FS: Write snippets error:', { name: err.name, message: err.message }, 'error');
      return { error: err.name || 'error', message: err.message };
    }
  } else {
    debugLog('Sandbox FS: Sending fs_write_snippets postMessage...');
    return new Promise((resolve) => {
      const messageId = generateMessageId();
      const wpOrigin = getWpOrigin();

      const timeout = setTimeout(() => {
        window.removeEventListener('message', listener);
        debugLog('Sandbox FS: Write snippets timed out (10s)', null, 'error');
        resolve({ error: 'timeout', message: 'Write snippets timed out' });
      }, 10000);

      const listener = (event) => {
        if (event.source !== window.parent) return;
        if (event.origin !== wpOrigin) return;
        if (event.data && event.data.response_to === messageId) {
          clearTimeout(timeout);
          window.removeEventListener('message', listener);
          debugLog('Sandbox FS: Received fs_write_snippets response:', event.data);
          resolve(event.data);
        }
      };

      window.addEventListener('message', listener);
      window.parent.postMessage(
        { message_id: messageId, type: 'fs_write_snippets', payload: { content } },
        wpOrigin
      );
    });
  }
}

/**
 * Handle opening native file picker to import JSON file.
 * @returns {Promise<{ success?: boolean, content?: string, filename?: string, cancelled?: boolean, error?: string, message?: string }>}
 */
export async function handleOpenFilePicker() {
  const isDirect = !isSandboxEnabled();
  debugLog('handleOpenFilePicker invoked', { route: isDirect ? 'direct' : 'sandbox-postMessage' });
  if (isDirect) {
    if (typeof window.showOpenFilePicker === 'function') {
      try {
        debugLog('Direct FS: Using native window.showOpenFilePicker');
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
        debugLog('Direct FS: File selected successfully:', { filename: file.name });
        return { success: true, content, filename: file.name };
      } catch (err) {
        if (err.name === 'AbortError') {
          debugLog('Direct FS: File picker aborted by user');
          return { cancelled: true };
        }
        debugLog('Direct FS: Error in showOpenFilePicker:', { name: err.name, message: err.message }, 'error');
        return { error: err.name || 'error', message: err.message };
      }
    } else {
      debugLog('Direct FS: showOpenFilePicker not supported, falling back to input[type=file]');
      return new Promise((resolve) => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json,application/json';
        input.style.display = 'none';
        document.body.appendChild(input);

        input.onchange = (e) => {
          const file = e.target.files?.[0];
          if (!file) {
            document.body.removeChild(input);
            debugLog('Direct FS: input[type=file] cancelled');
            resolve({ cancelled: true });
            return;
          }
          const reader = new FileReader();
          reader.onload = (event) => {
            document.body.removeChild(input);
            debugLog('Direct FS: input[type=file] read file successfully:', { filename: file.name });
            resolve({ success: true, content: event.target.result, filename: file.name });
          };
          reader.onerror = (err) => {
            document.body.removeChild(input);
            debugLog('Direct FS: input[type=file] read error:', err, 'error');
            resolve({ error: 'read_error', message: err.message || 'Failed to read file' });
          };
          reader.readAsText(file);
        };

        input.click();
      });
    }
  } else {
    debugLog('Sandbox FS: Sending fs_import_file postMessage...');
    return new Promise((resolve) => {
      const messageId = generateMessageId();
      const wpOrigin = getWpOrigin();

      const timeout = setTimeout(() => {
        window.removeEventListener('message', listener);
        debugLog('Sandbox FS: File import timed out (120s)', null, 'error');
        resolve({ error: 'timeout', message: 'File import timed out' });
      }, 120000);

      const listener = (event) => {
        if (event.source !== window.parent) return;
        if (event.origin !== wpOrigin) return;
        if (event.data && event.data.response_to === messageId) {
          clearTimeout(timeout);
          window.removeEventListener('message', listener);
          debugLog('Sandbox FS: Received fs_import_file response:', event.data);
          resolve(event.data);
        }
      };

      window.addEventListener('message', listener);
      window.parent.postMessage(
        { message_id: messageId, type: 'fs_import_file' },
        wpOrigin
      );
    });
  }
}

/**
 * Handle checking local File System connection status.
 * @returns {Promise<{ connected: boolean, hasStoredHandle?: boolean, permission?: string, dirName?: string }>}
 */
export async function handleGetLocalFsStatus() {
  const isDirect = !isSandboxEnabled();
  debugLog('handleGetLocalFsStatus invoked', { route: isDirect ? 'direct' : 'sandbox-postMessage' });
  if (isDirect) {
    if (!directDirHandle) {
      const stored = await getDirHandleFromDB();
      if (stored) {
        let perm = 'prompt';
        try {
          perm = await stored.queryPermission({ mode: 'readwrite' });
          if (perm === 'granted') directDirHandle = stored;
        } catch (e) {
          debugLog('Direct FS: Error querying permission in handleGetLocalFsStatus', e, 'warn');
        }
        debugLog('Direct FS: Stored handle found in DB:', { name: stored.name, permission: perm });
        return { connected: true, hasStoredHandle: true, permission: perm, dirName: stored.name };
      }
      debugLog('Direct FS: No stored handle in DB');
      return { connected: false, hasStoredHandle: false, dirName: null };
    }
    let perm = 'granted';
    try {
      perm = await directDirHandle.queryPermission({ mode: 'readwrite' });
    } catch (e) {
      debugLog('Direct FS: Error querying permission on active handle', e, 'warn');
    }
    debugLog('Direct FS: Active handle status:', { name: directDirHandle.name, permission: perm });
    return { connected: true, hasStoredHandle: true, permission: perm, dirName: directDirHandle.name };
  } else {
    debugLog('Sandbox FS: Sending fs_status postMessage...');
    return new Promise((resolve) => {
      const messageId = generateMessageId();
      const wpOrigin = getWpOrigin();

      const timeout = setTimeout(() => {
        window.removeEventListener('message', listener);
        debugLog('Sandbox FS: Status check timed out (5s)', null, 'warn');
        resolve({ connected: false });
      }, 5000);

      const listener = (event) => {
        if (event.source !== window.parent) return;
        if (event.origin !== wpOrigin) return;
        if (event.data && event.data.response_to === messageId) {
          clearTimeout(timeout);
          window.removeEventListener('message', listener);
          debugLog('Sandbox FS: Received fs_status response:', event.data);
          resolve(event.data);
        }
      };

      window.addEventListener('message', listener);
      window.parent.postMessage(
        { message_id: messageId, type: 'fs_status' },
        wpOrigin
      );
    });
  }
}

/**
 * Handle disconnecting local File System directory.
 * @returns {Promise<{ success: boolean }>}
 */
export async function handleDisconnectLocalFs() {
  const isDirect = !isSandboxEnabled();
  debugLog('handleDisconnectLocalFs invoked', { route: isDirect ? 'direct' : 'sandbox-postMessage' });
  if (isDirect) {
    directDirHandle = null;
    await removeDirHandleFromDB();
    debugLog('Direct FS: Disconnected and removed handle from DB');
    return { success: true };
  } else {
    debugLog('Sandbox FS: Sending fs_disconnect postMessage...');
    return new Promise((resolve) => {
      const messageId = generateMessageId();
      const wpOrigin = getWpOrigin();

      const timeout = setTimeout(() => {
        window.removeEventListener('message', listener);
        debugLog('Sandbox FS: Disconnect timed out (5s)', null, 'warn');
        resolve({ success: false });
      }, 5000);

      const listener = (event) => {
        if (event.source !== window.parent) return;
        if (event.origin !== wpOrigin) return;
        if (event.data && event.data.response_to === messageId) {
          clearTimeout(timeout);
          window.removeEventListener('message', listener);
          debugLog('Sandbox FS: Received fs_disconnect response:', event.data);
          resolve(event.data);
        }
      };

      window.addEventListener('message', listener);
      window.parent.postMessage(
        { message_id: messageId, type: 'fs_disconnect' },
        wpOrigin
      );
    });
  }
}

