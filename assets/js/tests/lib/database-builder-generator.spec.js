import {
  escapeElscriptString,
  serializeToElscript,
  parseListValue,
  compileFiltersToWhere,
  compileStepOptions,
  resolveBufferConfig,
  generateElscriptDatabasePipeline,
  toMysqlDatetime,
  fromMysqlDatetime,
  toUnixTimestamp,
  fromUnixTimestamp
} from '../../lib/database-builder-generator.js';

describe('database-builder-generator.js', () => {
  describe('escapeElscriptString() and serializeToElscript()', () => {
    it('escapes single quotes and newlines', () => {
      expect(escapeElscriptString('foo \'bar\' \n baz')).toBe('foo \\\'bar\\\' \\n baz');
    });

    it('serializes primitives, arrays, and objects', () => {
      expect(serializeToElscript(null)).toBe('null');
      expect(serializeToElscript(true)).toBe('true');
      expect(serializeToElscript(123)).toBe('123');
      expect(serializeToElscript('test')).toBe('\'test\'');
      expect(serializeToElscript([1, 2])).toBe('[\n  1,\n  2\n]');
      expect(serializeToElscript({ a: 1 })).toBe('{\n  a: 1\n}');
    });
  });

  describe('parseListValue()', () => {
    it('parses comma and newline separated lists with numbers', () => {
      const res = parseListValue('1, 2,  test , 4\n5');
      expect(res).toEqual([1, 2, 'test', 4, 5]);
    });

    it('handles single and double quotes containing commas without splitting', () => {
      const res1 = parseListValue('foo, bar, \'it is tricky to use \\, commas\'');
      expect(res1).toEqual(['foo', 'bar', 'it is tricky to use , commas']);

      const res2 = parseListValue('"this,is a problem"');
      expect(res2).toEqual(['this,is a problem']);

      const res3 = parseListValue('\'hello\', \'world\'');
      expect(res3).toEqual(['hello', 'world']);
    });

    it('preserves explicitly quoted strings that resemble numbers, booleans, or nulls', () => {
      const res = parseListValue('\'0123\', \'007\', 123, 19.99, true, \'true\', null, \'null\'');
      expect(res).toEqual(['0123', '007', 123, 19.99, true, 'true', null, 'null']);
    });

    it('supports unquoted escaped commas and trailing commas', () => {
      const res = parseListValue('foo\\, bar, baz, ');
      expect(res).toEqual(['foo, bar', 'baz']);
    });

    it('returns empty array for empty or non-string input', () => {
      expect(parseListValue('')).toEqual([]);
      expect(parseListValue(null)).toEqual([]);
      expect(parseListValue(undefined)).toEqual([]);
    });
  });

  describe('compileFiltersToWhere()', () => {
    it('compiles equality, comparisons, and in-site joins', () => {
      const filters = [
        { enabled: true, column: 'post_status', operator: '=', value: 'publish' },
        { enabled: true, column: 'post_author', operator: '$join', joinRef: '$prev.ID' },
        { enabled: false, column: 'ignored', operator: '=', value: 'xyz' },
        { enabled: true, column: 'ID', operator: '$in', value: '10, 20, 30' },
        {
          enabled: true,
          column: 'post_date',
          operator: '$between',
          valueBetweenMin: '2025-01-01',
          valueBetweenMax: '2025-12-31'
        },
        { enabled: true, column: 'comment_count', operator: '$gt', value: '5' },
        { enabled: true, column: 'post_content', operator: '$isNotNull' }
      ];

      const where = compileFiltersToWhere(filters);
      expect(where).toEqual({
        post_status: 'publish',
        post_author: '$prev.ID',
        ID: { $in: [10, 20, 30] },
        post_date: { $between: ['2025-01-01', '2025-12-31'] },
        comment_count: { $gt: 5 },
        post_content: { $isNotNull: true }
      });
    });

    it('returns null if no active filters are present', () => {
      expect(compileFiltersToWhere([])).toBeNull();
      expect(compileFiltersToWhere([{ enabled: false, column: 'a', value: 'b' }])).toBeNull();
      expect(compileFiltersToWhere([[ { enabled: false, column: 'a', value: 'b' } ]])).toBeNull();
    });

    it('compiles 2D filter groups with a single active group to a clean Object', () => {
      const groups = [
        [
          { enabled: true, column: 'post_type', operator: '=', value: 'post' },
          { enabled: true, column: 'post_status', operator: '=', value: 'publish' }
        ],
        [
          { enabled: false, column: 'post_type', operator: '=', value: 'page' }
        ]
      ];

      const where = compileFiltersToWhere(groups);
      expect(where).toEqual({
        post_type: 'post',
        post_status: 'publish'
      });
    });

    it('compiles 2D filter groups with multiple active groups to an Array of Objects (OR clauses)', () => {
      const groups = [
        [
          { enabled: true, column: 'post_type', operator: '=', value: 'post' },
          { enabled: true, column: 'post_status', operator: '=', value: 'publish' }
        ],
        [
          { enabled: true, column: 'post_type', operator: '=', value: 'page' },
          { enabled: true, column: 'post_status', operator: '=', value: 'draft' }
        ]
      ];

      const where = compileFiltersToWhere(groups);
      expect(where).toEqual([
        {
          post_type: 'post',
          post_status: 'publish'
        },
        {
          post_type: 'page',
          post_status: 'draft'
        }
      ]);
    });
  });

  describe('compileStepOptions()', () => {
    it('compiles select, orderby, limit, offset, and aliases', () => {
      const options = compileStepOptions({
        select: 'ID, post_title',
        orderbyCol: 'post_date',
        orderbyDir: 'DESC',
        limit: '15',
        offset: '30',
        as: 'custom_posts'
      });

      expect(options).toEqual({
        select: ['ID', 'post_title'],
        orderby: { post_date: 'DESC' },
        limit: 15,
        offset: 30,
        as: 'custom_posts'
      });
    });

    it('omits wildcard * select', () => {
      const options = compileStepOptions({
        select: '*',
        limit: 10
      });
      expect(options).toEqual({ limit: 10 });
    });

    it('compiles disablePrefix into empty prefix string', () => {
      const options = compileStepOptions({
        disablePrefix: true
      });
      expect(options).toEqual({ prefix: '' });
    });

    it('compiles custom prefix override', () => {
      const options = compileStepOptions({
        prefix: 'wp_2_'
      });
      expect(options).toEqual({ prefix: 'wp_2_' });
    });

    it('omits prefix when disablePrefix is false and prefix is empty', () => {
      const options = compileStepOptions({
        disablePrefix: false,
        prefix: ''
      });
      expect(options).toBeNull();
    });
  });

  describe('resolveBufferConfig()', () => {
    it('does not require buffer when within defaults', () => {
      const config = resolveBufferConfig({}, [{ options: { limit: 500 } }]);
      expect(config.required).toBe(false);
    });

    it('auto-requires buffer when any step limit exceeds 1000', () => {
      const config = resolveBufferConfig({}, [{ options: { limit: 2500 } }]);
      expect(config.required).toBe(true);
      expect(config.rows).toBe(2500);
    });

    it('respects explicitly enabled buffer with custom parameters', () => {
      const config = resolveBufferConfig(
        { enabled: true, rows: 5000, memory: 120, phpOverhead: 4.0 },
        []
      );
      expect(config.required).toBe(true);
      expect(config.rows).toBe(5000);
      expect(config.memory).toBe(120);
      expect(config.overhead).toBe(4.0);
    });

    it('does not require buffer when enabled but parameters are empty or match defaults', () => {
      const configEmpty = resolveBufferConfig(
        { enabled: true, rows: '', memory: '', phpOverhead: '' },
        []
      );
      expect(configEmpty.required).toBe(false);

      const configDefault = resolveBufferConfig(
        { enabled: true, rows: 1000, memory: 50, phpOverhead: 3.5 },
        []
      );
      expect(configDefault.required).toBe(false);
    });
  });

  describe('generateElscriptDatabasePipeline()', () => {
    it('does not generate .buffer(1000) when buffer is enabled without custom parameters', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'pipeline',
        steps: [{ table: 'posts', filters: [], options: {} }],
        buffer: { enabled: true, rows: '', memory: '', phpOverhead: '' },
        terminal: { action: 'fetch' }
      });
      expect(code).toBe('Database.mirror(\'posts\').fetch()');
      expect(code).not.toContain('.buffer(');
    });

    it('generates simple single mirror call', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'pipeline',
        steps: [{ table: 'posts', filters: [], options: {} }],
        terminal: { action: 'fetch' }
      });
      expect(code).toBe('Database.mirror(\'posts\').fetch()');
    });

    it('generates simple flush call', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'pipeline',
        steps: [{ table: 'posts', filters: [], options: {} }],
        terminal: { action: 'flush' }
      });
      expect(code).toBe('Database.mirror(\'posts\').flush()');
    });

    it('generates single mirror with filters and options', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'pipeline',
        steps: [
          {
            table: 'posts',
            filters: [
              { enabled: true, column: 'post_status', operator: '=', value: 'publish' }
            ],
            options: {
              select: ['ID', 'post_title'],
              limit: 10
            }
          }
        ],
        terminal: { action: 'fetch' }
      });

      expect(code).toContain('Database');
      expect(code).toContain('.mirror(');
      expect(code).toContain('\'posts\'');
      expect(code).toContain('post_status: \'publish\'');
      expect(code).toContain('select: [');
      expect(code).toContain('limit: 10');
      expect(code).toContain('.fetch()');
    });

    it('auto-injects .buffer(2500) when limit exceeds 1000', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'pipeline',
        steps: [
          {
            table: 'posts',
            filters: [],
            options: { limit: 2500 }
          }
        ],
        terminal: { action: 'fetch' }
      });

      expect(code).toContain('Database');
      expect(code).toContain('.buffer(2500)');
      expect(code).toContain('.mirror(');
    });

    it('generates multi-step pipeline with in-site joins', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'pipeline',
        steps: [
          {
            table: 'users',
            filters: [{ enabled: true, column: 'user_email', operator: '$like', value: '%@example.com' }],
            options: { select: ['ID', 'user_email'] }
          },
          {
            table: 'posts',
            filters: [
              { enabled: true, column: 'post_author', operator: '$join', joinRef: '$prev.ID' },
              { enabled: true, column: 'post_status', operator: '=', value: 'publish' }
            ],
            options: {}
          }
        ],
        terminal: { action: 'fetch' }
      });

      expect(code).toContain('.mirror(');
      expect(code).toContain('\'users\'');
      expect(code).toContain('\'posts\'');
      expect(code).toContain('post_author: \'$prev.ID\'');
      expect(code).toContain('.fetch()');
    });

    it('generates SQLite analytical query with .query()', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'pipeline',
        steps: [{ table: 'posts', filters: [], options: {} }],
        terminal: { action: 'query', querySql: 'SELECT * FROM wp_posts LIMIT 5' }
      });

      expect(code).toContain('.query(\'SELECT * FROM wp_posts LIMIT 5\')');
    });

    it('escapes quotes and backslashes in multi-line .query() SQL while keeping real newlines', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'pipeline',
        steps: [{ table: 'posts', filters: [], options: {} }],
        terminal: { action: 'query', querySql: 'SELECT *\nFROM wp_posts\nWHERE post_status = \'publish\' AND post_title LIKE \'%\\_%\'' }
      });

      expect(code).toContain('WHERE post_status = \\\'publish\\\' AND post_title LIKE \\\'%\\\\_%\\\'');
      expect(code).not.toContain('\\n\\n');
      expect(code).toContain('.query(\'\n');
    });

    it('preserves leading zeros and compiles plain integers as numbers in WHERE', () => {
      const where = compileFiltersToWhere([
        { enabled: true, column: 'post_name', operator: '=', value: '0123' },
        { enabled: true, column: 'ID', operator: '$gt', value: '10' },
        { enabled: true, column: 'menu_order', operator: '$between', valueBetweenMin: '007', valueBetweenMax: '9' }
      ]);

      expect(where).toEqual({
        post_name: '0123',
        ID: { $gt: 10 },
        menu_order: { $between: ['007', 9] }
      });
    });

    it('generates Direct Count mode with Database.count()', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'count',
        countConfig: {
          table: 'posts',
          filters: [{ enabled: true, column: 'post_status', operator: '=', value: 'publish' }]
        }
      });

      expect(code).toContain('Database.count(');
      expect(code).toContain('\'posts\'');
      expect(code).toContain('post_status: \'publish\'');
    });

    it('generates pipeline with disablePrefix for custom tables defaulting as to table name', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'pipeline',
        steps: [
          {
            table: 'custom_table',
            filters: [],
            options: {
              disablePrefix: true
            }
          }
        ],
        terminal: { action: 'fetch' }
      });

      expect(code).toContain('Database');
      expect(code).toContain('.mirror(');
      expect(code).toContain('\'custom_table\'');
      expect(code).toContain('null');
      expect(code).toContain('as: \'custom_table\'');
      expect(code).toContain('prefix: \'\'');
      expect(code).toContain('.fetch()');
    });

    it('generates pipeline with disablePrefix and respects explicit user alias', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'pipeline',
        steps: [
          {
            table: 'custom_table',
            as: 'ct',
            filters: [],
            options: {
              disablePrefix: true
            }
          }
        ],
        terminal: { action: 'query', querySql: 'SELECT * FROM ct' }
      });

      expect(code).toContain('Database');
      expect(code).toContain('.mirror(');
      expect(code).toContain('\'custom_table\'');
      expect(code).toContain('null');
      expect(code).toContain('as: \'ct\'');
      expect(code).toContain('prefix: \'\'');
      expect(code).toContain('.query(\'SELECT * FROM ct\')');
    });

    it('generates Direct Count with disablePrefix', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'count',
        countConfig: {
          table: 'custom_table',
          disablePrefix: true,
          filters: []
        }
      });

      expect(code).toContain('Database.count(');
      expect(code).toContain('\'custom_table\'');
      expect(code).toContain('prefix: \'\'');
    });

    it('generates Mirror Pipeline with OR filter groups', () => {
      const code = generateElscriptDatabasePipeline({
        mode: 'pipeline',
        steps: [
          {
            table: 'posts',
            filterGroups: [
              [
                { enabled: true, column: 'post_type', operator: '=', value: 'post' },
                { enabled: true, column: 'post_status', operator: '=', value: 'publish' }
              ],
              [
                { enabled: true, column: 'post_type', operator: '=', value: 'page' },
                { enabled: true, column: 'post_status', operator: '=', value: 'draft' }
              ]
            ]
          }
        ]
      });

      expect(code).toContain('Database');
      expect(code).toContain('.mirror(');
      expect(code).toContain('\'posts\'');
      expect(code).toContain('post_type: \'post\'');
      expect(code).toContain('post_status: \'publish\'');
      expect(code).toContain('post_type: \'page\'');
      expect(code).toContain('post_status: \'draft\'');
      expect(code).toContain('.fetch()');
    });
  });

  describe('Datetime and Timestamp conversion helpers', () => {
    it('toMysqlDatetime converts local ISO strings to MySQL DATETIME format', () => {
      expect(toMysqlDatetime('2026-10-22T16:45')).toBe('2026-10-22 16:45:00');
      expect(toMysqlDatetime('2026-10-22T16:45:30')).toBe('2026-10-22 16:45:30');
      expect(toMysqlDatetime('2026-10-22')).toBe('2026-10-22 00:00:00');
      expect(toMysqlDatetime('2026-10-22 14:00')).toBe('2026-10-22 14:00:00');
      expect(toMysqlDatetime('')).toBe('');
      expect(toMysqlDatetime(null)).toBe('');
    });

    it('fromMysqlDatetime converts MySQL DATETIME strings to local ISO format', () => {
      expect(fromMysqlDatetime('2026-10-22 16:45:00')).toBe('2026-10-22T16:45:00');
      expect(fromMysqlDatetime('2026-10-22 16:45')).toBe('2026-10-22T16:45');
      expect(fromMysqlDatetime('2026-10-22')).toBe('2026-10-22T00:00:00');
      expect(fromMysqlDatetime('2026-10-22T16:45:00')).toBe('2026-10-22T16:45:00');
      expect(fromMysqlDatetime('')).toBe('');
      expect(fromMysqlDatetime(null)).toBe('');
    });

    it('toUnixTimestamp converts date strings or numbers to seconds integer', () => {
      expect(toUnixTimestamp(1729615500)).toBe(1729615500);
      expect(toUnixTimestamp('1729615500')).toBe(1729615500);
      expect(toUnixTimestamp('')).toBe('');
      expect(toUnixTimestamp(null)).toBe('');

      // Parses valid ISO string
      const ts = toUnixTimestamp('2026-10-22T16:45:00');
      expect(typeof ts).toBe('number');
      expect(ts).toBeGreaterThan(0);
    });

    it('fromUnixTimestamp converts seconds to local ISO format', () => {
      const ts = 1729615500;
      const iso = fromUnixTimestamp(ts);
      expect(iso).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/);
      expect(fromUnixTimestamp('')).toBe('');
      expect(fromUnixTimestamp(0)).toBe('');
      expect(fromUnixTimestamp(null)).toBe('');
    });

    it('compileFiltersToWhere supports numeric UNIX timestamps in BETWEEN filters', () => {
      const filters = [
        {
          enabled: true,
          column: 'post_date',
          operator: '$between',
          valueBetweenMin: 1729615500,
          valueBetweenMax: 1729701900
        }
      ];

      const where = compileFiltersToWhere(filters);
      expect(where).toEqual({
        post_date: {
          $between: [1729615500, 1729701900]
        }
      });
    });
  });
});

