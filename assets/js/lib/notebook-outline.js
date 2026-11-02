/**
 * Utility functions for extracting markdown headings and generating hierarchical
 * outline trees for notebooks in Expression Lab.
 */

/**
 * Strips common markdown formatting tokens from heading text so labels
 * display cleanly in the outline tree.
 *
 * @param {string} text Raw heading text
 * @returns {string} Clean heading text
 */
export function stripMarkdownFormatting(text) {
  if (!text) return '';
  return text
    // Strip bold and italic: **text**, __text__, *text*, _text_
    .replace(/(\*\*|__)(.*?)\1/g, '$2')
    .replace(/(\*|_)(.*?)\1/g, '$2')
    // Strip strikethrough: ~~text~~
    .replace(/~~(.*?)~~/g, '$1')
    // Strip inline code: `code`
    .replace(/`([^`]+)`/g, '$1')
    // Strip markdown images first so leading ! is not left over: ![alt](url) -> alt
    .replace(/!\[([^\]]*)\]\([^)]+\)/g, '$1')
    // Strip markdown links: [label](url) -> label
    .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
    // Strip HTML tags: <span>text</span> -> text
    .replace(/<[^>]+>/g, '')
    // Strip trailing markdown hashes: ## Title ## -> Title
    .replace(/\s+#+$/, '')
    .trim();
}

/**
 * Parses all headings from a markdown cell source string, ignoring
 * any headings inside fenced code blocks.
 *
 * @param {string} source Markdown source text
 * @param {string|number} cellId ID of the notebook cell
 * @returns {Array<{ id: string, cellId: string|number, level: number, label: string }>}
 */
export function parseMarkdownHeadings(source, cellId) {
  if (!source || typeof source !== 'string') return [];

  const lines = source.split('\n');
  const headings = [];
  let inCodeBlock = false;

  for (let lineIdx = 0; lineIdx < lines.length; lineIdx++) {
    const line = lines[lineIdx];

    // Fenced code blocks (``` or ~~~)
    if (/^\s*(```|~~~)/.test(line)) {
      inCodeBlock = !inCodeBlock;
      continue;
    }

    if (inCodeBlock) {
      continue;
    }

    // Match ATX headings: # H1 through ###### H6
    const atxMatch = line.match(/^(#{1,6})\s+(.+)$/);
    if (atxMatch) {
      const level = atxMatch[1].length;
      const rawText = atxMatch[2];
      const cleanLabel = stripMarkdownFormatting(rawText);

      if (cleanLabel) {
        headings.push({
          id: `heading_${cellId}_${lineIdx}`,
          cellId,
          lineIndex: lineIdx,
          level,
          label: cleanLabel,
        });
      }
      continue;
    }

    // Match HTML headings: <h1>Title</h1> through <h6>Title</h6>
    const htmlMatch = line.match(/^<h([1-6])[^>]*>(.*?)<\/h\1>/i);
    if (htmlMatch) {
      const level = parseInt(htmlMatch[1], 10);
      const cleanLabel = stripMarkdownFormatting(htmlMatch[2]);

      if (cleanLabel) {
        headings.push({
          id: `heading_${cellId}_${lineIdx}`,
          cellId,
          lineIndex: lineIdx,
          level,
          label: cleanLabel,
        });
      }
    }
  }

  return headings;
}

/**
 * Builds a hierarchical tree from a flat list of headings based on their levels.
 * Preserves the order in which headings appear in the notebook.
 *
 * @param {Array<{ id: string, cellId: string|number, level: number, label: string }>} headings
 * @returns {Array<object>} Hierarchical tree with nested .children
 */
export function buildHeadingTree(headings) {
  if (!Array.isArray(headings) || headings.length === 0) {
    return [];
  }

  const root = [];
  const stack = []; // Elements: { level: number, node: object }

  for (let i = 0; i < headings.length; i++) {
    const heading = headings[i];
    const node = {
      ...heading,
      children: [],
      expanded: true,
    };

    // Pop any items that have level >= current heading level
    while (stack.length > 0 && stack[stack.length - 1].level >= heading.level) {
      stack.pop();
    }

    if (stack.length === 0) {
      root.push(node);
    } else {
      stack[stack.length - 1].node.children.push(node);
    }

    stack.push({ level: heading.level, node });
  }

  return root;
}

/**
 * Extracts and builds the hierarchical outline tree from all notebook cells.
 * Only markdown cells are scanned for headings.
 *
 * @param {Array<object>} cells Array of notebook cells
 * @returns {Array<object>} Hierarchical tree of headings
 */
export function extractNotebookOutline(cells) {
  if (!Array.isArray(cells) || cells.length === 0) {
    return [];
  }

  const flatHeadings = [];

  for (let i = 0; i < cells.length; i++) {
    const cell = cells[i];
    if (cell && cell.type === 'markdown') {
      const cellHeadings = parseMarkdownHeadings(cell.source || '', cell.id);
      flatHeadings.push(...cellHeadings);
    }
  }

  return buildHeadingTree(flatHeadings);
}

/**
 * Performs a fast, smooth animated scroll to the target cell or element.
 *
 * @param {HTMLElement} targetEl Target element to scroll to
 * @param {object} [options]
 * @param {number} [options.duration=180] Scroll duration in milliseconds (fast & smooth)
 * @param {number} [options.offset=15] Top offset buffer in pixels
 */
export function smoothFastScrollTo(targetEl, options = {}) {
  if (!targetEl) return;

  const duration = options.duration ?? 180;
  const offset = options.offset ?? 15;

  const container =
    (typeof targetEl.closest === 'function' ? targetEl.closest('.notebook-cells-scroll') : null) ||
    (typeof document !== 'undefined' ? document.querySelector('.notebook-cells-scroll') : null);

  if (!container || typeof window === 'undefined' || typeof window.requestAnimationFrame !== 'function') {
    if (typeof targetEl.scrollIntoView === 'function') {
      targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    return;
  }

  const containerRect = container.getBoundingClientRect();
  const targetRect = targetEl.getBoundingClientRect();
  const targetTop = container.scrollTop + (targetRect.top - containerRect.top) - offset;
  const startTop = container.scrollTop;
  const distance = targetTop - startTop;

  if (Math.abs(distance) < 2) return;

  let startTime = null;

  function step(timestamp) {
    if (!startTime) startTime = timestamp;
    const elapsed = timestamp - startTime;
    const progress = Math.min(elapsed / duration, 1);
    // Ease-out cubic for snappy, natural motion
    const ease = 1 - Math.pow(1 - progress, 3);
    container.scrollTop = startTop + distance * ease;

    if (progress < 1) {
      window.requestAnimationFrame(step);
    }
  }

  window.requestAnimationFrame(step);
}

/**
 * Briefly highlights a cell element with a subtle flash effect to visually confirm navigation.
 *
 * @param {HTMLElement} cellEl
 */
export function highlightCell(cellEl) {
  if (!cellEl || typeof cellEl.classList?.add !== 'function') return;

  cellEl.classList.remove('outline-cell-targeted');
  // Trigger DOM reflow to allow consecutive animations
  void cellEl.offsetWidth;
  cellEl.classList.add('outline-cell-targeted');

  setTimeout(() => {
    if (typeof cellEl.classList?.remove === 'function') {
      cellEl.classList.remove('outline-cell-targeted');
    }
  }, 1000);
}
