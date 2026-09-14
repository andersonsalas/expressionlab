import { renderSafeInlineMarkdown, sanitizeHtml } from '../../lib/helpers.js';

describe('renderSafeInlineMarkdown', () => {
  it('returns empty string for empty or nullish inputs', () => {
    expect(renderSafeInlineMarkdown('')).toBe('');
    expect(renderSafeInlineMarkdown(null)).toBe('');
    expect(renderSafeInlineMarkdown(undefined)).toBe('');
  });

  it('renders plain text safely without markdown', () => {
    const input = 'Syntax error: Unknown function foo()';
    expect(renderSafeInlineMarkdown(input)).toBe('Syntax error: Unknown function foo()');
  });

  it('correctly renders inline code with backticks and preserves raw < and > characters', () => {
    const input = 'Syntax error: Unexpected character = around position 50 for expression `prog( set(limite, 5), loop( (i) => { i < var.limite } ) )`.';
    const result = renderSafeInlineMarkdown(input);
    expect(result).toContain('<code>prog( set(limite, 5), loop( (i) =&gt; { i &lt; var.limite } ) )</code>');
    expect(result).not.toContain('&amp;gt;');
  });

  it('preserves quotes and unescaped HTML entities cleanly', () => {
    const input = 'Variable "foo" and \'bar\' in `test`';
    const result = renderSafeInlineMarkdown(input);
    expect(result).toBe('Variable "foo" and \'bar\' in <code>test</code>');
  });

  it('correctly formats bold and italic markdown', () => {
    const input = 'Notice: **Important** and *italic* note with `code`.';
    const result = renderSafeInlineMarkdown(input);
    expect(result).toBe('Notice: <strong>Important</strong> and <em>italic</em> note with <code>code</code>.');
  });

  it('sanitizes XSS attack vectors preventing tag execution', () => {
    const maliciousInput = '<script>alert(1)</script><img src=x onerror=alert(2)>`<div>safe code</div>`';
    const result = renderSafeInlineMarkdown(maliciousInput);
    expect(result).not.toContain('<script>');
    expect(result).not.toContain('<img');
    expect(result).not.toContain('onerror');
    expect(result).toContain('<code>&lt;div&gt;safe code&lt;/div&gt;</code>');
  });

  it('adds multiline-code class and preserves line breaks when code contains newlines', () => {
    const input = 'Syntax error: Unexpected character "=" around position 50 for expression `prog(\n  set(\'limite\', 5),\n  loop(\n    (i) => { i < var.limite }\n  )\n)`.';
    const result = renderSafeInlineMarkdown(input);
    expect(result).toContain('<code class="multiline-code">');
    expect(result).toContain('prog(\n  set(\'limite\', 5),\n  loop(\n    (i) =&gt; { i &lt; var.limite }\n  )\n)');
  });

  it('correctly handles escaped backticks in error messages and code spans with backticks', () => {
    const input = 'Syntax error: Unexpected character "\\`" around position 17 for expression `` NetworkSites.get(`).list_users() ``.';
    const result = renderSafeInlineMarkdown(input);
    expect(result).toBe('Syntax error: Unexpected character "`" around position 17 for expression <code>NetworkSites.get(`).list_users()</code>.');
  });

  it('correctly renders syntax errors with suggestions into code spans', () => {
    const input = 'Syntax error: Variable "n" is not valid around position 4 for expression `[2+n]`. Did you mean "fn"?';
    const result = renderSafeInlineMarkdown(input);
    expect(result).toBe('Syntax error: Variable "n" is not valid around position 4 for expression <code>[2+n]</code>. Did you mean "fn"?');
  });

  it('removes orphaned leading dot before suggestions following multiline code blocks', () => {
    const input = 'Syntax error: Variable "n" is not valid around position 33 for expression `prog(\n  set(\'cubo\', fn([\'n\'], n ** 3)),\n  var[\'cubo\'](3)\n)`. Did you mean "fn"?';
    const result = renderSafeInlineMarkdown(input);
    expect(result).toContain('<code class="multiline-code">');
    expect(result).toContain('</code> Did you mean "fn"?');
    expect(result).not.toContain('</code>. Did you mean');
  });
});

describe('sanitizeHtml', () => {
  it('preserves headings h1-h6 from markdown rendering', () => {
    const input = '<h3>Arrays (square brackets) represent an OR group:</h3><p>This expression:</p>';
    const result = sanitizeHtml(input);
    expect(result).toBe('<h3>Arrays (square brackets) represent an OR group:</h3><p>This expression:</p>');
  });

  it('preserves pre, code, list, and table tags', () => {
    const input = '<pre><code>SELECT * FROM wp_posts</code></pre><ul><li>Item 1</li></ul>';
    const result = sanitizeHtml(input);
    expect(result).toBe('<pre><code>SELECT * FROM wp_posts</code></pre><ul><li>Item 1</li></ul>');
  });

  it('sanitizes script tags and malicious attributes', () => {
    const input = '<h3>Title</h3><script>alert(1)</script><img src=x onerror=alert(1)><p onclick="bad()">Text</p>';
    const result = sanitizeHtml(input);
    expect(result).toBe('<h3>Title</h3><p>Text</p>');
  });
});
