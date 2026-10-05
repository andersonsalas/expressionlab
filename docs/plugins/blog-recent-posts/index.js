const blogPluginPath = require.resolve('@docusaurus/plugin-content-blog', {
  paths: [require.resolve('@docusaurus/preset-classic')],
});
const blogPlugin = require(blogPluginPath).default;

module.exports = async function blogRecentPostsPlugin(context, options) {
  const pluginInstance = await blogPlugin(context, options);

  return {
    ...pluginInstance,
    name: 'docusaurus-plugin-content-blog',
    async contentLoaded({ content, actions }) {
      await pluginInstance.contentLoaded({ content, actions });

      const recentPosts = (content.blogPosts || []).slice(0, 3).map((post) => {
        const metadata = post.metadata || {};
        return {
          id: metadata.id || post.id,
          title: metadata.title,
          permalink: metadata.permalink,
          date: metadata.date,
          formattedDate: metadata.formattedDate,
          authors: (metadata.authors || []).map((author) => ({
            name: author.name,
            title: author.title,
            url: author.url,
            imageURL: author.imageURL,
          })),
          description: metadata.description,
          readingTime: metadata.readingTime,
        };
      });

      actions.setGlobalData({ recentPosts });
    },
  };
};

module.exports.validateOptions = require(blogPluginPath).validateOptions;
