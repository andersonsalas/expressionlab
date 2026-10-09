<script setup>
import { ref, computed } from 'vue';
import ExpressionLabIcon from '../components/icons/ExpressionLabIcon.vue';
import KeyGenGrid from '../components/ui/KeyGenGrid.vue';
import { ed25519 } from '@noble/curves/ed25519.js';
import { arrayBufferToBase64, deriveKey, __, sprintf, sanitizeHtml } from '../lib/helpers.js';
import {
    MIN_PASSPHRASE_LENGTH,
    buildConfigSnippet,
    isValidMaxMindKey,
    isValidStagingUrl,
    toPositiveInteger,
} from '../lib/onboarding-config.js';

const steps = computed(() => [
    { id: 1, title: __('Welcome') },
    { id: 2, title: __('System Requirements') },
    { id: 3, title: __('Settings') },
    { id: 4, title: __('Key Generation') },
    { id: 5, title: __('Verification') }
]);

const currentStep = ref(1);
const agreementChecked = ref(false);
const password = ref('');
const generatingKeys = ref(false);
const keyGenMessage = ref('');
const keyGenError = ref('');

// Non-secret derived credentials ({ publicKeyBase64, saltBase64 }). The seed and the passphrase are never cached.
const generatedKeysCache = ref(null);
const hasCachedKeys = computed(() => generatedKeysCache.value !== null);

const settings = ref({
    stagingUrl: '',
    database: false,
    fileSystem: false,
    network: false,
    hooks: false,
    maxExecutionLimit: 2,
    signatureDuration: 120,
    maxMindApiKey: '',
});

const suggestedStagingDomain = computed(() => {
    return (window.el_settings && window.el_settings.site_url) ? window.el_settings.site_url : 'staging.example.com';
});

const reqs = ref({
    sqlite: window.el_settings?.server?.sqlite_enabled ?? false,
    sodium: window.el_settings?.server?.sodium_enabled ?? false,
    webCrypto: !!(window.crypto && window.crypto.subtle)
});

const stagingUrlInvalid = computed(() => !isValidStagingUrl(settings.value.stagingUrl));
const maxExecutionLimitInvalid = computed(() => toPositiveInteger(settings.value.maxExecutionLimit) === null);
const signatureDurationInvalid = computed(() => toPositiveInteger(settings.value.signatureDuration) === null);
const maxMindApiKeyInvalid = computed(() => !isValidMaxMindKey(settings.value.maxMindApiKey));
const settingsValid = computed(() => !stagingUrlInvalid.value
    && !maxExecutionLimitInvalid.value
    && !signatureDurationInvalid.value
    && !maxMindApiKeyInvalid.value);

const canReuseCachedKeys = computed(() => hasCachedKeys.value && password.value.length === 0);

const canProceed = computed(() => {
    if (currentStep.value === 1) return agreementChecked.value;
    // Note: SQLite3 is required for some features but not strictly required for the core functionality
    // of Expression Lab, so we will allow users to proceed even if it's missing.
    // We will just show a warning in the requirements step.
    if (currentStep.value === 2) return reqs.value.sodium && reqs.value.webCrypto;
    if (currentStep.value === 3) return settingsValid.value;
    if (currentStep.value === 4) return password.value.length >= MIN_PASSPHRASE_LENGTH || canReuseCachedKeys.value;
    return true;
});

// The snippet is derived reactively from the cached public credentials and the current
// settings, so going back to Step 3 and changing a value is reflected in Step 5.
const generatedConfig = computed(() => buildConfigSnippet(
    generatedKeysCache.value,
    settings.value,
    window.el_settings?.user_id
));

const prevStep = () => {
    if (generatingKeys.value) return;
    keyGenError.value = '';
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const nextStep = async () => {
    // Re-entrancy and gate guard (Enter key, double clicks, programmatic calls).
    if (generatingKeys.value || !canProceed.value) return;

    if (currentStep.value !== 4) {
        if (currentStep.value < steps.value.length) {
            currentStep.value++;
        }
        return;
    }

    // Keys were already derived in this session and no new passphrase was typed:
    // reuse the cached public credentials without re-deriving.
    if (canReuseCachedKeys.value) {
        currentStep.value = 5;
        return;
    }

    keyGenError.value = '';
    generatingKeys.value = true;

    // Phase 1: Preparing key generation (2 seconds)
    keyGenMessage.value = __('Preparing key generation...');
    await new Promise(resolve => setTimeout(resolve, 2000));

    // Phase 2: Generating cryptographic keys (Argon2id + noble-ed25519, min 2 seconds)
    keyGenMessage.value = __('Generating cryptographic keys...');
    const success = await generateKeys();

    if (success) {
        // Phase 3: Cryptographic keys generated successfully (2 seconds)
        keyGenMessage.value = __('Cryptographic keys generated successfully!');
        await new Promise(resolve => setTimeout(resolve, 2000));

        currentStep.value = 5;
    }

    generatingKeys.value = false;
};

const generateKeys = async () => {
    let seed = null;
    try {
        // Cryptographically secure 16-bytes (128 bits) random salt.
        const salt = window.crypto.getRandomValues(new Uint8Array(16));

        // Ensure key derivation phase displays for at least 2.0 seconds.
        const minDelay = new Promise(resolve => setTimeout(resolve, 2000));

        // Seed derivation with Argon2id.
        [seed] = await Promise.all([
            deriveKey(password.value, salt),
            minDelay
        ]);

        // Public key generation (noble-ed25519).
        const publicKeyBuffer = ed25519.getPublicKey(seed);

        // Convert to Base64 for wp-config.php. Only non-secret values are kept.
        generatedKeysCache.value = {
            publicKeyBase64: arrayBufferToBase64(publicKeyBuffer),
            saltBase64: arrayBufferToBase64(salt)
        };

        return true;
    } catch (e) {
        console.error('Key generation failed', e);
        keyGenError.value = sprintf(__('Key generation failed: %s'), e && e.message ? e.message : String(e));
        return false;
    } finally {
        // Clear the secrets on every path (success or failure).
        if (seed && typeof seed.fill === 'function') {
            seed.fill(0);
        }
        seed = null;
        password.value = '';
    }
};

</script>

<template>
  <header class="onboard-header">
    <nav
      class="wizard-progress"
      aria-label="Wizard progress"
    >
      <div 
        v-for="step in steps" 
        :key="step.id" 
        class="step-item"
        :class="{ 
          'completed': currentStep > step.id, 
          'active': currentStep === step.id 
        }"
        :aria-current="currentStep === step.id ? 'step' : undefined"
      >
        <div class="step-label">
          {{ step.title }}
        </div>
        <div class="step-indicator">
          <div class="step-dot" />
        </div>
      </div>
    </nav>
  </header>
  <main class="onboard-wizard">
    <!-- Step 1: Welcome -->
    <section
      v-if="currentStep === 1"
      class="step-container welcome-step"
    >
      <div class="step-body step-inner-container welcome-body">
        <div class="welcome-box">
          <div class="welcome-header">
            <div class="wizard-logo">
              <ExpressionLabIcon class="wizard-icon" />
            </div>
            <h1>{{ __('Welcome to Expression Lab') }}</h1>
            <p class="welcome-subtitle">
              {{ __('Configure the environment limits and generate your cryptographic access keys to get started.') }}
            </p>
          </div>
          
          <div class="alpha-notice-box">
            <h2 class="alpha-notice-title">
              <span class="codicon codicon-warning alpha-notice-icon" />
              {{ __('Warning: Alpha Software') }}
            </h2>
            <p class="alpha-notice-description">
              {{ __('Expression Lab contains diagnostic tools and evaluation engines designed for non-production environments. While built with defensive architecture (AST sandboxing, client-side cryptography), this codebase has not undergone independent third-party security audits.') }}
            </p>
          </div>

          <div class="agreement-check">
            <label>
              <input
                id="el-onboard-agreement"
                v-model="agreementChecked"
                type="checkbox"
              >
              <span>{{ __('I understand Expression Lab is unaudited alpha software and agree to run it only in staging, testing, or local environments.') }}</span>
            </label>
          </div>
        </div>
      </div>

      <div class="step-footer">
        <div class="buttons">
          <button
            class="btn btn-primary"
            :disabled="!canProceed"
            @click="nextStep"
          >
            {{ __('Begin install') }}
          </button>
        </div>
      </div>
    </section>

    <!-- Step 2: System Requirements -->
    <section
      v-if="currentStep === 2"
      class="step-container"
    >
      <div class="step-header">
        <div class="codicon codicon-server step-codicon" />
        <h2>{{ __('System Requirements') }}</h2>
      </div>
      <div class="step-body step-inner-container">
        <ul class="requirements-list">
          <li :class="{ ok: reqs.sqlite, fail: !reqs.sqlite }">
            <span>{{ __('SQLite3 PHP extension') }}</span>
            <span class="status">{{ reqs.sqlite ? __('OK') : __('Missing') }}</span>
          </li>
          <li :class="{ ok: reqs.sodium, fail: !reqs.sodium }">
            <span>{{ __('Sodium PHP extension') }}</span>
            <span class="status">{{ reqs.sodium ? __('OK') : __('Missing') }}</span>
          </li>
          <li :class="{ ok: reqs.webCrypto, fail: !reqs.webCrypto }">
            <span>{{ __('Web Crypto API (Browser)') }}</span>
            <span class="status">{{ reqs.webCrypto ? __('OK') : __('Missing') }}</span>
          </li>
        </ul>

        <div
          v-if="!reqs.sqlite"
          class="alpha-notice-box requirements-warning"
        >
          <div class="alpha-notice-title">
            <span class="codicon codicon-warning alpha-notice-icon" />
            {{ __('SQLite3 Extension Missing (Optional)') }}
          </div>
          <p class="alpha-notice-description">
            {{ __('Expression Lab can operate without SQLite3, but safe in-memory database mirroring will be disabled. You may continue the installation, or install the PHP sqlite3 extension to enable this feature.') }}
          </p>
        </div>

        <!-- eslint-disable vue/no-v-html -->
        <p
          class="requirements-description"
          v-html="sanitizeHtml(__('<strong>SQLite3</strong> enables safe, in-memory database mirroring to prevent direct queries to the live database. <strong>Sodium</strong> and the <strong>Web Crypto API</strong> are required to generate, sign, and validate secure communications within the sandboxed environment.'))"
        />
        <!-- eslint-enable vue/no-v-html -->
      </div>
      <div class="step-footer">
        <div class="buttons">
          <button
            class="btn btn-default"
            @click="prevStep"
          >
            {{ __('Back') }}
          </button>
          <button
            class="btn btn-primary"
            :disabled="!canProceed"
            @click="nextStep"
          >
            {{ __('Next') }}
          </button>
        </div>
      </div>
    </section>

    <!-- Step 3: Settings -->
    <section
      v-if="currentStep === 3"
      class="step-container settings-step"
    >
      <div class="step-header">
        <div class="codicon codicon-tools step-codicon" />
        <h2>{{ __('Settings') }}</h2>
      </div>
      <div class="step-body step-inner-container">
        <!-- Section: General -->
        <div class="settings-section">
          <h3 class="settings-section-title">
            {{ __('General') }}
          </h3>
          <div class="form-group settings-group">
            <label
              class="settings-label"
              for="el-onboard-staging-url"
            >{{ __('Staging URL or Domain') }}</label>
            <p class="settings-description">
              {{ __('If set, execution is restricted to matching URLs or domains. Verification checks the database-persisted site URL and ignores client-supplied HTTP request headers. When empty, environment checks are disabled.') }}
            </p>
            <input
              id="el-onboard-staging-url"
              v-model="settings.stagingUrl"
              type="text"
              class="settings-input"
              :class="{ 'is-invalid': stagingUrlInvalid }"
              :aria-invalid="stagingUrlInvalid ? 'true' : 'false'"
              :placeholder="suggestedStagingDomain"
              autocomplete="off"
              spellcheck="false"
            >
            <span
              v-if="stagingUrlInvalid"
              class="settings-error"
              role="alert"
            >{{ __('Invalid staging URL. Use only a host name, an optional port and an optional path (letters, digits, dots, hyphens, underscores, slashes and * wildcards).') }}</span>
            <span class="settings-hint">{{ __('Domain, hostname, or base URL path without protocol prefix (e.g. staging.example.com or 127.0.0.1/site). Wildcard (*) matching is supported.') }}</span>
          </div>
        </div>

        <hr class="settings-divider">

        <!-- Section: Permissions -->
        <div class="settings-section">
          <h3 class="settings-section-title">
            {{ __('Permissions') }}
          </h3>
          <p class="settings-intro">
            {{ __('All DSL execution operates in an isolated, read-only, and offline state by default. Elevated write privileges and outbound networking may be enabled selectively below:') }}
          </p>
          <div class="permission-list">
            <!-- Database -->
            <label class="permission-item">
              <input
                v-model="settings.database"
                type="checkbox"
                class="permission-checkbox"
              >
              <div class="permission-icon">
                <span class="codicon codicon-database" />
              </div>
              <div class="permission-info">
                <span class="permission-title">{{ __('Database') }}</span>
                <span class="permission-desc">{{ __('Permits write, update, and delete operations on database tables.') }}</span>
              </div>
            </label>

            <!-- File system -->
            <label class="permission-item">
              <input
                v-model="settings.fileSystem"
                type="checkbox"
                class="permission-checkbox"
              >
              <div class="permission-icon">
                <span class="codicon codicon-files" />
              </div>
              <div class="permission-info">
                <span class="permission-title">{{ __('File system') }}</span>
                <span class="permission-desc">{{ __('Permits file creation, modification, and deletion within the server filesystem.') }}</span>
              </div>
            </label>

            <!-- Network -->
            <label class="permission-item">
              <input
                v-model="settings.network"
                type="checkbox"
                class="permission-checkbox"
              >
              <div class="permission-icon">
                <span class="codicon codicon-globe" />
              </div>
              <div class="permission-info">
                <span class="permission-title">{{ __('Network') }}</span>
                <span class="permission-desc">{{ __('Permits outbound HTTP and network socket requests to external hosts.') }}</span>
              </div>
            </label>

            <!-- Hooks -->
            <label class="permission-item">
              <input
                v-model="settings.hooks"
                type="checkbox"
                class="permission-checkbox"
              >
              <div class="permission-icon">
                <span class="codicon codicon-debug-connected" />
              </div>
              <div class="permission-info">
                <span class="permission-title">{{ __('Hooks') }}</span>
                <span class="permission-desc">{{ __('Permits registration and invocation of native WordPress action and filter hooks.') }}</span>
              </div>
            </label>
          </div>
        </div>

        <hr class="settings-divider">

        <!-- Section: Engine constraints -->
        <div class="settings-section">
          <h3 class="settings-section-title">
            {{ __('Engine constraints') }}
          </h3>
          <div class="constraint-group">
            <div class="constraint-title">
              {{ __('Max execution time') }}
            </div>
            <p class="constraint-description">
              {{ __('Maximum execution time in seconds allocated per expression prior to process termination. Default: 2 seconds.') }}
            </p>
            <div class="constraint-field">
              <input
                id="el-onboard-max-execution-limit"
                v-model.number="settings.maxExecutionLimit"
                type="number"
                min="1"
                step="1"
                class="constraint-input"
                :class="{ 'is-invalid': maxExecutionLimitInvalid }"
                :aria-invalid="maxExecutionLimitInvalid ? 'true' : 'false'"
                :aria-label="__('Max execution time')"
              >
              <span class="constraint-unit">{{ __('seconds') }}</span>
            </div>
            <span
              v-if="maxExecutionLimitInvalid"
              class="settings-error"
              role="alert"
            >{{ __('Enter a whole number of seconds greater than zero.') }}</span>
          </div>

          <div class="constraint-group">
            <div class="constraint-title">
              {{ __('Signature duration') }}
            </div>
            <p class="constraint-description">
              {{ __('How long signed requests remain valid before expiring. The default 120 seconds works well for most setups.') }}
            </p>
            <div class="constraint-field">
              <input
                id="el-onboard-signature-duration"
                v-model.number="settings.signatureDuration"
                type="number"
                min="1"
                step="1"
                class="constraint-input"
                :class="{ 'is-invalid': signatureDurationInvalid }"
                :aria-invalid="signatureDurationInvalid ? 'true' : 'false'"
                :aria-label="__('Signature duration')"
              >
              <span class="constraint-unit">{{ __('seconds') }}</span>
            </div>
            <span
              v-if="signatureDurationInvalid"
              class="settings-error"
              role="alert"
            >{{ __('Enter a whole number of seconds greater than zero.') }}</span>
          </div>
        </div>

        <hr class="settings-divider">

        <!-- Section: Integrations -->
        <div class="settings-section">
          <h3 class="settings-section-title">
            {{ __('Integrations') }}
          </h3>
          <div class="form-group settings-group">
            <label
              class="settings-label"
              for="el-onboard-maxmind-key"
            >{{ __('MaxMind license key') }}</label>
            <!-- eslint-disable vue/no-v-html -->
            <p
              class="settings-description"
              v-html="sanitizeHtml(__('Optional key used to download and update the local GeoLite2 database.'))"
            />
            <input
              id="el-onboard-maxmind-key"
              v-model="settings.maxMindApiKey"
              type="text"
              class="settings-input"
              :class="{ 'is-invalid': maxMindApiKeyInvalid }"
              :aria-invalid="maxMindApiKeyInvalid ? 'true' : 'false'"
              :placeholder="__('MaxMind license key')"
              autocomplete="off"
              spellcheck="false"
            >
            <span
              v-if="maxMindApiKeyInvalid"
              class="settings-error"
              role="alert"
            >{{ __('Invalid license key. MaxMind license keys contain only letters, digits, hyphens and underscores.') }}</span>
            <span 
              class="settings-hint"
              v-html="sanitizeHtml(__('Once the setup is completed, run <code>wp expressionlab iplookup update</code> to fetch the file.'))"
            />
            <!-- eslint-enable vue/no-v-html -->
          </div>
        </div>
      </div>
      <div class="step-footer">
        <div class="buttons">
          <button
            class="btn btn-default"
            @click="prevStep"
          >
            {{ __('Back') }}
          </button>
          <button
            class="btn btn-primary"
            :disabled="!canProceed"
            @click="nextStep"
          >
            {{ __('Next') }}
          </button>
        </div>
      </div>
    </section>

    <!-- Step 4: Key Generation -->
    <section
      v-if="currentStep === 4"
      class="step-container"
    >
      <div class="step-header">
        <div class="codicon codicon-key step-codicon" />
        <h2>{{ __('Key Generation') }}</h2>
      </div>
      <div
        v-if="!generatingKeys"
        class="step-body step-inner-container"
      >
        <div
          v-if="hasCachedKeys"
          class="keygen-cached-notice-box keygen-cached-notice"
          role="status"
        >
          <div class="keygen-cached-notice-title">
            <span class="codicon codicon-pass keygen-cached-notice-icon" />
            {{ __('Keys already generated') }}
          </div>
          <p class="keygen-cached-notice-description">
            {{ __('Leave the passphrase empty and continue to keep the keys generated in this session, or enter a new passphrase to generate a new key pair.') }}
          </p>
        </div>
        <div
          v-if="keyGenError"
          class="keygen-error"
          role="alert"
        >
          {{ keyGenError }}
        </div>
        <div class="form-group">
          <label for="el-onboard-passphrase">{{ __('Passphrase:') }}</label>
          <input
            id="el-onboard-passphrase"
            v-model="password"
            type="password"
            class="settings-input"
            autocomplete="new-password"
            :placeholder="__('Min 8 characters')"
            @keyup.enter="canProceed && nextStep()"
          >
        </div>
        <p class="key-generation-description">
          {{ __('This passphrase derives the Ed25519 signing key in your browser.') }}
        </p>
        <!-- eslint-disable vue/no-v-html -->
        <ul class="key-generation-notes">
          <li v-html="sanitizeHtml(__('<strong>Zero storage:</strong> Neither the passphrase nor the private key touches the database or server filesystem.'))" />
          <li v-html="sanitizeHtml(__('<strong>Separation of access:</strong> Use a distinct phrase rather than your WordPress user password.'))" />
          <li v-html="sanitizeHtml(__('<strong>No recovery:</strong> If forgotten, reset requires removing the authentication constants from <code>wp-config.php</code>'))" />
        </ul>
        <!-- eslint-enable vue/no-v-html -->
      </div>  
      <div
        v-else
        class="step-body step-inner-container generating-keys"
      >
        <KeyGenGrid />
        <p class="keygen-status">
          {{ keyGenMessage }}
        </p>
      </div>
      <div class="step-footer">
        <div
          v-if="!generatingKeys"
          class="buttons"
        >
          <button
            class="btn btn-default"
            @click="prevStep"
          >
            {{ __('Back') }}
          </button>
          <button
            class="btn btn-primary"
            :disabled="!canProceed"
            @click="nextStep"
          >
            {{ canReuseCachedKeys ? __('Continue') : __('Generate Keys') }}
          </button>
        </div>
      </div>
    </section>

    <!-- Step 5: Finishing / Verification -->
    <section
      v-if="currentStep === 5"
      class="step-container finishing-step"
    >
      <div class="step-header">
        <div class="codicon codicon-verified step-codicon" />
        <h2>{{ __('Server Ownership Verification') }}</h2>
      </div>
      <div class="step-body step-inner-container">
        <!-- eslint-disable vue/no-v-html -->
        <p
          class="finishing-instruction"
          v-html="sanitizeHtml(__('Activation requires filesystem-level persistence. Paste the following constants into your <code>wp-config.php</code> file:'))"
        />
        <!-- eslint-enable vue/no-v-html -->
        <textarea
          id="el-onboard-config-output"
          :value="generatedConfig"
          readonly
          class="config-output"
          spellcheck="false"
          :aria-label="__('Server Ownership Verification')"
          @focus="$event.target.select()"
          @click="$event.target.select()"
        />
        <!-- eslint-disable vue/no-v-html -->
        <p
          class="finishing-hint"
          v-html="sanitizeHtml(__('Once the file is saved, reload this page to validate the configuration and unlock the console.'))"
        />
        <!-- eslint-enable vue/no-v-html -->
      </div>
      <div class="step-footer">
        <div class="buttons">
          <button
            class="btn btn-default"
            @click="prevStep"
          >
            {{ __('Back') }}
          </button>
        </div>
      </div>
    </section>
  </main>
  <footer class="onboard-footer">
    <p>{{ __('Expression Lab is a free, open-source plugin by Anderson Salas and contributors.') }}</p>
  </footer>  
</template>