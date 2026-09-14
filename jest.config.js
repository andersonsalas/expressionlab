module.exports = {
  testEnvironment: 'jest-environment-jsdom',
  setupFiles: ['<rootDir>/assets/js/tests/setupTests.js'],

  transform: {
    '^.+\\.vue$': 'vue-jest',
    '^.+\\.js$': 'babel-jest',
  },

  moduleFileExtensions: ['vue', 'js', 'json', 'node'],

  testMatch: [
    '<rootDir>/assets/js/tests/**/*.spec.js',
  ],

  moduleNameMapper: {
    '\\.(css|scss|less)$': '<rootDir>/assets/js/tests/__mocks__/styleMock.js',
    '\\.svg\\?raw$': '<rootDir>/assets/js/tests/__mocks__/svgRawMock.js',
    '^@codemirror/autocomplete$': '<rootDir>/assets/js/tests/__mocks__/codemirrorMock.js',
    '^marked$': '<rootDir>/node_modules/marked/lib/marked.umd.js',
    '^vega-embed$': '<rootDir>/node_modules/vega-embed/build/embed.js',
    '^vega-interpreter$': '<rootDir>/node_modules/vega-interpreter/build/vega-interpreter.js',
  },

  transformIgnorePatterns: [
    '/node_modules/(?!.*(@iconoir/vue|unicode-emoji-json|@noble|@codemirror|@marijn|marked|vega-embed|vega-interpreter)/)',
  ],

  collectCoverageFrom: [
    'assets/js/**/*.{js,vue}',
    '!assets/js/tests/**',
    '!assets/js/components/icons/**',
    '!assets/js/entries/**',
  ],
};
