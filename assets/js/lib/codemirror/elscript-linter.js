/**
 * Expression Lab DSL Linter for CodeMirror 6.
 *
 * Traverses the Lezer AST to provide real-time diagnostics:
 * 1. Syntax errors (unclosed brackets, unclosed strings/comments, unexpected tokens, missing commas).
 * 2. Semantic validation for Special Forms signatures and arity (prog, set, var, fn, map, filter, etc.).
 */

import { linter } from '@codemirror/lint';
import { syntaxTree } from '@codemirror/language';
import { parser } from './grammar/parser.js';

/**
 * Special Form arity constraints matching PHP LanguageParser.
 */
const SPECIAL_FORM_SPECS = {
  SetExpression: {
    name: 'set',
    min: 2,
    max: 2,
    signature: 'set[name, value]',
  },
  UnsetExpression: {
    name: 'unset',
    min: 1,
    max: 1,
    signature: 'unset[name]',
  },
  IssetExpression: {
    name: 'isset',
    min: 1,
    max: 1,
    signature: 'isset[name]',
  },
  VarExpression: {
    name: 'var',
    min: 1,
    max: 1,
    signature: 'var[name]',
  },
  ArgsExpression: {
    name: 'args',
    min: 1,
    max: 1,
    signature: 'args[name]',
  },
  ShowExpression: {
    name: 'show',
    min: 1,
    max: 1,
    signature: 'show[expression]',
  },
  FnExpression: {
    name: 'fn',
    min: 2,
    max: 2,
    signature: 'fn[params, body]',
  },
  MapExpression: {
    name: 'map',
    min: 2,
    max: 2,
    signature: 'map[iterable, fn]',
  },
  FilterExpression: {
    name: 'filter',
    min: 2,
    max: 3,
    signature: 'filter[iterable, fn, mode?]',
  },
  ReduceExpression: {
    name: 'reduce',
    min: 2,
    max: 3,
    signature: 'reduce[iterable, fn, initial?]',
  },
};

/**
 * Extracts argument nodes from a BracketList AST node.
 *
 * @param {import('@lezer/common').SyntaxNode} bracketNode
 * @returns {Array<import('@lezer/common').SyntaxNode>}
 */
function getBracketArguments(bracketNode) {
  if (!bracketNode) return [];
  const args = [];
  for (let ch = bracketNode.firstChild; ch; ch = ch.nextSibling) {
    if (ch.name !== '[' && ch.name !== ']' && ch.name !== ',' && ch.name !== 'BlockComment') {
      args.push(ch);
    }
  }
  return args;
}

/**
 * Merges adjacent or overlapping diagnostics to prevent redundant squiggly lines.
 *
 * @param {Array<object>} diagnostics
 * @returns {Array<object>}
 */
function mergeDiagnostics(diagnostics) {
  if (diagnostics.length <= 1) return diagnostics;
  const merged = [];
  for (const diag of diagnostics) {
    const prev = merged[merged.length - 1];
    if (prev && diag.from <= prev.to && diag.message === prev.message) {
      prev.to = Math.max(prev.to, diag.to);
    } else {
      merged.push({ ...diag });
    }
  }
  return merged;
}

/**
 * Evaluates syntax and semantic rules on an Expression Lab code string or EditorState.
 *
 * @param {import('@codemirror/state').EditorState|string} input
 * @returns {Array<object>} List of CodeMirror Diagnostic objects.
 */
export function lintExpressionLab(input) {
  if (!input) return [];

  let tree;
  let textLen = 0;

  if (typeof input === 'string') {
    textLen = input.length;
    if (textLen === 0) return [];
    tree = parser.parse(input);
  } else if (input.doc) {
    textLen = input.doc.length;
    if (textLen === 0) return [];
    tree = syntaxTree(input);
  } else {
    return [];
  }

  const diagnostics = [];

  tree.iterate({
    enter(node) {
      // 1. Syntax Error Nodes (Lezer ⚠)
      if (node.type.isError) {
        let from = node.from;
        let to = node.to > node.from ? node.to : Math.min(node.from + 1, textLen);

        if (from >= textLen) {
          from = Math.max(0, textLen - 1);
          to = textLen;
        }

        let message = 'Syntax error: unexpected token or expression';
        const parentName = node.node.parent?.name;

        if (node.from >= textLen) {
          message = 'Syntax error: unclosed block or incomplete expression';
        } else if (parentName === 'BracketList' || parentName === 'ProgExpression') {
          message = 'Syntax error: unexpected token or missing comma/bracket in special form';
        } else if (parentName === 'ObjectExpression' || parentName === 'ObjectProperty') {
          message = 'Syntax error: malformed object literal or missing comma';
        } else if (parentName === 'ArrayExpression') {
          message = 'Syntax error: malformed array literal or missing comma';
        }

        diagnostics.push({
          from,
          to,
          severity: 'error',
          message,
        });

        return false;
      }

      // 2. Semantic Signature and Arity Validation for Special Forms
      const spec = SPECIAL_FORM_SPECS[node.name];
      if (spec) {
        const bracket = node.node.getChild('BracketList');
        if (bracket) {
          // If there is already a syntax error inside the brackets, let it take precedence
          let hasChildError = false;
          for (let ch = bracket.firstChild; ch; ch = ch.nextSibling) {
            if (ch.type.isError) {
              hasChildError = true;
              break;
            }
          }

          if (!hasChildError) {
            const args = getBracketArguments(bracket);
            if (args.length < spec.min || args.length > spec.max) {
              const expectedStr = spec.min === spec.max
                ? `exactly ${spec.min} argument${spec.min === 1 ? '' : 's'}`
                : `between ${spec.min} and ${spec.max} arguments`;

              diagnostics.push({
                from: node.from,
                to: node.to,
                severity: 'error',
                message: `${spec.name}[] requires ${expectedStr}: ${spec.signature}`,
              });
            }
          }
        }
      }
    },
  });

  return mergeDiagnostics(diagnostics);
}

/**
 * Creates a CodeMirror 6 Linter extension for Expression Lab.
 *
 * @param {object} [options]
 * @param {number} [options.delay=200] Debounce delay in milliseconds.
 * @returns {import('@codemirror/state').Extension}
 */
export function expressionLabLinter(options = {}) {
  const delay = options.delay ?? 200;
  return linter((view) => lintExpressionLab(view.state), { delay });
}
