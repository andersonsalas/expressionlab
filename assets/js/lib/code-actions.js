/**
 * Documentation code block action handlers for Expression Lab.
 *
 * Handles copying code examples and inserting elscript snippets into
 * the active console editor or the Snippet Library modal editor.
 */

import { handleClipboardCopy } from './api/client.js';
import { useUiStore } from '../stores/ui.js';

let listenersAttached = false;

/**
 * Temporarily change the copy button icon to a checkmark for feedback.
 *
 * @param {HTMLElement} btn The button element.
 */
export function showCopiedFeedback(btn) {
  if (!btn) return;
  const icon = btn.querySelector('.codicon');
  if (!icon) return;

  const originalIconClass = icon.className;
  const originalTitle = btn.getAttribute('title') || '';

  icon.className = 'codicon codicon-check';
  btn.classList.add('copied');
  btn.setAttribute('title', 'Copied!');

  setTimeout(() => {
    icon.className = originalIconClass;
    btn.classList.remove('copied');
    btn.setAttribute('title', originalTitle);
  }, 1500);
}

/**
 * Handle a code action button click (copy or insert).
 *
 * @param {MouseEvent} event The click event.
 */
export function handleCodeActionClick(event) {
  const btn = event.target?.closest?.('.el-code-action-btn');
  if (!btn) return;

  event.preventDefault();
  event.stopPropagation();

  const action = btn.dataset.action;
  const rawCode = decodeURIComponent(btn.dataset.code || '');

  if (action === 'copy') {
    handleClipboardCopy(rawCode);
    showCopiedFeedback(btn);
  } else if (action === 'insert') {
    const uiStore = useUiStore();
    if (uiStore.activeModal === 'library') {
      uiStore.triggerLibrarySnippetInsert(rawCode);
    } else {
      uiStore.triggerSnippetInsert(rawCode);
    }
  }
}

/**
 * Prevent mousedown on action buttons from unfocusing the active editor.
 *
 * @param {MouseEvent} event The mousedown event.
 */
export function handleCodeActionMouseDown(event) {
  if (event.target?.closest?.('.el-code-action-btn')) {
    event.preventDefault();
  }
}

/**
 * Attach global event listeners for documentation code actions.
 */
export function setupCodeActionListeners() {
  if (listenersAttached || typeof document === 'undefined') return;

  document.addEventListener('mousedown', handleCodeActionMouseDown);
  document.addEventListener('click', handleCodeActionClick);
  listenersAttached = true;
}

/**
 * Remove global event listeners for documentation code actions.
 */
export function removeCodeActionListeners() {
  if (!listenersAttached || typeof document === 'undefined') return;

  document.removeEventListener('mousedown', handleCodeActionMouseDown);
  document.removeEventListener('click', handleCodeActionClick);
  listenersAttached = false;
}
