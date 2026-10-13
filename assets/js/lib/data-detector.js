/**
 * Algorithmic Data Type & Syntax Language Detector.
 *
 * Categorizes raw cell payloads for the Data Viewer Modal into:
 *  - 'json': Composite JSON object or array (eligible for interactive Preview).
 *  - 'php-serialized': Composite PHP serialized array or object (eligible for interactive Preview).
 *  - 'none': Plain scalar, non-composite, or unformatted text (only Raw and Highlight).
 */

import { parsePhpSerialized } from './php-serializer/parser.js';

// Composite payloads only. Enums (`E:len:"Class:Case";`) are leaf values, never `{...}` blocks.
const PHP_COMPOSITE_REGEX = /^(?:a:\d+:\{|O:\d+:"[^"]+":\d+:\{|C:\d+:"[^"]+":\d+:\{)/;
const SQL_KEYWORD_REGEX = /^(?:select|insert\s+into|update|delete\s+from|create\s+table|alter\s+table|drop\s+table|describe|show\s+tables)\s+/i;
const HTML_STRUCTURE_REGEX = /^(?:<!doctype\s+html|<html|<div|<span|<p|<table|<form|<body|<head|<script|<style|<svg|<a\s)/i;
const JS_KEYWORD_REGEX = /^(?:(?:const|let|var)\s+\w+\s*=|function\s*\w*\s*\(|\(?\w+\)?\s*=>\s*\{|import\s+.*from|export\s+(?:default|const|let|function))/;
const CSS_STRUCTURE_REGEX = /^[.#@a-zA-Z][\w.:\s,>+~*-]*\{\s*[\w-]+\s*:/;

/**
 * Unwraps outer single or double quotes from a string literal.
 *
 * @param {string} str - Raw string possibly enclosed in quotes.
 * @returns {string|null} - Unwrapped inner string or null if not quoted.
 */
export function unwrapStringLiteral(str) {
  if (typeof str !== 'string') return null;
  const trimmed = str.trim();
  if (trimmed.length < 2) return null;

  const first = trimmed[0];
  const last = trimmed[trimmed.length - 1];

  if ((first === '\'' && last === '\'') || (first === '"' && last === '"')) {
    const inner = trimmed.slice(1, -1);
    if (first === '"') {
      try {
        const parsed = JSON.parse(trimmed);
        if (typeof parsed === 'string') {
          return parsed;
        }
      } catch {
        // Fallback simple unescape
        return inner.replace(/\\"/g, '"').replace(/\\\\/g, '\\');
      }
    }
    return inner;
  }

  return null;
}

/**
 * Heuristically detects syntax highlighting language for text payloads.
 *
 * @param {string} str - Trimmed string.
 * @returns {string} - Language identifier for highlight.js or 'auto'.
 */
export function detectSyntaxLanguage(str) {
  if (!str || typeof str !== 'string') {
    return 'auto';
  }

  const trimmed = str.trim();
  if (!trimmed) {
    return 'auto';
  }

  if (SQL_KEYWORD_REGEX.test(trimmed)) {
    return 'sql';
  }

  if (HTML_STRUCTURE_REGEX.test(trimmed)) {
    return 'html';
  }

  if (trimmed.startsWith('<?php')) {
    return 'php';
  }

  if (JS_KEYWORD_REGEX.test(trimmed)) {
    return 'javascript';
  }

  if (CSS_STRUCTURE_REGEX.test(trimmed)) {
    return 'css';
  }

  // Fast JSON check
  if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
    try {
      JSON.parse(trimmed);
      return 'json';
    } catch {
      // not valid json
    }
  }

  // Direct or unwrapped PHP serialized check
  if (trimmed.endsWith('}') && PHP_COMPOSITE_REGEX.test(trimmed)) {
    return 'php';
  }

  const unwrapped = unwrapStringLiteral(trimmed);
  if (unwrapped && unwrapped.trim().endsWith('}') && PHP_COMPOSITE_REGEX.test(unwrapped.trim())) {
    return 'php';
  }

  return 'auto';
}

/**
 * Categorizes raw cell value for the Data Viewer modal and Console/Scratchpad.
 *
 * @param {any} rawValue - Raw cell data (string, object, number, etc.).
 * @returns {{
 *   format: 'json' | 'php-serialized' | 'none',
 *   rawString: string,
 *   data: any,
 *   unwrappedString?: string,
 *   suggestedLang: string
 * }}
 */
export function detectDataType(rawValue) {
  if (rawValue === null || rawValue === undefined) {
    const rawString = rawValue === null ? 'null' : 'undefined';
    return {
      format: 'none',
      rawString,
      data: null,
      suggestedLang: 'auto'
    };
  }

  // Handle in-memory non-scalar objects passed directly
  if (typeof rawValue === 'object') {
    const rawString = JSON.stringify(rawValue, null, 2);
    return {
      format: 'json',
      rawString,
      data: rawValue,
      suggestedLang: 'elscript'
    };
  }

  const rawString = String(rawValue);
  const trimmed = rawString.trim();

  // Fast bailout for short or empty strings
  if (trimmed.length < 2) {
    return {
      format: 'none',
      rawString,
      data: null,
      suggestedLang: detectSyntaxLanguage(trimmed)
    };
  }

  const tryDetectComposite = (target, isUnwrapped = false) => {
    // 1. Check for JSON composite object or array
    if ((target.startsWith('{') && target.endsWith('}')) || (target.startsWith('[') && target.endsWith(']'))) {
      try {
        const parsed = JSON.parse(target);
        if (typeof parsed === 'object' && parsed !== null) {
          return {
            format: 'json',
            rawString,
            data: parsed,
            unwrappedString: isUnwrapped ? target : undefined,
            suggestedLang: 'json'
          };
        }
      } catch {
        // Not valid JSON, continue to next check
      }
    }

    // 2. Check for PHP Serialized composite array or object
    if (target.endsWith('}') && PHP_COMPOSITE_REGEX.test(target)) {
      try {
        const ast = parsePhpSerialized(target);
        return {
          format: 'php-serialized',
          rawString,
          data: ast,
          unwrappedString: isUnwrapped ? target : undefined,
          suggestedLang: 'php'
        };
      } catch {
        // Not valid or corrupted PHP serialization, fall through
      }
    }

    return null;
  };

  // Direct check
  const directMatch = tryDetectComposite(trimmed, false);
  if (directMatch) {
    return directMatch;
  }

  // Check unwrapped string literal (e.g. from REPL scalar output)
  const unwrapped = unwrapStringLiteral(trimmed);
  if (unwrapped) {
    const unwrappedMatch = tryDetectComposite(unwrapped.trim(), true);
    if (unwrappedMatch) {
      return unwrappedMatch;
    }
  }

  // 3. Fallback: Not eligible for tree preview
  return {
    format: 'none',
    rawString,
    data: null,
    suggestedLang: detectSyntaxLanguage(trimmed)
  };
}
