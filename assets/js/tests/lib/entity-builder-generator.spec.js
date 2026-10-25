import {
  SUPPORTED_ENTITIES,
  ENTITY_CONFIGS,
  ENTITY_OPERATORS,
  normalizeOperator,
  formatConditionCall,
  getDuplicateColumnsInGroup,
  getDuplicateOrderColumns,
  generateElscriptEntityQuery
} from '../../lib/entity-builder-generator.js';

describe('entity-builder-generator', () => {
  describe('Constants and Operator Normalization', () => {
    it('exports supported entities and canonical configs', () => {
      expect(SUPPORTED_ENTITIES).toEqual(['Posts', 'Users', 'Media']);
      expect(ENTITY_CONFIGS.Posts.table).toBe('posts');
      expect(ENTITY_CONFIGS.Users.table).toBe('users');
      expect(ENTITY_CONFIGS.Media.table).toBe('posts');
      expect(ENTITY_CONFIGS.Media.baseConditions).toEqual([
        { column: 'post_type', operator: '=', value: 'attachment' }
      ]);
      expect(ENTITY_OPERATORS.length).toBeGreaterThanOrEqual(10);
    });

    it('normalizes operator strings accurately', () => {
      expect(normalizeOperator('=')).toBe('=');
      expect(normalizeOperator('$in')).toBe('in');
      expect(normalizeOperator('IN')).toBe('in');
      expect(normalizeOperator('$notIn')).toBe('not in');
      expect(normalizeOperator('$like')).toBe('like');
      expect(normalizeOperator('$notLike')).toBe('not like');
      expect(normalizeOperator('$between')).toBe('between');
      expect(normalizeOperator('$gt')).toBe('>');
      expect(normalizeOperator('$gte')).toBe('>=');
      expect(normalizeOperator('$lt')).toBe('<');
      expect(normalizeOperator('$lte')).toBe('<=');
      expect(normalizeOperator('$ne')).toBe('!=');
      expect(normalizeOperator('<>')).toBe('!=');
      expect(normalizeOperator(null)).toBe('=');
    });
  });

  describe('formatConditionCall', () => {
    it('returns null for disabled filter or empty column', () => {
      expect(formatConditionCall('where', null)).toBeNull();
      expect(formatConditionCall('where', { enabled: false, column: 'ID', operator: '=', value: 1 })).toBeNull();
      expect(formatConditionCall('where', { enabled: true, column: '', operator: '=', value: 1 })).toBeNull();
      expect(formatConditionCall('where', { enabled: true, column: '   ', operator: '=', value: 1 })).toBeNull();
    });

    it('formats equality with 2 arguments', () => {
      const call = formatConditionCall('where', {
        enabled: true,
        column: 'post_status',
        operator: '=',
        value: 'publish'
      });
      expect(call).toBe('where(\'post_status\', \'publish\')');
    });

    it('formats numeric values as numbers in equality', () => {
      const call = formatConditionCall('where', {
        enabled: true,
        column: 'post_author',
        operator: '=',
        value: '42'
      });
      expect(call).toBe('where(\'post_author\', 42)');
    });

    it('formats relational operators with 3 arguments', () => {
      const call = formatConditionCall('where', {
        enabled: true,
        column: 'ID',
        operator: '$gt',
        value: '100'
      });
      expect(call).toBe('where(\'ID\', \'>\', 100)');
    });

    it('formats LIKE and NOT LIKE operators with 3 arguments', () => {
      const callLike = formatConditionCall('where', {
        enabled: true,
        column: 'post_title',
        operator: '$like',
        value: '%test%'
      });
      expect(callLike).toBe('where(\'post_title\', \'like\', \'%test%\')');

      const callNotLike = formatConditionCall('where', {
        enabled: true,
        column: 'post_title',
        operator: '$notLike',
        value: '%draft%'
      });
      expect(callNotLike).toBe('where(\'post_title\', \'not like\', \'%draft%\')');
    });

    it('formats IN and NOT IN operators with arrays or delimited strings', () => {
      const callIn = formatConditionCall('where', {
        enabled: true,
        column: 'ID',
        operator: '$in',
        value: '1, 2, 3'
      });
      expect(callIn).toBe('where(\'ID\', \'in\', [\n  1,\n  2,\n  3\n])');

      const callNotIn = formatConditionCall('where', {
        enabled: true,
        column: 'post_name',
        operator: '$notIn',
        value: ['about', 'contact']
      });
      expect(callNotIn).toBe('where(\'post_name\', \'not in\', [\n  \'about\',\n  \'contact\'\n])');
    });

    it('formats BETWEEN operators with [min, max]', () => {
      const call = formatConditionCall('where', {
        enabled: true,
        column: 'post_date',
        operator: '$between',
        valueBetweenMin: '2026-01-01',
        valueBetweenMax: '2026-12-31'
      });
      expect(call).toBe('where(\'post_date\', \'between\', [\'2026-01-01\', \'2026-12-31\'])');
    });

    it('supports or_where method name for disjunctive conditions', () => {
      const call = formatConditionCall('or_where', {
        enabled: true,
        column: 'post_type',
        operator: '=',
        value: 'page'
      });
      expect(call).toBe('or_where(\'post_type\', \'page\')');
    });
  });

  describe('getDuplicateColumnsInGroup', () => {
    it('detects duplicate column names case-insensitively', () => {
      const group = [
        { enabled: true, column: 'post_status' },
        { enabled: true, column: 'post_type' },
        { enabled: true, column: 'POST_STATUS' },
        { enabled: false, column: 'post_type' } // disabled not counted
      ];
      expect(getDuplicateColumnsInGroup(group)).toEqual(['post_status']);
    });
  });

  describe('getDuplicateOrderColumns', () => {
    it('detects duplicate order column names case-insensitively', () => {
      const orderBys = [
        { column: 'post_author', direction: 'ASC' },
        { column: 'post_date', direction: 'DESC' },
        { column: 'POST_AUTHOR', direction: 'DESC' }
      ];
      expect(getDuplicateOrderColumns(orderBys)).toEqual(['post_author']);
    });

    it('returns empty array when no duplicates exist or input is invalid', () => {
      expect(getDuplicateOrderColumns([])).toEqual([]);
      expect(getDuplicateOrderColumns(null)).toEqual([]);
      expect(getDuplicateOrderColumns([{ column: 'id' }, { column: 'date' }])).toEqual([]);
    });
  });

  describe('generateElscriptEntityQuery', () => {
    it('generates simple single-line execution when no conditions are set', () => {
      expect(generateElscriptEntityQuery({ entity: 'Posts' })).toBe('Posts.get()');
      expect(generateElscriptEntityQuery({ entity: 'Users', terminal: { action: 'count' } })).toBe('Users.count()');
      expect(generateElscriptEntityQuery({ entity: 'Media', terminal: { action: 'to_sql' } })).toBe('Media.to_sql()');
      expect(generateElscriptEntityQuery({ entity: 'Posts', terminal: { action: 'to_sql', isCountSql: true } })).toBe('Posts.to_sql(true)');
    });

    it('generates chained queries with AND filters', () => {
      const code = generateElscriptEntityQuery({
        entity: 'Posts',
        filters: [
          [
            { enabled: true, column: 'post_type', operator: '=', value: 'post' },
            { enabled: true, column: 'post_status', operator: '=', value: 'publish' }
          ]
        ],
        terminal: { action: 'get' }
      });

      expect(code).toBe(
        'Posts.where(\'post_type\', \'post\')\n' +
        '    .where(\'post_status\', \'publish\')\n' +
        '    .get()'
      );
    });

    it('enforces DNF rules across multiple OR groups (first item or_where, rest where)', () => {
      const code = generateElscriptEntityQuery({
        entity: 'Posts',
        filterGroups: [
          // Group 1
          [
            { enabled: true, column: 'post_type', operator: '=', value: 'post' },
            { enabled: true, column: 'post_status', operator: '=', value: 'publish' }
          ],
          // Group 2
          [
            { enabled: true, column: 'post_type', operator: '=', value: 'page' },
            { enabled: true, column: 'post_status', operator: '=', value: 'draft' }
          ],
          // Group 3
          [
            { enabled: true, column: 'post_type', operator: '=', value: 'custom' }
          ]
        ],
        terminal: { action: 'get' }
      });

      expect(code).toBe(
        'Posts.where(\'post_type\', \'post\')\n' +
        '    .where(\'post_status\', \'publish\')\n' +
        '    .or_where(\'post_type\', \'page\')\n' +
        '    .where(\'post_status\', \'draft\')\n' +
        '    .or_where(\'post_type\', \'custom\')\n' +
        '    .get()'
      );
    });

    it('handles sorting (multiple order_by clauses)', () => {
      const code = generateElscriptEntityQuery({
        entity: 'Users',
        orderBys: [
          { column: 'user_registered', direction: 'DESC' },
          { column: 'display_name', direction: 'ASC' }
        ],
        terminal: { action: 'get' }
      });

      expect(code).toBe(
        'Users.order_by(\'user_registered\', \'DESC\')\n' +
        '    .order_by(\'display_name\', \'ASC\')\n' +
        '    .get()'
      );
    });

    it('handles limit and offset clauses', () => {
      const code = generateElscriptEntityQuery({
        entity: 'Media',
        limit: 25,
        offset: 50,
        terminal: { action: 'get' }
      });

      expect(code).toBe(
        'Media.limit(25)\n' +
        '    .offset(50)\n' +
        '    .get()'
      );
    });

    it('handles terminal action query with single-line and multiline SQL', () => {
      const codeSingle = generateElscriptEntityQuery({
        entity: 'Posts',
        filters: [
          [{ enabled: true, column: 'post_status', operator: '=', value: 'publish' }]
        ],
        terminal: {
          action: 'query',
          querySql: 'SELECT post_title FROM wp_posts'
        }
      });

      expect(codeSingle).toBe(
        'Posts.where(\'post_status\', \'publish\')\n' +
        '    .query(\'SELECT post_title FROM wp_posts\')'
      );

      const codeMulti = generateElscriptEntityQuery({
        entity: 'Posts',
        terminal: {
          action: 'query',
          querySql: 'SELECT\n  ID,\n  post_title\nFROM wp_posts\nWHERE post_status = \'publish\''
        }
      });

      expect(codeMulti).toContain('query(\'\nSELECT\n  ID,\n  post_title\nFROM wp_posts\nWHERE post_status = \\\'publish\\\'\n\')');
    });

    it('safely escapes column names and string literals', () => {
      const code = generateElscriptEntityQuery({
        entity: 'Posts',
        filters: [
          [{ enabled: true, column: 'author\'s_col', operator: '=', value: 'It\'s a test' }]
        ],
        terminal: { action: 'get' }
      });

      expect(code).toContain('where(\'author\\\'s_col\', \'It\\\'s a test\')');
    });
  });
});
