import { formatExpressionLab, tokenize } from '../../../lib/codemirror/formatter.js';

describe('Expression Lab DSL Formatter', () => {
  describe('tokenize', () => {
    it('tokenizes block comments and does NOT treat // as comments', () => {
      const code = '/* block comment */ x // not comment';
      const tokens = tokenize(code);
      const commentToken = tokens.find(t => t.type === 'BLOCK_COMMENT');
      expect(commentToken).toBeDefined();
      expect(commentToken.value).toBe('/* block comment */');

      // // should be tokenized as two operator tokens '/' '/' or operator, NOT comment
      const commentTokens = tokens.filter(t => t.type === 'BLOCK_COMMENT');
      expect(commentTokens.length).toBe(1);
      const slashTokens = tokens.filter(t => t.value === '/');
      expect(slashTokens.length).toBe(2);
    });

    it('tokenizes strings with escaped quotes', () => {
      const code = '\'hello \\\'world\\\'\' "foo \\"bar\\""';
      const tokens = tokenize(code);
      expect(tokens[0].value).toBe('\'hello \\\'world\\\'\'');
      expect(tokens[1].value).toBe('"foo \\"bar\\""');
    });

    it('tokenizes operators including ~ and not in', () => {
      const code = 'a ~ b not in c and d == 1';
      const tokens = tokenize(code);
      const ops = tokens.filter(t => t.type === 'OPERATOR').map(t => t.value);
      expect(ops).toContain('~');
      expect(ops).toContain('not in');
      expect(ops).toContain('and');
      expect(ops).toContain('==');
    });
  });

  describe('formatExpressionLab', () => {
    it('returns empty string for empty input', () => {
      expect(formatExpressionLab('')).toBe('');
      expect(formatExpressionLab('   ')).toBe('');
      expect(formatExpressionLab(null)).toBe('');
    });

    it('formats empty prog[]', () => {
      expect(formatExpressionLab('prog[]')).toBe('prog[]');
      expect(formatExpressionLab('prog[   ]')).toBe('prog[]');
    });

    it('formats single-line prog into indented multi-line statements', () => {
      const input = 'prog[set[\'x\', 1], set[\'y\', 2], var[\'x\'] + var[\'y\']]';
      const expected =
`prog[
    set['x', 1],
    set['y', 2],
    var['x'] + var['y']
]`;
      expect(formatExpressionLab(input)).toBe(expected);
    });

    it('formats nested prog blocks with incremented indentation', () => {
      const input = 'prog[set[\'result\', prog[set[\'temp\', 10], var[\'temp\'] * 2]], var[\'result\']]';
      const expected =
`prog[
    set['result', prog[
        set['temp', 10],
        var['temp'] * 2
    ]],
    var['result']
]`;
      expect(formatExpressionLab(input)).toBe(expected);
    });

    it('formats binary operators with proper spacing including ~', () => {
      const input = 'var[\'greeting\']~\' world\'~\'!\'';
      expect(formatExpressionLab(input)).toBe('var[\'greeting\'] ~ \' world\' ~ \'!\'');

      const opsInput = 'a+b-c*d/e%f';
      expect(formatExpressionLab(opsInput)).toBe('a + b - c * d / e % f');

      const compInput = 'x==1&&y!=2||z<=3';
      expect(formatExpressionLab(compInput)).toBe('x == 1 && y != 2 || z <= 3');
    });

    it('formats word operators and logical words', () => {
      const input = 'user.name startsWith \'A\' and role in [\'admin\', \'editor\']';
      expect(formatExpressionLab(input)).toBe('user.name startsWith \'A\' and role in [\'admin\', \'editor\']');
    });

    it('formats unary operators correctly', () => {
      expect(formatExpressionLab('!var[\'ready\']')).toBe('!var[\'ready\']');
      expect(formatExpressionLab('not true')).toBe('not true');
      expect(formatExpressionLab('-5 + 10')).toBe('-5 + 10');
    });

    it('formats method chains without spaces around dots', () => {
      const input = 'Users.find(1).posts.filter(fn[[\'p\'], p.status == \'publish\'])';
      expect(formatExpressionLab(input)).toBe('Users.find(1).posts.filter(fn[[\'p\'], p.status == \'publish\'])');
    });

    it('formats pipelines with map, filter, reduce', () => {
      const input = 'prog[set[\'users\', Users.all()], filter[var[\'users\'], fn[[\'u\'], u.is_active == true]]]';
      const expected =
`prog[
    set['users', Users.all()],
    filter[var['users'], fn[['u'], u.is_active == true]]
]`;
      expect(formatExpressionLab(input)).toBe(expected);
    });

    it('preserves and formats block comments', () => {
      const input = '/* Fetch user */\nprog[/* step 1 */ set[\'u\', Users.find(1)], var[\'u\']]';
      const result = formatExpressionLab(input);
      expect(result).toContain('/* Fetch user */');
      expect(result).toContain('/* step 1 */');
      expect(result).toContain('set[\'u\', Users.find(1)]');
    });

    it('formats inline objects cleanly', () => {
      const input = '{id: 1, name: \'John Doe\', active: true}';
      expect(formatExpressionLab(input)).toBe('{ id: 1, name: \'John Doe\', active: true }');
    });

    it('formats multiline objects cleanly with indentation', () => {
      const input = '{\nid: 1,\nname: \'John Doe\'\n}';
      const expected =
`{
    id: 1,
    name: 'John Doe'
}`;
      expect(formatExpressionLab(input)).toBe(expected);
    });

    it('formats range operator .. without spaces', () => {
      expect(formatExpressionLab('0..23')).toBe('0..23');
      expect(formatExpressionLab('set[\'hours\', 0..23]')).toBe('set[\'hours\', 0..23]');
    });

    it('formats ternary colons with spaces around and object colons without space before', () => {
      const input = '{ \'attacks\': (h >= 2) ? 10 : 0 }';
      expect(formatExpressionLab(input)).toBe('{ \'attacks\': (h >= 2) ? 10 : 0 }');
    });

    it('formats statement header block comment on its own line preceding the statement', () => {
      const input = `prog[
    /* Setup */
    set['x', 1]
]`;
      const expected = `prog[
    /* Setup */
    set['x', 1]
]`;
      expect(formatExpressionLab(input)).toBe(expected);
    });

    it('formats show[...] multiline when containing a complex call', () => {
      const input = `show[Graph.heatmap(var['data'], {
    'title': 'Test',
    'x': 'hour'
})]`;
      const result = formatExpressionLab(input);
      expect(result.startsWith('show[\n    Graph.heatmap(')).toBe(true);
      expect(result.endsWith('\n]')).toBe(true);
    });

    it('formats complex security grid snippet cleanly matching desired structure', () => {
      const input = `prog[
  set['days', ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']],
  set['hours', 0..23],

  /* Flatten the days x hours matrix accumulating with reduce and merge */
  set['security_grid', reduce[var['days'], fn[['acc', 'day'],
    merge(args['acc'], map[var['hours'], fn[['h'], {
      'day':     args['day'],
      'hour':    args['h'],
      'attacks': rand(0, 15) + ((args['h'] >= 2 && args['h'] <= 5) ? rand(35, 95) : (args['h'] >= 14 && args['h'] <= 16 ? rand(15, 45) : 0))
    }] ] )
  ], [] ] ],

  show[ Graph.heatmap(var['security_grid'], {
    'title': 'Security Audit: Intrusion Attempts (Day vs Hour)',
    'x': 'hour',
    'y': 'day',
    'value': 'attacks',
    'scheme': Graph.SCHEME_REDS,
    'height': 200
  }) ],

  'Security heatmap generated with ' ~ count(var['security_grid']) ~ ' hourly intervals.'
]`;

      const formatted = formatExpressionLab(input);

      // Verify block comment is on its own line above set
      expect(formatted).toContain('/* Flatten the days x hours matrix accumulating with reduce and merge */\n    set[\'security_grid\',');

      // Verify range operator has no spaces
      expect(formatted).toContain('0..23');

      // Verify ternary colon has spaces around
      expect(formatted).toContain('? rand(35, 95) : (args[\'h\'] >= 14');
      expect(formatted).toContain('? rand(15, 45) : 0');

      // Verify show[ is multiline
      expect(formatted).toContain('show[\n        Graph.heatmap(');

      // Verify preserved blank lines between sections
      expect(formatted).toContain('set[\'hours\', 0..23],\n\n    /* Flatten');
    });

    it('handles syntax errors or unbalanced code gracefully without throwing', () => {
      const broken = 'prog[set[\'x\', 1';
      expect(() => formatExpressionLab(broken)).not.toThrow();
    });
  });
});
