module.exports = {
  snippetCompletion: (template, completion = {}) => ({
    ...completion,
    apply: template,
  }),
  autocompletion: () => ({}),
  snippet: (template) => template,
  completeFromList: () => () => null,
  ifNotIn: () => () => null,
  closeCompletion: () => {},
};
