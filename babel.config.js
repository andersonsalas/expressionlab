module.exports = function (api) {
  const isTest = api.env('test');

  const presets = [
    [
      '@babel/preset-env',
      {
        targets: isTest ? { node: 'current' } : undefined,
        useBuiltIns: 'usage',
        corejs: 3,
      },
    ],
  ];

  const plugins = [
    '@babel/plugin-proposal-class-properties',
  ];

  return {
    presets,
    plugins,
  };
};