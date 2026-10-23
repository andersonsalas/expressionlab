/**
 * Database Schema Constants and WordPress Core Tables Reference
 *
 * Provides static table metadata, column suggestions, and common relational join mappings
 * for Expression Lab's Database Builder without requiring live database introspection.
 */

export const WP_CORE_TABLES = [
  'posts',
  'postmeta',
  'users',
  'usermeta',
  'terms',
  'term_taxonomy',
  'term_relationships',
  'comments',
  'commentmeta',
  'options',
  'links'
];

export const WP_TABLE_COLUMNS = {
  posts: [
    'ID',
    'post_author',
    'post_date',
    'post_date_gmt',
    'post_content',
    'post_title',
    'post_excerpt',
    'post_status',
    'comment_status',
    'ping_status',
    'post_password',
    'post_name',
    'to_ping',
    'pinged',
    'post_modified',
    'post_modified_gmt',
    'post_content_filtered',
    'post_parent',
    'guid',
    'menu_order',
    'post_type',
    'post_mime_type',
    'comment_count'
  ],
  postmeta: [
    'meta_id',
    'post_id',
    'meta_key',
    'meta_value'
  ],
  users: [
    'ID',
    'user_login',
    'user_pass',
    'user_nicename',
    'user_email',
    'user_url',
    'user_registered',
    'user_activation_key',
    'user_status',
    'display_name'
  ],
  usermeta: [
    'umeta_id',
    'user_id',
    'meta_key',
    'meta_value'
  ],
  terms: [
    'term_id',
    'name',
    'slug',
    'term_group'
  ],
  term_taxonomy: [
    'term_taxonomy_id',
    'term_id',
    'taxonomy',
    'description',
    'parent',
    'count'
  ],
  term_relationships: [
    'object_id',
    'term_taxonomy_id',
    'term_order'
  ],
  comments: [
    'comment_ID',
    'comment_post_ID',
    'comment_author',
    'comment_author_email',
    'comment_author_url',
    'comment_author_IP',
    'comment_date',
    'comment_date_gmt',
    'comment_content',
    'comment_karma',
    'comment_approved',
    'comment_agent',
    'comment_type',
    'comment_parent',
    'user_id'
  ],
  commentmeta: [
    'meta_id',
    'comment_id',
    'meta_key',
    'meta_value'
  ],
  options: [
    'option_id',
    'option_name',
    'option_value',
    'autoload'
  ],
  links: [
    'link_id',
    'link_url',
    'link_name',
    'link_image',
    'link_target',
    'link_description',
    'link_visible',
    'link_owner',
    'link_rating',
    'link_updated',
    'link_rel',
    'link_notes',
    'link_rss'
  ]
};

/**
 * Suggested relational link between tables.
 * [currentTable, foreignKey, targetTable, targetKey]
 */
export const COMMON_RELATIONS = [
  { table: 'postmeta', foreignKey: 'post_id', targetTable: 'posts', targetKey: 'ID' },
  { table: 'posts', foreignKey: 'post_author', targetTable: 'users', targetKey: 'ID' },
  { table: 'usermeta', foreignKey: 'user_id', targetTable: 'users', targetKey: 'ID' },
  { table: 'comments', foreignKey: 'comment_post_ID', targetTable: 'posts', targetKey: 'ID' },
  { table: 'comments', foreignKey: 'user_id', targetTable: 'users', targetKey: 'ID' },
  { table: 'commentmeta', foreignKey: 'comment_id', targetTable: 'comments', targetKey: 'comment_ID' },
  { table: 'term_relationships', foreignKey: 'object_id', targetTable: 'posts', targetKey: 'ID' },
  { table: 'term_relationships', foreignKey: 'term_taxonomy_id', targetTable: 'term_taxonomy', targetKey: 'term_taxonomy_id' },
  { table: 'term_taxonomy', foreignKey: 'term_id', targetTable: 'terms', targetKey: 'term_id' }
];

/**
 * Gets column suggestions for a table name.
 *
 * @param {string} table
 * @returns {string[]}
 */
export function getTableColumns(table) {
  if (!table || typeof table !== 'string') return [];
  const clean = table.toLowerCase().trim();
  return WP_TABLE_COLUMNS[clean] || [];
}

/**
 * Detects if there is a suggested in-site join between a current table and a previous table.
 *
 * @param {string} currentTable
 * @param {string} previousTable
 * @returns {{ foreignKey: string, targetKey: string } | null}
 */
export function findSuggestedJoin(currentTable, previousTable) {
  if (!currentTable || !previousTable) return null;
  const current = currentTable.toLowerCase().trim();
  const prev = previousTable.toLowerCase().trim();

  // 1. Direct relation: currentTable has a foreignKey referencing previousTable
  const directMatch = COMMON_RELATIONS.find(
    (rel) => rel.table === current && rel.targetTable === prev
  );

  if (directMatch) {
    return {
      foreignKey: directMatch.foreignKey,
      targetKey: directMatch.targetKey,
      isReverse: false
    };
  }

  // 2. Reverse relation: previousTable has a foreignKey referencing currentTable (e.g. posts.post_author -> users.ID)
  const reverseMatch = COMMON_RELATIONS.find(
    (rel) => rel.table === prev && rel.targetTable === current
  );

  if (reverseMatch) {
    return {
      foreignKey: reverseMatch.targetKey,
      targetKey: reverseMatch.foreignKey,
      isReverse: true
    };
  }

  return null;
}
