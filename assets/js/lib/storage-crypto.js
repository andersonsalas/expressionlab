import { arrayBufferToBase64, base64ToArrayBuffer, debugLog } from './helpers.js';

export const STORAGE_ENC_PREFIX = 'el_enc:v1:';
export const STORAGE_MODE_SEED = 'seed';
export const STORAGE_MODE_DEV = 'dev';
export const LICENSE_STORAGE_KEY = 'el_license';
export const LEGACY_LICENSE_STORAGE_KEY = 'expressionlab_pro_license';

export const STORAGE_STATUS_OK = 'ok';
export const STORAGE_STATUS_EMPTY = 'empty';
export const STORAGE_STATUS_LOCKED = 'locked';
export const STORAGE_STATUS_PLAINTEXT = 'plaintext';
export const STORAGE_STATUS_MODE_MISMATCH = 'mode_mismatch';
export const STORAGE_STATUS_INVALID = 'invalid';

const AES_GCM_IV_LENGTH = 12;
const AES_GCM_TAG_LENGTH_BITS = 128;
const AES_GCM_TAG_LENGTH_BYTES = AES_GCM_TAG_LENGTH_BITS / 8;

const textEncoder = new TextEncoder();
const textDecoder = new TextDecoder();

let activeKeyContext = null;
let fallbackDevKeyContext = null;

/**
 * Resolves the Web Crypto SubtleCrypto interface in browser or test environments.
 *
 * @returns {SubtleCrypto|null}
 */
function getSubtleCrypto() {
  if (typeof window !== 'undefined' && window.crypto && window.crypto.subtle) {
    return window.crypto.subtle;
  }
  if (typeof globalThis !== 'undefined' && globalThis.crypto && globalThis.crypto.subtle) {
    return globalThis.crypto.subtle;
  }
  return null;
}

/**
 * Fills a Uint8Array with cryptographically secure random bytes.
 *
 * Throws when no CSPRNG is available: an all-zero IV would cause catastrophic AES-GCM nonce reuse.
 *
 * @param {number} length
 * @returns {Uint8Array}
 */
function getRandomBytes(length) {
  const buf = new Uint8Array(length);
  if (typeof window !== 'undefined' && window.crypto && typeof window.crypto.getRandomValues === 'function') {
    window.crypto.getRandomValues(buf);
  } else if (typeof globalThis !== 'undefined' && globalThis.crypto && typeof globalThis.crypto.getRandomValues === 'function') {
    globalThis.crypto.getRandomValues(buf);
  } else {
    throw new Error('A cryptographically secure random number generator is not available.');
  }
  return buf;
}

/**
 * Encodes a string as UTF-8 bytes.
 *
 * @param {string} value
 * @returns {Uint8Array}
 */
function encodeUtf8(value) {
  return textEncoder.encode(String(value ?? ''));
}

/**
 * Builds the Additional Authenticated Data (AAD) binding the ciphertext to its
 * localStorage key name and the administrator public key.
 *
 * @param {string} storageKey
 * @param {string} publicKeyBase64
 * @returns {Uint8Array}
 */
function buildAdditionalData(storageKey, publicKeyBase64) {
  return encodeUtf8(`${String(storageKey || '')}:${String(publicKeyBase64 || '')}`);
}

/**
 * Determines whether the current environment requires a passphrase unlock before
 * storage keys can be derived.
 *
 * @param {object|null} [elSettings]
 * @returns {boolean}
 */
export function isPassphraseLockRequired(elSettings = typeof window !== 'undefined' ? window.el_settings : null) {
  if (!elSettings) return false;
  const hasCredentials = Boolean(elSettings.public_key && elSettings.salt);
  const lockEnabled = Boolean(elSettings.settings?.enable_sandbox || elSettings.settings?.debug_mode);
  return hasCredentials && lockEnabled;
}

/**
 * Derives a non-extractable AES-256-GCM CryptoKey from the 32-byte Argon2id seed
 * using HKDF-SHA256 for cryptographic domain separation from Ed25519 signing.
 *
 * @param {Uint8Array} seedBytes
 * @param {string} saltBase64
 * @param {string} publicKeyBase64
 * @param {number|string|null} [userId=null]
 * @returns {Promise<{cryptoKey: CryptoKey, mode: 'seed', publicKeyBase64: string, saltBase64: string, userId: number|string|null}>}
 */
export async function deriveStorageKeyFromSeed(seedBytes, saltBase64, publicKeyBase64, userId = null) {
  const subtle = getSubtleCrypto();
  if (!subtle) {
    throw new Error('Web Crypto API (SubtleCrypto) is not available.');
  }
  if (!seedBytes || typeof seedBytes.length !== 'number' || seedBytes.length < 16) {
    throw new Error('Invalid seed material for storage key derivation.');
  }

  const ikm = new Uint8Array(seedBytes.length);
  ikm.set(seedBytes);

  try {
    const baseKey = await subtle.importKey(
      'raw',
      ikm,
      'HKDF',
      false,
      ['deriveKey']
    );

    const normalizedSalt = String(saltBase64 || 'el-default-salt');
    const normalizedPubKey = String(publicKeyBase64 || '');

    const cryptoKey = await subtle.deriveKey(
      {
        name: 'HKDF',
        hash: 'SHA-256',
        salt: encodeUtf8(normalizedSalt),
        info: encodeUtf8(`expressionlab-storage-aead-v1:${normalizedPubKey}`),
      },
      baseKey,
      { name: 'AES-GCM', length: 256 },
      false,
      ['encrypt', 'decrypt']
    );

    return {
      cryptoKey,
      mode: STORAGE_MODE_SEED,
      publicKeyBase64: normalizedPubKey,
      saltBase64: normalizedSalt,
      userId: userId ?? null,
    };
  } finally {
    ikm.fill(0);
  }
}

/**
 * Derives a deterministic non-extractable AES-256-GCM CryptoKey for development environments
 * where EXPRESSION_LAB_SANDBOX_ENABLED is false and passphrase unlock is bypassed.
 *
 * @param {string} [publicKeyBase64='']
 * @param {string} [saltBase64='']
 * @param {number|string|null} [userId=null]
 * @returns {Promise<{cryptoKey: CryptoKey, mode: 'dev', publicKeyBase64: string, saltBase64: string, userId: number|string|null}>}
 */
export async function deriveStorageKeyForDev(publicKeyBase64 = '', saltBase64 = '', userId = null) {
  const subtle = getSubtleCrypto();
  if (!subtle) {
    throw new Error('Web Crypto API (SubtleCrypto) is not available.');
  }

  const normalizedPubKey = String(publicKeyBase64 || '');
  const normalizedSalt = String(saltBase64 || '');
  const normalizedUser = userId ?? 0;
  const ikm = encodeUtf8(`${normalizedPubKey}:${normalizedSalt}:${normalizedUser}`);

  try {
    const baseKey = await subtle.importKey(
      'raw',
      ikm,
      'HKDF',
      false,
      ['deriveKey']
    );

    const cryptoKey = await subtle.deriveKey(
      {
        name: 'HKDF',
        hash: 'SHA-256',
        salt: encodeUtf8(normalizedSalt || 'el-dev-salt'),
        info: encodeUtf8(`expressionlab-storage-dev-v1:${normalizedPubKey}`),
      },
      baseKey,
      { name: 'AES-GCM', length: 256 },
      false,
      ['encrypt', 'decrypt']
    );

    return {
      cryptoKey,
      mode: STORAGE_MODE_DEV,
      publicKeyBase64: normalizedPubKey,
      saltBase64: normalizedSalt,
      userId: normalizedUser,
    };
  } finally {
    ikm.fill(0);
  }
}

/**
 * Initializes the active storage encryption key from the user's Argon2id seed.
 * Also prepares fallback dev-mode parameters so entries created when sandbox was disabled
 * (or during dev-mode onboarding) can be decrypted and transparently upgraded.
 *
 * @param {Uint8Array} seedBytes
 * @param {string} saltBase64
 * @param {string} publicKeyBase64
 * @param {number|string|null} [userId=null]
 * @returns {Promise<object>}
 */
export async function initStorageCryptoFromSeed(
  seedBytes,
  saltBase64 = typeof window !== 'undefined' ? window.el_settings?.salt : '',
  publicKeyBase64 = typeof window !== 'undefined' ? window.el_settings?.public_key : '',
  userId = null
) {
  const resolvedUserId = userId ?? (typeof window !== 'undefined' ? window.el_settings?.user_id : null) ?? null;
  const context = await deriveStorageKeyFromSeed(seedBytes, saltBase64, publicKeyBase64, resolvedUserId);
  activeKeyContext = context;
  fallbackDevKeyContext = null;
  return context;
}

/**
 * Initializes the active storage encryption key in development mode (when sandbox is disabled).
 *
 * @param {object|null} [elSettings]
 * @returns {Promise<object>}
 */
export async function initStorageCryptoDevMode(elSettings = typeof window !== 'undefined' ? window.el_settings : null) {
  const pubKey = elSettings?.public_key || '';
  const salt = elSettings?.salt || '';
  const userId = elSettings?.user_id ?? 0;
  const context = await deriveStorageKeyForDev(pubKey, salt, userId);
  activeKeyContext = context;
  return context;
}

/**
 * Sets a pre-derived storage key context (e.g., cached during Onboarding Step 4).
 *
 * @param {object|null} context
 */
export function setStorageCryptoContext(context) {
  activeKeyContext = context || null;
  fallbackDevKeyContext = null;
}

/**
 * Clears all in-memory storage encryption keys (e.g., when locking the console).
 */
export function clearStorageCrypto() {
  activeKeyContext = null;
  fallbackDevKeyContext = null;
}

/**
 * Checks whether an active storage encryption key is ready in memory.
 *
 * @returns {boolean}
 */
export function isStorageCryptoReady() {
  return Boolean(activeKeyContext && activeKeyContext.cryptoKey);
}

/**
 * Returns the active storage encryption mode ('seed' | 'dev' | null).
 *
 * @returns {'seed'|'dev'|null}
 */
export function getStorageCryptoMode() {
  return activeKeyContext?.mode || null;
}

/**
 * Resolves the active key context, auto-initializing dev mode only when passphrase lock
 * is not required (e.g., EXPRESSION_LAB_SANDBOX_ENABLED === false).
 *
 * @returns {Promise<object|null>}
 */
export async function ensureStorageCryptoContext() {
  if (activeKeyContext && activeKeyContext.cryptoKey) {
    return activeKeyContext;
  }
  if (isPassphraseLockRequired()) {
    return null;
  }
  try {
    return await initStorageCryptoDevMode();
  } catch (err) {
    debugLog('Failed to initialize dev storage crypto context', err, 'warn');
    return null;
  }
}

/**
 * Encrypts a plaintext string for a specific localStorage key using AES-256-GCM.
 * Returns the serialized envelope `el_enc:v1:<mode>:<base64(iv || ciphertext || tag)>`,
 * or null if the console is locked or encryption fails.
 *
 * @param {string} storageKey
 * @param {string} plainText
 * @param {object|null} [customContext=null]
 * @returns {Promise<string|null>}
 */
export async function encryptStorageValue(storageKey, plainText, customContext = null) {
  const subtle = getSubtleCrypto();
  if (!subtle) return null;

  const context = customContext || (activeKeyContext && activeKeyContext.cryptoKey ? activeKeyContext : await ensureStorageCryptoContext());
  if (!context || !context.cryptoKey) {
    return null;
  }

  try {
    const iv = getRandomBytes(AES_GCM_IV_LENGTH);
    const additionalData = buildAdditionalData(storageKey, context.publicKeyBase64);
    const plainBytes = encodeUtf8(plainText ?? '');

    const cipherBuffer = await subtle.encrypt(
      {
        name: 'AES-GCM',
        iv,
        additionalData,
        tagLength: AES_GCM_TAG_LENGTH_BITS,
      },
      context.cryptoKey,
      plainBytes
    );

    const cipherBytes = new Uint8Array(cipherBuffer);
    const combined = new Uint8Array(iv.length + cipherBytes.length);
    combined.set(iv, 0);
    combined.set(cipherBytes, iv.length);

    const payloadBase64 = arrayBufferToBase64(combined);
    return `${STORAGE_ENC_PREFIX}${context.mode}:${payloadBase64}`;
  } catch (err) {
    debugLog('Failed to encrypt localStorage value', { key: storageKey, err }, 'warn');
    return null;
  }
}

/**
 * Internal helper to decrypt an AES-256-GCM payload with a specific key context.
 * Returns null for structurally invalid payloads; throws when GCM authentication fails.
 *
 * @param {SubtleCrypto} subtle
 * @param {object} context
 * @param {string} storageKey
 * @param {string} payloadBase64
 * @returns {Promise<string|null>}
 */
async function decryptWithContext(subtle, context, storageKey, payloadBase64) {
  const combined = base64ToArrayBuffer(payloadBase64);
  // IV plus the 128-bit tag is the smallest well-formed payload (empty plaintext).
  if (!combined || combined.length < AES_GCM_IV_LENGTH + AES_GCM_TAG_LENGTH_BYTES) {
    return null;
  }

  const iv = combined.slice(0, AES_GCM_IV_LENGTH);
  const cipherBytes = combined.slice(AES_GCM_IV_LENGTH);
  const additionalData = buildAdditionalData(storageKey, context.publicKeyBase64);

  const plainBuffer = await subtle.decrypt(
    {
      name: 'AES-GCM',
      iv,
      additionalData,
      tagLength: AES_GCM_TAG_LENGTH_BITS,
    },
    context.cryptoKey,
    cipherBytes
  );

  return textDecoder.decode(new Uint8Array(plainBuffer));
}

/**
 * Decrypts a stored localStorage envelope (`el_enc:v1:<mode>:<base64>`) for a specific key.
 *
 * - Rejects unencrypted values or tampered ciphertexts.
 * - When active mode is `seed` and a stored envelope is `dev` (e.g., created while sandbox
 *   was disabled), decrypts using the fallback `dev` key and reports `shouldUpgrade = true`
 *   so the caller can transparently re-encrypt under `seed`.
 * - When active mode is `dev` and a stored envelope is `seed`, returns `null` without
 *   destroying the `seed`-encrypted entry.
 *
 * The returned `status` lets callers distinguish a missing value (`empty`), a locked console
 * (`locked`), unencrypted legacy data (`plaintext`), a non-destructive `seed`/`dev` mismatch
 * (`mode_mismatch`) and malformed or unauthenticated ciphertexts (`invalid`).
 *
 * @param {string} storageKey
 * @param {string|null} rawValue
 * @param {object|null} [customContext=null]
 * @returns {Promise<{ value: string|null, shouldUpgrade: boolean, status: string }>}
 */
export async function decryptStorageEnvelope(storageKey, rawValue, customContext = null) {
  const result = (status, value = null, shouldUpgrade = false) => ({ value, shouldUpgrade, status });

  if (typeof rawValue !== 'string' || rawValue === '') {
    return result(STORAGE_STATUS_EMPTY);
  }

  if (!rawValue.startsWith(STORAGE_ENC_PREFIX)) {
    return result(STORAGE_STATUS_PLAINTEXT);
  }

  const subtle = getSubtleCrypto();
  if (!subtle) {
    return result(STORAGE_STATUS_LOCKED);
  }

  const context = customContext || (activeKeyContext && activeKeyContext.cryptoKey ? activeKeyContext : await ensureStorageCryptoContext());
  if (!context || !context.cryptoKey) {
    return result(STORAGE_STATUS_LOCKED);
  }

  const remainder = rawValue.slice(STORAGE_ENC_PREFIX.length);
  const colonIdx = remainder.indexOf(':');
  if (colonIdx === -1) {
    return result(STORAGE_STATUS_INVALID);
  }

  const envelopeMode = remainder.slice(0, colonIdx);
  const payloadBase64 = remainder.slice(colonIdx + 1);
  if (!payloadBase64) {
    return result(STORAGE_STATUS_INVALID);
  }

  try {
    if (envelopeMode === context.mode) {
      const plainText = await decryptWithContext(subtle, context, storageKey, payloadBase64);
      return plainText === null ? result(STORAGE_STATUS_INVALID) : result(STORAGE_STATUS_OK, plainText);
    }

    // If unlocked with seed, allow reading & upgrading dev-mode envelopes.
    if (context.mode === STORAGE_MODE_SEED && envelopeMode === STORAGE_MODE_DEV) {
      if (!fallbackDevKeyContext) {
        const userId = context.userId ?? (typeof window !== 'undefined' ? window.el_settings?.user_id : 0) ?? 0;
        fallbackDevKeyContext = await deriveStorageKeyForDev(
          context.publicKeyBase64,
          context.saltBase64,
          userId
        );
      }
      const plainText = await decryptWithContext(subtle, fallbackDevKeyContext, storageKey, payloadBase64);
      return plainText === null ? result(STORAGE_STATUS_INVALID) : result(STORAGE_STATUS_OK, plainText, true);
    }

    // If running in dev mode and encountering a seed-encrypted envelope, treat as cache miss
    // without deleting the seed-encrypted data.
    if (context.mode === STORAGE_MODE_DEV && envelopeMode === STORAGE_MODE_SEED) {
      return result(STORAGE_STATUS_MODE_MISMATCH);
    }

    return result(STORAGE_STATUS_INVALID);
  } catch {
    debugLog('Failed to decrypt localStorage value', { key: storageKey, mode: envelopeMode }, 'warn');
    return result(STORAGE_STATUS_INVALID);
  }
}

/**
 * Decrypts a stored localStorage value and returns the plaintext string (or null).
 *
 * @param {string} storageKey
 * @param {string|null} rawValue
 * @param {object|null} [customContext=null]
 * @returns {Promise<string|null>}
 */
export async function decryptStorageValue(storageKey, rawValue, customContext = null) {
  const { value } = await decryptStorageEnvelope(storageKey, rawValue, customContext);
  return value;
}

