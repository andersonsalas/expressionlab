const Encore = require('@symfony/webpack-encore');

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const withProAddon = process.argv.includes('--with-addons') || process.env.EXPRESSION_LAB_WITH_ADDONS === '1';

const addonDir = path.resolve(__dirname, 'assets/addons');
const addonManifestPath = path.resolve(addonDir, 'addons.json');
const addonIntegrityPath = path.resolve(addonDir, 'integrity.json');
const addonSignaturePath = path.resolve(addonDir, 'signature.json');

/**
 * Ed25519 public key that verifies Professional Edition addon bundles at BUILD time.
 * Intentionally distinct from EXPRESSION_LAB_PUBLIC_KEY (expressionlab.php), which verifies
 * plugin updates at runtime and is never used to sign addons.
 */
const EXPRESSION_LAB_ADDON_PUBLIC_KEY = 'a12b0b4c98d97be3491fdff944be38984664e10d8f6ecdeb1d87dea78ef0b5d5';

const SEMVER_RE = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/;
const RELEASE_RE = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/;

/** SemVer 2.0.0 precedence (build metadata unsupported). Returns -1, 0 or 1. */
const compareSemver = (a, b) => {
  const pa = SEMVER_RE.exec(a);
  const pb = SEMVER_RE.exec(b);
  for (let i = 1; i <= 3; i++) {
    const diff = Number(pa[i]) - Number(pb[i]);
    if (diff !== 0) {
      return Math.sign(diff);
    }
  }
  if (pa[4] === undefined || pb[4] === undefined) {
    return pa[4] === pb[4] ? 0 : (pa[4] === undefined ? 1 : -1);
  }
  const ia = pa[4].split('.');
  const ib = pb[4].split('.');
  for (let i = 0; i < Math.max(ia.length, ib.length); i++) {
    if (ia[i] === undefined) {
      return -1;
    }
    if (ib[i] === undefined) {
      return 1;
    }
    const na = /^\d+$/.test(ia[i]);
    const nb = /^\d+$/.test(ib[i]);
    if (na && nb && Number(ia[i]) !== Number(ib[i])) {
      return Math.sign(Number(ia[i]) - Number(ib[i]));
    }
    if (na !== nb) {
      return na ? -1 : 1;
    }
    if (!na && ia[i] !== ib[i]) {
      return ia[i] < ib[i] ? -1 : 1;
    }
  }
  return 0;
};

const collectAddonFiles = (rootDir, currentDir = rootDir, out = []) => {
  for (const entry of fs.readdirSync(currentDir, { withFileTypes: true })) {
    const fullPath = path.join(currentDir, entry.name);
    const rel = path.relative(rootDir, fullPath).split(path.sep).join('/');
    if (entry.isSymbolicLink()) {
      throw new Error(`[Error] Symbolic links are not allowed in assets/addons: ${rel}`);
    }
    if (entry.isDirectory()) {
      collectAddonFiles(rootDir, fullPath, out);
    } else if (entry.isFile()) {
      out.push(rel);
    } else {
      throw new Error(`[Error] Unexpected non-regular file in assets/addons: ${rel}`);
    }
  }
  return out;
};

let addonManifest = null;

if (withProAddon) {
  if (Encore.isProduction()) {
    console.info('[Info] Starting production build for Professional Edition...');

    if (!/^[0-9a-f]{64}$/.test(EXPRESSION_LAB_ADDON_PUBLIC_KEY)) {
      throw new Error('[Error] Addon public key is invalid');
    }

    const pubKey = crypto.createPublicKey({
      key: {
        kty: 'OKP',
        crv: 'Ed25519',
        x: Buffer.from(EXPRESSION_LAB_ADDON_PUBLIC_KEY, 'hex').toString('base64url'),
      },
      format: 'jwk',
    });

    if (!fs.existsSync(addonManifestPath)) {
      throw new Error('[Error] addons.json was not found in assets/addons');
    }
    if (!fs.existsSync(addonIntegrityPath)) {
      throw new Error('[Error] integrity.json was not found in assets/addons');
    }
    if (!fs.existsSync(addonSignaturePath)) {
      throw new Error('[Error] signature.json was not found in assets/addons');
    }

    const integrityBuffer = fs.readFileSync(addonIntegrityPath);

    let signatureManifest;
    try {
      signatureManifest = JSON.parse(fs.readFileSync(addonSignaturePath, 'utf8'));
    } catch (err) {
      throw new Error('[Error] Failed to parse assets/addons/signature.json');
    }

    let integrityManifest;
    try {
      integrityManifest = JSON.parse(integrityBuffer.toString('utf8'));
    } catch (err) {
      throw new Error('[Error] Failed to parse assets/addons/integrity.json');
    }

    const integritySig = signatureManifest?.['integrity.json'];
    if (!integritySig || typeof integritySig !== 'string') {
      throw new Error('[Error] Missing signature for integrity.json in assets/addons/signature.json');
    }

    let isIntegrityValid = false;
    try {
      isIntegrityValid = crypto.verify(null, integrityBuffer, pubKey, Buffer.from(integritySig, 'hex'));
    } catch (err) {
      throw new Error('[Error] Signature check failed for integrity.json');
    }

    if (!isIntegrityValid) {
      throw new Error('[Error] Signature mismatch for integrity.json');
    }

    if (!integrityManifest?.['addons.json']) {
      throw new Error('[Error] addons.json is not declared in assets/addons/integrity.json');
    }
    if (!signatureManifest?.['addons.json'] || typeof signatureManifest['addons.json'] !== 'string') {
      throw new Error('[Error] Missing signature for addons.json in assets/addons/signature.json');
    }

    try {
      addonManifest = JSON.parse(fs.readFileSync(addonManifestPath, 'utf8'));
    } catch (err) {
      throw new Error('[Error] Failed to parse assets/addons/addons.json');
    }

    if (!addonManifest || addonManifest.schema_version !== 1 || addonManifest.name !== 'expressionlab-addons') {
      throw new Error('[Error] Invalid manifest.');
    }

    const corePkg = require('./package.json');
    if (typeof addonManifest.version !== 'string' || !SEMVER_RE.test(addonManifest.version)) {
      throw new Error('[Error] Addon version must be strict SemVer.');
    }
    if (typeof corePkg.version !== 'string' || !SEMVER_RE.test(corePkg.version)) {
      throw new Error(`[Error] Core package.json version "${corePkg.version}" is not strict SemVer.`);
    }

    // 1:1 release contract: CI (and bin/build-plugin.sh) pin the exact addon version to ship.
    const expectedAddonVersion = process.env.EXPRESSION_LAB_EXPECTED_ADDON_VERSION;
    if (expectedAddonVersion !== undefined && expectedAddonVersion !== '' && addonManifest.version !== expectedAddonVersion) {
      throw new Error(
        `[Error] Addon version ${addonManifest.version} does not match the expected version ${expectedAddonVersion}.`
      );
    }

    if (typeof addonManifest.min_core_version !== 'string' || !RELEASE_RE.test(addonManifest.min_core_version)) {
      throw new Error('[Error] Addon min_core_version is mandatory and must be X.Y.Z.');
    }
    if (compareSemver(corePkg.version, addonManifest.min_core_version) < 0) {
      throw new Error(
        `[Error] Addon requires Core >= ${addonManifest.min_core_version}, current Core is ${corePkg.version}.`
      );
    }

    const normalizeRel = (p) => path.posix.normalize(String(p)).replace(/^(\.\/)+/, '');
    const requiredFiles = new Set([
      'addons.json',
      normalizeRel(addonManifest.entry || 'js/addon.min.js'),
      ...(Array.isArray(addonManifest.styles) ? addonManifest.styles.map(normalizeRel) : ['css/addon.min.css']),
      ...(addonManifest.wasm ? Object.values(addonManifest.wasm).map(normalizeRel) : []),
    ]);

    // Strict whitelist: only declared assets plus the two meta files may exist in assets/addons.
    const allowedFiles = new Set([...requiredFiles, 'integrity.json', 'signature.json']);
    for (const rel of collectAddonFiles(addonDir)) {
      if (!allowedFiles.has(rel)) {
        throw new Error(`[Error] Unexpected file in assets/addons: ${rel}`);
      }
    }
    for (const rel of Object.keys(integrityManifest)) {
      if (!requiredFiles.has(rel)) {
        throw new Error(`[Error] Undeclared entry in assets/addons/integrity.json: ${rel}`);
      }
    }
    for (const rel of Object.keys(signatureManifest)) {
      if (rel !== 'integrity.json' && !requiredFiles.has(rel)) {
        throw new Error(`[Error] Undeclared entry in assets/addons/signature.json: ${rel}`);
      }
    }

    for (const file of requiredFiles) {
      if (!integrityManifest[file]) {
        throw new Error(`[Error] Required build asset '${file}' is not declared in assets/addons/integrity.json`);
      }
      if (!signatureManifest[file] || typeof signatureManifest[file] !== 'string') {
        throw new Error(`[Error] Missing signature for required build asset '${file}' in assets/addons/signature.json`);
      }
    }

    const allFilesToVerify = new Set([...Object.keys(integrityManifest), ...requiredFiles]);

    for (const fileRel of allFilesToVerify) {
      const fullPath = path.resolve(addonDir, fileRel);
      if (!fullPath.startsWith(addonDir + path.sep)) {
        throw new Error(`[Error] Invalid path: ${fileRel}`);
      }
      if (!fs.existsSync(fullPath)) {
        throw new Error(`[Error] Missing file: ${fileRel}`);
      }

      const fileBuffer = fs.readFileSync(fullPath);
      const actualHash = crypto.createHash('sha256').update(fileBuffer).digest('hex');
      if (actualHash !== integrityManifest[fileRel]) {
        throw new Error(`[Error] Integrity mismatch for ${fileRel}`);
      }

      const expectedSignature = signatureManifest?.[fileRel];
      if (!expectedSignature || typeof expectedSignature !== 'string') {
        throw new Error(`[Error] Missing signature for ${fileRel} in assets/addons/signature.json`);
      }

      let isSignatureValid = false;
      try {
        isSignatureValid = crypto.verify(null, fileBuffer, pubKey, Buffer.from(expectedSignature, 'hex'));
      } catch (err) {
        throw new Error(`[Error] Signature check failed for ${fileRel}`);
      }

      if (!isSignatureValid) {
        throw new Error(`[Error] Signature mismatch for ${fileRel}`);
      }
    }
  } else {
    if (fs.existsSync(addonManifestPath)) {
      try {
        addonManifest = JSON.parse(fs.readFileSync(addonManifestPath, 'utf8'));
      } catch (err) {
        console.warn('[Warning] Failed to parse assets/addons/addons.json');
      }
    } else {
      console.warn('[Warning] assets/addons/addons.json is not present yet. Waiting for build...');
    }
  }
}

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

const appEntries = ['./assets/js/entries/app.js'];
const onboardEntries = ['./assets/js/entries/onboard.js'];
if (withProAddon) {
  const styleRels = Array.isArray(addonManifest?.styles) && addonManifest.styles.length > 0
    ? addonManifest.styles
    : ['css/addon.min.css'];
  for (const styleRel of styleRels) {
    const stylePath = path.resolve(addonDir, styleRel);
    if (fs.existsSync(stylePath)) {
      appEntries.push(stylePath);
      onboardEntries.push(stylePath);
    }
  }
}

const addonEntryPath = path.resolve(addonDir, addonManifest?.entry || 'js/addon.min.js');

Encore
  .setOutputPath('assets/build/')
  .setPublicPath('/assets/build')

  .addEntry('expressionlab-onboard', onboardEntries)
  .addEntry('expressionlab-app', appEntries)
  .addEntry('expressionlab-app-sandbox', './assets/js/entries/app-sandbox.js')

  .disableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())

  .configureBabel(null, {
    exclude: /(node_modules|assets[\\/]addons)/,
  })

  .addAliases({
    '@addon': withProAddon ? addonEntryPath : false,
    'vue$': path.resolve(__dirname, 'node_modules/vue/dist/vue.esm-bundler.js'),
    'pinia$': path.resolve(__dirname, 'node_modules/pinia'),
    'vue-router$': path.resolve(__dirname, 'node_modules/vue-router'),
  })

  .configureDefinePlugin(options => {
    options['__WITH_ADDONS__'] = JSON.stringify(withProAddon);
    if (Encore.isProduction()) {
      options['__VUE_PROD_DEVTOOLS__'] = 'false';
      options['__USE_DEVTOOLS__'] = 'false';
    }
  })

  .addRule({
    resourceQuery: /raw/,
    type: 'asset/source',
  })

  .addRule({
    test: /\.wasm$/,
    type: 'asset/inline',
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
        fs.writeFileSync(outputPath, JSON.stringify(integrityManifest, null, 2) + '\n');
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