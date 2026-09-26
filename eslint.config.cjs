const {
    defineConfig,
} = require('eslint/config');

const globals = require('globals');
const js = require('@eslint/js');

const {
    FlatCompat,
} = require('@eslint/eslintrc');

const compat = new FlatCompat({
    baseDirectory: __dirname,
    recommendedConfig: js.configs.recommended,
    allConfig: js.configs.all
});

module.exports = defineConfig([
	{
		ignores: [
			'assets/build/**',
			'**/assets/build/**',
			'assets/js/lib/codemirror/grammar/parser*.js',
			'coverage/**',
			'docs/**',
			'node_modules/**',
			'vendor/**',
		],
	},
	{
		languageOptions: {
			globals: {
				...globals.browser,
				...globals.node,
				...globals.jest,
			},

			ecmaVersion: 'latest',
			sourceType: 'module',
			parserOptions: {},
		},

		extends: compat.extends('eslint:recommended', 'plugin:vue/recommended'),

		rules: {
			//'vue/html-quotes': ['error', 'single'],
			'quotes': ['error', 'single'],
			'no-unused-vars': 'off',
		},
	}
]);
