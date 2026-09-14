const Encore = require('@symfony/webpack-encore');

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
  .setOutputPath('assets/build/')
  .setPublicPath('/assets/build')

  .addEntry('expressionlab-onboard', './assets/js/entries/onboard.js')
  .addEntry('expressionlab-app', './assets/js/entries/app.js')
  .addEntry('expressionlab-app-sandbox', './assets/js/entries/app-sandbox.js')

  .disableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())

  .configureBabel(null)

  .addRule({
    resourceQuery: /raw/,
    type: 'asset/source',
  })

  .configureDevServerOptions(options => {
    options.allowedHosts = 'all';
    options.client = {
      ...options.client,
      overlay: {
        runtimeErrors: (error) => {
          // Ignore specific error messages that are not critical.
          if ('ResizeObserver loop completed with undelivered notifications.' === error.message) {
            return false;
          }
          return true;
        },
      },
    }
  })

  .configureWatchOptions(function(watchOptions) {
    watchOptions.ignored = [
      '**/node_modules/**',
      '**/vendor/**',
      '**/wp-content/plugins/expressionlab/assets/build/**',
    ];
    watchOptions.poll = 1000; 
  })

  .enableSassLoader(function (config) {
    config.sassOptions = {
      silenceDeprecations: [
        'color-functions',
        'global-builtin',
        'import'
      ],
      quietDeps: true,
    }
  }, { resolveUrlLoader: false })

  .enableVueLoader((options) => {
    if (Encore.isProduction()) {
      options.compilerOptions = {
        ...options.compilerOptions,
        // Remove data-test attributes in production builds.
        nodeTransforms: [
          (node) => {
            if (node.type === 1) { // Element node.
              for (let i = node.props.length - 1; i >= 0; i--) { // Attribute.
                const prop = node.props[i];
                if (prop.type === 6 && prop.name.startsWith('data-test')) {
                  node.props.splice(i, 1);
                }
              }
            }
          }
        ]
      };
    }
  }, { runtimeCompilerBuild: true })

  .configureCssLoader((options) => {
    options.url = {
      filter: (url) => {
        if (url.startsWith('./')) {
          return false;
        }
        if (-1 === url.indexOf('images/icon-')) {
          return false;
        }
        return true;
      },
    };
  })

  .configureFontRule({
    filename: (pathData) => {
      if (pathData.filename.includes('codicon')) {
        return '[name][ext]';
      }
      return 'fonts/[name].[hash:8][ext]';
    }
  })

  .configureLoaderRule('scss', loaderRule => {
    loaderRule.oneOf.forEach(rule => {
      rule.use.forEach(loader => {
        if (loader.loader && loader.loader.includes('sass-loader')) {
          // Add additional data to all SCSS files.
          loader.options.additionalData = `
            @use "~/assets/scss/_variables.scss" as *;
          `;
        }
      });
    });
  });
;

if ( Encore.isProduction() ) {
  class AssetIntegrityPlugin {
    apply(compiler) {
      compiler.hooks.afterEmit.tap('AssetIntegrityPlugin', () => {
        const dirsToScan = [
          path.resolve(__dirname, 'assets/build'),
          path.resolve(__dirname, 'assets/fonts')
        ];
        
        const integrityManifest = {};

        dirsToScan.forEach(dir => {
          if (fs.existsSync(dir)) {
            const files = fs.readdirSync(dir);
            files.forEach(file => {
              if (/\.(js|css|woff2)$/.test(file)) {
                const filePath = path.join(dir, file);
                const fileBuffer = fs.readFileSync(filePath);
                const hash = crypto.createHash('sha256').update(fileBuffer).digest('hex');
                const relativeKey = path.relative(__dirname, filePath).replace(/\\/g, '/');
                integrityManifest[relativeKey] = hash;
              }
            });
          }
        });
        const outputPath = path.resolve(__dirname, 'assets/integrity.json');
        fs.writeFileSync(outputPath, JSON.stringify(integrityManifest, null, 2));
      });
    }
  }

  Encore
    .addPlugin(new AssetIntegrityPlugin())
    .addAliases({
      '@vue/devtools-api': false
    })
    .configureDefinePlugin(options => {
      options['__VUE_PROD_DEVTOOLS__'] = 'false';
      options['__USE_DEVTOOLS__'] = 'false';
    })
  ;
}

module.exports = Encore.getWebpackConfig();