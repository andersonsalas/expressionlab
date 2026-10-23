/**
 * HTTP Request Builder - Code Generator Helper for Expression Lab
 *
 * Generates idiomatic Elscript code calling Http.get(), Http.post(), or Http.head().
 */

/**
 * Escapes a string to be safely embedded inside an Elscript single-quoted string literal.
 *
 * @param {string} str
 * @returns {string}
 */
export function escapeElscriptString(str) {
  if (typeof str !== 'string') {
    str = String(str ?? '');
  }
  return str
    .replace(/\\/g, '\\\\')
    .replace(/'/g, '\\\'')
    .replace(/\n/g, '\\n')
    .replace(/\r/g, '\\r');
}

/**
 * Serializes a JavaScript value into formatted Elscript syntax.
 *
 * @param {*} val
 * @param {number} indentLevel
 * @param {boolean} [alwaysQuoteKeys=false]
 * @returns {string}
 */
export function serializeToElscript(val, indentLevel = 0, alwaysQuoteKeys = false) {
  const indent = '  '.repeat(indentLevel);
  const nextIndent = '  '.repeat(indentLevel + 1);

  if (val === null || val === undefined) {
    return 'null';
  }
  if (typeof val === 'boolean') {
    return val ? 'true' : 'false';
  }
  if (typeof val === 'number') {
    return Number.isFinite(val) ? String(val) : 'null';
  }
  if (typeof val === 'string') {
    return `'${escapeElscriptString(val)}'`;
  }
  if (Array.isArray(val)) {
    if (val.length === 0) return '[]';
    const items = val.map((item) => `${nextIndent}${serializeToElscript(item, indentLevel + 1, alwaysQuoteKeys)}`);
    return `[\n${items.join(',\n')}\n${indent}]`;
  }
  if (typeof val === 'object') {
    const keys = Object.keys(val);
    if (keys.length === 0) return '{}';
    const entries = keys.map((key) => {
      const isValidIdentifier = /^[a-zA-Z_][a-zA-Z0-9_]*$/.test(key);
      const formattedKey = alwaysQuoteKeys || !isValidIdentifier
        ? `'${escapeElscriptString(key)}'`
        : key;
      const formattedVal = serializeToElscript(val[key], indentLevel + 1, alwaysQuoteKeys);
      return `${nextIndent}${formattedKey}: ${formattedVal}`;
    });
    return `{\n${entries.join(',\n')}\n${indent}}`;
  }
  return 'null';
}

/**
 * Decodes a URI component without throwing on malformed sequences (e.g. a lone `%` while typing).
 *
 * @param {string} str
 * @returns {string}
 */
function safeDecode(str) {
  try {
    return decodeURIComponent(str);
  } catch {
    return str;
  }
}

/**
 * Parses a full URL into base URL and query parameters list.
 * A trailing `#fragment` is kept attached to the returned `baseUrl` so it survives round-trips.
 *
 * @param {string} fullUrl
 * @returns {{ baseUrl: string, params: Array<{ key: string, value: string, enabled: boolean }> }}
 */
export function parseUrlAndParams(fullUrl) {
  if (!fullUrl || typeof fullUrl !== 'string') {
    return { baseUrl: '', params: [] };
  }

  const hashIndex = fullUrl.indexOf('#');
  const hash = hashIndex === -1 ? '' : fullUrl.slice(hashIndex);
  const withoutHash = hashIndex === -1 ? fullUrl : fullUrl.slice(0, hashIndex);

  const qIndex = withoutHash.indexOf('?');
  if (qIndex === -1) {
    return { baseUrl: fullUrl, params: [] };
  }

  const baseUrl = withoutHash.slice(0, qIndex) + hash;
  const queryString = withoutHash.slice(qIndex + 1);
  const params = [];

  const pairs = queryString.split('&');
  for (const pair of pairs) {
    if (!pair) continue;
    const eqIndex = pair.indexOf('=');
    if (eqIndex === -1) {
      params.push({
        enabled: true,
        key: safeDecode(pair),
        value: ''
      });
    } else {
      params.push({
        enabled: true,
        key: safeDecode(pair.slice(0, eqIndex)),
        value: safeDecode(pair.slice(eqIndex + 1))
      });
    }
  }

  return { baseUrl, params };
}

/**
 * Reconstructs a full URL string given a base URL, active query parameters, and optional API key.
 *
 * @param {string} baseUrl
 * @param {Array<{ enabled: boolean, key: string, value: string }>} params
 * @param {Object} [auth]
 * @returns {string}
 */
export function buildFullUrl(baseUrl, params = [], auth = null) {
  let cleanBase = (baseUrl || '').trim();

  // Preserve the fragment (always last in a URL) and re-append it after the query string.
  let hash = '';
  const hashIndex = cleanBase.indexOf('#');
  if (hashIndex !== -1) {
    hash = cleanBase.slice(hashIndex);
    cleanBase = cleanBase.slice(0, hashIndex);
  }

  const qIndex = cleanBase.indexOf('?');
  if (qIndex !== -1) {
    cleanBase = cleanBase.slice(0, qIndex);
  }

  const queryParts = [];

  // 1. Regular query parameters
  if (Array.isArray(params)) {
    for (const p of params) {
      if (p.enabled && p.key && p.key.trim() !== '') {
        const k = encodeURIComponent(p.key.trim());
        const v = encodeURIComponent(p.value ?? '');
        queryParts.push(`${k}=${v}`);
      }
    }
  }

  // 2. API Key as query parameter
  if (
    auth &&
    auth.type === 'apikey' &&
    auth.apiKeyAddTo === 'query' &&
    auth.apiKeyName &&
    auth.apiKeyName.trim() !== ''
  ) {
    const k = encodeURIComponent(auth.apiKeyName.trim());
    const v = encodeURIComponent(auth.apiKeyValue ?? '');
    queryParts.push(`${k}=${v}`);
  }

  if (queryParts.length === 0) {
    return `${cleanBase}${hash}`;
  }

  return cleanBase
    ? `${cleanBase}?${queryParts.join('&')}${hash}`
    : `?${queryParts.join('&')}${hash}`;
}

/**
 * Base64-encodes a string as UTF-8 (btoa alone throws on non-Latin1 characters).
 *
 * @param {string} raw
 * @returns {string}
 */
function toBase64Utf8(raw) {
  if (typeof TextEncoder === 'function' && typeof btoa === 'function') {
    const bytes = new TextEncoder().encode(raw);
    let binary = '';
    for (const b of bytes) {
      binary += String.fromCharCode(b);
    }
    return btoa(binary);
  }
  return Buffer.from(raw, 'utf8').toString('base64');
}

/**
 * Builds the headers object from the configuration, including authorization and auto Content-Type.
 *
 * @param {Object} config
 * @returns {Record<string, string>}
 */
export function resolveHeaders(config) {
  const headers = {};

  // 1. User-defined headers
  if (Array.isArray(config.headers)) {
    for (const h of config.headers) {
      if (h.enabled && h.key && h.key.trim() !== '') {
        headers[h.key.trim()] = h.value ?? '';
      }
    }
  }

  // 2. Authorization
  if (config.auth) {
    const { type, bearerToken, basicUser, basicPass, apiKeyName, apiKeyValue, apiKeyAddTo } = config.auth;

    if (type === 'bearer' && bearerToken && bearerToken.trim() !== '') {
      headers['Authorization'] = `Bearer ${bearerToken.trim()}`;
    } else if (type === 'basic' && (basicUser || basicPass)) {
      const user = basicUser || '';
      const pass = basicPass || '';
      const raw = `${user}:${pass}`;
      const encoded = toBase64Utf8(raw);
      headers['Authorization'] = `Basic ${encoded}`;
    } else if (type === 'apikey' && apiKeyAddTo === 'header' && apiKeyName && apiKeyName.trim() !== '') {
      headers[apiKeyName.trim()] = apiKeyValue || '';
    }
  }

  // 3. Auto Content-Type for POST
  if (config.method === 'POST' && config.body) {
    const existingContentTypeKey = Object.keys(headers).find(
      (k) => k.toLowerCase() === 'content-type'
    );

    if (!existingContentTypeKey) {
      if (config.body.type === 'json') {
        headers['Content-Type'] = 'application/json';
      } else if (config.body.type === 'form-data') {
        headers['Content-Type'] = 'application/x-www-form-urlencoded';
      } else if (config.body.type === 'raw' && config.body.rawContentType) {
        headers['Content-Type'] = config.body.rawContentType;
      }
    }
  }

  return headers;
}

/**
 * Resolves transport options according to Http.ALLOWED_OPTIONS.
 * Only non-default options are forwarded.
 *
 * @param {Object} settings
 * @returns {Record<string, any>}
 */
export function resolveOptions(settings) {
  if (!settings) return {};

  const options = {};

  // Timeout (default 10)
  const timeoutNum = Number(settings.timeout);
  if (!isNaN(timeoutNum) && timeoutNum > 0 && timeoutNum !== 10) {
    options['timeout'] = timeoutNum;
  }

  // User-Agent (default WP user agent)
  if (settings.userAgent && settings.userAgent.trim() !== '') {
    options['user-agent'] = settings.userAgent.trim();
  }

  // HTTP Version (default 1.1)
  if (settings.httpVersion && settings.httpVersion !== '1.1') {
    options['httpversion'] = settings.httpVersion;
  }

  // Blocking (default true)
  if (settings.blocking === false) {
    options['blocking'] = false;
  }

  // Compress (WordPress default false)
  if (settings.compress === true) {
    options['compress'] = true;
  }
  // Decompress (WordPress default true): only an explicit `false` is forwarded
  if (settings.decompress === false) {
    options['decompress'] = false;
  }

  // Cookies
  if (Array.isArray(settings.cookies)) {
    const activeCookies = {};
    for (const c of settings.cookies) {
      if (c.enabled && c.key && c.key.trim() !== '') {
        activeCookies[c.key.trim()] = c.value ?? '';
      }
    }
    if (Object.keys(activeCookies).length > 0) {
      options['cookies'] = activeCookies;
    }
  }

  return options;
}

/**
 * Resolves the request body for POST methods.
 *
 * @param {Object} bodyConfig
 * @returns {any} Evaluated value or null
 */
export function resolveBody(bodyConfig) {
  if (!bodyConfig || bodyConfig.type === 'none') {
    return null;
  }

  if (bodyConfig.type === 'raw') {
    return bodyConfig.rawText ?? '';
  }

  if (bodyConfig.type === 'form-data') {
    const formObj = {};
    if (Array.isArray(bodyConfig.formData)) {
      for (const row of bodyConfig.formData) {
        if (row.enabled && row.key && row.key.trim() !== '') {
          formObj[row.key.trim()] = row.value ?? '';
        }
      }
    }
    return formObj;
  }

  if (bodyConfig.type === 'json') {
    const text = (bodyConfig.jsonText || '').trim();
    if (!text) return {};
    try {
      return JSON.parse(text);
    } catch {
      // In case of invalid JSON during typing, treat as raw text fallback
      return text;
    }
  }

  return null;
}

/**
 * Generates the full Elscript code expression for an HTTP request.
 *
 * @param {Object} config
 * @returns {string}
 */
export function generateElscriptHttpRequest(config) {
  const method = (config.method || 'GET').toUpperCase();
  const fullUrl = buildFullUrl(config.url || '', config.params, config.auth);
  const headers = resolveHeaders(config);
  const options = resolveOptions(config.settings);

  const hasHeaders = Object.keys(headers).length > 0;
  const hasOptions = Object.keys(options).length > 0;

  if (method === 'GET' || method === 'HEAD') {
    const functionName = method === 'GET' ? 'Http.get' : 'Http.head';

    if (!hasHeaders && !hasOptions) {
      return `${functionName}('${escapeElscriptString(fullUrl)}')`;
    }

    const args = [`'${escapeElscriptString(fullUrl)}'`];

    if (hasHeaders || hasOptions) {
      args.push(serializeToElscript(headers, 1, true));
    }

    if (hasOptions) {
      args.push(serializeToElscript(options, 1));
    }

    const formattedArgs = args.map((arg) => `  ${arg}`).join(',\n');
    return `${functionName}(\n${formattedArgs}\n)`;
  }

  if (method === 'POST') {
    const bodyVal = resolveBody(config.body);
    const hasBody = bodyVal !== null;

    if (!hasBody && !hasHeaders && !hasOptions) {
      return `Http.post('${escapeElscriptString(fullUrl)}')`;
    }

    const args = [`'${escapeElscriptString(fullUrl)}'`];

    // POST parameter order: url, body = null, headers = [], options = []
    if (hasBody || hasHeaders || hasOptions) {
      args.push(hasBody ? serializeToElscript(bodyVal, 1) : 'null');
    }

    if (hasHeaders || hasOptions) {
      args.push(hasHeaders ? serializeToElscript(headers, 1, true) : '{}');
    }

    if (hasOptions) {
      args.push(serializeToElscript(options, 1));
    }

    const formattedArgs = args.map((arg) => `  ${arg}`).join(',\n');
    return `Http.post(\n${formattedArgs}\n)`;
  }

  return `Http.get('${escapeElscriptString(fullUrl)}')`;
}
