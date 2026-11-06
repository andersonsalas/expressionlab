/**
 * Expression Lab
 * (c) Anderson Salas <github@andersonsalas.com>
 * @license GPL-2.0+
 **/

const CTRL_OR_CMD_TOKENS = new Set(['ctrl', 'cmd', 'command', 'meta', '⌘']);
const SHIFT_TOKENS = new Set(['shift', '⇧']);
const ALT_TOKENS = new Set(['alt', 'opt', 'option', '⌥']);

export function matchesShortcut(event, shortcutStr) {
  if (!event || !shortcutStr || typeof shortcutStr !== 'string') return false;
  const parts = shortcutStr.split('+').map((s) => s.trim().toLowerCase());
  const hasCtrlOrCmd = parts.some((p) => CTRL_OR_CMD_TOKENS.has(p));
  const hasShift = parts.some((p) => SHIFT_TOKENS.has(p));
  const hasAlt = parts.some((p) => ALT_TOKENS.has(p));
  const keyPart = parts.find(
    (p) => !CTRL_OR_CMD_TOKENS.has(p) && !SHIFT_TOKENS.has(p) && !ALT_TOKENS.has(p)
  );

  const eventCtrlOrCmd = Boolean(event.ctrlKey || event.metaKey);
  const eventShift = Boolean(event.shiftKey);
  const eventAlt = Boolean(event.altKey);

  if (hasCtrlOrCmd !== eventCtrlOrCmd) return false;
  if (hasShift !== eventShift) return false;
  if (hasAlt !== eventAlt) return false;

  if (!keyPart) return false;
  return event.key?.toLowerCase() === keyPart.toLowerCase();
}

export class MenuRegistry {
  constructor() {
    this.transformers = new Map();
  }

  /**
   * Register a transformer function for a given menu id.
   * @param {string} menuId
   * @param {Function} transformerFn
   */
  registerTransformer(menuId, transformerFn) {
    if (typeof transformerFn !== 'function') return;
    if (!this.transformers.has(menuId)) {
      this.transformers.set(menuId, []);
    }
    this.transformers.get(menuId).push(transformerFn);
  }

  /**
   * Apply all transformers for a menu id to baseGroups.
   * @param {string} menuId
   * @param {Array} baseGroups
   * @param {Object} context
   * @returns {Array}
   */
  apply(menuId, baseGroups, context = {}) {
    const list = this.transformers.get(menuId);
    if (!list || list.length === 0) {
      return baseGroups;
    }

    let current = (baseGroups || []).map((g) => ({
      ...g,
      items: Array.isArray(g.items) ? g.items.map((item) => ({ ...item })) : [],
    }));

    for (const transform of list) {
      try {
        const next = transform(current, context);
        if (Array.isArray(next)) {
          current = next;
        }
      } catch (err) {
        console.error(`[MenuRegistry] Error applying transformer for '${menuId}':`, err);
      }
    }

    return current;
  }

  /**
   * Clears transformers for a given menuId, or all if not provided.
   * @param {string} [menuId]
   */
  reset(menuId = null) {
    if (menuId) {
      this.transformers.delete(menuId);
    } else {
      this.transformers.clear();
    }
  }
}

export const menuRegistry = new MenuRegistry();

