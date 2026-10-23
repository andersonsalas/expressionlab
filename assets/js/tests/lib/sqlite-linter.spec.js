import { getLineAndColumn, validateSql, createSqlLinter, formatSql } from '../../lib/sqlite-linter.js';

describe('sqlite-linter.js', () => {
  describe('getLineAndColumn()', () => {
    it('calculates correct 1-indexed line and column', () => {
      const text = 'SELECT *\nFROM wp_posts\nWHERE id = 1';
      expect(getLineAndColumn(text, 0)).toEqual({ line: 1, column: 1 });
      expect(getLineAndColumn(text, 8)).toEqual({ line: 1, column: 9 });
      expect(getLineAndColumn(text, 9)).toEqual({ line: 2, column: 1 });
      expect(getLineAndColumn(text, 23)).toEqual({ line: 3, column: 1 });
    });
  });

  describe('validateSql()', () => {
    it('accepts valid SELECT queries with where, order, limit', () => {
      const q1 = 'SELECT * FROM wp_posts LIMIT 10';
      expect(validateSql(q1)).toEqual({ isValid: true, error: null });

      const q2 = `
        SELECT p.ID, p.post_title, u.user_login
        FROM wp_posts AS p
        INNER JOIN wp_users AS u ON u.ID = p.post_author
        WHERE p.post_status = 'publish' AND p.ID > 5
        ORDER BY p.post_date DESC
        LIMIT 25 OFFSET 50;
      `;
      expect(validateSql(q2)).toEqual({ isValid: true, error: null });
    });

    it('accepts valid subqueries, comments, and escaped strings', () => {
      const q = `
        -- Fetch users with recent posts
        /* Multi-line
           comment */
        SELECT *
        FROM wp_users
        WHERE ID IN (SELECT post_author FROM wp_posts WHERE post_title LIKE 'It''s fine')
      `;
      expect(validateSql(q)).toEqual({ isValid: true, error: null });
    });

    it('accepts WITH, EXPLAIN, and PRAGMA statements', () => {
      expect(validateSql('WITH cte AS (SELECT 1) SELECT * FROM cte')).toEqual({ isValid: true, error: null });
      expect(validateSql('EXPLAIN QUERY PLAN SELECT 1')).toEqual({ isValid: true, error: null });
      expect(validateSql('PRAGMA table_info(wp_posts)')).toEqual({ isValid: true, error: null });
    });

    it('returns valid on empty or whitespace strings', () => {
      expect(validateSql('')).toEqual({ isValid: true, error: null });
      expect(validateSql('   \n  \t  ')).toEqual({ isValid: true, error: null });
      expect(validateSql(null)).toEqual({ isValid: true, error: null });
    });

    it('detects unclosed single quote strings', () => {
      const res = validateSql('SELECT * FROM wp_posts WHERE post_title = \'Unclosed string');
      expect(res.isValid).toBe(false);
      expect(res.error).toContain('Unterminated string literal');
    });

    it('detects unclosed quoted identifiers', () => {
      const res1 = validateSql('SELECT * FROM "unclosed_table');
      expect(res1.isValid).toBe(false);
      expect(res1.error).toContain('Unterminated quoted identifier \'"\'');

      const res2 = validateSql('SELECT * FROM `unclosed_table');
      expect(res2.isValid).toBe(false);
      expect(res2.error).toContain('Unterminated quoted identifier \'`\'');

      const res3 = validateSql('SELECT * FROM [unclosed_table');
      expect(res3.isValid).toBe(false);
      expect(res3.error).toContain('Unterminated quoted identifier \'[\'');
    });

    it('detects unclosed block comments', () => {
      const res = validateSql('SELECT * FROM wp_posts /* open comment');
      expect(res.isValid).toBe(false);
      expect(res.error).toContain('Unterminated block comment \'/*\'');
    });

    it('detects unclosed and unexpected parentheses', () => {
      const res1 = validateSql('SELECT * FROM wp_posts WHERE id IN (1, 2, 3');
      expect(res1.isValid).toBe(false);
      expect(res1.error).toContain('Unclosed parenthesis \'(\'');

      const res2 = validateSql('SELECT * FROM wp_posts WHERE id = 1)');
      expect(res2.isValid).toBe(false);
      expect(res2.error).toContain('Unexpected closing parenthesis \')\'');
    });

    it('detects non-SELECT root statements', () => {
      const res = validateSql('DELETE FROM wp_posts');
      expect(res.isValid).toBe(false);
      expect(res.error).toContain('Expected SELECT statement, found \'DELETE\'');
    });

    it('detects comma syntax errors', () => {
      const res1 = validateSql('SELECT id,, post_title FROM wp_posts');
      expect(res1.isValid).toBe(false);
      expect(res1.error).toContain('Unexpected duplicate comma');

      const res2 = validateSql('SELECT id, post_title, FROM wp_posts');
      expect(res2.isValid).toBe(false);
      expect(res2.error).toContain('Unexpected token \'FROM\' after comma');
    });

    it('detects missing column expressions after SELECT', () => {
      const res = validateSql('SELECT FROM wp_posts');
      expect(res.isValid).toBe(false);
      expect(res.error).toContain('Unexpected token \'FROM\'. Expected column expressions after SELECT');
    });

    it('detects misordered clauses', () => {
      const res1 = validateSql('SELECT * FROM wp_posts ORDER BY post_date WHERE post_status = 1');
      expect(res1.isValid).toBe(false);
      expect(res1.error).toContain('WHERE clause cannot follow ORDER BY');

      const res2 = validateSql('SELECT * FROM wp_posts LIMIT 10 WHERE post_status = 1');
      expect(res2.isValid).toBe(false);
      expect(res2.error).toContain('WHERE clause cannot follow LIMIT');

      const res3 = validateSql('SELECT * FROM wp_posts LIMIT 10 ORDER BY post_date');
      expect(res3.isValid).toBe(false);
      expect(res3.error).toContain('ORDER BY clause cannot follow LIMIT');
    });

    it('detects incomplete statement tails at EOF', () => {
      expect(validateSql('SELECT * FROM').error).toContain('Incomplete statement: expected expression after \'FROM\'');
      expect(validateSql('SELECT * FROM wp_posts WHERE').error).toContain('Incomplete statement: expected expression after \'WHERE\'');
      expect(validateSql('SELECT * FROM wp_posts ORDER BY').error).toContain('Incomplete statement: expected expression after \'BY\'');
      expect(validateSql('SELECT * FROM wp_posts LIMIT').error).toContain('Incomplete statement: expected expression after \'LIMIT\'');
      expect(validateSql('SELECT * FROM wp_posts JOIN').error).toContain('Incomplete statement: expected expression after \'JOIN\'');
      expect(validateSql('SELECT * FROM wp_posts JOIN wp_users ON').error).toContain('Incomplete statement: expected expression after \'ON\'');
      expect(validateSql('SELECT * FROM wp_posts,').error).toContain('Incomplete statement: expected expression after \',\'');
      expect(validateSql('SELECT * FROM wp_posts WHERE id =').error).toContain('Incomplete statement: expected expression after \'=\'');
    });

    it('handles parameter placeholders without hanging or infinite loop', () => {
      expect(validateSql('SELECT * FROM custom_table WHERE ?')).toEqual({ isValid: true, error: null });
      expect(validateSql('SELECT * FROM custom_table WHERE id = ?')).toEqual({ isValid: true, error: null });
      expect(validateSql('SELECT * FROM custom_table WHERE id = ?1 AND name = :name AND slug = @slug AND status = $status')).toEqual({ isValid: true, error: null });
    });

    it('handles operators like modulo, concatenation, bitwise without infinite loop', () => {
      expect(validateSql('SELECT id % 2, col1 || col2 FROM custom_table')).toEqual({ isValid: true, error: null });
      expect(validateSql('SELECT * FROM custom_table WHERE id == 1 AND status <> 2')).toEqual({ isValid: true, error: null });
    });

    it('safely handles unrecognized characters without hanging', () => {
      expect(validateSql('SELECT * FROM custom_table WHERE id = 1 # test')).toBeDefined();
    });
  });

  describe('createSqlLinter()', () => {
    it('creates a CodeMirror linter that returns diagnostics on error', () => {
      const mockCmLinter = (fn) => fn;
      const linterFn = createSqlLinter(mockCmLinter);

      const validView = {
        state: {
          doc: {
            toString: () => 'SELECT * FROM wp_posts',
            length: 22
          }
        }
      };
      expect(linterFn(validView)).toEqual([]);

      const invalidView = {
        state: {
          doc: {
            toString: () => 'SELECT * FROM',
            length: 13
          }
        }
      };
      const diagnostics = linterFn(invalidView);
      expect(diagnostics.length).toBe(1);
      expect(diagnostics[0].severity).toBe('error');
      expect(diagnostics[0].message).toContain('Incomplete statement: expected expression after \'FROM\'');
    });
  });

  describe('formatSql()', () => {
    it('returns empty string for empty inputs', () => {
      expect(formatSql('')).toBe('');
      expect(formatSql('   ')).toBe('');
      expect(formatSql(null)).toBe('');
    });

    it('formats simple SELECT query with uppercase keywords and line breaks', () => {
      const input = 'select * from wp_posts limit 10';
      const output = formatSql(input);
      expect(output).toBe('SELECT *\nFROM wp_posts\nLIMIT 10');
    });

    it('formats multi-column SELECT query with indentation', () => {
      const input = 'select id, post_title, post_date from wp_posts limit 5';
      const output = formatSql(input);
      expect(output).toBe('SELECT\n  id,\n  post_title,\n  post_date\nFROM wp_posts\nLIMIT 5');
    });

    it('formats WHERE clause with AND, OR on new indented lines', () => {
      const input = 'select id from wp_posts where post_status = \'publish\' and id > 5 or comment_count > 0';
      const output = formatSql(input);
      expect(output).toBe('SELECT id\nFROM wp_posts\nWHERE post_status = \'publish\'\n  AND id > 5\n  OR comment_count > 0');
    });

    it('formats compound JOINs on new lines', () => {
      const input = 'select p.id, u.user_login from wp_posts p inner join wp_users u on u.id = p.post_author where p.post_status = \'publish\'';
      const output = formatSql(input);
      expect(output).toBe('SELECT\n  p.id,\n  u.user_login\nFROM wp_posts p\nINNER JOIN wp_users u ON u.id = p.post_author\nWHERE p.post_status = \'publish\'');
    });

    it('preserves strings and identifier quotes intact', () => {
      const input = 'select "my_col", `other` from [wp_posts] where title = \'hello\'\'s world\'';
      const output = formatSql(input);
      expect(output).toContain('\'hello\'\'s world\'');
      expect(output).toContain('"my_col"');
      expect(output).toContain('`other`');
      expect(output).toContain('[wp_posts]');
    });

    it('formats subqueries inside parentheses', () => {
      const input = 'select * from wp_posts where id in (select post_id from wp_postmeta where meta_key = \'foo\') limit 5';
      const output = formatSql(input);
      expect(output).toBe('SELECT *\nFROM wp_posts\nWHERE id IN (SELECT post_id FROM wp_postmeta WHERE meta_key = \'foo\')\nLIMIT 5');
    });

    it('formats parameter placeholders correctly', () => {
      const input = 'select * from wp_posts where id = ? and post_author = :author limit ?1';
      const output = formatSql(input);
      expect(output).toBe('SELECT *\nFROM wp_posts\nWHERE id = ?\n  AND post_author = :author\nLIMIT ?1');
    });
  });
});
