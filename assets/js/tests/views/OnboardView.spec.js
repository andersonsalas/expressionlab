import { mount } from '@vue/test-utils';
import OnboardView from '../../views/OnboardView.vue';
import { deriveKey } from '../../lib/helpers.js';
import { ed25519 } from '@noble/curves/ed25519.js';

jest.mock('../../lib/helpers.js', () => {
  const actual = jest.requireActual('../../lib/helpers.js');
  return {
    ...actual,
    deriveKey: jest.fn(),
  };
});

jest.mock('@noble/curves/ed25519.js', () => ({
  ed25519: {
    getPublicKey: jest.fn(() => new Uint8Array(32).fill(2)),
  },
}));

const { setImmediate: realSetImmediate } = jest.requireActual('timers');
const flushPromises = () => new Promise((resolve) => realSetImmediate(resolve));

const PASSPHRASE = 'correct horse battery';

const createElSettings = (overrides = {}) => ({
  user_id: 7,
  site_url: 'staging.example.com',
  server: { sqlite_enabled: true, sodium_enabled: true },
  ...overrides,
});

let derivedSeeds;
let originalCrypto;

const mountView = () => mount(OnboardView, {
  attachTo: document.body,
  global: {
    stubs: {
      KeyGenGrid: { template: '<div class="keygen-grid-stub" />' },
      ExpressionLabIcon: true,
    },
  },
});

const activeStepLabel = (wrapper) => wrapper.find('.step-item.active .step-label').text();
const primaryButton = (wrapper) => wrapper.find('section .step-footer .btn-primary');
const backButton = (wrapper) => wrapper.find('section .step-footer .btn-default');
const snippetLines = (wrapper) => wrapper.find('textarea.config-output').element.value.split('\n');

const clickNext = async (wrapper) => {
  await primaryButton(wrapper).trigger('click');
  await flushPromises();
};

const clickBack = async (wrapper) => {
  await backButton(wrapper).trigger('click');
  await flushPromises();
};

/** Advance through the three artificial 2s phases of key generation. */
const runKeyGeneration = async (wrapper) => {
  for (let i = 0; i < 6; i++) {
    jest.advanceTimersByTime(2000);
    await flushPromises();
  }
  await wrapper.vm.$nextTick();
};

const goToSettings = async (wrapper) => {
  await wrapper.find('#el-onboard-agreement').setValue(true);
  await clickNext(wrapper); // -> 2
  await clickNext(wrapper); // -> 3
};

const goToKeyGeneration = async (wrapper) => {
  await goToSettings(wrapper);
  await clickNext(wrapper); // -> 4
};

const generateKeysWith = async (wrapper, passphrase = PASSPHRASE) => {
  await wrapper.find('#el-onboard-passphrase').setValue(passphrase);
  await primaryButton(wrapper).trigger('click');
  await runKeyGeneration(wrapper);
};

const goToVerification = async (wrapper) => {
  await goToKeyGeneration(wrapper);
  await generateKeysWith(wrapper);
};

describe('OnboardView.vue', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    derivedSeeds = [];
    deriveKey.mockReset();
    deriveKey.mockImplementation(async () => {
      const seed = new Uint8Array(32).fill(7);
      derivedSeeds.push(seed);
      return seed;
    });
    ed25519.getPublicKey.mockReset();
    ed25519.getPublicKey.mockImplementation(() => new Uint8Array(32).fill(2));

    originalCrypto = window.crypto;
    Object.defineProperty(window, 'el_settings', {
      configurable: true,
      writable: true,
      value: createElSettings(),
    });
  });

  afterEach(() => {
    Object.defineProperty(window, 'crypto', { configurable: true, writable: true, value: originalCrypto });
    jest.clearAllTimers();
    jest.useRealTimers();
    jest.restoreAllMocks();
    document.body.innerHTML = '';
  });

  describe('Step 1: Welcome (agreement gate)', () => {
    it('keeps "Begin install" disabled until the agreement is checked', async () => {
      const wrapper = mountView();
      expect(activeStepLabel(wrapper)).toBe('Welcome');
      expect(primaryButton(wrapper).attributes('disabled')).toBeDefined();

      await primaryButton(wrapper).trigger('click');
      expect(activeStepLabel(wrapper)).toBe('Welcome');

      await wrapper.find('#el-onboard-agreement').setValue(true);
      expect(primaryButton(wrapper).attributes('disabled')).toBeUndefined();

      await clickNext(wrapper);
      expect(activeStepLabel(wrapper)).toBe('System Requirements');
    });

    it('marks the active step with aria-current', () => {
      const wrapper = mountView();
      expect(wrapper.find('.step-item.active').attributes('aria-current')).toBe('step');
      expect(wrapper.findAll('[aria-current="step"]')).toHaveLength(1);
    });
  });

  describe('Step 2: Requirements (strict vs optional)', () => {
    const goToRequirements = async (wrapper) => {
      await wrapper.find('#el-onboard-agreement').setValue(true);
      await clickNext(wrapper);
    };

    it('allows proceeding without SQLite3 but shows the optional warning', async () => {
      window.el_settings = createElSettings({ server: { sqlite_enabled: false, sodium_enabled: true } });
      const wrapper = mountView();
      await goToRequirements(wrapper);

      expect(wrapper.find('.requirements-warning').exists()).toBe(true);
      expect(primaryButton(wrapper).attributes('disabled')).toBeUndefined();
      await clickNext(wrapper);
      expect(activeStepLabel(wrapper)).toBe('Settings');
    });

    it('hides the SQLite3 warning when the extension is present', async () => {
      const wrapper = mountView();
      await goToRequirements(wrapper);
      expect(wrapper.find('.requirements-warning').exists()).toBe(false);
    });

    it('blocks proceeding when Sodium is missing', async () => {
      window.el_settings = createElSettings({ server: { sqlite_enabled: true, sodium_enabled: false } });
      const wrapper = mountView();
      await goToRequirements(wrapper);

      expect(primaryButton(wrapper).attributes('disabled')).toBeDefined();
      await clickNext(wrapper);
      expect(activeStepLabel(wrapper)).toBe('System Requirements');
    });

    it('blocks proceeding when the Web Crypto API is missing', async () => {
      Object.defineProperty(window, 'crypto', { configurable: true, writable: true, value: { getRandomValues: originalCrypto.getRandomValues } });
      const wrapper = mountView();
      await goToRequirements(wrapper);

      expect(primaryButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it('treats a missing server payload as failing requirements', async () => {
      window.el_settings = {};
      const wrapper = mountView();
      await goToRequirements(wrapper);
      expect(primaryButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it('navigates back to Welcome preserving the agreement', async () => {
      const wrapper = mountView();
      await goToRequirements(wrapper);
      await clickBack(wrapper);
      expect(activeStepLabel(wrapper)).toBe('Welcome');
      expect(wrapper.find('#el-onboard-agreement').element.checked).toBe(true);
    });
  });

  describe('Step 3 & 5: Settings and snippet generation', () => {
    it('emits only the three mandatory constants with untouched defaults', async () => {
      const wrapper = mountView();
      await goToVerification(wrapper);

      expect(activeStepLabel(wrapper)).toBe('Verification');
      const lines = snippetLines(wrapper);
      expect(lines).toHaveLength(3);
      expect(lines[0]).toBe('define( \'EXPRESSION_LAB_ADMIN_USER_ID\', 7 );');
      expect(lines[1]).toBe('define( \'EXPRESSION_LAB_ADMIN_PUBLIC_KEY\', \'AgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgI=\' );');
      expect(lines[2]).toMatch(/^define\( 'EXPRESSION_LAB_ADMIN_SALT', '[A-Za-z0-9+/]{22}==' \);$/);
    });

    it('emits custom permission and constraint deltas', async () => {
      const wrapper = mountView();
      await goToSettings(wrapper);

      const checkboxes = wrapper.findAll('.permission-checkbox');
      await checkboxes[0].setValue(true); // database
      await checkboxes[3].setValue(true); // hooks
      await wrapper.find('#el-onboard-max-execution-limit').setValue('5');
      await wrapper.find('#el-onboard-signature-duration').setValue('300');
      await clickNext(wrapper);
      await generateKeysWith(wrapper);

      const lines = snippetLines(wrapper);
      expect(lines).toEqual(expect.arrayContaining([
        'define( \'EXPRESSION_LAB_DATABASE_READONLY\', false );',
        'define( \'EXPRESSION_LAB_HOOKS_ENABLED\', true );',
        'define( \'EXPRESSION_LAB_MAX_EXECUTION_LIMIT\', 5 );',
        'define( \'EXPRESSION_LAB_SIGNATURE_DURATION\', 300 );',
      ]));
      expect(lines.join('\n')).not.toContain('FILESYSTEM_READONLY');
      expect(lines.join('\n')).not.toContain('NETWORK_READONLY');
      expect(lines[lines.length - 4]).toBe('');
    });

    it('emits the normalized staging URL', async () => {
      const wrapper = mountView();
      await goToSettings(wrapper);
      await wrapper.find('#el-onboard-staging-url').setValue('  https://staging.example.com/site/ ');
      expect(wrapper.find('.settings-error').exists()).toBe(false);
      await clickNext(wrapper);
      await generateKeysWith(wrapper);

      expect(snippetLines(wrapper)[0]).toBe('define( \'EXPRESSION_LAB_STAGING_URL\', \'staging.example.com/site\' );');
    });

    it('uses the current site URL as staging placeholder', async () => {
      const wrapper = mountView();
      await goToSettings(wrapper);
      expect(wrapper.find('#el-onboard-staging-url').attributes('placeholder')).toBe('staging.example.com');
    });

    it.each([
      'staging.test\'); phpinfo(); //',
      'staging.test\\',
      'staging example.com',
      'staging.example.com?debug=1',
    ])('rejects a malicious or malformed staging URL %p and blocks progress', async (value) => {
      const wrapper = mountView();
      await goToSettings(wrapper);
      await wrapper.find('#el-onboard-staging-url').setValue(value);

      expect(wrapper.find('#el-onboard-staging-url').attributes('aria-invalid')).toBe('true');
      expect(wrapper.find('.settings-error').exists()).toBe(true);
      expect(primaryButton(wrapper).attributes('disabled')).toBeDefined();
      await clickNext(wrapper);
      expect(activeStepLabel(wrapper)).toBe('Settings');
    });

    it('rejects a malformed MaxMind key and accepts a valid one', async () => {
      const wrapper = mountView();
      await goToSettings(wrapper);
      const input = wrapper.find('#el-onboard-maxmind-key');

      await input.setValue('abc\'); phpinfo(); //');
      expect(primaryButton(wrapper).attributes('disabled')).toBeDefined();

      await input.setValue('AbC123_def_mmk');
      expect(primaryButton(wrapper).attributes('disabled')).toBeUndefined();
      await clickNext(wrapper);
      await generateKeysWith(wrapper);
      expect(snippetLines(wrapper)[0]).toBe('define( \'EXPRESSION_LAB_MAXMIND_API_KEY\', \'AbC123_def_mmk\' );');
    });

    it.each(['', '0', '-1', '2.5'])('rejects invalid max execution time %p', async (value) => {
      const wrapper = mountView();
      await goToSettings(wrapper);
      await wrapper.find('#el-onboard-max-execution-limit').setValue(value);

      expect(wrapper.find('#el-onboard-max-execution-limit').attributes('aria-invalid')).toBe('true');
      expect(primaryButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it.each(['', '0', '-120', '1.5'])('rejects invalid signature duration %p', async (value) => {
      const wrapper = mountView();
      await goToSettings(wrapper);
      await wrapper.find('#el-onboard-signature-duration').setValue(value);

      expect(primaryButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it('keeps the generated snippet read-only', async () => {
      const wrapper = mountView();
      await goToVerification(wrapper);
      const textarea = wrapper.find('textarea.config-output');
      expect(textarea.attributes('readonly')).toBeDefined();
    });
  });

  describe('Step 4: Key generation', () => {
    it('requires at least 8 characters', async () => {
      const wrapper = mountView();
      await goToKeyGeneration(wrapper);
      const input = wrapper.find('#el-onboard-passphrase');

      await input.setValue('1234567');
      expect(primaryButton(wrapper).attributes('disabled')).toBeDefined();
      expect(primaryButton(wrapper).text()).toBe('Generate Keys');

      await input.setValue('12345678');
      expect(primaryButton(wrapper).attributes('disabled')).toBeUndefined();
    });

    it('uses a non-autofill password field', async () => {
      const wrapper = mountView();
      await goToKeyGeneration(wrapper);
      const input = wrapper.find('#el-onboard-passphrase');
      expect(input.attributes('type')).toBe('password');
      expect(input.attributes('autocomplete')).toBe('new-password');
    });

    it('advances with Enter only when the passphrase is valid', async () => {
      const wrapper = mountView();
      await goToKeyGeneration(wrapper);
      const input = wrapper.find('#el-onboard-passphrase');

      await input.setValue('short');
      await input.trigger('keyup.enter');
      await runKeyGeneration(wrapper);
      expect(deriveKey).not.toHaveBeenCalled();
      expect(activeStepLabel(wrapper)).toBe('Key Generation');

      await input.setValue(PASSPHRASE);
      await input.trigger('keyup.enter');
      await runKeyGeneration(wrapper);
      expect(deriveKey).toHaveBeenCalledTimes(1);
      expect(activeStepLabel(wrapper)).toBe('Verification');
    });

    it('derives with a fresh 16-byte salt, zeroes the seed and clears the passphrase', async () => {
      let seedSeenByEd25519 = null;
      ed25519.getPublicKey.mockImplementation((seed) => {
        seedSeenByEd25519 = Array.from(seed);
        return new Uint8Array(32).fill(2);
      });
      const getRandomValues = jest.spyOn(window.crypto, 'getRandomValues');

      const wrapper = mountView();
      await goToKeyGeneration(wrapper);
      await generateKeysWith(wrapper);

      expect(deriveKey).toHaveBeenCalledTimes(1);
      const [passArg, saltArg] = deriveKey.mock.calls[0];
      expect(passArg).toBe(PASSPHRASE);
      expect(saltArg).toBeInstanceOf(Uint8Array);
      expect(saltArg).toHaveLength(16);
      expect(getRandomValues).toHaveBeenCalledWith(saltArg);

      expect(seedSeenByEd25519).toEqual(new Array(32).fill(7));
      expect(Array.from(derivedSeeds[0]).every((b) => b === 0)).toBe(true);

      await clickBack(wrapper);
      expect(wrapper.find('#el-onboard-passphrase').element.value).toBe('');
    });

    it('shows the generation animation and hides navigation while deriving', async () => {
      const wrapper = mountView();
      await goToKeyGeneration(wrapper);
      await wrapper.find('#el-onboard-passphrase').setValue(PASSPHRASE);
      await primaryButton(wrapper).trigger('click');
      await flushPromises();

      expect(wrapper.find('.keygen-grid-stub').exists()).toBe(true);
      expect(wrapper.find('.keygen-status').text()).toBe('Preparing key generation...');
      expect(backButton(wrapper).exists()).toBe(false);
      expect(primaryButton(wrapper).exists()).toBe(false);

      await runKeyGeneration(wrapper);
      expect(activeStepLabel(wrapper)).toBe('Verification');
    });

    it('ignores repeated activation while a derivation is running', async () => {
      const wrapper = mountView();
      await goToKeyGeneration(wrapper);
      const input = wrapper.find('#el-onboard-passphrase');
      await input.setValue(PASSPHRASE);
      await input.trigger('keyup.enter');
      await input.trigger('keyup.enter');
      await runKeyGeneration(wrapper);

      expect(deriveKey).toHaveBeenCalledTimes(1);
    });

    it('never persists secrets to Web Storage, the network, or the snippet', async () => {
      const setItem = jest.spyOn(Storage.prototype, 'setItem');
      const fetchSpy = jest.fn();
      window.fetch = fetchSpy;
      const xhrOpen = jest.spyOn(XMLHttpRequest.prototype, 'open');

      const wrapper = mountView();
      await goToVerification(wrapper);

      expect(setItem).not.toHaveBeenCalled();
      expect(fetchSpy).not.toHaveBeenCalled();
      expect(xhrOpen).not.toHaveBeenCalled();
      expect(snippetLines(wrapper).join('\n')).not.toContain(PASSPHRASE);
      delete window.fetch;
    });
  });

  describe('Error handling during derivation', () => {
    it('stays on Step 4 and shows an inline error when deriveKey rejects', async () => {
      const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
      const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});
      deriveKey.mockImplementation(async () => {
        throw new Error('argon2 out of memory');
      });

      const wrapper = mountView();
      await goToKeyGeneration(wrapper);
      await generateKeysWith(wrapper);

      expect(activeStepLabel(wrapper)).toBe('Key Generation');
      expect(consoleError).toHaveBeenCalledWith('Key generation failed', expect.any(Error));
      const error = wrapper.find('.keygen-error');
      expect(error.exists()).toBe(true);
      expect(error.attributes('role')).toBe('alert');
      expect(error.text()).toBe('Key generation failed: argon2 out of memory');
      expect(alertSpy).not.toHaveBeenCalled();
      expect(wrapper.find('#el-onboard-passphrase').element.value).toBe('');
      expect(primaryButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it('zeroes the seed even when the public key derivation throws', async () => {
      jest.spyOn(console, 'error').mockImplementation(() => {});
      ed25519.getPublicKey.mockImplementation(() => {
        throw new Error('bad seed');
      });

      const wrapper = mountView();
      await goToKeyGeneration(wrapper);
      await generateKeysWith(wrapper);

      expect(activeStepLabel(wrapper)).toBe('Key Generation');
      expect(wrapper.find('.keygen-error').text()).toContain('bad seed');
      expect(Array.from(derivedSeeds[0]).every((b) => b === 0)).toBe(true);
      expect(wrapper.find('.keygen-cached-notice').exists()).toBe(false);
    });

    it('clears the error after a successful retry', async () => {
      jest.spyOn(console, 'error').mockImplementation(() => {});
      deriveKey.mockImplementationOnce(async () => {
        throw new Error('transient');
      });

      const wrapper = mountView();
      await goToKeyGeneration(wrapper);
      await generateKeysWith(wrapper);
      expect(wrapper.find('.keygen-error').exists()).toBe(true);

      await generateKeysWith(wrapper);
      expect(activeStepLabel(wrapper)).toBe('Verification');
      await clickBack(wrapper);
      expect(wrapper.find('.keygen-error').exists()).toBe(false);
    });
  });

  describe('Bidirectional navigation with credential persistence', () => {
    it('returns from Step 5 to Step 3, applies new settings and comes back without re-entering the passphrase', async () => {
      const wrapper = mountView();
      await goToVerification(wrapper);
      const originalLines = snippetLines(wrapper);
      expect(originalLines).toHaveLength(3);

      // 5 -> 4: cached keys are announced and can be reused.
      await clickBack(wrapper);
      expect(activeStepLabel(wrapper)).toBe('Key Generation');
      expect(wrapper.find('.keygen-cached-notice').exists()).toBe(true);
      expect(wrapper.find('#el-onboard-passphrase').element.value).toBe('');
      expect(primaryButton(wrapper).attributes('disabled')).toBeUndefined();
      expect(primaryButton(wrapper).text()).toBe('Continue');

      // 4 -> 3: change settings.
      await clickBack(wrapper);
      expect(activeStepLabel(wrapper)).toBe('Settings');
      await wrapper.findAll('.permission-checkbox')[2].setValue(true); // network
      await wrapper.find('#el-onboard-staging-url').setValue('*.staging.example.com');

      // 3 -> 4 -> 5 without typing anything and without timers.
      await clickNext(wrapper);
      await clickNext(wrapper);
      expect(activeStepLabel(wrapper)).toBe('Verification');

      const lines = snippetLines(wrapper);
      expect(lines).toContain('define( \'EXPRESSION_LAB_NETWORK_READONLY\', false );');
      expect(lines).toContain('define( \'EXPRESSION_LAB_STAGING_URL\', \'*.staging.example.com\' );');
      // Same credentials as before (no re-derivation).
      expect(lines.slice(-3)).toEqual(originalLines);
      expect(deriveKey).toHaveBeenCalledTimes(1);
    });

    it('preserves Step 3 settings across back/forward navigation', async () => {
      const wrapper = mountView();
      await goToSettings(wrapper);
      await wrapper.findAll('.permission-checkbox')[1].setValue(true);
      await wrapper.find('#el-onboard-staging-url').setValue('localhost/site');
      await clickNext(wrapper);
      await clickBack(wrapper);

      expect(wrapper.findAll('.permission-checkbox')[1].element.checked).toBe(true);
      expect(wrapper.find('#el-onboard-staging-url').element.value).toBe('localhost/site');
    });

    it('regenerates a new key pair when a new passphrase is typed with cached keys', async () => {
      const wrapper = mountView();
      await goToVerification(wrapper);
      const firstSalt = snippetLines(wrapper)[2];

      await clickBack(wrapper);
      await wrapper.find('#el-onboard-passphrase').setValue('abc');
      expect(primaryButton(wrapper).attributes('disabled')).toBeDefined();
      expect(primaryButton(wrapper).text()).toBe('Generate Keys');

      await generateKeysWith(wrapper, 'another strong passphrase');
      expect(deriveKey).toHaveBeenCalledTimes(2);
      expect(deriveKey.mock.calls[1][0]).toBe('another strong passphrase');
      expect(activeStepLabel(wrapper)).toBe('Verification');
      expect(snippetLines(wrapper)[2]).not.toBe(firstSalt);
    });

    it('does not offer key reuse before any key was generated', async () => {
      const wrapper = mountView();
      await goToKeyGeneration(wrapper);
      expect(wrapper.find('.keygen-cached-notice').exists()).toBe(false);
      expect(primaryButton(wrapper).attributes('disabled')).toBeDefined();
    });
  });
});
