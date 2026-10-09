/**
 * Pure helpers used by the onboarding wizard to validate user-supplied settings
 * and to build the `wp-config.php` snippet shown in the verification step.
 *
 * Every value interpolated into the generated PHP code goes through one of the
 * sanitizers below, so the snippet can never break out of its string/number
 * literal context regardless of what the user types in the settings form.
 */

/**
 * Default values of the engine constraints (must match expressionlab.php).
 */
export const DEFAULT_MAX_EXECUTION_LIMIT = 2;
export const DEFAULT_SIGNATURE_DURATION = 120;

/**
 * Minimum passphrase length accepted by the key generation step.
 */
export const MIN_PASSPHRASE_LENGTH = 8;

/**
 * Upper bound for integer constraints (portable 32-bit PHP integer).
 */
export const MAX_INTEGER_CONSTRAINT = 2147483647;

const PROTOCOL_PREFIX_REGEX = /^[a-z][a-z0-9+.-]*:\/\//i;

/**
 * Accepted staging URL pattern (protocol already stripped):
 * - host: DNS labels (with optional `*` wildcards) or a bracketed IPv6 literal,
 * - optional numeric port,
 * - optional path made of URL-safe characters (with optional `*` wildcards).
 */
const STAGING_URL_REGEX = /^(?:\[[0-9a-f:.]+\]|[a-z0-9*_-]+(?:\.[a-z0-9*_-]+)*)(?::\d{1,5})?(?:\/[a-z0-9*._~%+-]*)*$/i;

/**
 * MaxMind license keys are made of alphanumeric characters and underscores.
 */
const MAXMIND_KEY_REGEX = /^[A-Za-z0-9_-]+$/;

/**
 * Escapes a value so it can be safely embedded inside a single-quoted PHP string literal.
 *
 * Inside single quotes PHP only interprets `\\` and `\'`, so escaping backslashes first
 * and single quotes second is sufficient to make break-outs impossible. ASCII control
 * characters (including newlines) are stripped as an additional hardening measure.
 *
 * @param {*} value
 * @returns {string}
 */
export function escapePhpSingleQuoted(value) {
  return String(value ?? '')
    // eslint-disable-next-line no-control-regex
    .replace(/[\u0000-\u001f\u007f]/g, '')
    .replace(/\\/g, '\\\\')
    .replace(/'/g, '\\\'');
}

/**
 * Normalizes a staging URL pattern: trims whitespace, strips the protocol prefix
 * and removes trailing slashes (the PHP matcher ignores them anyway).
 *
 * @param {*} value
 * @returns {string}
 */
export function normalizeStagingUrl(value) {
  if (typeof value !== 'string') {
    return '';
  }
  return value.trim().replace(PROTOCOL_PREFIX_REGEX, '').replace(/\/+$/, '');
}

/**
 * Checks whether a (normalized) staging URL pattern is syntactically valid.
 * An empty value is valid and means "environment check disabled".
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isValidStagingUrl(value) {
  const normalized = normalizeStagingUrl(value);
  if (normalized === '') {
    return true;
  }
  return STAGING_URL_REGEX.test(normalized);
}

/**
 * Checks whether a MaxMind license key is syntactically valid.
 * An empty value is valid and means "no MaxMind integration".
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isValidMaxMindKey(value) {
  const trimmed = typeof value === 'string' ? value.trim() : '';
  if (trimmed === '') {
    return true;
  }
  return MAXMIND_KEY_REGEX.test(trimmed);
}

/**
 * Converts a value to a strictly positive integer.
 *
 * Rejects NaN, Infinity, negative numbers, zero, decimals, empty strings, strings
 * that are not plain digit sequences (e.g. `1e3`, `0x10`, `12abc`) and values above
 * {@link MAX_INTEGER_CONSTRAINT}.
 *
 * @param {*} value
 * @returns {number|null} The integer, or null if the value is not acceptable.
 */
export function toPositiveInteger(value) {
  let n;
  if (typeof value === 'number') {
    n = value;
  } else if (typeof value === 'string' && /^\d+$/.test(value.trim())) {
    n = Number(value.trim());
  } else {
    return null;
  }
  if (!Number.isSafeInteger(n) || n <= 0 || n > MAX_INTEGER_CONSTRAINT) {
    return null;
  }
  return n;
}

/**
 * Builds the `wp-config.php` snippet.
 *
 * Only the three mandatory authentication constants are always emitted; optional
 * constants are emitted exclusively when they differ from the plugin defaults.
 *
 * @param {{publicKeyBase64: string, saltBase64: string}|null} keys Derived public credentials.
 * @param {object} settings Wizard settings.
 * @param {*} userId WordPress user ID of the administrator.
 * @returns {string} The snippet, or an empty string when no keys are available.
 */
export function buildConfigSnippet(keys, settings, userId) {
  if (!keys || !keys.publicKeyBase64 || !keys.saltBase64) {
    return '';
  }

  const s = settings || {};
  const lines = [];

  // Optional staging URL guardrail.
  const stagingUrl = normalizeStagingUrl(s.stagingUrl);
  if (stagingUrl) {
    lines.push(`define( 'EXPRESSION_LAB_STAGING_URL', '${escapePhpSingleQuoted(stagingUrl)}' );`);
  }

  // Optional permissions (only when granted).
  if (s.database === true) {
    lines.push('define( \'EXPRESSION_LAB_DATABASE_READONLY\', false );');
  }
  if (s.fileSystem === true) {
    lines.push('define( \'EXPRESSION_LAB_FILESYSTEM_READONLY\', false );');
  }
  if (s.network === true) {
    lines.push('define( \'EXPRESSION_LAB_NETWORK_READONLY\', false );');
  }
  if (s.hooks === true) {
    lines.push('define( \'EXPRESSION_LAB_HOOKS_ENABLED\', true );');
  }

  // Optional engine constraints (only when valid and different from defaults).
  const maxExecutionLimit = toPositiveInteger(s.maxExecutionLimit);
  if (maxExecutionLimit !== null && maxExecutionLimit !== DEFAULT_MAX_EXECUTION_LIMIT) {
    lines.push(`define( 'EXPRESSION_LAB_MAX_EXECUTION_LIMIT', ${maxExecutionLimit} );`);
  }
  const signatureDuration = toPositiveInteger(s.signatureDuration);
  if (signatureDuration !== null && signatureDuration !== DEFAULT_SIGNATURE_DURATION) {
    lines.push(`define( 'EXPRESSION_LAB_SIGNATURE_DURATION', ${signatureDuration} );`);
  }

  // Optional integrations.
  const maxMindKey = typeof s.maxMindApiKey === 'string' ? s.maxMindApiKey.trim() : '';
  if (maxMindKey) {
    lines.push(`define( 'EXPRESSION_LAB_MAXMIND_API_KEY', '${escapePhpSingleQuoted(maxMindKey)}' );`);
  }

  // Spacer between custom settings and required authentication constants.
  if (lines.length > 0) {
    lines.push('');
  }

  // Required authentication constants.
  const adminUserId = toPositiveInteger(userId) ?? 1;
  lines.push(`define( 'EXPRESSION_LAB_ADMIN_USER_ID', ${adminUserId} );`);
  lines.push(`define( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY', '${escapePhpSingleQuoted(keys.publicKeyBase64)}' );`);
  lines.push(`define( 'EXPRESSION_LAB_ADMIN_SALT', '${escapePhpSingleQuoted(keys.saltBase64)}' );`);

  return lines.join('\n');
}
