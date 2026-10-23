/**
 * Database Query Builder - Code Generator Helper for Expression Lab
 *
 * Generates canonical, safe, and idiomatically formatted Elscript code
 * using Database.buffer(), Database.mirror(), Database.query(), Database.fetch(),
 * Database.flush(), and Database.count().
 */

/**
 * Escapes a string to be safely embedded inside an Elscript single-quoted string literal.
 *
 * @param {string} str
 * @returns {string}
 */
export function escapeElscriptString(str) {
  if (typeof str !== 'string') {
    str = String(str ?? '');
  }
  return str
    .replace(/\\/g, '\\\\')
    .replace(/'/g, '\\\'')
    .replace(/\n/g, '\\n')
    .replace(/\r/g, '\\r');
}

/**
 * Converts a plain integer string to a number, keeping strings with leading zeros
 * (e.g. '0123') and unsafe integers as strings so no information is lost.
 *
 * @param {string} raw
 * @returns {number|string}
 */
function toScalarValue(raw) {
  if (/^-?(0|[1-9]\d*)$/.test(raw) && Number.isSafeInteger(Number(raw))) {
    return Number(raw);
  }
  return raw;
}

/**
 * Serializes a JavaScript value into formatted Elscript syntax.
 *
 * @param {*} val
 * @param {number} indentLevel
 * @param {boolean} [alwaysQuoteKeys=false]
 * @returns {string}
 */
export function serializeToElscript(val, indentLevel = 0, alwaysQuoteKeys = false) {
  const indent = '  '.repeat(indentLevel);
  const nextIndent = '  '.repeat(indentLevel + 1);

  if (val === null || val === undefined) {
    return 'null';
  }
  if (typeof val === 'boolean') {
    return val ? 'true' : 'false';
  }
  if (typeof val === 'number') {
    return Number.isFinite(val) ? String(val) : 'null';
  }
  if (typeof val === 'string') {
    return `'${escapeElscriptString(val)}'`;
  }
  if (Array.isArray(val)) {
    if (val.length === 0) return '[]';
    const items = val.map((item) => `${nextIndent}${serializeToElscript(item, indentLevel + 1, alwaysQuoteKeys)}`);
    return `[\n${items.join(',\n')}\n${indent}]`;
  }
  if (typeof val === 'object') {
    const keys = Object.keys(val);
    if (keys.length === 0) return '{}';
    const entries = keys.map((key) => {
      const isValidIdentifier = /^[a-zA-Z_][a-zA-Z0-9_]*$/.test(key);
      const formattedKey = alwaysQuoteKeys || !isValidIdentifier
        ? `'${escapeElscriptString(key)}'`
        : key;
      const formattedVal = serializeToElscript(val[key], indentLevel + 1, alwaysQuoteKeys);
      return `${nextIndent}${formattedKey}: ${formattedVal}`;
    });
    return `{\n${entries.join(',\n')}\n${indent}}`;
  }
  return 'null';
}

/**
 * Infers a primitive value (integer, float, boolean, null) from an unquoted token.
 * If the token was enclosed in quotes, it is preserved as an explicit string.
 *
 * @param {string} val
 * @param {boolean} wasQuoted
 * @returns {string|number|boolean|null}
 */
function inferPrimitiveValue(val, wasQuoted) {
  if (wasQuoted) {
    return val;
  }
  const trimmed = val.trim();

  // Integer
  if (/^-?\d+$/.test(trimmed)) {
    const num = Number(trimmed);
    if (Number.isSafeInteger(num)) {
      return num;
    }
  }

  // Float / Decimal (e.g. 12.5, -0.99)
  if (/^-?\d+\.\d+$/.test(trimmed)) {
    const num = Number(trimmed);
    if (Number.isFinite(num)) {
      return num;
    }
  }

  // Boolean
  if (trimmed.toLowerCase() === 'true') {
    return true;
  }
  if (trimmed.toLowerCase() === 'false') {
    return false;
  }

  // Null
  if (trimmed.toLowerCase() === 'null') {
    return null;
  }

  return trimmed;
}

/**
 * Parses a comma-separated or newline-separated string into a clean array of values.
 * Supports single and double quotes (with commas inside), escape characters (\, \', \"),
 * and infers numbers, floats, booleans, and nulls when unquoted, while preserving explicitly
 * quoted values as strings (e.g. '0123' -> '0123', 123 -> 123).
 *
 * @param {string} raw
 * @returns {Array<string|number|boolean|null>}
 */
export function parseListValue(raw) {
  if (!raw || typeof raw !== 'string') return [];

  const tokens = [];
  let currentToken = '';
  let inQuote = null; // null | "'" | '"'
  let escaped = false;
  let wasQuoted = false;
  let afterQuote = false;

  for (let i = 0; i < raw.length; i++) {
    const char = raw[i];

    if (escaped) {
      currentToken += char;
      escaped = false;
      continue;
    }

    if (char === '\\') {
      escaped = true;
      continue;
    }

    if (inQuote !== null) {
      if (char === inQuote) {
        inQuote = null;
        afterQuote = true;
      } else {
        currentToken += char;
      }
      continue;
    }

    // Opening quote (only if currentToken is empty or only whitespace)
    if (char === '\'' || char === '"') {
      if (currentToken.trim() === '') {
        currentToken = '';
        inQuote = char;
        wasQuoted = true;
        afterQuote = false;
        continue;
      }
    }

    // Delimiters (comma, newline, carriage return)
    if (char === ',' || char === '\n' || char === '\r') {
      if (wasQuoted || currentToken.trim().length > 0) {
        tokens.push(inferPrimitiveValue(wasQuoted ? currentToken : currentToken.trim(), wasQuoted));
      }
      currentToken = '';
      wasQuoted = false;
      afterQuote = false;
      continue;
    }

    // If we're after a closing quote and see whitespace before a delimiter, skip it
    if (afterQuote) {
      if (char === ' ' || char === '\t') {
        continue;
      }
      // If there are non-whitespace characters attached after quote
      afterQuote = false;
    }

    currentToken += char;
  }

  // Trailing backslash
  if (escaped) {
    currentToken += '\\';
  }

  // Push final token
  if (wasQuoted || currentToken.trim().length > 0) {
    tokens.push(inferPrimitiveValue(wasQuoted ? currentToken : currentToken.trim(), wasQuoted));
  }

  return tokens;
}

/**
 * Compiles a single flat list of filter conditions (AND group).
 *
 * @param {Array<{ enabled: boolean, column: string, operator: string, value?: any, valueBetweenMin?: string, valueBetweenMax?: string, joinRef?: string }>} filters
 * @returns {Object|null}
 */
function compileSingleFilterGroup(filters = []) {
  if (!Array.isArray(filters) || filters.length === 0) return null;

  const where = {};
  let count = 0;

  for (const filter of filters) {
    if (!filter || !filter.enabled) continue;
    const col = (filter.column || '').trim();
    if (!col) continue;

    const op = filter.operator || '=';

    if (op === '$join') {
      const ref = (filter.joinRef || '').trim();
      if (ref) {
        where[col] = ref;
        count++;
      }
      continue;
    }

    if (op === '$isNull' || op === '$isNotNull') {
      where[col] = { [op]: true };
      count++;
      continue;
    }

    if (op === '$between') {
      const min = (filter.valueBetweenMin ?? '').toString().trim();
      const max = (filter.valueBetweenMax ?? '').toString().trim();
      if (min !== '' && max !== '') {
        const parsedMin = toScalarValue(min);
        const parsedMax = toScalarValue(max);
        where[col] = { $between: [parsedMin, parsedMax] };
        count++;
      }
      continue;
    }

    if (op === '$in' || op === '$notIn') {
      const rawVal = filter.value;
      const list = Array.isArray(rawVal) ? rawVal : parseListValue(String(rawVal || ''));
      if (list.length > 0) {
        where[col] = { [op]: list };
        count++;
      }
      continue;
    }

    if (op === '$like' || op === '$notLike') {
      const val = typeof filter.value === 'string' ? filter.value.trim() : String(filter.value ?? '');
      if (val !== '') {
        where[col] = { [op]: val };
        count++;
      }
      continue;
    }

    if (['$gt', '$gte', '$lt', '$lte', '$ne'].includes(op)) {
      const rawVal = (filter.value ?? '').toString().trim();
      if (rawVal !== '') {
        const val = toScalarValue(rawVal);
        where[col] = { [op]: val };
        count++;
      }
      continue;
    }

    // Default: '=' direct value
    const rawVal = (filter.value ?? '').toString().trim();
    if (rawVal !== '') {
      const val = toScalarValue(rawVal);
      where[col] = val;
      count++;
    }
  }

  return count > 0 ? where : null;
}

/**
 * Compiles raw filter rows or filter groups into a structured WHERE clause for Database.mirror.
 *
 * Supports both:
 * 1. 1D array of filter rows: compiles to an Object { col: val } (AND conditions).
 * 2. 2D array of filter groups:
 *    - If 0 groups have valid conditions: returns null.
 *    - If 1 group has valid conditions: returns an Object { col: val } (AND conditions).
 *    - If >= 2 groups have valid conditions: returns an Array of Objects [ { ... }, { ... } ] (OR conditions).
 *
 * @param {Array<Object>|Array<Array<Object>>} filtersOrGroups
 * @returns {Object|Array<Object>|null}
 */
export function compileFiltersToWhere(filtersOrGroups = []) {
  if (!Array.isArray(filtersOrGroups) || filtersOrGroups.length === 0) return null;

  const is2D = Array.isArray(filtersOrGroups[0]);

  if (!is2D) {
    return compileSingleFilterGroup(filtersOrGroups);
  }

  const validGroups = [];
  for (const group of filtersOrGroups) {
    const compiled = compileSingleFilterGroup(group);
    if (compiled) {
      validGroups.push(compiled);
    }
  }

  if (validGroups.length === 0) return null;
  if (validGroups.length === 1) return validGroups[0];
  return validGroups;
}

/**
 * Compiles step configuration options into the $options argument of Database.mirror.
 *
 * @param {Object} options
 * @returns {Object|null}
 */
export function compileStepOptions(options = {}) {
  if (!options || typeof options !== 'object') return null;

  const result = {};
  let count = 0;

  // select
  if (options.select) {
    if (Array.isArray(options.select)) {
      const cleanCols = options.select.map((c) => String(c).trim()).filter(Boolean);
      if (cleanCols.length > 0 && !(cleanCols.length === 1 && cleanCols[0] === '*')) {
        result.select = cleanCols;
        count++;
      }
    } else if (typeof options.select === 'string') {
      const cols = options.select
        .split(',')
        .map((c) => c.trim())
        .filter(Boolean);
      if (cols.length > 0 && !(cols.length === 1 && cols[0] === '*')) {
        result.select = cols;
        count++;
      }
    }
  }

  // orderby
  if (options.orderbyCol && String(options.orderbyCol).trim()) {
    const col = String(options.orderbyCol).trim();
    const dir = (options.orderbyDir || 'ASC').toUpperCase() === 'DESC' ? 'DESC' : 'ASC';
    result.orderby = { [col]: dir };
    count++;
  }

  // limit
  if (options.limit !== undefined && options.limit !== null && options.limit !== '') {
    const lim = Math.max(0, parseInt(options.limit, 10));
    if (!isNaN(lim) && lim > 0) {
      result.limit = lim;
      count++;
    }
  }

  // offset
  if (options.offset !== undefined && options.offset !== null && options.offset !== '') {
    const off = Math.max(0, parseInt(options.offset, 10));
    if (!isNaN(off) && off > 0) {
      result.offset = off;
      count++;
    }
  }

  // as (SQLite alias)
  if (options.as && String(options.as).trim()) {
    result.as = String(options.as).trim();
    count++;
  }

  // prefix override
  if (options.disablePrefix) {
    result.prefix = '';
    count++;
  } else if (options.prefix !== undefined && options.prefix !== null && String(options.prefix).trim() !== '') {
    result.prefix = String(options.prefix).trim();
    count++;
  }

  return count > 0 ? result : null;
}

/**
 * Determines if an automatic or manual buffer call is required.
 *
 * @param {Object} bufferState
 * @param {Array<Object>} steps
 * @returns {{ required: boolean, rows: number, memory?: number, overhead?: number }}
 */
export function resolveBufferConfig(bufferState = {}, steps = []) {
  const isEnabled = Boolean(bufferState.enabled);

  const hasRowsInput =
    isEnabled &&
    bufferState.rows !== '' &&
    bufferState.rows !== null &&
    bufferState.rows !== undefined &&
    String(bufferState.rows).trim() !== '';
  const parsedRows = hasRowsInput ? parseInt(bufferState.rows, 10) : NaN;
  const customRows = !isNaN(parsedRows) && parsedRows > 0 ? parsedRows : undefined;
  const rowsDiffer = customRows !== undefined && customRows !== 1000;

  const hasMemoryInput =
    isEnabled &&
    bufferState.memory !== '' &&
    bufferState.memory !== null &&
    bufferState.memory !== undefined &&
    String(bufferState.memory).trim() !== '';
  const parsedMemory = hasMemoryInput ? parseInt(bufferState.memory, 10) : NaN;
  const customMemory = !isNaN(parsedMemory) && parsedMemory > 0 ? parsedMemory : undefined;
  const memoryDiffers = customMemory !== undefined && customMemory !== 50;

  const hasOverheadInput =
    isEnabled &&
    bufferState.phpOverhead !== '' &&
    bufferState.phpOverhead !== null &&
    bufferState.phpOverhead !== undefined &&
    String(bufferState.phpOverhead).trim() !== '';
  const parsedOverhead = hasOverheadInput ? parseFloat(bufferState.phpOverhead) : NaN;
  const customOverhead = !isNaN(parsedOverhead) && parsedOverhead > 0 ? parsedOverhead : undefined;
  const overheadDiffers = customOverhead !== undefined && customOverhead !== 3.5;

  let maxExplicitLimit = 0;
  for (const step of steps) {
    const lim = parseInt(step?.options?.limit, 10);
    if (!isNaN(lim) && lim > maxExplicitLimit) {
      maxExplicitLimit = lim;
    }
  }

  const autoNeeded = maxExplicitLimit > 1000;
  const hasCustomBuffer = isEnabled && (rowsDiffer || memoryDiffers || overheadDiffers);

  let effectiveRows = 1000;
  if (customRows !== undefined) {
    effectiveRows = customRows;
  }
  if (autoNeeded) {
    effectiveRows = Math.max(effectiveRows, maxExplicitLimit);
  }

  const effectiveMemory = customMemory !== undefined ? customMemory : 50;
  const effectiveOverhead = customOverhead !== undefined ? customOverhead : 3.5;

  const required = hasCustomBuffer || (autoNeeded && effectiveRows > 1000);

  return {
    required,
    rows: effectiveRows,
    memory: memoryDiffers || overheadDiffers ? effectiveMemory : undefined,
    overhead: overheadDiffers ? effectiveOverhead : undefined
  };
}

/**
 * Generates canonical, fully-formatted Elscript code for a Database query or pipeline.
 *
 * @param {Object} state
 * @returns {string}
 */
export function generateElscriptDatabasePipeline(state = {}) {
  const mode = state.mode || 'pipeline';

  // Mode 1: Direct Count
  if (mode === 'count') {
    const table = (state.countConfig?.table || state.steps?.[0]?.table || '').trim();
    const filterData = state.countConfig?.filterGroups || state.countConfig?.filters || state.steps?.[0]?.filterGroups || state.steps?.[0]?.filters || [];
    const where = compileFiltersToWhere(filterData);
    const options = {};
    if (state.countConfig?.disablePrefix) {
      options.prefix = '';
    } else if (state.countConfig?.prefix && state.countConfig.prefix.trim()) {
      options.prefix = state.countConfig.prefix.trim();
    }
    const hasOptions = Object.keys(options).length > 0;

    const tableArg = `'${escapeElscriptString(table)}'`;
    if (!where && !hasOptions) {
      return `Database.count(${tableArg})`;
    }

    const whereStr = where ? serializeToElscript(where, 1) : '{}';

    if (!hasOptions) {
      return `Database.count(\n  ${tableArg},\n  ${whereStr.trim()}\n)`;
    }

    const optionsStr = serializeToElscript(options, 1);
    return `Database.count(\n  ${tableArg},\n  ${whereStr.trim()},\n  ${optionsStr.trim()}\n)`;
  }

  // Mode 2: Mirror Pipeline
  const steps = Array.isArray(state.steps) && state.steps.length > 0
    ? state.steps
    : [{ table: '', filters: [], options: {} }];

  const bufferConfig = resolveBufferConfig(state.buffer, steps);
  const terminal = state.terminal || { action: 'fetch' };

  const hasMultipleSteps = steps.length > 1;
  const hasBuffer = bufferConfig.required;
  const isComplex = hasMultipleSteps || hasBuffer || steps.some((s) => {
    const table = (s.table || '').trim();
    const hasWhere = Boolean(compileFiltersToWhere(s.filterGroups || s.filters || []));
    const isDisablePrefix = Boolean(s.disablePrefix ?? s.options?.disablePrefix);
    const userAlias = (s.as || s.options?.as || '').trim();
    const effectiveAlias = userAlias || (isDisablePrefix && table ? table : '');
    const mergedOpts = {
      ...(s.options || {}),
      as: effectiveAlias,
      disablePrefix: isDisablePrefix
    };
    const hasOpts = Boolean(compileStepOptions(mergedOpts));
    return hasWhere || hasOpts;
  });

  // Buffer call
  let bufferLine = '';
  if (hasBuffer) {
    if (bufferConfig.overhead !== undefined) {
      bufferLine = `.buffer(${bufferConfig.rows}, ${bufferConfig.memory ?? 50}, ${bufferConfig.overhead})`;
    } else if (bufferConfig.memory !== undefined) {
      bufferLine = `.buffer(${bufferConfig.rows}, ${bufferConfig.memory})`;
    } else {
      bufferLine = `.buffer(${bufferConfig.rows})`;
    }
  }

  // Mirror calls
  const mirrorSegments = steps.map((step) => {
    const table = (step.table || '').trim();
    const tableArg = `'${escapeElscriptString(table)}'`;
    const where = compileFiltersToWhere(step.filterGroups || step.filters || []);
    const isDisablePrefix = Boolean(step.disablePrefix ?? step.options?.disablePrefix);
    const userAlias = (step.as || step.options?.as || '').trim();
    const effectiveAlias = userAlias || (isDisablePrefix && table ? table : '');
    const mergedOptions = {
      ...(step.options || {}),
      as: effectiveAlias,
      disablePrefix: isDisablePrefix
    };
    const options = compileStepOptions(mergedOptions);

    if (!where && !options) {
      return `.mirror(${tableArg})`;
    }

    if (where && !options) {
      const whereStr = serializeToElscript(where, 1);
      return `.mirror(\n  ${tableArg},\n  ${whereStr.trim()}\n)`;
    }

    if (!where && options) {
      const optionsStr = serializeToElscript(options, 1);
      return `.mirror(\n  ${tableArg},\n  null,\n  ${optionsStr.trim()}\n)`;
    }

    const whereStr = serializeToElscript(where, 1);
    const optionsStr = serializeToElscript(options, 1);
    return `.mirror(\n  ${tableArg},\n  ${whereStr.trim()},\n  ${optionsStr.trim()}\n)`;
  });

  // Terminal call
  let terminalSegment = '.fetch()';
  if (terminal.action === 'flush') {
    terminalSegment = '.flush()';
  } else if (terminal.action === 'query') {
    const rawSql = (terminal.querySql || '').trim();
    if (!rawSql) {
      terminalSegment = '.query(\'\')';
    } else if (rawSql.includes('\n')) {
      const escapedSql = rawSql
        .replace(/\\/g, '\\\\')
        .replace(/'/g, '\\\'')
        .replace(/\r/g, '\\r');
      terminalSegment = `.query('\n${escapedSql}\n')`;
    } else {
      terminalSegment = `.query('${escapeElscriptString(rawSql)}')`;
    }
  }

  // If very simple single mirror without buffer or arguments:
  if (!isComplex && !hasBuffer && terminal.action === 'fetch') {
    const singleTable = (steps[0].table || '').trim();
    return `Database.mirror('${escapeElscriptString(singleTable)}').fetch()`;
  }
  if (!isComplex && !hasBuffer && terminal.action === 'flush') {
    const singleTable = (steps[0].table || '').trim();
    return `Database.mirror('${escapeElscriptString(singleTable)}').flush()`;
  }

  // Assembled multiline expression
  const parts = ['Database'];
  if (bufferLine) {
    parts.push(`  ${bufferLine}`);
  }

  for (const seg of mirrorSegments) {
    if (seg.includes('\n')) {
      // Indent internal lines
      const indented = seg
        .split('\n')
        .map((line, idx) => (idx === 0 ? `  ${line}` : `  ${line}`))
        .join('\n');
      parts.push(indented);
    } else {
      parts.push(`  ${seg}`);
    }
  }

  if (terminalSegment.includes('\n')) {
    const indented = terminalSegment
      .split('\n')
      .map((line, idx) => (idx === 0 ? `  ${line}` : `  ${line}`))
      .join('\n');
    parts.push(indented);
  } else {
    parts.push(`  ${terminalSegment}`);
  }

  return parts.join('\n');
}

/**
 * Converts an ISO local datetime string (YYYY-MM-DDTHH:mm or YYYY-MM-DDTHH:mm:ss)
 * to a MySQL standard DATETIME string (YYYY-MM-DD HH:mm:ss).
 *
 * @param {string} isoLocalStr
 * @returns {string}
 */
export function toMysqlDatetime(isoLocalStr) {
  if (!isoLocalStr || typeof isoLocalStr !== 'string') return '';
  const trimmed = isoLocalStr.trim();
  if (!trimmed) return '';

  if (trimmed.includes('T')) {
    const [date, time] = trimmed.split('T');
    const timeWithSec = (time && time.length === 5) ? `${time}:00` : (time || '00:00:00');
    return `${date} ${timeWithSec}`;
  }

  if (/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/.test(trimmed)) {
    const [date, time] = trimmed.split(/\s+/);
    const timeWithSec = time.length === 5 ? `${time}:00` : time;
    return `${date} ${timeWithSec}`;
  }

  if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
    return `${trimmed} 00:00:00`;
  }

  return trimmed;
}

/**
 * Converts a MySQL DATETIME string (YYYY-MM-DD HH:mm:ss) to an HTML5 datetime-local string (YYYY-MM-DDTHH:mm:ss).
 *
 * @param {string} mysqlStr
 * @returns {string}
 */
export function fromMysqlDatetime(mysqlStr) {
  if (!mysqlStr || typeof mysqlStr !== 'string') return '';
  const trimmed = mysqlStr.trim();
  if (!trimmed) return '';

  if (trimmed.includes('T')) {
    return trimmed.slice(0, 19);
  }

  if (trimmed.includes(' ')) {
    return trimmed.replace(' ', 'T').slice(0, 19);
  }

  if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
    return `${trimmed}T00:00:00`;
  }

  return '';
}

/**
 * Converts an ISO local datetime string or date string to a UNIX timestamp integer (seconds since epoch in local timezone).
 *
 * @param {number|string} val
 * @returns {number|''}
 */
export function toUnixTimestamp(val) {
  if (val === null || val === undefined || val === '') return '';
  if (typeof val === 'number' && Number.isSafeInteger(val)) {
    return val;
  }
  const str = String(val).trim();
  if (!str) return '';
  if (/^-?\d+$/.test(str) && Number.isSafeInteger(Number(str))) {
    return Number(str);
  }

  const isoStr = str.includes(' ') ? str.replace(' ', 'T') : str;
  const date = new Date(isoStr);
  if (isNaN(date.getTime())) return '';
  return Math.floor(date.getTime() / 1000);
}

/**
 * Converts a UNIX timestamp (seconds since epoch) into a local ISO datetime string (YYYY-MM-DDTHH:mm:ss)
 * suitable for an HTML5 datetime-local input.
 *
 * @param {number|string} timestamp
 * @returns {string}
 */
export function fromUnixTimestamp(timestamp) {
  if (timestamp === null || timestamp === undefined || timestamp === '') return '';
  const num = Number(timestamp);
  if (!Number.isFinite(num) || num <= 0) return '';
  const d = new Date(num * 1000);
  if (isNaN(d.getTime())) return '';
  const pad = (n) => String(n).padStart(2, '0');
  const year = d.getFullYear();
  const month = pad(d.getMonth() + 1);
  const day = pad(d.getDate());
  const hours = pad(d.getHours());
  const minutes = pad(d.getMinutes());
  const seconds = pad(d.getSeconds());
  return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
}

