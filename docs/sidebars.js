// @ts-check

/** @type {import('@docusaurus/plugin-content-docs').SidebarsConfig} */
const sidebars = {
  docsSidebar: [
    'intro',
    {
      type: 'category',
      label: 'Getting Started',
      collapsed: false,
      items: [
        'getting-started/installation',
        'getting-started/quick-start',
        'getting-started/configuration',
        'getting-started/basic-syntax',
        'getting-started/scripting',
      ],
    },
    {
      type: 'category',
      label: 'API Reference',
      collapsed: false,
      items: [
        'api-reference/database',
        'api-reference/options',
        'api-reference/sites',
        'api-reference/users',
        'api-reference/posts',
        'api-reference/media',
        'api-reference/files',
        'api-reference/http',
        'api-reference/ip-lookup',
        'api-reference/graph',
        'api-reference/console',
        'api-reference/functions-and-constants',
      ],
    },
    'features/snippet-library',
    'security/security-and-environment',
    'hooks',
  ],
};

module.exports = sidebars;
