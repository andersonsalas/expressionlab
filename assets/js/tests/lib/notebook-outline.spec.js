import {
  stripMarkdownFormatting,
  parseMarkdownHeadings,
  buildHeadingTree,
  extractNotebookOutline,
  smoothFastScrollTo,
  highlightCell,
} from '../../lib/notebook-outline.js';

describe('notebook-outline.js', () => {
  describe('stripMarkdownFormatting', () => {
    it('handles empty or null values', () => {
      expect(stripMarkdownFormatting('')).toBe('');
      expect(stripMarkdownFormatting(null)).toBe('');
      expect(stripMarkdownFormatting(undefined)).toBe('');
    });

    it('strips bold and italic syntax', () => {
      expect(stripMarkdownFormatting('**Bold** and *Italic*')).toBe('Bold and Italic');
      expect(stripMarkdownFormatting('__Bold__ and _Italic_')).toBe('Bold and Italic');
    });

    it('strips inline code and strikethrough', () => {
      expect(stripMarkdownFormatting('`inline code` and ~~deleted~~')).toBe('inline code and deleted');
    });

    it('strips markdown links and images', () => {
      expect(stripMarkdownFormatting('[My Link](https://example.com)')).toBe('My Link');
      expect(stripMarkdownFormatting('![Alt Text](elab-blob://12345)')).toBe('Alt Text');
    });

    it('strips html tags and trailing hashes', () => {
      expect(stripMarkdownFormatting('<span>Header</span> ##')).toBe('Header');
    });
  });

  describe('parseMarkdownHeadings', () => {
    it('parses ATX headings (h1 through h6)', () => {
      const source = `
# Level 1
Some regular text
## Level 2 **bold**
### Level 3
#### Level 4
##### Level 5
###### Level 6
`;
      const headings = parseMarkdownHeadings(source, 'cell-1');
      expect(headings).toHaveLength(6);
      expect(headings[0]).toEqual({
        id: 'heading_cell-1_1',
        cellId: 'cell-1',
        lineIndex: 1,
        level: 1,
        label: 'Level 1',
      });
      expect(headings[1].label).toBe('Level 2 bold');
      expect(headings[1].level).toBe(2);
      expect(headings[5].level).toBe(6);
    });

    it('ignores headings inside fenced code blocks', () => {
      const source = `
# Real Heading
\`\`\`bash
# Not a heading (bash comment)
## Also not a heading
\`\`\`
~~~python
# Python comment
~~~
## Another Real Heading
`;
      const headings = parseMarkdownHeadings(source, 'cell-2');
      expect(headings).toHaveLength(2);
      expect(headings[0].label).toBe('Real Heading');
      expect(headings[1].label).toBe('Another Real Heading');
    });

    it('parses HTML headings', () => {
      const source = '<h2>HTML Title</h2>';
      const headings = parseMarkdownHeadings(source, 'cell-3');
      expect(headings).toHaveLength(1);
      expect(headings[0].level).toBe(2);
      expect(headings[0].label).toBe('HTML Title');
    });
  });

  describe('buildHeadingTree', () => {
    it('returns empty array when no headings provided', () => {
      expect(buildHeadingTree([])).toEqual([]);
      expect(buildHeadingTree(null)).toEqual([]);
    });

    it('builds a nested tree preserving document order', () => {
      const flat = [
        { id: '1', level: 1, label: 'Section 1' },
        { id: '2', level: 2, label: 'Subsection 1.1' },
        { id: '3', level: 3, label: 'Detail 1.1.1' },
        { id: '4', level: 2, label: 'Subsection 1.2' },
        { id: '5', level: 1, label: 'Section 2' },
      ];

      const tree = buildHeadingTree(flat);

      expect(tree).toHaveLength(2);
      expect(tree[0].label).toBe('Section 1');
      expect(tree[0].children).toHaveLength(2);
      expect(tree[0].children[0].label).toBe('Subsection 1.1');
      expect(tree[0].children[0].children).toHaveLength(1);
      expect(tree[0].children[0].children[0].label).toBe('Detail 1.1.1');
      expect(tree[0].children[1].label).toBe('Subsection 1.2');

      expect(tree[1].label).toBe('Section 2');
      expect(tree[1].children).toHaveLength(0);
    });

    it('handles documents starting with higher heading levels without h1', () => {
      const flat = [
        { id: '1', level: 2, label: 'Start at H2' },
        { id: '2', level: 3, label: 'Sub at H3' },
      ];

      const tree = buildHeadingTree(flat);
      expect(tree).toHaveLength(1);
      expect(tree[0].label).toBe('Start at H2');
      expect(tree[0].children[0].label).toBe('Sub at H3');
    });
  });

  describe('extractNotebookOutline', () => {
    it('extracts headings only from markdown cells in cell order', () => {
      const cells = [
        {
          id: 'c1',
          type: 'markdown',
          source: '# Introduction\nWelcome to notebook',
        },
        {
          id: 'c2',
          type: 'code',
          source: '# PHP comment\nreturn 42;',
        },
        {
          id: 'c3',
          type: 'markdown',
          source: '## Architecture\n### Components\n# Next Chapter',
        },
      ];

      const tree = extractNotebookOutline(cells);

      expect(tree).toHaveLength(2);
      expect(tree[0].label).toBe('Introduction');
      expect(tree[0].cellId).toBe('c1');
      expect(tree[0].children).toHaveLength(1);
      expect(tree[0].children[0].label).toBe('Architecture');
      expect(tree[0].children[0].cellId).toBe('c3');
      expect(tree[0].children[0].children[0].label).toBe('Components');

      expect(tree[1].label).toBe('Next Chapter');
      expect(tree[1].cellId).toBe('c3');
    });
  });

  describe('smoothFastScrollTo and highlightCell', () => {
    it('calls scrollIntoView when container is not present', () => {
      const el = document.createElement('div');
      el.scrollIntoView = jest.fn();

      smoothFastScrollTo(el);

      expect(el.scrollIntoView).toHaveBeenCalledWith({
        behavior: 'smooth',
        block: 'start',
      });
    });

    it('adds and removes highlight class', () => {
      jest.useFakeTimers();
      const el = document.createElement('div');

      highlightCell(el);
      expect(el.classList.contains('outline-cell-targeted')).toBe(true);

      jest.advanceTimersByTime(1100);
      expect(el.classList.contains('outline-cell-targeted')).toBe(false);
      jest.useRealTimers();
    });
  });
});
