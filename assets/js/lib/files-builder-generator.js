/**
 * Expression Lab - Files Builder Generator.
 *
 * Provides pure serialization functions, path normalization, and Elscript
 * code generation for the `Files` service operations.
 *
 * @package ExpressionLab
 */

export const FILES_MAX_READ_BYTES = 262144; // 256 KB
export const FILES_MAX_READ_KB = 256;
export const FILES_DEFAULT_READ_KB = 256;
export const FILES_MIN_READ_KB = 1;
export const FILES_MAX_TAIL_BYTES = 1048576; // 1 MB
export const DEFAULT_TAIL_LINES = 50;
export const MIN_TAIL_LINES = 1;
export const MAX_TAIL_LINES = 500;

export const PERMS_PROJECTIONS = Object.freeze([
  'octal',
  'symbolic',
  'owner',
  'group',
  'readable',
  'writable',
  'executable',
]);

export const STATS_PROJECTIONS = Object.freeze(['total_size', 'files', 'directories', 'total_bytes']);

export const FILES_OPERATIONS = Object.freeze({
  READ: 'read',
  TAIL: 'tail',
  SIZE: 'size',
  MODIFIED: 'modified',
  PERMS: 'perms',
  LIST: 'list',
  STATS: 'stats',
  EXISTS: 'exists',
  IS_FILE: 'is_file',
  IS_DIR: 'is_dir',
});

export const FILES_OPERATION_CONFIGS = Object.freeze({
  [FILES_OPERATIONS.TAIL]: {
    id: FILES_OPERATIONS.TAIL,
    label: 'Files.tail()',
    method: 'tail',
    appliesTo: 'file',
    icon: 'codicon-output',
    shortDesc: 'Read the last lines of a file.',
    defaultParams: {
      lines: DEFAULT_TAIL_LINES,
    },
  },
  [FILES_OPERATIONS.READ]: {
    id: FILES_OPERATIONS.READ,
    label: 'Files.read()',
    method: 'read',
    appliesTo: 'file',
    icon: 'codicon-file-text',
    shortDesc: 'Read content of a text file up to 256 KB.',
    defaultParams: {
      max_bytes: FILES_MAX_READ_BYTES,
      read_kb: FILES_MAX_READ_KB,
    },
  },
  [FILES_OPERATIONS.SIZE]: {
    id: FILES_OPERATIONS.SIZE,
    label: 'Files.size()',
    method: 'size',
    appliesTo: 'both',
    icon: 'codicon-dashboard',
    shortDesc: 'Retrieve file size.',
    defaultParams: {
      property: 'all', // 'all', 'human', 'bytes'
    },
  },
  [FILES_OPERATIONS.MODIFIED]: {
    id: FILES_OPERATIONS.MODIFIED,
    label: 'Files.modified()',
    method: 'modified',
    appliesTo: 'both',
    icon: 'codicon-history',
    shortDesc: 'Get file modification timestamp.',
    defaultParams: {
      property: 'all', // 'all', 'formatted', 'timestamp'
    },
  },
  [FILES_OPERATIONS.PERMS]: {
    id: FILES_OPERATIONS.PERMS,
    label: 'Files.perms()',
    method: 'perms',
    appliesTo: 'both',
    icon: 'codicon-shield',
    shortDesc: 'Retrieve Unix permissions and ownership.',
    defaultParams: {
      property: 'all', // 'all', 'octal', 'symbolic', 'owner', 'group', 'readable', 'writable', 'executable'
    },
  },
  [FILES_OPERATIONS.LIST]: {
    id: FILES_OPERATIONS.LIST,
    label: 'Files.list()',
    method: 'list',
    appliesTo: 'dir',
    icon: 'codicon-list-flat',
    shortDesc: 'List directory contents.',
    defaultParams: {},
  },
  [FILES_OPERATIONS.STATS]: {
    id: FILES_OPERATIONS.STATS,
    label: 'Files.stats()',
    method: 'stats',
    appliesTo: 'dir',
    icon: 'codicon-pie-chart',
    shortDesc: 'Compute disk space distribution.',
    defaultParams: {
      property: 'all', // 'all', 'files', 'total_size', 'total_bytes', 'directories'
    },
  },
  [FILES_OPERATIONS.EXISTS]: {
    id: FILES_OPERATIONS.EXISTS,
    label: 'Files.exists()',
    method: 'exists',
    appliesTo: 'both',
    icon: 'codicon-pass',
    shortDesc: 'Check if file or directory exists.',
    defaultParams: {},
  },
  [FILES_OPERATIONS.IS_FILE]: {
    id: FILES_OPERATIONS.IS_FILE,
    label: 'Files.is_file()',
    method: 'is_file',
    appliesTo: 'both',
    icon: 'codicon-file',
    shortDesc: 'Check if path is a regular file.',
    defaultParams: {},
  },
  [FILES_OPERATIONS.IS_DIR]: {
    id: FILES_OPERATIONS.IS_DIR,
    label: 'Files.is_dir()',
    method: 'is_dir',
    appliesTo: 'both',
    icon: 'codicon-folder',
    shortDesc: 'Check if path is a directory.',
    defaultParams: {},
  },
});

/**
 * Normalizes and sanitizes a relative filesystem path.
 *
 * Strips leading slashes, converts backslashes, collapses consecutive slashes,
 * removes directory traversal segments (../), and trims whitespace.
 *
 * @param {string} rawPath
 * @returns {string}
 */
export function sanitizeRelativePath(rawPath) {
  if (typeof rawPath !== 'string') {
    return '';
  }

  let cleaned = rawPath.trim();
  if (!cleaned) {
    return '';
  }

  // Convert Windows backslashes to forward slashes.
  cleaned = cleaned.replace(/\\/g, '/');

  // Strip leading drive letters (e.g., C:/).
  cleaned = cleaned.replace(/^[a-zA-Z]:\/?/, '');

  // Strip leading slashes.
  cleaned = cleaned.replace(/^\/+/, '');

  // Collapse consecutive slashes.
  cleaned = cleaned.replace(/\/+/g, '/');

  // Remove directory traversal segments (../ and ./)
  const parts = cleaned.split('/');
  const safeParts = [];

  for (const part of parts) {
    if (part === '.' || part === '..' || part === '') {
      continue;
    }
    safeParts.push(part);
  }

  return safeParts.join('/');
}

/**
 * Escapes single quotes and backslashes for Elscript string literals.
 *
 * @param {string} str
 * @returns {string}
 */
export function escapeElscriptString(str) {
  if (typeof str !== 'string') return '';
  return str.replace(/\\/g, '\\\\').replace(/'/g, '\\\'');
}

/**
 * Checks whether an operation is applicable to the target type.
 *
 * @param {string} operation Operation key.
 * @param {'file'|'dir'} targetType Target type.
 * @returns {boolean}
 */
export function isOperationApplicable(operation, targetType) {
  const config = FILES_OPERATION_CONFIGS[operation];
  if (!config) return false;
  if (config.appliesTo === 'both') return true;
  return config.appliesTo === targetType;
}

/**
 * Generates an Elscript expression for a `Files.*` invocation.
 *
 * @param {Object} options Configuration object.
 * @param {string} options.operation Operation name (e.g. 'read', 'tail', 'list', 'stats').
 * @param {string} options.path Relative target path (or '.' for root directory operations).
 * @param {number} [options.tailLines] Number of lines for tail() (1 to 500).
 * @param {number} [options.readBytes] Maximum bytes for read() (1 to 262144).
 * @param {number} [options.readKb] Maximum kilobytes for read() (1 to 256).
 * @param {string} [options.sizeProperty] Projection for size() ('all', 'human', 'bytes').
 * @param {string} [options.modifiedProperty] Projection for modified() ('all', 'formatted', 'timestamp').
 * @param {string} [options.statsProperty] Projection for stats() ('all', 'files', 'total_size', 'total_bytes', 'directories').
 * @returns {string} Formatted Elscript expression or empty string if invalid.
 */
export function generateFilesExpression(options = {}) {
  if (!options || typeof options !== 'object') {
    return '';
  }

  const { operation } = options;
  if (!operation || !FILES_OPERATION_CONFIGS[operation]) {
    return '';
  }

  const rawPath = options.path ?? '';
  const sanitized = sanitizeRelativePath(rawPath);

  // Directory listing / stats can default to root if empty or '.'
  const isDirOp = operation === FILES_OPERATIONS.LIST || operation === FILES_OPERATIONS.STATS;
  const isRoot = !sanitized;

  if (!isDirOp && !sanitized) {
    return '';
  }

  const escapedPath = escapeElscriptString(sanitized);

  switch (operation) {
    case FILES_OPERATIONS.TAIL: {
      let lines = options.tailLines;
      if (lines === undefined || lines === null || lines === '' || isNaN(Number(lines))) {
        lines = DEFAULT_TAIL_LINES;
      } else {
        lines = Math.min(Math.max(MIN_TAIL_LINES, Math.round(Number(lines))), MAX_TAIL_LINES);
      }

      if (lines === DEFAULT_TAIL_LINES) {
        return `Files.tail('${escapedPath}')`;
      }
      return `Files.tail('${escapedPath}', ${lines})`;
    }

    case FILES_OPERATIONS.READ: {
      let bytes = options.readBytes;
      if (options.readKb !== undefined && options.readKb !== null && options.readKb !== '') {
        const kb = Number(options.readKb);
        if (!isNaN(kb)) {
          bytes = Math.round(kb * 1024);
        }
      }
      if (bytes !== undefined && bytes !== null && bytes !== '' && !isNaN(Number(bytes))) {
        bytes = Math.min(Math.max(1, Math.round(Number(bytes))), FILES_MAX_READ_BYTES);
        if (bytes !== FILES_MAX_READ_BYTES) {
          return `Files.read('${escapedPath}', ${bytes})`;
        }
      }
      return `Files.read('${escapedPath}')`;
    }


    case FILES_OPERATIONS.SIZE: {
      const base = `Files.size('${escapedPath}')`;
      const prop = options.sizeProperty;
      if (prop === 'human' || prop === 'bytes') {
        return `${base}['${prop}']`;
      }
      return base;
    }

    case FILES_OPERATIONS.MODIFIED: {
      const base = `Files.modified('${escapedPath}')`;
      const prop = options.modifiedProperty;
      if (prop === 'formatted' || prop === 'timestamp') {
        return `${base}['${prop}']`;
      }
      return base;
    }

    case FILES_OPERATIONS.PERMS: {
      const base = `Files.perms('${escapedPath}')`;
      const prop = options.permsProperty;
      if (PERMS_PROJECTIONS.includes(prop)) {
        return `${base}['${prop}']`;
      }
      return base;
    }

    case FILES_OPERATIONS.LIST: {
      if (isRoot) {
        return 'Files.list()';
      }
      return `Files.list('${escapedPath}')`;
    }

    case FILES_OPERATIONS.STATS: {
      const base = isRoot ? 'Files.stats()' : `Files.stats('${escapedPath}')`;
      const prop = options.statsProperty;
      if (STATS_PROJECTIONS.includes(prop)) {
        return `${base}['${prop}']`;
      }
      return base;
    }

    case FILES_OPERATIONS.EXISTS:
      return `Files.exists('${escapedPath}')`;

    case FILES_OPERATIONS.IS_FILE:
      return `Files.is_file('${escapedPath}')`;

    case FILES_OPERATIONS.IS_DIR:
      return `Files.is_dir('${escapedPath}')`;

    default:
      return '';
  }
}

/**
 * Recursively filters an array of tree nodes matching a search query.
 *
 * Keeps directories if any of their descendant nodes match the search term.
 *
 * @param {Array<Object>} nodes Tree nodes.
 * @param {string} query Search text.
 * @returns {Array<Object>} Filtered tree.
 */
export function filterTreeNodes(nodes, query) {
  if (!Array.isArray(nodes) || nodes.length === 0) {
    return [];
  }

  const term = (query || '').trim().toLowerCase();
  if (!term) {
    return nodes;
  }

  const result = [];

  for (const node of nodes) {
    const nameMatches = (node.name || '').toLowerCase().includes(term);
    const pathMatches = (node.path || '').toLowerCase().includes(term);

    if (node.type === 'dir' && Array.isArray(node.children) && node.children.length > 0) {
      const filteredChildren = filterTreeNodes(node.children, term);
      if (filteredChildren.length > 0 || nameMatches || pathMatches) {
        result.push({
          ...node,
          children: filteredChildren,
        });
      }
    } else if (nameMatches || pathMatches) {
      result.push(node);
    }
  }

  return result;
}
