import { snippetCompletion } from '@codemirror/autocomplete';
import { buildDocNode } from './doc-tooltip-builder.js';
import { DSL_KEYWORDS } from './keywords.js';

/**
 * Shared string parser state machine.
 *
 * Tracks string literals, escape sequences, and parenthesis depth
 * while iterating through text. Used by multiple autocomplete functions
 * to avoid duplicating the same parsing logic.
 *
 * @typedef {Object} ParserState
 * @property {number} i - Current index
 * @property {boolean} inString - Whether we're inside a string literal
 * @property {string|null} quoteChar - The quote character (' " `)
 * @property {number} depth - Current parenthesis depth
 */

/**
 * Create a parser state at the beginning of text.
 * @returns {ParserState}
 */
function createParserState() {
    return { i: 0, inString: false, quoteChar: null, depth: 0 };
}

/**
 * Advance the parser by one character, updating state.
 * Returns the current character before advancing.
 *
 * @param {string} text
 * @param {ParserState} state
 * @returns {{ ch: string, wasEscaped: boolean }}
 */
function advanceParser(text, state) {
    const ch = text[state.i];
    let wasEscaped = false;

    if (ch === '\\' && state.inString && state.i + 1 < text.length) {
        state.i += 2;
        return { ch, wasEscaped: true };
    }

    if (state.inString) {
        if (ch === state.quoteChar) {
            state.inString = false;
            state.quoteChar = null;
        }
    } else {
        if (ch === '\'' || ch === '"' || ch === '`') {
            state.inString = true;
            state.quoteChar = ch;
        } else if (ch === '(') {
            state.depth++;
        } else if (ch === ')') {
            state.depth--;
        }
    }

    state.i++;
    return { ch, wasEscaped: false };
}

/**
 * Check whether the cursor sits inside a string literal.
 *
 * @param {string} text Text from the document start up to the cursor.
 * @returns {boolean}
 */
function isInsideString(text) {
    const state = createParserState();
    while (state.i < text.length) {
        advanceParser(text, state);
    }
    return state.inString;
}

/**
 * Locate the innermost expression at the cursor by tracking parenthesis
 * depth, commas and string literals.
 *
 * When the cursor is inside `foo( bar, |)` the returned expression is the
 * text after the last comma (or opening paren) at the current depth.
 *
 * @param {string} text Full text from document start to cursor.
 * @returns {{ expression: string, offset: number } | null}
 *          null when the cursor is inside a string literal.
 */
function findInnermostExpression(text) {
    const stack = [0];
    const state = createParserState();

    while (state.i < text.length) {
        const ch = text[state.i];
        const { wasEscaped } = advanceParser(text, state);
        if (wasEscaped) continue;

        if (state.inString) continue;

        if (ch === '(') {
            stack.push(state.i + 1);
        } else if (ch === ')') {
            if (stack.length > 1) stack.pop();
        } else if (ch === ',') {
            stack[stack.length - 1] = state.i + 1;
        }
    }

    if (state.inString) return null;

    const offset = stack[stack.length - 1];
    return { expression: text.substring(offset), offset };
}

/**
 * Collapse balanced parenthesized content so that `foo(bar, baz).qux(1)`
 * becomes `foo().qux()`.  String literals at depth 0 are preserved.
 *
 * @param {string} expr
 * @returns {string}
 */
function collapseParenContent(expr) {
    let result = '';
    const state = createParserState();

    while (state.i < expr.length) {
        const ch = expr[state.i];
        const { wasEscaped } = advanceParser(expr, state);
        if (wasEscaped) {
            if (state.depth === 0) result += ch + (expr[state.i - 1] || '');
            continue;
        }

        if (state.inString) {
            if (state.depth === 0) result += ch;
            continue;
        }

        if (ch === '\'' || ch === '"' || ch === '`') {
            if (state.depth === 0) result += ch;
            continue;
        }

        if (ch === '(') {
            if (state.depth === 1) result += '(';
        } else if (ch === ')') {
            if (state.depth === 0) result += ')';
        } else if (state.depth === 0) {
            result += ch;
        }
    }

    return result;
}

/**
 * Return the index of the last `.` that is NOT inside parentheses or strings.
 *
 * @param {string} text
 * @returns {number} Position of the last depth-0 dot, or -1.
 */
function findLastDotAtDepthZero(text) {
    let lastDot = -1;
    const state = createParserState();

    while (state.i < text.length) {
        const ch = text[state.i];
        const { wasEscaped } = advanceParser(text, state);
        if (wasEscaped) continue;
        if (state.inString) continue;

        if (ch === '.' && state.depth === 0) lastDot = state.i - 1;
    }

    return lastDot;
}
/**
 * Strip whitespace outside of strings.
 *
 * @param {string} text
 * @returns {string}
 */
function stripWhitespaceOutsideStrings(text) {
    let result = '';
    const state = createParserState();

    while (state.i < text.length) {
        const ch = text[state.i];
        const { wasEscaped } = advanceParser(text, state);
        if (wasEscaped) {
            result += ch + (text[state.i - 1] || '');
            continue;
        }

        if (state.inString) {
            result += ch;
            continue;
        }

        if (ch === '\'' || ch === '"' || ch === String.fromCharCode(96)) {
            result += ch;
            continue;
        }

        if (/\s/.test(ch)) continue;
        result += ch;
    }

    return result;
}

/**
 * Parse a normalised (paren-collapsed) expression into chain segments.
 *
 * Splits on `.` and `?.` while preserving `()` suffixes on method segments.
 * E.g. "Users.find().set_domain()." → ["Users", "find()", "set_domain()", ""]
 *
 * @param {string} normalizedExpr
 * @returns {string[]}
 */
function parseChainSegments(normalizedExpr) {
    const parts = [];
    let current = '';
    let i = 0;

    while (i < normalizedExpr.length) {
        const ch = normalizedExpr[i];
        if (ch === '?' && normalizedExpr[i + 1] === '.') {
            parts.push(current);
            current = '';
            i += 2;
        } else if (ch === '.') {
            parts.push(current);
            current = '';
            i++;
        } else {
            current += ch;
            i++;
        }
    }
    parts.push(current);

    return parts;
}

/**
 * Resolve a chain expression using the type registry (lazy graph walk).
 *
 * Instead of matching against pre-materialised regex rules, this walks a
 * type graph where each type maps its members to their return types.
 * Supports infinite chain depth at O(1) per step.
 *
 * @param {string} normalizedExpr  Paren-collapsed, whitespace-stripped expression.
 * @param {object} chainData       { typeRegistry: {}, rootObjects: Set }
 * @returns {{ members: Array } | null}
 */
function resolveChain(normalizedExpr, chainData) {
    const { typeRegistry, rootObjects } = chainData;
    if (!typeRegistry || !rootObjects) return null;

    // Must end with ?.word or .word (possibly empty word) to trigger chain completion.
    if (!/\??\.\w*$/.test(normalizedExpr)) return null;

    const segments = parseChainSegments(normalizedExpr);
    if (segments.length < 2) return null;

    const root = segments[0];
    if (!rootObjects.has(root) || !typeRegistry[root]) return null;

    let currentType = root;
    let stepsWalked = 0;

    // Walk through all segments except the last (which is the partial input).
    for (let i = 1; i < segments.length - 1; i++) {
        const seg = segments[i];
        const memberName = seg.replace(/\(\)$/, '');

        const typeMembers = typeRegistry[currentType];
        if (!typeMembers || !typeMembers[memberName]) return null;

        const returnType = typeMembers[memberName].returnType;
        if (!returnType || !typeRegistry[returnType]) return null;

        currentType = returnType;
        stepsWalked++;
    }

    // Require at least one resolved step; "Root." alone uses flat completions.
    if (stepsWalked === 0) return null;

    const typeMembers = typeRegistry[currentType];
    if (!typeMembers) return null;

    const members = Object.entries(typeMembers).map(([name, data]) => ({
        label: name,
        insertText: data.insertText || name,
        type: data.type,
        doc: data.doc || '',
    }));

    return { members };
}

/**
 * Create a CodeMirror info function from a structured doc object.
 * Returns a function that builds a DOM node on demand.
 *
 * @param {object|string} doc Structured doc object or fallback string.
 * @returns {function|string} Info function or empty string.
 */
function makeInfoFn(doc) {
    if (!doc || (typeof doc === 'string' && doc === '')) return '';
    if (typeof doc === 'string') return doc;
    return () => buildDocNode(doc);
}

/**
 * Map a chained option to a CodeMirror completion entry.
 */
function mapCompletionOption(opt) {
    const type = (opt.type || 'variable').toLowerCase();
    const raw = opt.insertText || opt.label;
    const hasPlaceholders = /\$\{\d+/.test(raw);

    if (hasPlaceholders) {
        return snippetCompletion(raw, {
            label: opt.label,
            type,
            info: makeInfoFn(opt.doc),
        });
    }

    return {
        label: opt.label,
        type,
        info: makeInfoFn(opt.doc),
        apply: (view, completion, from, to) => {
            const text = raw;
            const newDocLength = view.state.doc.length - (to - from) + text.length;
            const tr = view.state.update({
                changes: { from, to, insert: text },
                selection: { anchor: newDocLength },
            });
            view.dispatch(tr);
        },
    };
}

/**
 * Map a default (flat) completion item to a CodeMirror completion entry.
 */
function mapFallbackOption(item) {
    const type = (item.kind || 'variable').toLowerCase();

    if (type === 'function' || type === 'method' || type === 'keyword') {
        return snippetCompletion(item.insertText || item.label, {
            label: item.label,
            type,
            info: makeInfoFn(item.doc),
        });
    }

    return {
        label: item.label,
        type,
        info: makeInfoFn(item.doc),
        apply: (view, completion, from, to) => {
            const text = item.insertText || item.label;
            const tr = view.state.update({
                changes: { from, to, insert: text },
                selection: { anchor: from + text.length },
            });
            view.dispatch(tr);
        },
    };
}

/**
 * Create a CodeMirror completion source for the expression language.
 *
 * The source handles:
 * - Suppression inside string literals.
 * - Recursive detection of the innermost expression when the cursor is
 *   inside nested parentheses (e.g. `Foo.bar( Baz.qux( | ) )`).
 * - Chained autocomplete via a type graph: balanced parenthesised content
 *   is collapsed, then the chain is walked lazily through a type registry
 *   to resolve return types at each step. Supports infinite chain depth.
 * - A default keyword / identifier fallback.
 *
 * @param {() => Array}  getCompletionItems Returns the flat completion list.
 * @param {() => object} getChainData       Returns { typeRegistry, rootObjects } or null.
 * @returns {function}   A CodeMirror CompletionSource.
 */
export function createCompletionSource(getCompletionItems, getChainData) {
    return (context) => {
        const pos = context.pos;
        const fullText = context.state.doc.sliceString(0, pos);

        // 0. Suppress autocomplete inside string literals.
        if (isInsideString(fullText)) return null;

        // 1. Find the innermost expression (handles nested parens / commas).
        const inner = findInnermostExpression(fullText);
        if (!inner) return null;

        const exprText = inner.expression.trimStart();
        const exprStart = inner.offset + (inner.expression.length - exprText.length);

        // 2. Try chained autocomplete via type-graph walk.
        const chainData = getChainData();
        if (chainData && exprText.length > 0) {
            const normalized = stripWhitespaceOutsideStrings(collapseParenContent(exprText));
            const chainResult = resolveChain(normalized, chainData);

            if (chainResult && chainResult.members.length > 0) {
                const lastDot = findLastDotAtDepthZero(exprText);
                const from = exprStart + lastDot + 1;

                return {
                    from,
                    options: chainResult.members.map(opt => mapCompletionOption(opt)),
                };
            }
        }

        // 3. Default fallback.
        const word = context.matchBefore(/[\w.]*/);
        if (!word || (word.from === word.to && !context.explicit)) return null;

        const completionItems = getCompletionItems() || [];
        const items = word.text.includes('.')
            ? completionItems
            : [...DSL_KEYWORDS, ...completionItems];

        return {
            from: word.from,
            options: items.map(item => mapFallbackOption(item)),
        };
    };
}


