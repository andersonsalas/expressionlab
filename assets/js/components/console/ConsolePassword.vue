<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { ed25519 } from '@noble/curves/ed25519.js';
import { base64ToArrayBuffer, arrayBufferToBase64, deriveKey, __, sprintf, sanitizeHtml } from '../../lib/helpers.js';

const emit = defineEmits(['unlocked']);

const password = ref('');
const loading = ref(false);
const error = ref('');
const isShaking = ref(false);
const passwordInput = ref(null);

const user_display_name = window.el_settings.user.display_name;
const user_gravatar = window.el_settings.user.gravatar;

const handleUnlock = async () => {
  if ( password.value.length === 0 ) {
    error.value = __('Please enter your passphrase.');
    nextTick(() => {
        if (passwordInput.value) {
            passwordInput.value.focus();
        }
    });
    return;
  }

  if (password.value.length < 8) {
    error.value = __('Password too short.');
    nextTick(() => {
        if (passwordInput.value) {
            passwordInput.value.focus();
        }
    });
    return;
  }
  
  loading.value = true;
  error.value = '';

  await new Promise(resolve => setTimeout(resolve, 100));

  try {
      const saltBase64 = window.el_settings.salt; 
      const expectedPublicKey = window.el_settings.public_key;

      if (!saltBase64) {
        throw new Error(__('Salt not found in configuration.'));
      }

      const salt = base64ToArrayBuffer(saltBase64);
      const seed = await deriveKey(password.value, salt);

      if (expectedPublicKey) {
        const derivedPublicKey = ed25519.getPublicKey(seed);
        const derivedPublicKeyBase64 = arrayBufferToBase64(derivedPublicKey);
        if (derivedPublicKeyBase64 !== expectedPublicKey) {
          throw new Error(__('Invalid passphrase.'));
        }
      }

      await new Promise((resolve, reject) => {
        emit('unlocked', seed, (err) => {
          if (err) reject(new Error(err));
          else resolve();
        });
      });

  } catch (e) {
      isShaking.value = true;
      setTimeout(() => { isShaking.value = false; }, 500);

      // Simplify error message if it's our custom error
      if ( e.message === 'Invalid passphrase.' || e.message === __('Invalid passphrase.') ) {
          error.value = __('Invalid passphrase.');
      } else {
          error.value = sprintf(__('Failed: %s'), e.message);
      }

      nextTick(() => {
          if (passwordInput.value) {
              passwordInput.value.focus();
          }
      });
  } finally {
      loading.value = false;
      password.value = '';
  }
};

onMounted(() => {
  passwordInput.value.focus();
});
</script>

<template>
  <div class="console-password-overlay">
    <div
      class="console-password-modal"
      :class="{ 'shake': isShaking }"
    >
      <img
        :src="user_gravatar"
        :alt="__('User Avatar')"
        class="user-avatar"
      >
      <h2>{{ user_display_name }}</h2>
      <p>{{ __('Enter your passphrase:') }}</p>
        
      <div class="form-group">
        <input 
          ref="passwordInput" 
          v-model="password" 
          type="password"
          :disabled="loading"
          @keyup.enter="handleUnlock"
        >
      </div>

      <div
        v-if="error"
        class="error-message"
      >
        {{ error }}
      </div>

      <button
        class="btn btn-primary"
        :disabled="loading"
        @click="handleUnlock"
      >
        <span v-if="loading">{{ __('Unlocking...') }}</span>
        <span v-else>{{ __('Unlock') }}</span>
      </button>

      <!-- eslint-disable vue/no-v-html -->
      <p
        class="forgot-passphrase"
        v-html="sanitizeHtml(__('If you forgot this passphrase, you must delete the Expression Lab constants from your <code>wp-config.php</code> to trigger a new installation.'))"
      />
      <!-- eslint-enable vue/no-v-html -->
    </div>
  </div>
</template>
