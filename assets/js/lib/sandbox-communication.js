/**
 * Sandbox communication service for Expression Lab.
 *
 * Provides a structured message-passing interface between the main
 * console page and the sandbox iframe, with origin validation and
 * a request/response pattern.
 */

import { debugLog } from './helpers';

/** Allowed origin for sandbox messages. */
const SANDBOX_ORIGIN = window.location.origin;

/**
 * Validate that a message event comes from the expected origin.
 *
 * @param {MessageEvent} event
 * @returns {boolean}
 */
function isValidOrigin(event) {
  return event.origin === SANDBOX_ORIGIN;
}

/**
 * Send a structured command to the sandbox iframe.
 *
 * @param {HTMLIFrameElement} iframe - The sandbox iframe element.
 * @param {string} action - The command action name.
 * @param {*} [payload] - Optional payload to send.
 */
export function sendToSandbox(iframe, action, payload) {
  debugLog('Sending message to sandbox:', { action, payload, iframe });
  if (!iframe || !iframe.contentWindow) {
    debugLog('Sandbox iframe not available. Cannot send message.', null, 'error');
    return;
  }
  
  const message = { action, payload, ...(typeof payload === 'object' ? payload : {}) };
  iframe.contentWindow.postMessage(message, '*');
}

/**
 * Create a message handler for receiving messages from the sandbox.
 *
 * @param {object} handlers - Map of action names to handler functions.
 * @param {Function} handlers[action] - Handler receives (payload, event).
 * @returns {Function} The event listener function (for cleanup).
 */
export function createSandboxMessageHandler(handlers) {
  return (event) => {
    if (!isValidOrigin(event)) return;

    const { action, payload } = event.data || {};
    if (!action) return;

    const handler = handlers[action];
    if (typeof handler === 'function') {
      handler(payload, event);
    }
  };
}

/**
 * Request a value from the sandbox and wait for a response.
 *
 * @param {HTMLIFrameElement} iframe - The sandbox iframe element.
 * @param {string} action - The request action name.
 * @param {*} [payload] - Optional payload.
 * @param {number} [timeout=5000] - Timeout in ms.
 * @returns {Promise<*>} The response payload.
 */
export function requestFromSandbox(iframe, action, payload, timeout = 5000) {
  return new Promise((resolve, reject) => {
    const responseAction = `${action}_response`;
    let resolved = false;

    const handler = (event) => {
      if (!isValidOrigin(event)) return;
      const data = event.data || {};
      if (data.action === responseAction) {
        resolved = true;
        window.removeEventListener('message', handler);
        resolve(data.payload);
      }
    };

    window.addEventListener('message', handler);
    sendToSandbox(iframe, action, payload);

    setTimeout(() => {
      if (!resolved) {
        window.removeEventListener('message', handler);
        reject(new Error(`Sandbox request "${action}" timed out after ${timeout}ms`));
      }
    }, timeout);
  });
}
