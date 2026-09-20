import DOMPurify from 'dompurify';
import { Marked } from 'marked';
import { argon2id } from 'hash-wasm';

const inlineMarkdownEngine = new Marked({
  renderer: {
    codespan(token) {
      let rawContent = token.raw ? token.raw.replace(/^`+|`+$/g, '') : (token.text || '');
      if (rawContent.startsWith(' ') && rawContent.endsWith(' ') && rawContent.trim().length > 0) {
        rawContent = rawContent.slice(1, -1);
      }
      const isMulti = rawContent.includes('\n');
      const escaped = rawContent
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
      const className = isMulti ? ' class="multiline-code"' : '';
      return `<code${className}>${escaped}</code>`;
    },
  },
});

export function renderSafeInlineMarkdown(text) {
  if (text === null || text === undefined) {
    return '';
  }
  const str = typeof text === 'string' ? text : String(text);
  if (!str) {
    return '';
  }
  try {
    let rawHtml = inlineMarkdownEngine.parseInline(str);
    // Clean up orphaned dot/punctuation immediately following a block/multiline code element.
    rawHtml = rawHtml.replace(/(<code class="multiline-code">[\s\S]*?<\/code>)\s*\.\s*/g, '$1 ');
    rawHtml = rawHtml.replace(/(<code class="multiline-code">[\s\S]*?<\/code>)\s*\.?\s*$/g, '$1');
    return DOMPurify.sanitize(rawHtml, {
      ALLOWED_TAGS: [
        'code',
        'strong',
        'em',
        'span',
        'b',
        'i',
        'br',
        'a',
      ],
      ALLOWED_ATTR: [
        'class',
        'href',
        'target',
        'rel',
      ],
    });
  } catch {
    return DOMPurify.sanitize(str);
  }
}

export function sanitizeIcon(icon) {
  return DOMPurify.sanitize(icon, { USE_PROFILES: { svg: true } });
}

/**
 * Sanitizes an SVG markup string using DOMPurify with the SVG profile,
 * stripping script tags, foreignObject elements, and executable event attributes.
 *
 * @param {string} svgString Raw SVG markup.
 * @return {string} Sanitized SVG markup string.
 */
export function sanitizeSvg(svgString) {
  if (!svgString || typeof svgString !== 'string') {
    return '';
  }
  return DOMPurify.sanitize(svgString, {
    USE_PROFILES: { svg: true, svgFilters: true },
  });
}

export function sanitizeHtml(html) {
  return DOMPurify.sanitize(
    html,
    {
      ALLOWED_TAGS: [
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'br',
        'hr',
        'blockquote',
        'span',
        'p',
        'a',
        'b',
        'i',
        'em',
        'strong',
        'del',
        's',
        'sup',
        'sub',
        'kbd',
        'mark',
        'ul',
        'ol',
        'li',
        'table',
        'thead',
        'tbody',
        'tfoot',
        'pre',
        'code',
        'tr',
        'th',
        'td',
        'div',
        'button'
      ],
      ALLOWED_ATTR: [
        'href',
        'class',
        'id',
        'title',
        'target',
        'rel',
        'type',
        'data-code',
        'data-action',
        'aria-label'
      ]
    }
  );
}

export function arrayBufferToBase64(buffer) {
  let binary = '';
  const bytes = new Uint8Array(buffer);
  const len = bytes.byteLength;
  for (let i = 0; i < len; i++) {
      binary += String.fromCharCode(bytes[i]);
  }
  return window.btoa(binary);
}

export function base64ToArrayBuffer(base64) {
  const binaryIsstring = window.atob(base64);
  const len = binaryIsstring.length;
  const bytes = new Uint8Array(len);
  for (let i = 0; i < len; i++) {
    bytes[i] = binaryIsstring.charCodeAt(i);
  }
  return bytes;
}

export async function deriveKey(password, salt) {
  return await argon2id({
    password: password,
    salt: salt,
    parallelism: 1,
    iterations: 15,
    memorySize: 65536,
    hashLength: 32,
    outputType: 'binary'
  });
}

export function cleanUpWpAdmin() {
  const wpbodyContent = document.getElementById('wpbody-content');
  if (wpbodyContent) {
    Array.from(wpbodyContent.children).forEach(child => {
      if (!child.querySelector('.expressionlab-app') && !child.classList.contains('expressionlab-app')) {
        child.remove();
      }
    });

    const wrap = wpbodyContent.querySelector('.wrap');
    if (wrap) {
      Array.from(wrap.children).forEach(child => {
        if (!child.classList.contains('expressionlab-app')) {
          child.remove();
        }
      });
    }
  }
}

export function debugLog(message, data = undefined, type = 'log', route = null) {
  if (!window.el_settings?.settings?.browser_log) {
    return;
  }
  const isSandbox = typeof window !== 'undefined' &&
    window.el_settings?.settings?.enable_sandbox === true &&
    window.el_settings?.settings?.debug_mode === false;

  const resolvedRoute = route || (isSandbox ? 'Sandbox' : 'Direct');
  const prefix = `[Expression Lab : ${resolvedRoute}]`;
  const fn = (console[type] && typeof console[type] === 'function') ? console[type] : console.log;

  if (data !== undefined && data !== null) {
    fn(prefix, message, data);
  } else {
    fn(prefix, message);
  }
}

/**
 * Retrieve the translation of text using the localized i18n data.
 *
 * @param {string} text
 * @param {string} [_domain='expression-lab']
 * @returns {string}
 */
export function __(text, _domain = 'expression-lab') {
  if (typeof text !== 'string') {
    return text;
  }
  if (typeof window !== 'undefined' && window.el_settings && window.el_settings.i18n && window.el_settings.i18n[text] !== undefined) {
    return window.el_settings.i18n[text];
  }
  return text;
}

/**
 * Basic sprintf replacement matching WordPress sprintf syntax.
 * Supports %s, %d, %f, and numbered placeholders like %1$s, %2$d.
 *
 * @param {string} format
 * @param {...*} args
 * @returns {string}
 */
export function sprintf(format, ...args) {
  if (typeof format !== 'string') {
    return '';
  }
  let index = 0;
  return format.replace(/%(\d+\$)?([%dfsv])/g, (match, paramPos, specifier) => {
    if (specifier === '%') {
      return '%';
    }
    let arg;
    if (paramPos) {
      const pos = parseInt(paramPos.slice(0, -1), 10) - 1;
      arg = args[pos];
    } else {
      arg = args[index++];
    }

    if (arg === undefined || arg === null) {
      return '';
    }

    if (specifier === 'd') {
      return parseInt(arg, 10);
    }
    if (specifier === 'f') {
      return parseFloat(arg);
    }
    return String(arg);
  });
}

/**
 * Generate a unique snippet name, appending (1), (2), ...(n) if a snippet with the same name exists.
 *
 * @param {string} name 
 * @param {Set<string>} existingNames 
 * @returns {string}
 */
export function getUniqueSnippetName(name, existingNames) {
  const defaultUntitled = __('Untitled');
  const trimmed = (name || defaultUntitled).trim() || defaultUntitled;
  if (!existingNames.has(trimmed.toLowerCase())) {
    existingNames.add(trimmed.toLowerCase());
    return trimmed;
  }
  let baseName = trimmed;
  let counter = 1;
  const match = trimmed.match(/^(.*?)\s*\((\d+)\)$/);
  if (match) {
    baseName = match[1].trim() || defaultUntitled;
    counter = parseInt(match[2], 10) + 1;
  }
  let candidate = `${baseName} (${counter})`;
  while (existingNames.has(candidate.toLowerCase())) {
    counter++;
    candidate = `${baseName} (${counter})`;
  }
  existingNames.add(candidate.toLowerCase());
  return candidate;
}

/**
 * Normalize a snippet object ensuring id is the trimmed name and properties are standardized.
 *
 * @param {object} s 
 * @returns {{ id: string, name: string, code: string }}
 */
export function normalizeSnippet(s) {
  const defaultUntitled = __('Untitled');
  const name = (s.name || s.title || defaultUntitled).trim() || defaultUntitled;
  return {
    id: name,
    name,
    code: typeof s.code === 'string' ? s.code : (typeof s.content === 'string' ? s.content : '')
  };
}