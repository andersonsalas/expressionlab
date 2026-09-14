/** @type {import('stylelint').Config} */
module.exports = {
  extends: [
    'stylelint-config-standard',
    'stylelint-config-standard-scss'
  ],
  rules: {
    'no-invalid-position-at-import-rule': null,
  },
  overrides: [
    {
      files:  ['*.scss', '**/*.scss'],
      extends: [
        'stylelint-config-standard-scss'
      ]
    },
    {
      files:  ['*.vue', '**/*.vue'],
      extends: [
        'stylelint-config-standard-scss',
        'stylelint-config-standard-vue/scss'
      ]
    },
  ],
};