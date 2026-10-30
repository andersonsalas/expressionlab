const Encore = require('@symfony/webpack-encore');

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const withAddons = process.argv.includes('--with-addons') || process.env.EXPRESSION_LAB_WITH_ADDONS === '1';

const addonDir = path.resolve(__dirname, 'assets/addons');
const addonManifestPath = path.resolve(addonDir, 'addons.json');
const addonIntegrityPath = path.resolve(addonDir, 'integrity.json');

if (withAddons) {
  if (Encore.isProduction()) {
    if (!fs.existsSync(addonManifestPath) || !fs.existsSync(addonIntegrityPath)) {
      throw new Error('[Addon Error] withAddons is enabled, but addons.json or integrity.json was not found in assets/addons/.');
    }
    const integrityManifest = JSON.parse(fs.readFileSync(addonIntegrityPath, 'utf8'));
    for (const [fileRel, expectedHash] of Object.entries(integrityManifest)) {
      const fullPath = path.resolve(addonDir, fileRel);
      if (!fs.existsSync(fullPath)) {
        throw new Error(`[Addon Error] Missing addon file: ${fileRel}`);
      }
      const actualHash = crypto.createHash('sha256').update(fs.readFileSync(fullPath)).digest('hex');
      if (actualHash !== expectedHash) {
        throw new Error(`[Addon Error] Integrity mismatch for ${fileRel}: expected ${expectedHash}, got ${actualHash}`);
      }
    }
  } else {
    if (!fs.existsSync(addonManifestPath)) {
      console.warn('[Addon Dev] withAddons is enabled, but assets/addons/addons.json is not present yet. Waiting for build...');
    }
  }
}

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

const appEntries = ['./assets/js/entries/app.js'];
const addonCssPath = path.resolve(addonDir, 'css/addon.min.css');
if (withAddons && fs.existsSync(addonCssPath)) {
  appEntries.push(addonCssPath);
}

const addonEntryPath = path.resolve(addonDir, 'js/addon.min.js');

Encore
  .setOutputPath('assets/build/')
  .setPublicPath('/assets/build')

  .addEntry('expressionlab-onboard', './assets/js/entries/onboard.js')
  .addEntry('expressionlab-app', appEntries)
  .addEntry('expressionlab-app-sandbox', './assets/js/entries/app-sandbox.js')

  .disableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())

  .configureBabel(null, {
    exclude: /(node_modules|assets[\\/]addons)/,
  })

  .addAliases({
    '@addon': withAddons ? addonEntryPath : false,
    'vue$': path.resolve(__dirname, 'node_modules/vue/dist/vue.esm-bundler.js'),
    'pinia$': path.resolve(__dirname, 'node_modules/pinia'),
    'vue-router$': path.resolve(__dirname, 'node_modules/vue-router'),
  })

  .configureDefinePlugin(options => {
    options['__WITH_ADDONS__'] = JSON.stringify(withAddons);
    if (Encore.isProduction()) {
      options['__VUE_PROD_DEVTOOLS__'] = 'false';
      options['__USE_DEVTOOLS__'] = 'false';
    }
  })

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
    watchOptions.aggregateTimeout = 300;
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

class CodiconWoff2Plugin {
  apply(compiler) {
    const handler = async () => {
      const ttfPath = path.resolve(__dirname, 'node_modules/@vscode/codicons/dist/codicon.ttf');
      const woff2Path = path.resolve(__dirname, 'assets/fonts/codicon.woff2');

      if (!fs.existsSync(ttfPath)) {
        return;
      }

      if (fs.existsSync(woff2Path)) {
        const ttfStat = fs.statSync(ttfPath);
        const woff2Stat = fs.statSync(woff2Path);
        if (woff2Stat.mtimeMs >= ttfStat.mtimeMs) {
          return;
        }
      }

      try {
        const wawoff2 = require('wawoff2');
        const input = fs.readFileSync(ttfPath);
        const output = await wawoff2.compress(input);
        fs.mkdirSync(path.dirname(woff2Path), { recursive: true });
        fs.writeFileSync(woff2Path, output);

        const integrityPath = path.resolve(__dirname, 'assets/integrity.json');
        if (fs.existsSync(integrityPath)) {
          const hash = crypto.createHash('sha256').update(output).digest('hex');
          const integrityManifest = JSON.parse(fs.readFileSync(integrityPath, 'utf8'));
          integrityManifest['assets/fonts/codicon.woff2'] = hash;
          fs.writeFileSync(integrityPath, JSON.stringify(integrityManifest, null, 2) + '\n');
        }
      } catch (err) {
        console.error('Failed to convert codicon.ttf to codicon.woff2:', err);
      }
    };

    compiler.hooks.beforeRun.tapPromise('CodiconWoff2Plugin', handler);
    compiler.hooks.watchRun.tapPromise('CodiconWoff2Plugin', handler);
  }
}

Encore.addPlugin(new CodiconWoff2Plugin());

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
  ;
}

module.exports = Encore.getWebpackConfig();