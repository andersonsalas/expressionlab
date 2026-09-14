import { arrayBufferToBase64, base64ToArrayBuffer, deriveKey, getUniqueSnippetName, normalizeSnippet, __, sprintf } from '../../lib/helpers.js';

import { ed25519 } from '@noble/curves/ed25519.js';

const hexToBytes = (hex) => new Uint8Array(hex.match(/.{1,2}/g).map(byte => parseInt(byte, 16)));
const bytesToHex = (bytes) => Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');

describe('Helpers - Cryptography', () => {
  const testPassword = 'test-password-1234';
  const testSaltBase64 = 'YTM0NTVmNmc3aDg5MGFiYw==';
  const testSalt = base64ToArrayBuffer(testSaltBase64);

  test('arrayBufferToBase64 and base64ToArrayBuffer are consistent', () => {
    const original = new Uint8Array([1, 2, 3, 4, 5, 255]);
    const base64 = arrayBufferToBase64(original.buffer);
    const back = base64ToArrayBuffer(base64);
    expect(back).toEqual(original);
  });

  test('deriveKey returns a 32-byte buffer with Argon2id', async () => {
    const key = await deriveKey(testPassword, testSalt);
    expect(key).toBeInstanceOf(Uint8Array);
    expect(key.length).toBe(32);
  });

  describe('Ed25519 RFC 8032 Test Vectors', () => {
    // RFC 8032 Section 7.1 Test Vector 1
    const sk1 = hexToBytes('9d61b19deffd5a60ba844af492ec2cc44449c5697b326919703bac031cae7f60');
    const expectedPk1 = 'd75a980182b10ab7d54bfed3c964073a0ee172f3daa62325af021a68f707511a';
    const expectedSig1 = 'e5564300c360ac729086e2cc806e828a84877f1eb8e5d974d873e065224901555fb8821590a33bacc61e39701cf9b46bd25bf5f0595bbe24655141438e7a100b';

    test('derives correct public key matching RFC 8032 vector 1', () => {
      const pk = ed25519.getPublicKey(sk1);
      expect(bytesToHex(pk)).toBe(expectedPk1);
    });

    test('generates valid signature for empty message matching RFC 8032 vector 1', async () => {
      const msg = new Uint8Array(0);
      const sig = await ed25519.sign(msg, sk1);
      expect(bytesToHex(sig)).toBe(expectedSig1);

      const isValid = await ed25519.verify(sig, msg, hexToBytes(expectedPk1));
      expect(isValid).toBe(true);
    });

    // RFC 8032 Section 7.1 Test Vector 2
    const sk2 = hexToBytes('4ccd089b28ff96da9db6c346ec114e0f5b8a319f35aba624da8cf6ed4fb8a6fb');
    const expectedPk2 = '3d4017c3e843895a92b70aa74d1b7ebc9c982ccf2ec4968cc0cd55f12af4660c';
    const expectedSig2 = '92a009a9f0d4cab8720e820b5f642540a2b27b5416503f8fb3762223ebdb69da085ac1e43e15996e458f3613d0f11d8c387b2eaeb4302aeeb00d291612bb0c00';

    test('generates and verifies signature matching RFC 8032 vector 2', async () => {
      const pk = ed25519.getPublicKey(sk2);
      expect(bytesToHex(pk)).toBe(expectedPk2);

      const msg = new Uint8Array([0x72]); // 'r'
      const sig = await ed25519.sign(msg, sk2);
      expect(bytesToHex(sig)).toBe(expectedSig2);

      const isValid = await ed25519.verify(sig, msg, pk);
      expect(isValid).toBe(true);
    });

    test('rejects tampered message signature', async () => {
      const pk = ed25519.getPublicKey(sk2);
      const msg = new Uint8Array([0x72]);
      const sig = await ed25519.sign(msg, sk2);

      const tamperedMsg = new Uint8Array([0x73]);
      const isValid = await ed25519.verify(sig, tamperedMsg, pk);
      expect(isValid).toBe(false);
    });
  });
});

describe('Helpers - Snippets', () => {
  describe('normalizeSnippet', () => {
    test('trims whitespace from name and uses it as id', () => {
      const result = normalizeSnippet({ name: '  My Snippet  ', code: 'print("hi")' });
      expect(result.id).toBe('My Snippet');
      expect(result.name).toBe('My Snippet');
      expect(result.code).toBe('print("hi")');
    });

    test('supports title property and fallback to Untitled', () => {
      const result = normalizeSnippet({ title: ' Custom ' });
      expect(result.id).toBe('Custom');
      expect(result.name).toBe('Custom');
      expect(result.code).toBe('');

      const untitled = normalizeSnippet({});
      expect(untitled.id).toBe('Untitled');
      expect(untitled.name).toBe('Untitled');
    });
  });

  describe('getUniqueSnippetName', () => {
    test('returns original trimmed name if not in existingNames', () => {
      const existing = new Set(['snippet a', 'snippet b']);
      const name = getUniqueSnippetName('  Snippet C  ', existing);
      expect(name).toBe('Snippet C');
      expect(existing.has('snippet c')).toBe(true);
    });

    test('appends (1) when duplicate exists', () => {
      const existing = new Set(['my snippet']);
      const name = getUniqueSnippetName('My Snippet', existing);
      expect(name).toBe('My Snippet (1)');
      expect(existing.has('my snippet (1)')).toBe(true);
    });

    test('increments counter (1), (2), (3) on consecutive duplicates', () => {
      const existing = new Set(['test', 'test (1)']);
      const name = getUniqueSnippetName('Test', existing);
      expect(name).toBe('Test (2)');
      expect(existing.has('test (2)')).toBe(true);

      const next = getUniqueSnippetName('Test', existing);
      expect(next).toBe('Test (3)');
      expect(existing.has('test (3)')).toBe(true);
    });

    test('handles incoming name with existing (n) suffix properly', () => {
      const existing = new Set(['snippet (1)']);
      const name = getUniqueSnippetName('Snippet (1)', existing);
      expect(name).toBe('Snippet (2)');
    });
  });
});

describe('Helpers - i18n & Translation', () => {
  beforeEach(() => {
    delete window.el_settings;
  });

  describe('__', () => {
    test('returns original string when window.el_settings is not set', () => {
      expect(__('Welcome')).toBe('Welcome');
    });

    test('returns original string when text is not found in i18n dictionary', () => {
      window.el_settings = { i18n: { 'Other text': 'Otro texto' } };
      expect(__('Welcome')).toBe('Welcome');
    });

    test('returns translated string when available in i18n dictionary', () => {
      window.el_settings = { i18n: { 'Welcome': 'Bienvenido' } };
      expect(__('Welcome')).toBe('Bienvenido');
    });

    test('returns non-string values as is', () => {
      expect(__(123)).toBe(123);
      expect(__(null)).toBe(null);
    });
  });

  describe('sprintf', () => {
    test('replaces %s and %d with arguments in order', () => {
      expect(sprintf('Hello %s, you have %d messages', 'Alice', 5)).toBe('Hello Alice, you have 5 messages');
    });

    test('supports numbered placeholders (%1$s, %2$d)', () => {
      expect(sprintf('Page %1$d / %2$d', 3, 10)).toBe('Page 3 / 10');
      expect(sprintf('User %2$s has ID %1$d', 42, 'admin')).toBe('User admin has ID 42');
    });

    test('handles escaped percent %%', () => {
      expect(sprintf('Progress: %d%% completed', 100)).toBe('Progress: 100% completed');
    });

    test('handles empty or non-string format gracefully', () => {
      expect(sprintf('')).toBe('');
      expect(sprintf(null)).toBe('');
    });
  });
});
