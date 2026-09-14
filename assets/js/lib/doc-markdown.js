/**
 * Markdown rendering and signature highlighting for documentation tooltips in Expression Lab.
 *
 * Configured with Highlight.js to render code blocks with 'elscript' syntax highlighting by default,
 * and specialized 'elscript-signature' highlighting for signature blocks.
 */

import { Marked } from 'marked';
import hljs from 'highlight.js/lib/core';
import elscriptLang from './highlight/elscript.js';
import elscriptSignatureLang from './highlight/elscript-signature.js';
import javascriptLang from 'highlight.js/lib/languages/javascript';
import sqlLang from 'highlight.js/lib/languages/sql';
import phpLang from 'highlight.js/lib/languages/php';
import jsonLang from 'highlight.js/lib/languages/json';
import { sanitizeHtml } from './helpers';

// Ensure required languages are registered.
if (!hljs.getLanguage('elscript')) {
  hljs.registerLanguage('elscript', elscriptLang);
  hljs.registerLanguage('expressionlab', elscriptLang);
}
if (!hljs.getLanguage('elscript-signature')) {
  hljs.registerLanguage('elscript-signature', elscriptSignatureLang);
}
if (!hljs.getLanguage('javascript')) {
  hljs.registerLanguage('javascript', javascriptLang);
  hljs.registerLanguage('js', javascriptLang);
}
if (!hljs.getLanguage('sql')) {
  hljs.registerLanguage('sql', sqlLang);
}
if (!hljs.getLanguage('php')) {
  hljs.registerLanguage('php', phpLang);
}
if (!hljs.getLanguage('json')) {
  hljs.registerLanguage('json', jsonLang);
}

/**
 * Marked engine customized for documentation tooltips.
 * Code blocks without language default to 'elscript'.
 */
const docMarkdownEngine = new Marked({
  renderer: {
    code({ text, lang }) {
      const language = lang && hljs.getLanguage(lang) ? lang : 'elscript';
      const isElscript = language === 'elscript' || language === 'expressionlab';
      const encodedCode = encodeURIComponent(text);

      const copyBtn = `<button type="button" class="el-code-action-btn el-code-copy-btn" data-action="copy" data-code="${encodedCode}" title="Copy to clipboard" aria-label="Copy code"><span class="codicon codicon-copy"></span></button>`;
      const insertBtn = isElscript
        ? `<button type="button" class="el-code-action-btn el-code-insert-btn" data-action="insert" data-code="${encodedCode}" title="Insert into console" aria-label="Insert code"><span class="codicon codicon-insert"></span></button>`
        : '';

      const actions = `<div class="el-code-actions">${copyBtn}${insertBtn}</div>`;

      try {
        const highlighted = hljs.highlight(text, { language }).value;
        return `<div class="el-code-block-wrapper"><pre class="hljs"><code class="hljs language-${language}">${highlighted}</code></pre>${actions}</div>`;
      } catch {
        return `<div class="el-code-block-wrapper"><pre><code>${text}</code></pre>${actions}</div>`;
      }
    },
  },
});

/**
 * Render markdown string to sanitized HTML with syntax highlighting for code blocks.
 *
 * @param {string} markdown Raw markdown string.
 * @returns {string} Sanitized HTML.
 */
export function renderDocMarkdown(markdown) {
  if (!markdown || typeof markdown !== 'string') return '';
  try {
    return sanitizeHtml(docMarkdownEngine.parse(markdown));
  } catch {
    return sanitizeHtml(markdown);
  }
}

/**
 * Highlight a function/method signature using 'elscript-signature' grammar.
 *
 * @param {string} signature Raw signature string.
 * @returns {string} Sanitized HTML with highlighting spans.
 */
export function highlightSignature(signature) {
  if (!signature || typeof signature !== 'string') return '';
  try {
    const { value } = hljs.highlight(signature, { language: 'elscript-signature' });
    return sanitizeHtml(value);
  } catch {
    return sanitizeHtml(signature);
  }
}
