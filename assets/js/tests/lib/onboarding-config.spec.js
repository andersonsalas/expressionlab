import {
  DEFAULT_MAX_EXECUTION_LIMIT,
  DEFAULT_SIGNATURE_DURATION,
  MAX_INTEGER_CONSTRAINT,
  buildConfigSnippet,
  escapePhpSingleQuoted,
  isValidMaxMindKey,
  isValidStagingUrl,
  normalizeStagingUrl,
  toPositiveInteger,
} from '../../lib/onboarding-config.js';

const KEYS = { publicKeyBase64: 'AgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgI=', saltBase64: 'AAECAwQFBgcICQoLDA0ODw==' };

const defaultSettings = () => ({
  stagingUrl: '',
  database: false,
  fileSystem: false,
  network: false,
  hooks: false,
  maxExecutionLimit: DEFAULT_MAX_EXECUTION_LIMIT,
  signatureDuration: DEFAULT_SIGNATURE_DURATION,
  maxMindApiKey: '',
});

/**
 * Minimal model of how PHP parses a single-quoted string literal starting at `start`
 * (index right after the opening quote). Returns the index of the closing quote.
 */
const findPhpSingleQuotedEnd = (code, start) => {
  for (let i = start; i < code.length; i++) {
    if (code[i] === '\\' && (code[i + 1] === '\\' || code[i + 1] === '\'')) {
      i++;
      continue;
    }
    if (code[i] === '\'') {
      return i;
    }
  }
  return -1;
};

/**
 * Asserts that a `define( 'NAME', '<value>' );` line keeps the value inside its literal.
 */
const expectSafeDefine = (line, name) => {
  const prefix = `define( '${name}', '`;
  expect(line.startsWith(prefix)).toBe(true);
  const end = findPhpSingleQuotedEnd(line, prefix.length);
  expect(end).toBeGreaterThan(-1);
  expect(line.slice(end)).toBe('\' );');
};

describe('onboarding-config', () => {
  describe('escapePhpSingleQuoted', () => {
    it('escapes single quotes and backslashes (backslashes first)', () => {
      expect(escapePhpSingleQuoted('a\'b')).toBe('a\\\'b');
      expect(escapePhpSingleQuoted('a\\b')).toBe('a\\\\b');
      expect(escapePhpSingleQuoted('\\\'')).toBe('\\\\\\\'');
      expect(escapePhpSingleQuoted('abc\\')).toBe('abc\\\\');
    });

    it('strips ASCII control characters, including newlines', () => {
      expect(escapePhpSingleQuoted('a\nb\r\tc\u0000d\u007f')).toBe('abcd');
    });

    it('handles null/undefined/non-strings', () => {
      expect(escapePhpSingleQuoted(null)).toBe('');
      expect(escapePhpSingleQuoted(undefined)).toBe('');
      expect(escapePhpSingleQuoted(42)).toBe('42');
    });

    it.each([
      'staging.test\'); phpinfo(); //',
      '\\\'); phpinfo(); //',
      'x\\',
      '\'\'\'',
      '\\\\\'\\',
      '?>\n<?php system($_GET[1]); ?>',
    ])('never lets the payload break out of the literal: %p', (payload) => {
      const line = `define( 'X', '${escapePhpSingleQuoted(payload)}' );`;
      expectSafeDefine(line, 'X');
    });
  });

  describe('normalizeStagingUrl / isValidStagingUrl', () => {
    it('strips protocol, whitespace and trailing slashes', () => {
      expect(normalizeStagingUrl('  https://staging.example.com/ ')).toBe('staging.example.com');
      expect(normalizeStagingUrl('HTTP://127.0.0.1/site//')).toBe('127.0.0.1/site');
      expect(normalizeStagingUrl(null)).toBe('');
    });

    it.each([
      '',
      'staging.example.com',
      '*.staging.example.com',
      'staging-*.example.com',
      'localhost',
      'localhost:8080',
      '127.0.0.1/expressionlab',
      '127.0.0.1/staging-*',
      'staging.example.com/sub/site',
      '[::1]:8080/site',
      'https://staging.example.com/',
      'my_site.test',
    ])('accepts %p', (value) => {
      expect(isValidStagingUrl(value)).toBe(true);
    });

    it.each([
      'staging.test\'); phpinfo(); //',
      'staging.example.com\\',
      'stag ing.example.com',
      'staging.example.com?x=1',
      'staging.example.com#frag',
      'user@staging.example.com',
      'localhost:port',
      'localhost:123456',
      '<script>',
      'staging.exa\nmple.com',
      '..',
      '/site',
    ])('rejects %p', (value) => {
      expect(isValidStagingUrl(value)).toBe(false);
    });
  });

  describe('isValidMaxMindKey', () => {
    it('accepts empty and well-formed keys', () => {
      expect(isValidMaxMindKey('')).toBe(true);
      expect(isValidMaxMindKey('   ')).toBe(true);
      expect(isValidMaxMindKey('AbC123_def456_mmk')).toBe(true);
      expect(isValidMaxMindKey(undefined)).toBe(true);
    });

    it('rejects quotes, backslashes, whitespace and other symbols', () => {
      expect(isValidMaxMindKey('abc\'def')).toBe(false);
      expect(isValidMaxMindKey('abc\\')).toBe(false);
      expect(isValidMaxMindKey('abc def')).toBe(false);
      expect(isValidMaxMindKey('abc;')).toBe(false);
    });
  });

  describe('toPositiveInteger', () => {
    it.each([
      [1, 1],
      [2, 2],
      [120, 120],
      ['300', 300],
      [' 45 ', 45],
      [1e3, 1000],
      [MAX_INTEGER_CONSTRAINT, MAX_INTEGER_CONSTRAINT],
    ])('accepts %p -> %p', (input, expected) => {
      expect(toPositiveInteger(input)).toBe(expected);
    });

    it.each([
      0, -1, -0, 2.5, NaN, Infinity, -Infinity, MAX_INTEGER_CONSTRAINT + 1, 1e21,
      '', ' ', '1e3', '0x10', '12abc', '-5', '2.5', null, undefined, true, {}, [],
    ])('rejects %p', (input) => {
      expect(toPositiveInteger(input)).toBeNull();
    });
  });

  describe('buildConfigSnippet', () => {
    it('returns an empty string without keys', () => {
      expect(buildConfigSnippet(null, defaultSettings(), 1)).toBe('');
      expect(buildConfigSnippet({ publicKeyBase64: '', saltBase64: 'x' }, defaultSettings(), 1)).toBe('');
    });

    it('emits only the three mandatory constants with default settings', () => {
      const snippet = buildConfigSnippet(KEYS, defaultSettings(), 7);
      expect(snippet.split('\n')).toEqual([
        'define( \'EXPRESSION_LAB_ADMIN_USER_ID\', 7 );',
        `define( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY', '${KEYS.publicKeyBase64}' );`,
        `define( 'EXPRESSION_LAB_ADMIN_SALT', '${KEYS.saltBase64}' );`,
      ]);
    });

    it('emits deltas, a blank spacer line and keeps the mandatory constants last', () => {
      const snippet = buildConfigSnippet(KEYS, {
        ...defaultSettings(),
        stagingUrl: 'https://staging.example.com/',
        database: true,
        fileSystem: true,
        network: true,
        hooks: true,
        maxExecutionLimit: 5,
        signatureDuration: '300',
        maxMindApiKey: ' abc_123 ',
      }, 3);

      expect(snippet.split('\n')).toEqual([
        'define( \'EXPRESSION_LAB_STAGING_URL\', \'staging.example.com\' );',
        'define( \'EXPRESSION_LAB_DATABASE_READONLY\', false );',
        'define( \'EXPRESSION_LAB_FILESYSTEM_READONLY\', false );',
        'define( \'EXPRESSION_LAB_NETWORK_READONLY\', false );',
        'define( \'EXPRESSION_LAB_HOOKS_ENABLED\', true );',
        'define( \'EXPRESSION_LAB_MAX_EXECUTION_LIMIT\', 5 );',
        'define( \'EXPRESSION_LAB_SIGNATURE_DURATION\', 300 );',
        'define( \'EXPRESSION_LAB_MAXMIND_API_KEY\', \'abc_123\' );',
        '',
        'define( \'EXPRESSION_LAB_ADMIN_USER_ID\', 3 );',
        `define( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY', '${KEYS.publicKeyBase64}' );`,
        `define( 'EXPRESSION_LAB_ADMIN_SALT', '${KEYS.saltBase64}' );`,
      ]);
    });

    it('treats truthy non-boolean permission values as not granted', () => {
      const snippet = buildConfigSnippet(KEYS, { ...defaultSettings(), database: 'yes', hooks: 1 }, 1);
      expect(snippet).not.toContain('DATABASE_READONLY');
      expect(snippet).not.toContain('HOOKS_ENABLED');
    });

    it.each([
      [0], [-3], [2.5], [NaN], [''], ['1e3'], [1e21], [Infinity],
    ])('omits invalid numeric constraint %p (never emits NaN/scientific/negative literals)', (value) => {
      const snippet = buildConfigSnippet(KEYS, { ...defaultSettings(), maxExecutionLimit: value, signatureDuration: value }, 1);
      expect(snippet).not.toContain('MAX_EXECUTION_LIMIT');
      expect(snippet).not.toContain('SIGNATURE_DURATION');
      expect(snippet).not.toMatch(/NaN|Infinity|e\+|-\d/);
    });

    it('safely escapes hostile staging URL and MaxMind values (defense in depth)', () => {
      const snippet = buildConfigSnippet(KEYS, {
        ...defaultSettings(),
        stagingUrl: 'staging.test\'); phpinfo(); //',
        maxMindApiKey: '\\\'); phpinfo(); //',
      }, 1);
      const lines = snippet.split('\n');
      expectSafeDefine(lines[0], 'EXPRESSION_LAB_STAGING_URL');
      expectSafeDefine(lines[1], 'EXPRESSION_LAB_MAXMIND_API_KEY');
    });

    it('coerces the user id to a positive integer', () => {
      expect(buildConfigSnippet(KEYS, defaultSettings(), '12')).toContain('EXPRESSION_LAB_ADMIN_USER_ID\', 12 );');
      expect(buildConfigSnippet(KEYS, defaultSettings(), '1); phpinfo(); //')).toContain('EXPRESSION_LAB_ADMIN_USER_ID\', 1 );');
      expect(buildConfigSnippet(KEYS, defaultSettings(), undefined)).toContain('EXPRESSION_LAB_ADMIN_USER_ID\', 1 );');
    });

    it('tolerates missing settings', () => {
      expect(buildConfigSnippet(KEYS, undefined, 1).split('\n')).toHaveLength(3);
    });
  });
});
