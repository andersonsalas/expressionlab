import {
  FILES_OPERATIONS,
  FILES_OPERATION_CONFIGS,
  FILES_MAX_READ_BYTES,
  FILES_MAX_READ_KB,
  FILES_MIN_READ_KB,
  DEFAULT_TAIL_LINES,
  MIN_TAIL_LINES,
  MAX_TAIL_LINES,
  sanitizeRelativePath,
  escapeElscriptString,
  isOperationApplicable,
  generateFilesExpression,
  filterTreeNodes,
} from '../../lib/files-builder-generator.js';

describe('files-builder-generator', () => {
  describe('Constants and Configurations', () => {
    test('defines all standard operations', () => {
      expect(FILES_OPERATIONS.READ).toBe('read');
      expect(FILES_OPERATIONS.TAIL).toBe('tail');
      expect(FILES_OPERATIONS.SIZE).toBe('size');
      expect(FILES_OPERATIONS.MODIFIED).toBe('modified');
      expect(FILES_OPERATIONS.LIST).toBe('list');
      expect(FILES_OPERATIONS.STATS).toBe('stats');
      expect(FILES_OPERATIONS.EXISTS).toBe('exists');
      expect(FILES_OPERATIONS.IS_FILE).toBe('is_file');
      expect(FILES_OPERATIONS.IS_DIR).toBe('is_dir');
    });

    test('operation configs have appropriate targets and metadata', () => {
      expect(FILES_OPERATION_CONFIGS.tail.appliesTo).toBe('file');
      expect(FILES_OPERATION_CONFIGS.read.appliesTo).toBe('file');
      expect(FILES_OPERATION_CONFIGS.list.appliesTo).toBe('dir');
      expect(FILES_OPERATION_CONFIGS.stats.appliesTo).toBe('dir');
      expect(FILES_OPERATION_CONFIGS.size.appliesTo).toBe('both');
      expect(FILES_OPERATION_CONFIGS.modified.appliesTo).toBe('both');
      expect(FILES_OPERATION_CONFIGS.exists.appliesTo).toBe('both');
    });

    test('isOperationApplicable verifies targets accurately', () => {
      expect(isOperationApplicable('tail', 'file')).toBe(true);
      expect(isOperationApplicable('tail', 'dir')).toBe(false);
      expect(isOperationApplicable('list', 'dir')).toBe(true);
      expect(isOperationApplicable('list', 'file')).toBe(false);
      expect(isOperationApplicable('size', 'file')).toBe(true);
      expect(isOperationApplicable('size', 'dir')).toBe(true);
      expect(isOperationApplicable('unknown', 'file')).toBe(false);
    });
  });

  describe('sanitizeRelativePath', () => {
    test('cleans leading slashes and trailing whitespace', () => {
      expect(sanitizeRelativePath('  /wp-content/debug.log  ')).toBe('wp-content/debug.log');
      expect(sanitizeRelativePath('///wp-config.php')).toBe('wp-config.php');
    });

    test('normalizes Windows backslashes and drive letters', () => {
      expect(sanitizeRelativePath('wp-content\\plugins\\my-plugin')).toBe('wp-content/plugins/my-plugin');
      expect(sanitizeRelativePath('C:\\var\\www\\html\\wp-config.php')).toBe('var/www/html/wp-config.php');
    });

    test('collapses duplicate slashes and removes directory traversal dots', () => {
      expect(sanitizeRelativePath('wp-content//plugins///test/')).toBe('wp-content/plugins/test');
      expect(sanitizeRelativePath('../../wp-config.php')).toBe('wp-config.php');
      expect(sanitizeRelativePath('wp-content/./debug.log')).toBe('wp-content/debug.log');
    });

    test('handles invalid inputs gracefully', () => {
      expect(sanitizeRelativePath('')).toBe('');
      expect(sanitizeRelativePath(null)).toBe('');
      expect(sanitizeRelativePath(undefined)).toBe('');
    });
  });

  describe('escapeElscriptString', () => {
    test('escapes single quotes and backslashes', () => {
      expect(escapeElscriptString('file\'name.txt')).toBe('file\\\'name.txt');
      expect(escapeElscriptString('path\\with\\slashes')).toBe('path\\\\with\\\\slashes');
      expect(escapeElscriptString(null)).toBe('');
    });
  });

  describe('generateFilesExpression', () => {
    test('returns empty string if input is missing or invalid', () => {
      expect(generateFilesExpression()).toBe('');
      expect(generateFilesExpression({ operation: 'tail', path: '' })).toBe('');
      expect(generateFilesExpression({ operation: 'invalid', path: 'wp-config.php' })).toBe('');
    });

    describe('tail()', () => {
      test('omits lines argument when using default 50 lines or null', () => {
        expect(generateFilesExpression({
          operation: 'tail',
          path: 'wp-content/debug.log',
          tailLines: 50,
        })).toBe('Files.tail(\'wp-content/debug.log\')');

        expect(generateFilesExpression({
          operation: 'tail',
          path: 'wp-content/debug.log',
          tailLines: null,
        })).toBe('Files.tail(\'wp-content/debug.log\')');
      });

      test('includes lines argument when custom', () => {
        expect(generateFilesExpression({
          operation: 'tail',
          path: 'wp-content/debug.log',
          tailLines: 15,
        })).toBe('Files.tail(\'wp-content/debug.log\', 15)');
      });

      test('clamps lines between 1 and 500', () => {
        expect(generateFilesExpression({
          operation: 'tail',
          path: 'wp-content/debug.log',
          tailLines: 0,
        })).toBe('Files.tail(\'wp-content/debug.log\', 1)');

        expect(generateFilesExpression({
          operation: 'tail',
          path: 'wp-content/debug.log',
          tailLines: 1000,
        })).toBe('Files.tail(\'wp-content/debug.log\', 500)');
      });
    });

    describe('read()', () => {
      test('omits byte limit when using default 256 KB', () => {
        expect(generateFilesExpression({
          operation: 'read',
          path: 'wp-config.php',
          readBytes: FILES_MAX_READ_BYTES,
        })).toBe('Files.read(\'wp-config.php\')');
      });

      test('omits byte limit when readKb is default (256 KB) or null', () => {
        expect(generateFilesExpression({
          operation: 'read',
          path: 'wp-config.php',
          readKb: FILES_MAX_READ_KB,
        })).toBe('Files.read(\'wp-config.php\')');

        expect(generateFilesExpression({
          operation: 'read',
          path: 'wp-config.php',
          readKb: null,
        })).toBe('Files.read(\'wp-config.php\')');
      });

      test('converts readKb to bytes automatically in generated code', () => {
        expect(generateFilesExpression({
          operation: 'read',
          path: 'wp-config.php',
          readKb: 64,
        })).toBe('Files.read(\'wp-config.php\', 65536)');

        expect(generateFilesExpression({
          operation: 'read',
          path: 'wp-config.php',
          readKb: 128,
        })).toBe('Files.read(\'wp-config.php\', 131072)');
      });

      test('clamps readKb between 1 byte and 262144 bytes', () => {
        expect(generateFilesExpression({
          operation: 'read',
          path: 'wp-config.php',
          readKb: 0,
        })).toBe('Files.read(\'wp-config.php\', 1)');

        expect(generateFilesExpression({
          operation: 'read',
          path: 'wp-config.php',
          readKb: 1000,
        })).toBe('Files.read(\'wp-config.php\')');
      });

      test('includes byte limit when custom bytes provided directly', () => {
        expect(generateFilesExpression({
          operation: 'read',
          path: 'wp-config.php',
          readBytes: 512,
        })).toBe('Files.read(\'wp-config.php\', 512)');
      });
    });

    describe('size()', () => {
      test('generates full array, human string, or raw bytes projection', () => {
        expect(generateFilesExpression({
          operation: 'size',
          path: 'wp-config.php',
          sizeProperty: 'all',
        })).toBe('Files.size(\'wp-config.php\')');

        expect(generateFilesExpression({
          operation: 'size',
          path: 'wp-config.php',
          sizeProperty: 'human',
        })).toBe('Files.size(\'wp-config.php\')[\'human\']');

        expect(generateFilesExpression({
          operation: 'size',
          path: 'wp-config.php',
          sizeProperty: 'bytes',
        })).toBe('Files.size(\'wp-config.php\')[\'bytes\']');
      });
    });

    describe('modified()', () => {
      test('generates full array, formatted string, or timestamp projection', () => {
        expect(generateFilesExpression({
          operation: 'modified',
          path: 'wp-config.php',
          modifiedProperty: 'all',
        })).toBe('Files.modified(\'wp-config.php\')');

        expect(generateFilesExpression({
          operation: 'modified',
          path: 'wp-config.php',
          modifiedProperty: 'formatted',
        })).toBe('Files.modified(\'wp-config.php\')[\'formatted\']');

        expect(generateFilesExpression({
          operation: 'modified',
          path: 'wp-config.php',
          modifiedProperty: 'timestamp',
        })).toBe('Files.modified(\'wp-config.php\')[\'timestamp\']');
      });
    });

    describe('perms()', () => {
      test('generates full array, octal, symbolic, owner, or group projection', () => {
        expect(generateFilesExpression({
          operation: 'perms',
          path: 'wp-config.php',
          permsProperty: 'all',
        })).toBe('Files.perms(\'wp-config.php\')');

        expect(generateFilesExpression({
          operation: 'perms',
          path: 'wp-config.php',
          permsProperty: 'octal',
        })).toBe('Files.perms(\'wp-config.php\')[\'octal\']');

        expect(generateFilesExpression({
          operation: 'perms',
          path: 'wp-config.php',
          permsProperty: 'symbolic',
        })).toBe('Files.perms(\'wp-config.php\')[\'symbolic\']');

        expect(generateFilesExpression({
          operation: 'perms',
          path: 'wp-config.php',
          permsProperty: 'owner',
        })).toBe('Files.perms(\'wp-config.php\')[\'owner\']');
      });
    });

    describe('list()', () => {
      test('defaults to Files.list() when path is empty or dot', () => {
        expect(generateFilesExpression({
          operation: 'list',
          path: '.',
        })).toBe('Files.list()');

        expect(generateFilesExpression({
          operation: 'list',
          path: '',
        })).toBe('Files.list()');
      });

      test('generates Files.list() with specific directory', () => {
        expect(generateFilesExpression({
          operation: 'list',
          path: 'wp-content/plugins',
        })).toBe('Files.list(\'wp-content/plugins\')');
      });
    });

    describe('stats()', () => {
      test('defaults to Files.stats() for root directory', () => {
        expect(generateFilesExpression({
          operation: 'stats',
          path: '.',
        })).toBe('Files.stats()');
      });

      test('generates Files.stats() with specific directory and property projections', () => {
        expect(generateFilesExpression({
          operation: 'stats',
          path: 'wp-content/themes',
        })).toBe('Files.stats(\'wp-content/themes\')');

        expect(generateFilesExpression({
          operation: 'stats',
          path: 'wp-content/themes',
          statsProperty: 'files',
        })).toBe('Files.stats(\'wp-content/themes\')[\'files\']');

        expect(generateFilesExpression({
          operation: 'stats',
          path: 'wp-content/themes',
          statsProperty: 'total_size',
        })).toBe('Files.stats(\'wp-content/themes\')[\'total_size\']');
      });
    });

    describe('projection and path safety', () => {
      test('treats a directory named root as a regular path', () => {
        expect(generateFilesExpression({ operation: 'list', path: 'root' })).toBe('Files.list(\'root\')');
        expect(generateFilesExpression({ operation: 'stats', path: 'root' })).toBe('Files.stats(\'root\')');
      });

      test('ignores unknown perms and stats projections', () => {
        expect(generateFilesExpression({
          operation: 'perms',
          path: 'wp-config.php',
          permsProperty: 'x\']; Files.read(\'wp-config.php',
        })).toBe('Files.perms(\'wp-config.php\')');

        expect(generateFilesExpression({
          operation: 'stats',
          path: 'wp-content',
          statsProperty: 'bogus',
        })).toBe('Files.stats(\'wp-content\')');
      });

      test('escapes quotes in paths', () => {
        expect(generateFilesExpression({ operation: 'exists', path: 'a\'b.txt' })).toBe('Files.exists(\'a\\\'b.txt\')');
      });
    });

    describe('exists(), is_file(), is_dir()', () => {
      test('generates existence checks accurately', () => {
        expect(generateFilesExpression({
          operation: 'exists',
          path: 'wp-content/debug.log',
        })).toBe('Files.exists(\'wp-content/debug.log\')');

        expect(generateFilesExpression({
          operation: 'is_file',
          path: 'wp-config.php',
        })).toBe('Files.is_file(\'wp-config.php\')');

        expect(generateFilesExpression({
          operation: 'is_dir',
          path: 'wp-content/plugins',
        })).toBe('Files.is_dir(\'wp-content/plugins\')');
      });
    });
  });

  describe('filterTreeNodes', () => {
    const mockTree = [
      {
        name: 'wp-config.php',
        path: 'wp-config.php',
        type: 'file',
      },
      {
        name: 'wp-content',
        path: 'wp-content',
        type: 'dir',
        children: [
          {
            name: 'debug.log',
            path: 'wp-content/debug.log',
            type: 'file',
          },
          {
            name: 'plugins',
            path: 'wp-content/plugins',
            type: 'dir',
            children: [
              {
                name: 'expressionlab',
                path: 'wp-content/plugins/expressionlab',
                type: 'dir',
                children: [],
              },
            ],
          },
        ],
      },
    ];

    test('returns original tree when search query is empty', () => {
      expect(filterTreeNodes(mockTree, '')).toEqual(mockTree);
      expect(filterTreeNodes(mockTree, '  ')).toEqual(mockTree);
    });

    test('filters nodes matching filename, keeping parent directory structure', () => {
      const filtered = filterTreeNodes(mockTree, 'debug');
      expect(filtered.length).toBe(1);
      expect(filtered[0].name).toBe('wp-content');
      expect(filtered[0].children.length).toBe(1);
      expect(filtered[0].children[0].name).toBe('debug.log');
    });

    test('matches root level files directly', () => {
      const filtered = filterTreeNodes(mockTree, 'config');
      expect(filtered.length).toBe(1);
      expect(filtered[0].name).toBe('wp-config.php');
    });

    test('returns empty array when nothing matches', () => {
      const filtered = filterTreeNodes(mockTree, 'nonexistent_file');
      expect(filtered).toEqual([]);
    });
  });
});
