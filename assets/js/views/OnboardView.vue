<script setup>
import { ref, computed } from 'vue';
import ExpressionLabIcon from '../components/icons/ExpressionLabIcon.vue';
import { ed25519 } from '@noble/curves/ed25519.js';
import { arrayBufferToBase64, deriveKey, __, sprintf, sanitizeHtml } from '../lib/helpers.js';

const steps = computed(() => [
    { id: 1, title: __('Welcome') },
    { id: 2, title: __('System Requirements') },
    { id: 3, title: __('Key Generation') },
    { id: 4, title: __('Verification') }
]);

const currentStep = ref(1);
const agreementChecked = ref(false);
const password = ref('');
const generatedConfig = ref('');
const generatingKeys = ref(false);
const reqs = ref({
    sqlite: window.el_settings.server.sqlite_enabled,
    sodium: window.el_settings.server.sodium_enabled,
    webCrypto: !!(window.crypto && window.crypto.subtle)
});

const canProceed = computed(() => {
    if (currentStep.value === 1) return agreementChecked.value;
    // Note: SQLite3 is required for some features but not strictly required for the core functionality
    // of Expression Lab, so we will allow users to proceed even if it's missing.
    // We will just show a warning in the requirements step.
    if (currentStep.value === 2) return  reqs.value.sodium && reqs.value.webCrypto;
    if (currentStep.value === 3) return password.value.length >= 8;
    return true;
});

const nextStep = async () => {
    if (currentStep.value === 3) {
        generatingKeys.value = true;
        // Allow UI to update before starting key generation.
        await new Promise(resolve => setTimeout(resolve, 100)); 
        await generateKeys();
        generatingKeys.value = false;
    } else {
        currentStep.value++;
    }
};

const generateKeys = async () => {
    try {
        // Cryptographically secure 16-bytes (128 bits) random salt.
        const salt = window.crypto.getRandomValues(new Uint8Array(16));

        // Seed derivation with Argon2id.
        const seed = await deriveKey(password.value, salt);

        // Public key generation (noble-ed25519).
        const publicKeyBuffer = ed25519.getPublicKey(seed);

        // Convert to Base64 for wp-config.php
        const saltBase64 = arrayBufferToBase64(salt);
        const publicKeyBase64 = arrayBufferToBase64(publicKeyBuffer);

        const userId = window.el_settings.user_id;

        // Print the results.
        generatedConfig.value = `define( 'EXPRESSION_LAB_ADMIN_USER_ID', ${userId} );\n` +
                                `define( 'EXPRESSION_LAB_ADMIN_PUBLIC_KEY', '${publicKeyBase64}' );\n` +
                                `define( 'EXPRESSION_LAB_ADMIN_SALT', '${saltBase64}' );`;

        currentStep.value = 4;

        // Clear the memory for security.
        seed.fill(0);
        password.value = ''; 
    } catch (e) {
        console.error('Key generation failed', e);
        alert(sprintf(__('Key generation failed: %s'), e.message));
    }
};

</script>

<template>
  <div class="onboard-header">
    <div class="wizard-progress">
      <div 
        v-for="step in steps" 
        :key="step.id" 
        class="step-item"
        :class="{ 
          'completed': currentStep > step.id, 
          'active': currentStep === step.id 
        }"
      >
        <div class="step-label">
          {{ step.title }}
        </div>
        <div class="step-indicator">
          <div class="step-dot" />
        </div>
      </div>
    </div>
  </div>
  <div class="onboard-wizard">
    <!-- Step 1: Welcome -->
    <div
      v-if="currentStep === 1"
      class="step-container welcome-step"
    >
      <div class="wizard-logo">
        <ExpressionLabIcon class="wizard-icon" />
      </div>
      <h1>{{ __('Welcome to Expression Lab') }}</h1>
      <p class="welcome-subtitle">
        {{ __('The installation wizard guides the initial configuration of the plugin.') }}
      </p>
      
      <div class="alpha-notice-box">
        <h2 class="alpha-notice-title">
          <span class="codicon codicon-warning alpha-notice-icon" />
          {{ __('Experimental Alpha Software') }}
        </h2>
        <p class="alpha-notice-description">
          {{ __('Expression Lab is in alpha. While designed with security-first patterns (AST sandboxing, client-side cryptographic keys), it has not undergone third-party security audits.') }}
        </p>
        <ul class="alpha-notice-list">
          <li>{{ __('Intended for local development, testing, or staging environments.') }}</li>
          <li>{{ __('Do not use in production, high-security, governmental, or critical infrastructure sites.') }}</li>
          <li>{{ __('Provided "as is" under GPL-2.0 without warranty or liability for data loss.') }}</li>
        </ul>
      </div>

      <div class="agreement-check">
        <label>
          <input
            v-model="agreementChecked"
            type="checkbox"
          >
          <span>{{ __('I understand Expression Lab is an unaudited alpha prototype and agree to use it only in non-critical environments at my own risk.') }}</span>
        </label>
      </div>

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

    <!-- Step 2: System Requirements -->
    <div
      v-if="currentStep === 2"
      class="step-container"
    >
      <div class="codicon codicon-server step-codicon" />
      <h2>{{ __('System Requirements') }}</h2>
      <div class="step-inner-container">
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
        <!-- eslint-disable vue/no-v-html -->
        <p
          class="requirements-description"
          v-html="sanitizeHtml(__('<strong>SQLite3</strong> enables safe, in-memory database mirroring to prevent direct queries to the live database. <strong>Sodium</strong> and the <strong>Web Crypto API</strong> are required to generate, sign, and validate secure communications within the sandboxed environment.'))"
        />
        <!-- eslint-enable vue/no-v-html -->
      </div>
      <div class="buttons">
        <button
          class="btn btn-primary"
          :disabled="!canProceed"
          @click="nextStep"
        >
          {{ __('Next') }}
        </button>
      </div>
    </div>

    <!-- Step 3: Key Generation -->
    <div
      v-if="currentStep === 3"
      class="step-container"
    >
      <div
        v-if="!generatingKeys"
        class="codicon codicon-key step-codicon"
      />
      <h2 v-if="!generatingKeys">
        {{ __('Key Generation') }}
      </h2>
      <div
        v-if="!generatingKeys"
        class="step-inner-container"
      >
        <div class="form-group">
          <label>{{ __('Passphrase:') }}</label>
          <input
            v-model="password"
            type="password"
            :placeholder="__('Min 8 characters')"
            @keyup.enter="canProceed && nextStep()"
          >
        </div>
        <p class="key-generation-description">
          {{ __('This passphrase is used only to derive the private key. Keep the following in mind:') }}
        </p>
        <!-- eslint-disable vue/no-v-html -->
        <ul class="key-generation-notes">
          <li v-html="sanitizeHtml(__('<strong>Zero storage:</strong> Neither this passphrase nor the derived private key are ever stored on the server or the database.'))" />
          <li v-html="sanitizeHtml(__('<strong>Security:</strong> Avoid reusing WordPress passwords or trivial terms (e.g., <code>admin</code>, <code>12345678</code>).'))" />
          <li v-html="sanitizeHtml(__('<strong>No recovery:</strong> If this passphrase is lost, cryptographic constants must be removed from <code>wp-config.php</code> to restart the setup.'))" />
        </ul>
        <!-- eslint-enable vue/no-v-html -->
      </div>  
      <div
        v-if="generatingKeys"
        class="step-inner-container generating-keys"
      >
        <!-- Cube spinner -->
        <div class="cube-spinner">
          <div />
          <div />
          <div />
          <div />
          <div />
          <div />
        </div>
        <p>{{ __('Generating cryptographic keys...') }}</p>
      </div>
      <div
        v-if="!generatingKeys"
        class="buttons"
      >
        <button
          class="btn btn-primary"
          :disabled="!canProceed"
          @click="nextStep"
        >
          {{ __('Generate Keys') }}
        </button>
      </div>
    </div>

    <!-- Step 4: Finishing -->
    <div
      v-if="currentStep === 4"
      class="step-container finishing-step"
    >
      <div class="codicon codicon-verified step-codicon" />
      <h2>{{ __('Server Ownership Verification') }}</h2>
      <div class="step-inner-container">
        <!-- eslint-disable vue/no-v-html -->
        <p v-html="sanitizeHtml(__('For security reasons, server access must be verified. Copy the following code and paste it into the <code>wp-config.php</code> file:'))" />
        <!-- eslint-enable vue/no-v-html -->
        <textarea
          v-model="generatedConfig"
          readonly
          class="config-output"
        />
        <!-- eslint-disable vue/no-v-html -->
        <p v-html="sanitizeHtml(__('Once changes are saved to the <code>wp-config.php</code> file, reload this page to verify the setup and complete the installation.'))" />
        <!-- eslint-enable vue/no-v-html -->
      </div>
    </div>
  </div>
  <div class="onboard-footer">
    <p>{{ __('Expression Lab is a free, open-source plugin by Anderson Salas and contributors.') }}</p>
  </div>  
</template>