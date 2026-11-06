import {
  STORAGE_ENC_PREFIX,
  STORAGE_MODE_SEED,
  STORAGE_MODE_DEV,
  STORAGE_STATUS_OK,
  STORAGE_STATUS_EMPTY,
  STORAGE_STATUS_LOCKED,
  STORAGE_STATUS_PLAINTEXT,
  STORAGE_STATUS_MODE_MISMATCH,
  STORAGE_STATUS_INVALID,
  isPassphraseLockRequired,
  isStorageCryptoReady,
  getStorageCryptoMode,
  deriveStorageKeyFromSeed,
  deriveStorageKeyForDev,
  initStorageCryptoFromSeed,
  initStorageCryptoDevMode,
  ensureStorageCryptoContext,
  clearStorageCrypto,
  encryptStorageValue,
  decryptStorageEnvelope,
  decryptStorageValue,
} from '../../lib/storage-crypto.js';

const SAMPLE_PUBLIC_KEY = 'AgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgI=';
const SAMPLE_SALT = 'AAECAwQFBgcICQoLDA0ODw==';
const SAMPLE_SEED = new Uint8Array(32).fill(7);
const ENVELOPE_PREFIX_SEED = `${STORAGE_ENC_PREFIX}${STORAGE_MODE_SEED}:`;
const ENVELOPE_PREFIX_DEV = `${STORAGE_ENC_PREFIX}${STORAGE_MODE_DEV}:`;

describe('lib/storage-crypto.js', () => {
  beforeEach(() => {
    clearStorageCrypto();
    window.el_settings = {
      user_id: 7,
      public_key: SAMPLE_PUBLIC_KEY,
      salt: SAMPLE_SALT,
      settings: {
        enable_sandbox: true,
        debug_mode: false,
      },
    };
  });

  afterEach(() => {
    clearStorageCrypto();
    delete window.el_settings;
  });

  describe('Key derivation (HKDF-SHA256 -> AES-256-GCM)', () => {
    it('derives a non-extractable AES-GCM CryptoKey from seed', async () => {
      const ctx = await deriveStorageKeyFromSeed(SAMPLE_SEED, SAMPLE_SALT, SAMPLE_PUBLIC_KEY, 7);
      expect(ctx.mode).toBe('seed');
      expect(ctx.publicKeyBase64).toBe(SAMPLE_PUBLIC_KEY);
      expect(ctx.saltBase64).toBe(SAMPLE_SALT);
      expect(ctx.cryptoKey).toBeDefined();
      expect(ctx.cryptoKey.extractable).toBe(false);
      expect(ctx.cryptoKey.algorithm).toEqual(expect.objectContaining({ name: 'AES-GCM', length: 256 }));
      expect(ctx.cryptoKey.usages).toEqual(expect.arrayContaining(['encrypt', 'decrypt']));
    });

    it('derives a non-extractable AES-GCM CryptoKey for dev mode', async () => {
      const ctx = await deriveStorageKeyForDev(SAMPLE_PUBLIC_KEY, SAMPLE_SALT, 7);
      expect(ctx.mode).toBe('dev');
      expect(ctx.publicKeyBase64).toBe(SAMPLE_PUBLIC_KEY);
      expect(ctx.cryptoKey.extractable).toBe(false);
    });
  });

  describe('Symmetric encryption & decryption (AES-256-GCM + AAD)', () => {
    it('encrypts with random 12-byte IVs and decrypts back to plaintext in seed mode', async () => {
      await initStorageCryptoFromSeed(SAMPLE_SEED);

      const plaintext = JSON.stringify({ email: 'devops@company.com', key: 'stub-license-key' });
      const enc1 = await encryptStorageValue('el_license', plaintext);
      const enc2 = await encryptStorageValue('el_license', plaintext);

      expect(enc1.startsWith(ENVELOPE_PREFIX_SEED)).toBe(true);
      expect(enc2.startsWith(ENVELOPE_PREFIX_SEED)).toBe(true);
      expect(enc1).not.toBe(enc2);
      expect(enc1).not.toContain('devops@company.com');

      const dec1 = await decryptStorageValue('el_license', enc1);
      const dec2 = await decryptStorageValue('el_license', enc2);
      expect(dec1).toBe(plaintext);
      expect(dec2).toBe(plaintext);
    });

    it('rejects cross-key substitution attacks via AAD binding (storageKey + publicKey)', async () => {
      await initStorageCryptoFromSeed(SAMPLE_SEED);

      const envelopeForOutline = await encryptStorageValue('el_outline_cache', '{"functions":[]}');
      const resultOnLicenseKey = await decryptStorageEnvelope('el_license', envelopeForOutline);

      expect(resultOnLicenseKey.value).toBeNull();
      expect(resultOnLicenseKey.shouldUpgrade).toBe(false);
    });

    it('rejects tampered ciphertexts and keys derived from a different seed', async () => {
      await initStorageCryptoFromSeed(SAMPLE_SEED);
      const envelope = await encryptStorageValue('el_snippets', '[{"id":1}]');

      // Flip a character inside the base64 payload (authentication tag region, before any '=' padding)
      const flipIdx = envelope.length - 10;
      const flippedChar = envelope[flipIdx] === 'A' ? 'Z' : 'A';
      const tampered = envelope.slice(0, flipIdx) + flippedChar + envelope.slice(flipIdx + 1);

      const tamperedRes = await decryptStorageEnvelope('el_snippets', tampered);
      expect(tamperedRes.value).toBeNull();

      // Switch to a different seed (simulating wp-config key rotation)
      const otherSeed = new Uint8Array(32).fill(9);
      await initStorageCryptoFromSeed(otherSeed);

      const wrongKeyRes = await decryptStorageEnvelope('el_snippets', envelope);
      expect(wrongKeyRes.value).toBeNull();
    });

    it('rejects unencrypted legacy plaintext values', async () => {
      await initStorageCryptoFromSeed(SAMPLE_SEED);

      const res = await decryptStorageEnvelope('el_user_settings', '{"defaultTabMode":"scratchpad"}');
      expect(res.value).toBeNull();
      expect(res.shouldUpgrade).toBe(false);
    });
  });

  describe('Locked state & Dual-Mode (seed vs dev)', () => {
    it('refuses to encrypt or decrypt when locked in sandbox mode', async () => {
      window.el_settings.settings.enable_sandbox = true;
      clearStorageCrypto();

      expect(await ensureStorageCryptoContext()).toBeNull();
      expect(await encryptStorageValue('el_license', 'secret')).toBeNull();

      const res = await decryptStorageEnvelope('el_license', `${ENVELOPE_PREFIX_SEED}AAAA`);
      expect(res.value).toBeNull();
      expect(res.shouldUpgrade).toBe(false);
    });

    it('auto-initializes dev context when EXPRESSION_LAB_SANDBOX_ENABLED is false', async () => {
      window.el_settings.settings.enable_sandbox = false;

      const ctx = await ensureStorageCryptoContext();
      expect(ctx).not.toBeNull();
      expect(ctx.mode).toBe('dev');

      const enc = await encryptStorageValue('el_filesystem_cache', '{"tree":[]}');
      expect(enc.startsWith(ENVELOPE_PREFIX_DEV)).toBe(true);

      const dec = await decryptStorageValue('el_filesystem_cache', enc);
      expect(dec).toBe('{"tree":[]}');
    });

    it('transparently upgrades dev envelopes when unlocked with a real seed', async () => {
      // 1. Encrypt in dev mode (EXPRESSION_LAB_SANDBOX_ENABLED = false)
      window.el_settings.settings.enable_sandbox = false;
      await initStorageCryptoDevMode();
      const devEnvelope = await encryptStorageValue('el_license', '{"email":"a@b.com","key":"stub-license-key"}');
      expect(devEnvelope.startsWith(ENVELOPE_PREFIX_DEV)).toBe(true);

      // 2. Switch to sandbox mode and unlock with real seed
      window.el_settings.settings.enable_sandbox = true;
      await initStorageCryptoFromSeed(SAMPLE_SEED);

      const res = await decryptStorageEnvelope('el_license', devEnvelope);
      expect(res.value).toBe('{"email":"a@b.com","key":"stub-license-key"}');
      expect(res.shouldUpgrade).toBe(true);
    });

    it('preserves seed envelopes without decrypting them when running in dev mode', async () => {
      // 1. Encrypt in seed mode
      await initStorageCryptoFromSeed(SAMPLE_SEED);
      const seedEnvelope = await encryptStorageValue('el_license', '{"email":"a@b.com","key":"stub-license-key"}');
      expect(seedEnvelope.startsWith(ENVELOPE_PREFIX_SEED)).toBe(true);

      // 2. Switch to dev mode without seed
      clearStorageCrypto();
      window.el_settings.settings.enable_sandbox = false;
      await initStorageCryptoDevMode();

      const res = await decryptStorageEnvelope('el_license', seedEnvelope);
      expect(res.value).toBeNull();
      expect(res.shouldUpgrade).toBe(false);
      expect(res.status).toBe(STORAGE_STATUS_MODE_MISMATCH);
    });

    it('never falls back to dev mode while locked when the passphrase lock is required', async () => {
      for (const settings of [
        { enable_sandbox: true, debug_mode: false },
        { enable_sandbox: false, debug_mode: true },
        { enable_sandbox: true, debug_mode: true },
      ]) {
        window.el_settings.settings = settings;
        clearStorageCrypto();
        expect(isPassphraseLockRequired()).toBe(true);
        expect(await ensureStorageCryptoContext()).toBeNull();
        expect(getStorageCryptoMode()).toBeNull();
      }
    });

    it('does not decrypt dev envelopes derived for a different administrator identity', async () => {
      window.el_settings.settings.enable_sandbox = false;
      await initStorageCryptoDevMode({ ...window.el_settings, user_id: 99 });
      const foreignDev = await encryptStorageValue('el_snippets', '[]');

      window.el_settings.settings.enable_sandbox = true;
      await initStorageCryptoFromSeed(SAMPLE_SEED);
      const res = await decryptStorageEnvelope('el_snippets', foreignDev);
      expect(res.value).toBeNull();
      expect(res.shouldUpgrade).toBe(false);
      expect(res.status).toBe(STORAGE_STATUS_INVALID);
    });
  });

  describe('Envelope edge cases', () => {
    beforeEach(async () => {
      await initStorageCryptoFromSeed(SAMPLE_SEED);
    });

    it('round-trips an empty string (IV + tag only payload)', async () => {
      const env = await encryptStorageValue('el_use_local_fs', '');
      const res = await decryptStorageEnvelope('el_use_local_fs', env);
      expect(res.status).toBe(STORAGE_STATUS_OK);
      expect(res.value).toBe('');
    });

    it('round-trips large payloads (> 64 KB) and multi-byte UTF-8 content', async () => {
      const large = JSON.stringify({ tree: 'ñ€😀'.repeat(20000), pad: 'x'.repeat(70000) });
      expect(large.length).toBeGreaterThan(64 * 1024);
      const env = await encryptStorageValue('el_filesystem_cache', large);
      expect(await decryptStorageValue('el_filesystem_cache', env)).toBe(large);
    });

    it('reports explicit statuses for empty, plaintext, malformed, truncated and unknown-mode values', async () => {
      expect((await decryptStorageEnvelope('el_snippets', null)).status).toBe(STORAGE_STATUS_EMPTY);
      expect((await decryptStorageEnvelope('el_snippets', '')).status).toBe(STORAGE_STATUS_EMPTY);
      expect((await decryptStorageEnvelope('el_snippets', '[]')).status).toBe(STORAGE_STATUS_PLAINTEXT);
      expect((await decryptStorageEnvelope('el_snippets', `${STORAGE_ENC_PREFIX}seed`)).status).toBe(STORAGE_STATUS_INVALID);
      expect((await decryptStorageEnvelope('el_snippets', ENVELOPE_PREFIX_SEED)).status).toBe(STORAGE_STATUS_INVALID);
      expect((await decryptStorageEnvelope('el_snippets', `${ENVELOPE_PREFIX_SEED}%%%not-base64%%%`)).status).toBe(STORAGE_STATUS_INVALID);
      expect((await decryptStorageEnvelope('el_snippets', `${ENVELOPE_PREFIX_SEED}AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA`)).status).toBe(STORAGE_STATUS_INVALID);

      const env = await encryptStorageValue('el_snippets', '[]');
      const unknownMode = env.replace(ENVELOPE_PREFIX_SEED, `${STORAGE_ENC_PREFIX}other:`);
      expect((await decryptStorageEnvelope('el_snippets', unknownMode)).status).toBe(STORAGE_STATUS_INVALID);
    });

    it('rejects ciphertexts replayed under a different administrator public key', async () => {
      const env = await encryptStorageValue('el_license', '{"email":"a@b.com","key":"stub-license-key"}');
      await initStorageCryptoFromSeed(SAMPLE_SEED, SAMPLE_SALT, 'AwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwM=');
      const res = await decryptStorageEnvelope('el_license', env);
      expect(res.value).toBeNull();
      expect(res.status).toBe(STORAGE_STATUS_INVALID);
    });

    it('reports a locked status after clearStorageCrypto() in sandbox mode', async () => {
      const env = await encryptStorageValue('el_license', 'x');
      clearStorageCrypto();
      expect(isStorageCryptoReady()).toBe(false);
      expect((await decryptStorageEnvelope('el_license', env)).status).toBe(STORAGE_STATUS_LOCKED);
    });

    it('refuses to encrypt when no CSPRNG is available instead of using a predictable IV', async () => {
      const original = window.crypto.getRandomValues;
      const originalGlobal = globalThis.crypto.getRandomValues;
      try {
        Object.defineProperty(window.crypto, 'getRandomValues', { value: undefined, configurable: true, writable: true });
        if (globalThis.crypto !== window.crypto) {
          Object.defineProperty(globalThis.crypto, 'getRandomValues', { value: undefined, configurable: true, writable: true });
        }
        expect(await encryptStorageValue('el_license', 'secret')).toBeNull();
      } finally {
        Object.defineProperty(window.crypto, 'getRandomValues', { value: original, configurable: true, writable: true });
        if (globalThis.crypto !== window.crypto) {
          Object.defineProperty(globalThis.crypto, 'getRandomValues', { value: originalGlobal, configurable: true, writable: true });
        }
      }
    });
  });
});
