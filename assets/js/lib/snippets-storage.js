import {
  handleLocalStorage,
  handleReadLocalSnippets,
  handleWriteLocalSnippets,
} from './api/client.js';
import { getUniqueSnippetName, normalizeSnippet, __ } from './helpers.js';

export const SNIPPETS_STORAGE_KEY = 'el_snippets';
export const USE_LOCAL_FS_KEY = 'el_use_local_fs';

/**
 * Loads all snippets from Local FileSystem (if enabled) or localStorage.
 *
 * @returns {Promise<{ snippets: Array, useLocalFs: boolean }>}
 */
export async function loadSavedSnippets() {
  const savedUseFs = await handleLocalStorage('getItem', USE_LOCAL_FS_KEY);
  if (savedUseFs === 'true') {
    try {
      const result = await handleReadLocalSnippets();
      if (result && result.success && Array.isArray(result.snippets)) {
        return {
          snippets: result.snippets.map(normalizeSnippet),
          useLocalFs: true,
        };
      }
    } catch {
      // Fall through to localStorage fallback
    }
  }

  try {
    const raw = await handleLocalStorage('getItem', SNIPPETS_STORAGE_KEY);
    if (raw !== null && raw !== undefined) {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) {
        return {
          snippets: parsed.map(normalizeSnippet),
          useLocalFs: false,
        };
      }
    }
  } catch {
    // Ignore JSON parse errors
  }

  return {
    snippets: [],
    useLocalFs: false,
  };
}

/**
 * Persists an array of snippets to either Local FileSystem or localStorage.
 *
 * @param {Array} snippets
 * @param {boolean} useLocalFs
 * @returns {Promise<boolean>}
 */
export async function persistSavedSnippets(snippets, useLocalFs = false) {
  if (useLocalFs) {
    const res = await handleWriteLocalSnippets(snippets);
    return Boolean(res && res.success);
  }

  await handleLocalStorage('setItem', SNIPPETS_STORAGE_KEY, JSON.stringify(snippets, null, 2));
  return true;
}

/**
 * Automatically creates and saves a new snippet from code.
 *
 * @param {string} code
 * @param {object} [options]
 * @param {string} [options.name]
 * @param {string[]} [options.tags]
 * @returns {Promise<object>} The newly created snippet
 */
export async function saveSnippetToLibrary(code, options = {}) {
  const { snippets, useLocalFs } = await loadSavedSnippets();
  const existingNames = new Set(snippets.map((s) => (s.name || '').toLowerCase()));
  const defaultTitle = options.name || __('Scratchpad Snippet');
  const uniqueName = getUniqueSnippetName(defaultTitle, existingNames);

  const newSnippet = {
    id: uniqueName,
    name: uniqueName,
    code: typeof code === 'string' ? code : '',
    tags: Array.isArray(options.tags) && options.tags.length > 0 ? options.tags : ['scratchpad'],
  };

  snippets.push(newSnippet);
  await persistSavedSnippets(snippets, useLocalFs);

  return newSnippet;
}
