import {
  escapeElscriptString,
  serializeToElscript,
  parseUrlAndParams,
  buildFullUrl,
  resolveHeaders,
  resolveOptions,
  resolveBody,
  generateElscriptHttpRequest
} from '../../lib/http-builder-generator.js';

describe('http-builder-generator.js', () => {
  describe('escapeElscriptString()', () => {
    it('escapes single quotes and backslashes', () => {
      expect(escapeElscriptString('hello \'world\' \\')).toBe('hello \\\'world\\\' \\\\');
    });

    it('escapes newlines', () => {
      expect(escapeElscriptString('line1\nline2')).toBe('line1\\nline2');
    });
  });

  describe('serializeToElscript()', () => {
    it('serializes primitives', () => {
      expect(serializeToElscript(null)).toBe('null');
      expect(serializeToElscript(true)).toBe('true');
      expect(serializeToElscript(false)).toBe('false');
      expect(serializeToElscript(42)).toBe('42');
      expect(serializeToElscript('hello')).toBe('\'hello\'');
    });

    it('serializes arrays', () => {
      expect(serializeToElscript([])).toBe('[]');
      expect(serializeToElscript([1, 'a', true])).toBe('[\n  1,\n  \'a\',\n  true\n]');
    });

    it('serializes objects and quotes special keys', () => {
      expect(serializeToElscript({})).toBe('{}');
      const obj = {
        name: 'test',
        'Content-Type': 'application/json',
        count: 5
      };
      const res = serializeToElscript(obj);
      expect(res).toContain('name: \'test\'');
      expect(res).toContain('\'Content-Type\': \'application/json\'');
      expect(res).toContain('count: 5');
    });
  });

  describe('parseUrlAndParams() and buildFullUrl()', () => {
    it('parses URL query params into structured list', () => {
      const { baseUrl, params } = parseUrlAndParams('https://api.example.com/items?page=1&limit=20');
      expect(baseUrl).toBe('https://api.example.com/items');
      expect(params).toHaveLength(2);
      expect(params[0]).toEqual({ enabled: true, key: 'page', value: '1' });
      expect(params[1]).toEqual({ enabled: true, key: 'limit', value: '20' });
    });

    it('builds full URL including query params and active api key', () => {
      const url = buildFullUrl(
        'https://api.example.com/items',
        [
          { enabled: true, key: 'page', value: '2' },
          { enabled: false, key: 'disabled', value: 'no' }
        ],
        {
          type: 'apikey',
          apiKeyAddTo: 'query',
          apiKeyName: 'api_token',
          apiKeyValue: 'secret123'
        }
      );
      expect(url).toBe('https://api.example.com/items?page=2&api_token=secret123');
    });

    it('does not throw on malformed percent sequences', () => {
      const { params } = parseUrlAndParams('https://a.com/?q=100%&k=%E0%A4');
      expect(params[0]).toEqual({ enabled: true, key: 'q', value: '100%' });
      expect(params).toHaveLength(2);
    });

    it('preserves URL fragments through parse/build round-trip', () => {
      const { baseUrl, params } = parseUrlAndParams('https://a.com/p?x=1#frag');
      expect(params).toEqual([{ enabled: true, key: 'x', value: '1' }]);
      expect(buildFullUrl(baseUrl, params)).toBe('https://a.com/p?x=1#frag');
      expect(buildFullUrl('https://a.com/p#frag', [{ enabled: true, key: 'y', value: '2' }]))
        .toBe('https://a.com/p?y=2#frag');
    });

    it('encodes special characters in query values', () => {
      const url = buildFullUrl('https://a.com', [{ enabled: true, key: 'q', value: 'a b&c=d' }]);
      expect(url).toBe('https://a.com?q=a%20b%26c%3Dd');
    });
  });

  describe('resolveHeaders()', () => {
    it('resolves Bearer token', () => {
      const headers = resolveHeaders({
        method: 'GET',
        headers: [{ enabled: true, key: 'Accept', value: 'application/json' }],
        auth: {
          type: 'bearer',
          bearerToken: 'my-jwt-token'
        }
      });
      expect(headers['Accept']).toBe('application/json');
      expect(headers['Authorization']).toBe('Bearer my-jwt-token');
    });

    it('resolves Basic auth', () => {
      const headers = resolveHeaders({
        method: 'GET',
        headers: [],
        auth: {
          type: 'basic',
          basicUser: 'admin',
          basicPass: 'secret'
        }
      });
      expect(headers['Authorization']).toBe(`Basic ${Buffer.from('admin:secret').toString('base64')}`);
    });

    it('encodes Basic auth credentials as UTF-8 without throwing', () => {
      const headers = resolveHeaders({
        method: 'GET',
        auth: { type: 'basic', basicUser: 'josé', basicPass: '€uro' }
      });
      expect(headers['Authorization']).toBe(`Basic ${Buffer.from('josé:€uro', 'utf8').toString('base64')}`);
    });

    it('does not override a user-supplied Content-Type (case-insensitive)', () => {
      const headers = resolveHeaders({
        method: 'POST',
        headers: [{ enabled: true, key: 'content-type', value: 'application/vnd.api+json' }],
        body: { type: 'json', jsonText: '{}' }
      });
      expect(headers['Content-Type']).toBeUndefined();
      expect(headers['content-type']).toBe('application/vnd.api+json');
    });

    it('auto-adds Content-Type for POST JSON', () => {
      const headers = resolveHeaders({
        method: 'POST',
        headers: [],
        body: { type: 'json', jsonText: '{}' }
      });
      expect(headers['Content-Type']).toBe('application/json');
    });
  });

  describe('resolveOptions()', () => {
    it('filters out defaults and keeps non-default options', () => {
      const options = resolveOptions({
        timeout: 25,
        userAgent: 'CustomBot/1.0',
        httpVersion: '1.0',
        blocking: false,
        compress: true,
        decompress: false,
        cookies: [{ enabled: true, key: 'session', value: 'abc' }]
      });

      expect(options.timeout).toBe(25);
      expect(options['user-agent']).toBe('CustomBot/1.0');
      expect(options.httpversion).toBe('1.0');
      expect(options.blocking).toBe(false);
      expect(options.compress).toBe(true);
      expect(options.decompress).toBe(false);
      expect(options.cookies).toEqual({ session: 'abc' });
    });

    it('returns empty object when all options match defaults', () => {
      const options = resolveOptions({
        timeout: 10,
        userAgent: '',
        httpVersion: '1.1',
        blocking: true,
        compress: false,
        decompress: true,
        cookies: []
      });
      expect(Object.keys(options)).toHaveLength(0);
    });
  });

  describe('resolveBody()', () => {
    it('handles raw, form-data, and json', () => {
      expect(resolveBody({ type: 'none' })).toBeNull();
      expect(resolveBody({ type: 'raw', rawText: 'hello' })).toBe('hello');
      expect(resolveBody({
        type: 'form-data',
        formData: [
          { enabled: true, key: 'k1', value: 'v1' },
          { enabled: false, key: 'k2', value: 'v2' }
        ]
      })).toEqual({ k1: 'v1' });
      expect(resolveBody({ type: 'json', jsonText: '{"num": 1}' })).toEqual({ num: 1 });
    });
  });

  describe('generateElscriptHttpRequest()', () => {
    it('generates simple GET request', () => {
      const code = generateElscriptHttpRequest({
        method: 'GET',
        url: 'https://api.github.com/zen',
        params: [],
        headers: [],
        settings: {}
      });
      expect(code).toBe('Http.get(\'https://api.github.com/zen\')');
    });

    it('generates GET request with headers and options', () => {
      const code = generateElscriptHttpRequest({
        method: 'GET',
        url: 'https://api.github.com/user',
        headers: [{ enabled: true, key: 'Accept', value: 'application/json' }],
        settings: { timeout: 30 }
      });

      expect(code).toContain('Http.get(');
      expect(code).toContain('\'https://api.github.com/user\'');
      expect(code).toContain('\'Accept\': \'application/json\'');
      expect(code).toContain('timeout: 30');
    });

    it('generates POST request with JSON body and auto Content-Type', () => {
      const code = generateElscriptHttpRequest({
        method: 'POST',
        url: 'https://httpbin.org/post',
        body: {
          type: 'json',
          jsonText: JSON.stringify({ action: 'sync', site_id: 1 })
        },
        headers: []
      });

      expect(code).toContain('Http.post(');
      expect(code).toContain('\'https://httpbin.org/post\'');
      expect(code).toContain('action: \'sync\'');
      expect(code).toContain('site_id: 1');
      expect(code).toContain('\'Content-Type\': \'application/json\'');
    });

    it('generates HEAD request', () => {
      const code = generateElscriptHttpRequest({
        method: 'HEAD',
        url: 'https://wordpress.org'
      });
      expect(code).toBe('Http.head(\'https://wordpress.org\')');
    });

    it('emits null body and {} headers placeholders for POST with options only', () => {
      const code = generateElscriptHttpRequest({
        method: 'POST',
        url: 'https://httpbin.org/post',
        body: { type: 'none' },
        settings: { timeout: 30 }
      });
      expect(code).toBe('Http.post(\n  \'https://httpbin.org/post\',\n  null,\n  {},\n  {\n    timeout: 30\n  }\n)');
    });

    it('emits {} headers placeholder for GET with options only', () => {
      const code = generateElscriptHttpRequest({
        method: 'GET',
        url: 'https://a.com',
        settings: { timeout: 30 }
      });
      expect(code).toContain('\'https://a.com\',\n  {},\n  {');
    });

    it('never forwards unsupported options', () => {
      const options = resolveOptions({ timeout: 20, method: 'DELETE', redirection: 9 });
      expect(Object.keys(options)).toEqual(['timeout']);
    });

    it('escapes quotes and newlines in URL, header values and raw body', () => {
      const code = generateElscriptHttpRequest({
        method: 'POST',
        url: 'https://a.com/?q=it\'s',
        headers: [{ enabled: true, key: 'X-Note', value: 'a\'b\\c' }],
        body: { type: 'raw', rawText: 'line1\nit\'s', rawContentType: 'text/plain' }
      });
      expect(code).toContain('it\\\'s');
      expect(code).toContain('\'X-Note\': \'a\\\'b\\\\c\'');
      expect(code).toContain('\'line1\\nit\\\'s\'');
    });

    it('falls back to raw text for invalid JSON bodies', () => {
      expect(resolveBody({ type: 'json', jsonText: '{ "a": ' })).toBe('{ "a":');
    });

    it('serializes cookies as an associative map', () => {
      const code = generateElscriptHttpRequest({
        method: 'GET',
        url: 'https://a.com',
        settings: { cookies: [{ enabled: true, key: 'sid', value: 'x y' }] }
      });
      expect(code).toContain('cookies: {\n      sid: \'x y\'\n    }');
    });
  });
});
