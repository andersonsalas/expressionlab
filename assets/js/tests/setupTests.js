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
    filesystem: {
      presets: [
        {
          id: 'debug_log',
          name: 'debug.log',
          path: 'wp-content/debug.log',
          exists: true,
          type: 'log',
          size: '1.2 KB',
          description: 'WordPress active runtime debug log file'
        },
        {
          id: 'wp_config',
          name: 'wp-config.php',
          path: 'wp-config.php',
          exists: true,
          type: 'config',
          size: '3.4 KB',
          description: 'Core WordPress configuration and database definitions'
        }
      ],
      tree: [
        {
          name: 'wp-config.php',
          path: 'wp-config.php',
          type: 'file',
          size: '3.4 KB',
          ext: 'php',
          opaque: false
        },
        {
          name: 'wp-content',
          path: 'wp-content',
          type: 'dir',
          opaque: false,
          children: [
            {
              name: 'debug.log',
              path: 'wp-content/debug.log',
              type: 'file',
              size: '1.2 KB',
              ext: 'log',
              opaque: false
            },
            {
              name: 'plugins',
              path: 'wp-content/plugins',
              type: 'dir',
              opaque: false,
              children: [
                {
                  name: 'expressionlab',
                  path: 'wp-content/plugins/expressionlab',
                  type: 'dir',
                  opaque: true,
                  children: []
                }
              ]
            }
          ]
        }
      ],
      limits: {
        max_read_bytes: 262144,
        max_tail_bytes: 1048576
      }
    }
  };
}

