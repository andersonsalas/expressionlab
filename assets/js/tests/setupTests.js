const { TextEncoder, TextDecoder } = require('util');

if (typeof global.TextEncoder === 'undefined') {
  global.TextEncoder = TextEncoder;
}

if (typeof global.TextDecoder === 'undefined') {
  global.TextDecoder = TextDecoder;
}

// Ensure crypto has getRandomValues if needed by noble-curves
if (typeof global.crypto === 'undefined') {
  const crypto = require('crypto');
  global.crypto = {
    getRandomValues: (arr) => crypto.randomFillSync(arr),
    subtle: crypto.webcrypto ? crypto.webcrypto.subtle : undefined,
  };
} else if (!global.crypto.getRandomValues) {
  const crypto = require('crypto');
  global.crypto.getRandomValues = (arr) => crypto.randomFillSync(arr);
}

if (typeof window !== 'undefined' && !window.el_settings) {
  window.el_settings = {
    ajax_url: 'http://example.com/wp-admin/admin-ajax.php',
    nonce: 'test_nonce',
    settings: {
      enable_sandbox: false,
      debug_mode: true,
    },
  };
}

