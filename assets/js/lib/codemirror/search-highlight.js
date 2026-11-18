import { StateEffect, StateField, RangeSetBuilder } from '@codemirror/state';
import { EditorView, Decoration } from '@codemirror/view';

export const setSearchHighlightEffect = StateEffect.define();

const matchMark = Decoration.mark({ class: 'cm-searchMatch' });
const activeMatchMark = Decoration.mark({ class: 'cm-searchMatch cm-searchMatch-selected' });

function buildSearchDecorations(docText, query, options = {}) {
  const trimmed = query || '';
  if (!trimmed) {
    return Decoration.none;
  }
  const { caseSensitive = false, wholeWord = false, regexp = false, activeRange = null } = options;
  let pattern = '';
  if (regexp) {
    pattern = trimmed;
  } else {
    const escaped = trimmed.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&');
    pattern = wholeWord ? `(?<![\\p{L}\\p{N}_])${escaped}(?![\\p{L}\\p{N}_])` : escaped;
  }

  const flags = (caseSensitive ? 'g' : 'gi') + (wholeWord || regexp ? 'u' : '');
  let rx;
  try {
    rx = new RegExp(pattern, flags);
  } catch {
    return Decoration.none;
  }

  const builder = new RangeSetBuilder();
  let match;
  while ((match = rx.exec(docText)) !== null) {
    const from = match.index;
    const to = from + match[0].length;
    if (from < to) {
      const isActive = activeRange && activeRange.from === from && activeRange.to === to;
      builder.add(from, to, isActive ? activeMatchMark : matchMark);
    }
    if (match[0].length === 0) {
      rx.lastIndex++;
      if (rx.lastIndex > docText.length) break;
    }
  }
  return builder.finish();
}

export const searchHighlightField = StateField.define({
  create() {
    return {
      query: '',
      activeRange: null,
      caseSensitive: false,
      wholeWord: false,
      regexp: false,
      decorations: Decoration.none,
    };
  },
  update(value, tr) {
    for (const effect of tr.effects) {
      if (effect.is(setSearchHighlightEffect)) {
        const { query, activeRange, caseSensitive, wholeWord, regexp } = effect.value || {};
        const trimmed = query || '';
        if (!trimmed) {
          return {
            query: '',
            activeRange: null,
            caseSensitive: false,
            wholeWord: false,
            regexp: false,
            decorations: Decoration.none,
          };
        }
        const docText = tr.state.doc.toString();
        const decorations = buildSearchDecorations(docText, trimmed, {
          caseSensitive: Boolean(caseSensitive),
          wholeWord: Boolean(wholeWord),
          regexp: Boolean(regexp),
          activeRange: activeRange || null,
        });
        return {
          query: trimmed,
          activeRange: activeRange || null,
          caseSensitive: Boolean(caseSensitive),
          wholeWord: Boolean(wholeWord),
          regexp: Boolean(regexp),
          decorations,
        };
      }
    }
    if (tr.docChanged && value.query) {
      const docText = tr.state.doc.toString();
      const decorations = buildSearchDecorations(docText, value.query, {
        caseSensitive: value.caseSensitive,
        wholeWord: value.wholeWord,
        regexp: value.regexp,
        activeRange: value.activeRange,
      });
      return { ...value, decorations };
    }
    return value;
  },
  provide: (f) => EditorView.decorations.from(f, (v) => v.decorations),
});

