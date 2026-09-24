/**
 * CodeMirror 6 command for formatting Expression Lab DSL.
 */

import { formatExpressionLab } from './formatter.js';

/**
 * Calculates a mapped cursor position in the formatted string
 * based on non-whitespace character offset from the original code.
 *
 * @param {string} original Original code.
 * @param {string} formatted Formatted code.
 * @param {number} originalCursor Original cursor position.
 * @returns {number} Estimated cursor position in formatted code.
 */
export function calculateMappedCursor(original, formatted, originalCursor) {
  if (originalCursor <= 0) return 0;
  if (originalCursor >= original.length) return formatted.length;

  // Count non-whitespace characters up to original cursor
  let nonWsCount = 0;
  for (let i = 0; i < originalCursor && i < original.length; i++) {
    if (!/\s/.test(original[i])) {
      nonWsCount++;
    }
  }

  // Find equivalent index in formatted text with the same non-ws count
  let targetIdx = 0;
  let count = 0;
  while (targetIdx < formatted.length && count < nonWsCount) {
    if (!/\s/.test(formatted[targetIdx])) {
      count++;
    }
    targetIdx++;
  }

  return Math.min(targetIdx, formatted.length);
}

/**
 * Formats the document in an EditorView instance.
 * Compatible as a CodeMirror 6 Command (returns boolean).
 *
 * @param {import('@codemirror/view').EditorView} view
 * @returns {boolean} True if formatted changes were dispatched.
 */
export function formatEditorDocument(view) {
  if (!view || !view.state || !view.state.doc) {
    return false;
  }

  const originalDoc = view.state.doc.toString();
  if (!originalDoc.trim()) {
    return false;
  }

  try {
    const formatted = formatExpressionLab(originalDoc, { indent: '    ' });
    if (!formatted || formatted === originalDoc) {
      return false;
    }

    const currentHead = view.state.selection?.main?.head ?? originalDoc.length;
    const mappedCursor = calculateMappedCursor(originalDoc, formatted, currentHead);

    view.dispatch({
      changes: { from: 0, to: view.state.doc.length, insert: formatted },
      selection: { anchor: mappedCursor, head: mappedCursor },
      scrollIntoView: true,
    });

    return true;
  } catch (err) {
    // Graceful error handling
    return false;
  }
}
