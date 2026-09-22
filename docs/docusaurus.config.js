// @ts-check
// `@type` JSDoc annotations allow editor autocompletion and type checking
// (when paired with `@ts-check`).

const { themes: prismThemes } = require('prism-react-renderer');
const packageJson = require('./package.json');

/**
 * Remove italic styles from a prism theme
 * @param {import('prism-react-renderer').PrismTheme} theme
 * @returns {import('prism-react-renderer').PrismTheme}
 */
const removeItalicsFromTheme = (theme) => ({
  ...theme,
  styles: (theme.styles || []).map((entry) => {
    if (entry.style && entry.style.fontStyle === 'italic') {
      const { fontStyle: _fontStyle, ...rest } = entry.style;
      return { ...entry, style: rest };
    }
    return entry;
  }),
});

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'Expression Lab',
  tagline: 'Sandboxed DSL & REPL Console for WordPress',
  favicon: 'img/expressionlab-logo.svg',

  customFields: {
    pluginVersion: packageJson.version,
  },

  // Set the production url of your site here
  url: 'https://expressionlab.io',
  // Set the /<baseUrl>/ pathname under which your site is served
  baseUrl: '/',

  // GitHub pages deployment config.
  organizationName: 'andersonsalas',
  projectName: 'expressionlab',

  onBrokenLinks: 'throw',
  markdown: {
    mermaid: true,
    hooks: {
      onBrokenMarkdownLinks: 'warn',
    },
  },

  // Internationalization configuration
  i18n: {
    defaultLocale: 'en',
    locales: ['en', 'es'],
    localeConfigs: {
      en: {
        label: 'English',
        htmlLang: 'en-US',
      },
      es: {
        label: 'Español',
        htmlLang: 'es-ES',
      },
    },
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          sidebarPath: './sidebars.js',
          routeBasePath: 'docs', // Serve documentation under /docs/
          versions: {
            current: {
              label: '0.0.2-alpha',
            },
          },
        },
        blog: false, // Documentation-focused site
        theme: {
          customCss: './src/css/custom.css',
        },
      }),
    ],
  ],

  plugins: [
    function webpackFallbackPlugin() {
      return {
        name: 'webpack-fallback-plugin',
        configureWebpack() {
          return {
            resolve: {
              alias: {
                canvas: false,
              },
              fallback: {
                canvas: false,
              },
            },
          };
        },
      };
    },
  ],

  themes: [
    '@docusaurus/theme-mermaid',
    [
      require.resolve('@easyops-cn/docusaurus-search-local'),
      {
        hashed: true,
        language: ['en', 'es'],
        docsRouteBasePath: '/docs',
        indexDocs: true,
        indexBlog: false,
        indexPages: true,
        highlightSearchTermsOnTargetPage: true,
      },
    ],
  ],

  themeConfig:
    /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      navbar: {
        title: 'Expression Lab',
        logo: {
          alt: 'Expression Lab Logo',
          src: 'img/logo.svg',
        },
        items: [
          {
            type: 'docSidebar',
            sidebarId: 'docsSidebar',
            position: 'left',
            label: 'Documentation',
          },
          {
            type: 'docsVersionDropdown',
            position: 'right',
            dropdownActiveClassDisabled: true,
          },
          {
            type: 'localeDropdown',
            position: 'right',
          },
          {
            href: 'https://github.com/andersonsalas/expressionlab',
            position: 'right',
            className: 'header-github-link',
            'aria-label': 'GitHub repository',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [],
      },
      prism: {
        theme: removeItalicsFromTheme(prismThemes.github),
        darkTheme: removeItalicsFromTheme(prismThemes.dracula),
        additionalLanguages: ['php', 'json', 'bash', 'yaml'],
      },
    }),
};

module.exports = config;
