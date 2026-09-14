/**
 * Documentation tooltip DOM builder for Expression Lab.
 *
 * Builds DOM nodes from structured documentation objects.
 * Used by both CodeMirror autocomplete (info panel) and the sidebar tooltip.
 *
 * Structured doc format (from PHP):
 *   - Functions/Methods: { summary, description, signature, parameters: [...], return: { type, description }, see: [...] }
 *   - Constants:         { summary, description, signature, type, value, valueStr }
 *   - Properties:        { summary, description, signature, type, default? }
 */

import { renderDocMarkdown, highlightSignature } from './doc-markdown';
import { __, renderSafeInlineMarkdown } from './helpers.js';
import { handleOpenUrl } from './api/client.js';

/**
 * Create a DOM element with optional class, text, and children.
 *
 * @param {string} tag       HTML tag name.
 * @param {string} className CSS class(es).
 * @param {string} [text]    Optional text content.
 * @returns {HTMLElement}
 */
function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text) node.textContent = text;
  return node;
}

/**
 * Build a DOM node representing structured documentation.
 *
 * @param {object} doc The structured documentation object.
 * @returns {HTMLElement|null} The documentation DOM node, or null if doc is empty.
 */
export function buildDocNode(doc) {
  if (!doc || typeof doc !== 'object') return null;

  const container = el('div', 'doc-tooltip');
  container.dataset.elFormatted = 'true';

  // Summary.
  if (doc.summary) {
    container.appendChild(el('p', 'cm-docblock summary', doc.summary));
  }

  // Signature (syntax-highlighted).
  if (doc.signature) {
    const pre = el('pre', 'cm-docblock signature');
    pre.innerHTML = highlightSignature(doc.signature);
    pre.classList.add('hljs');
    container.appendChild(pre);
  }

  // Description (rendered as markdown with code highlighting).
  if (doc.description) {
    const wrapper = el('div', 'cm-docblock description-wrapper');
    const inner = el('div', 'cm-docblock _description');
    inner.innerHTML = renderDocMarkdown(doc.description);
    wrapper.appendChild(inner);
    container.appendChild(wrapper);
  }

  // Tags / Sections container.
  const tagsContainer = el('div', 'cm-docblock tags');
  let hasContent = false;

  // Parameters (for functions/methods).
  if (doc.parameters && doc.parameters.length > 0) {
    const paramSection = el('div', 'cm-docblock doc-section doc-parameters');
    paramSection.appendChild(el('div', 'cm-docblock doc-section-title', __('Parameters:')));

    const paramList = el('ul', 'cm-docblock doc-list');
    doc.parameters.forEach((param) => {
      const item = el('li', 'cm-docblock doc-item');

      const isRequired = param.required !== false;
      const icon = el('span', `codicon codicon-symbol-field ${isRequired ? 'param-required' : 'param-optional'}`);
      item.appendChild(icon);

      if (param.name) {
        item.appendChild(el('span', 'cm-docblock variable', param.name));
      }

      if (param.type) {
        if (param.name) item.appendChild(document.createTextNode(' '));
        item.appendChild(el('span', 'cm-docblock type', `(${param.type})`));
      }

      if (param.description) {
        item.appendChild(document.createTextNode(' '));
        item.appendChild(el('span', 'cm-docblock doc-separator', '-'));
        item.appendChild(document.createTextNode(' '));
        const descSpan = el('span', 'cm-docblock doc-desc');
        descSpan.innerHTML = renderSafeInlineMarkdown(param.description);
        item.appendChild(descSpan);
      }

      paramList.appendChild(item);
    });

    paramSection.appendChild(paramList);
    tagsContainer.appendChild(paramSection);
    hasContent = true;
  }

  // Return (for functions/methods).
  if (doc.return) {
    const returnSection = el('div', 'cm-docblock doc-section doc-returns');
    returnSection.appendChild(el('span', 'cm-docblock doc-section-title', __('Returns:')));
    returnSection.appendChild(document.createTextNode(' '));

    if (doc.return.type) {
      returnSection.appendChild(el('span', 'cm-docblock type', doc.return.type));
    }

    if (doc.return.description) {
      returnSection.appendChild(document.createTextNode(' '));
      returnSection.appendChild(el('span', 'cm-docblock doc-separator', '-'));
      returnSection.appendChild(document.createTextNode(' '));
      const returnDescSpan = el('span', 'cm-docblock doc-desc');
      returnDescSpan.innerHTML = renderSafeInlineMarkdown(doc.return.description);
      returnSection.appendChild(returnDescSpan);
    }

    tagsContainer.appendChild(returnSection);
    hasContent = true;
  }

  // See references (for functions/methods).
  if (doc.see && doc.see.length > 0) {
    const seeSection = el('div', 'cm-docblock doc-section doc-see');
    seeSection.appendChild(el('div', 'cm-docblock doc-section-title', __('See also:')));

    const seeList = el('ul', 'cm-docblock doc-list');
    doc.see.forEach((ref) => {
      const item = el('li', 'cm-docblock doc-item');

      try {
        new URL(ref);
        const link = el('a', 'cm-docblock doc-link');
        link.href = ref;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';

        const icon = el('span', 'codicon codicon-link');
        link.appendChild(icon);
        link.appendChild(document.createTextNode(ref));

        // Prevent CodeMirror focusout from closing tooltip before click fires.
        link.addEventListener('mousedown', (e) => {
          e.stopPropagation();
        });
        link.addEventListener('click', (e) => {
          e.stopPropagation();
          e.preventDefault();
          handleOpenUrl(ref);
        });

        item.appendChild(link);
      } catch {
        const icon = el('span', 'codicon codicon-link');
        item.appendChild(icon);
        item.appendChild(el('span', 'cm-docblock doc-ref', ref));
      }

      seeList.appendChild(item);
    });

    seeSection.appendChild(seeList);
    tagsContainer.appendChild(seeSection);
    hasContent = true;
  }

  // Type (for constants/properties).
  if (doc.type && !doc.parameters) {
    const typeSection = el('div', 'cm-docblock doc-section doc-type');
    typeSection.appendChild(el('span', 'cm-docblock doc-section-title', __('Type:')));
    typeSection.appendChild(document.createTextNode(' '));
    typeSection.appendChild(el('span', 'cm-docblock type', doc.type));
    tagsContainer.appendChild(typeSection);
    hasContent = true;
  }

  // Value (for constants).
  if (doc.valueStr !== undefined) {
    const valSection = el('div', 'cm-docblock doc-section doc-value');
    valSection.appendChild(el('span', 'cm-docblock doc-section-title', __('Value:')));
    valSection.appendChild(document.createTextNode(' '));
    const valDesc = el('span', 'cm-docblock doc-desc');
    valDesc.appendChild(el('code', null, doc.valueStr));
    valSection.appendChild(valDesc);
    tagsContainer.appendChild(valSection);
    hasContent = true;
  }

  // Default value (for properties).
  if (doc.default !== undefined) {
    const defSection = el('div', 'cm-docblock doc-section doc-default');
    defSection.appendChild(el('span', 'cm-docblock doc-section-title', __('Default:')));
    defSection.appendChild(document.createTextNode(' '));
    const defDesc = el('span', 'cm-docblock doc-desc');
    defDesc.appendChild(el('code', null, String(doc.default)));
    defSection.appendChild(defDesc);
    tagsContainer.appendChild(defSection);
    hasContent = true;
  }

  if (hasContent) {
    container.appendChild(tagsContainer);
  }

  // Handle any other links in markdown description/parameters.
  container.addEventListener('mousedown', (e) => {
    if (e.target?.closest?.('a[href]')) {
      e.stopPropagation();
    }
  });

  container.addEventListener('click', (e) => {
    const link = e.target?.closest?.('a[href]');
    if (link && !link.classList.contains('doc-link')) {
      const href = link.getAttribute('href');
      if (href) {
        e.stopPropagation();
        e.preventDefault();
        handleOpenUrl(href);
      }
    }
  });

  return container;
}
