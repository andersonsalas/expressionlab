import { buildDocNode } from '../../lib/doc-tooltip-builder.js';

describe('doc-tooltip-builder.js', () => {
  it('returns null for null, undefined, or non-object input', () => {
    expect(buildDocNode(null)).toBeNull();
    expect(buildDocNode(undefined)).toBeNull();
    expect(buildDocNode('not an object')).toBeNull();
  });

  it('builds a doc tooltip with summary and syntax-highlighted signature', () => {
    const doc = {
      summary: 'Executes a database query',
      signature: 'Database.query(string:sql, [array:params = []]) => QueryResult',
    };

    const node = buildDocNode(doc);
    expect(node).not.toBeNull();
    expect(node.className).toContain('doc-tooltip');
    expect(node.dataset.elFormatted).toBe('true');

    const summary = node.querySelector('.cm-docblock.summary');
    expect(summary).not.toBeNull();
    expect(summary.textContent).toBe('Executes a database query');

    const signature = node.querySelector('pre.cm-docblock.signature');
    expect(signature).not.toBeNull();
    expect(signature.classList.contains('hljs')).toBe(true);
    expect(signature.innerHTML).toContain('Database');
    expect(signature.innerHTML).toContain('class="hljs-type"');
    expect(signature.innerHTML).toContain('string');
    expect(signature.innerHTML).toContain('class="hljs-variable"');
    expect(signature.innerHTML).toContain('sql');
    expect(signature.innerHTML).toContain('QueryResult');
  });

  it('renders markdown description cleanly and securely', () => {
    const doc = {
      description: 'Supports **bold**, *italic*, and `code` formatting.',
    };

    const node = buildDocNode(doc);
    const descWrapper = node.querySelector('.cm-docblock._description');
    expect(descWrapper).not.toBeNull();
    expect(descWrapper.innerHTML).toContain('<strong>bold</strong>');
    expect(descWrapper.innerHTML).toContain('<em>italic</em>');
    expect(descWrapper.innerHTML).toContain('<code>code</code>');
  });

  it('highlights code blocks inside markdown descriptions with elscript by default', () => {
    const doc = {
      description: 'Example:\n\n```\nDatabase.mirror(\'posts\', { \'status\': \'publish\' })\n```',
    };

    const node = buildDocNode(doc);
    const codeBlock = node.querySelector('.cm-docblock._description pre code');
    expect(codeBlock).not.toBeNull();
    expect(codeBlock.className).toContain('language-elscript');
    expect(codeBlock.innerHTML).toContain('class="hljs-built_in"');
    expect(codeBlock.innerHTML).toContain('Database');
    expect(codeBlock.innerHTML).toContain('class="hljs-string"');
  });

  it('highlights code blocks with explicit language tags (sql, php, js)', () => {
    const doc = {
      description: 'SQL Query:\n\n```sql\nSELECT * FROM wp_posts WHERE post_status = \'publish\'\n```\n\nPHP:\n\n```php\nfunction test() { return 123; }\n```',
    };

    const node = buildDocNode(doc);
    const codeBlocks = node.querySelectorAll('.cm-docblock._description pre code');
    expect(codeBlocks.length).toBe(2);

    // SQL block
    expect(codeBlocks[0].className).toContain('language-sql');
    expect(codeBlocks[0].innerHTML).toContain('class="hljs-keyword"');
    expect(codeBlocks[0].innerHTML).toContain('SELECT');

    // PHP block
    expect(codeBlocks[1].className).toContain('language-php');
    expect(codeBlocks[1].innerHTML).toContain('class="hljs-keyword"');
    expect(codeBlocks[1].innerHTML).toContain('function');
  });

  it('renders parameters with structured section, codicons, types, names and descriptions without dollar prefix', () => {
    const doc = {
      parameters: [
        { name: 'sql', type: 'string', description: 'The SQL statement to execute', required: true },
        { name: 'bindings', type: 'Array', description: 'Query parameters', required: false },
      ],
    };

    const node = buildDocNode(doc);
    const paramSection = node.querySelector('.cm-docblock.doc-section.doc-parameters');
    expect(paramSection).not.toBeNull();

    const title = paramSection.querySelector('.cm-docblock.doc-section-title');
    expect(title).not.toBeNull();
    expect(title.textContent).toBe('Parameters:');

    const items = paramSection.querySelectorAll('.cm-docblock.doc-list li');
    expect(items.length).toBe(2);

    expect(items[0].querySelector('.codicon-symbol-field.param-required')).not.toBeNull();
    expect(items[0].querySelector('.cm-docblock.variable').textContent).toBe('sql');
    expect(items[0].querySelector('.cm-docblock.type').textContent).toBe('(string)');
    expect(items[0].querySelector('.cm-docblock.doc-desc').textContent).toBe('The SQL statement to execute');

    expect(items[1].querySelector('.codicon-symbol-field.param-optional')).not.toBeNull();
    expect(items[1].querySelector('.cm-docblock.variable').textContent).toBe('bindings');
    expect(items[1].querySelector('.cm-docblock.type').textContent).toBe('(Array)');
    expect(items[1].querySelector('.cm-docblock.doc-desc').textContent).toBe('Query parameters');
  });

  it('renders return information with structured section, type and description', () => {
    const doc = {
      return: {
        type: 'QueryResult|false',
        description: 'Result set or false on error',
      },
    };

    const node = buildDocNode(doc);
    const returnSection = node.querySelector('.cm-docblock.doc-section.doc-returns');
    expect(returnSection).not.toBeNull();

    const title = returnSection.querySelector('.cm-docblock.doc-section-title');
    expect(title).not.toBeNull();
    expect(title.textContent).toBe('Returns:');

    expect(returnSection.querySelector('.cm-docblock.type').textContent).toBe('QueryResult|false');
    expect(returnSection.querySelector('.cm-docblock.doc-desc').textContent).toBe('Result set or false on error');
  });

  it('renders parameters and return descriptions with inline markdown and code tags', () => {
    const doc = {
      parameters: [
        {
          name: 'format',
          type: 'string',
          description: 'Serialization format: `Options.FORMAT_JSON` or `Options.FORMAT_SERIALIZED`',
          required: true,
        },
      ],
      return: {
        type: 'mixed',
        description: 'The processed option value, or `default_value` if not found.',
      },
    };

    const node = buildDocNode(doc);
    const paramDesc = node.querySelector('.cm-docblock.doc-parameters .cm-docblock.doc-desc');
    expect(paramDesc).not.toBeNull();
    expect(paramDesc.querySelectorAll('code').length).toBe(2);
    expect(paramDesc.innerHTML).toContain('<code>Options.FORMAT_JSON</code>');
    expect(paramDesc.innerHTML).toContain('<code>Options.FORMAT_SERIALIZED</code>');

    const returnDesc = node.querySelector('.cm-docblock.doc-returns .cm-docblock.doc-desc');
    expect(returnDesc).not.toBeNull();
    expect(returnDesc.querySelector('code')).not.toBeNull();
    expect(returnDesc.innerHTML).toContain('<code>default_value</code>');
  });

  it('renders see references with structured section, clickable URLs and refs', () => {
    const doc = {
      see: [
        'https://developer.wordpress.org/reference/',
        'Users.find()',
      ],
    };

    const node = buildDocNode(doc);
    const seeSection = node.querySelector('.cm-docblock.doc-section.doc-see');
    expect(seeSection).not.toBeNull();

    const title = seeSection.querySelector('.cm-docblock.doc-section-title');
    expect(title).not.toBeNull();
    expect(title.textContent).toBe('See also:');

    const link = seeSection.querySelector('a.cm-docblock.doc-link');
    expect(link).not.toBeNull();
    expect(link.querySelector('.codicon-link')).not.toBeNull();
    expect(link.href).toBe('https://developer.wordpress.org/reference/');
    expect(link.target).toBe('_blank');
    expect(link.rel).toContain('noopener');

    const refSpan = seeSection.querySelector('.cm-docblock.doc-ref');
    expect(refSpan).not.toBeNull();
    expect(refSpan.textContent).toBe('Users.find()');

    const origOpen = window.open;
    window.open = jest.fn();

    link.click();
    expect(window.open).toHaveBeenCalledWith('https://developer.wordpress.org/reference/', '_blank', 'noopener,noreferrer');

    window.open = origOpen;
  });

  it('delegates clicks on markdown links in description to handleOpenUrl', () => {
    const origOpen = window.open;
    window.open = jest.fn();

    const doc = {
      description: 'See [Documentation](https://example.com/guide) for details.',
    };

    const node = buildDocNode(doc);
    const link = node.querySelector('a[href="https://example.com/guide"]');
    expect(link).not.toBeNull();

    link.click();
    expect(window.open).toHaveBeenCalledWith('https://example.com/guide', '_blank', 'noopener,noreferrer');

    window.open = origOpen;
  });

  it('renders constant/property types and default values with structured sections', () => {
    const doc = {
      type: 'string',
      valueStr: '8.2.0',
      default: 'utf8mb4',
    };

    const node = buildDocNode(doc);
    const tags = node.querySelector('.cm-docblock.tags');
    expect(tags).not.toBeNull();
    expect(tags.textContent).toContain('Type: string');
    expect(tags.textContent).toContain('Value: 8.2.0');
    expect(tags.textContent).toContain('Default: utf8mb4');

    expect(node.querySelector('.cm-docblock.doc-value code')).not.toBeNull();
    expect(node.querySelector('.cm-docblock.doc-value code').textContent).toBe('8.2.0');
    expect(node.querySelector('.cm-docblock.doc-default code')).not.toBeNull();
    expect(node.querySelector('.cm-docblock.doc-default code').textContent).toBe('utf8mb4');
  });

  it('renders copy and insert buttons on default elscript code blocks', () => {
    const doc = {
      description: 'Example:\n\n```\nDatabase.mirror(\'posts\').fetch()\n```',
    };

    const node = buildDocNode(doc);
    const wrapper = node.querySelector('.el-code-block-wrapper');
    expect(wrapper).not.toBeNull();

    const copyBtn = wrapper.querySelector('.el-code-copy-btn');
    expect(copyBtn).not.toBeNull();
    expect(copyBtn.getAttribute('data-action')).toBe('copy');
    expect(decodeURIComponent(copyBtn.getAttribute('data-code'))).toBe('Database.mirror(\'posts\').fetch()');

    const insertBtn = wrapper.querySelector('.el-code-insert-btn');
    expect(insertBtn).not.toBeNull();
    expect(insertBtn.getAttribute('data-action')).toBe('insert');
    expect(decodeURIComponent(insertBtn.getAttribute('data-code'))).toBe('Database.mirror(\'posts\').fetch()');
  });

  it('renders copy button but omits insert button on non-elscript code blocks', () => {
    const doc = {
      description: 'SQL query:\n\n```sql\nSELECT * FROM wp_posts\n```',
    };

    const node = buildDocNode(doc);
    const wrapper = node.querySelector('.el-code-block-wrapper');
    expect(wrapper).not.toBeNull();

    const copyBtn = wrapper.querySelector('.el-code-copy-btn');
    expect(copyBtn).not.toBeNull();

    const insertBtn = wrapper.querySelector('.el-code-insert-btn');
    expect(insertBtn).toBeNull();
  });
});
