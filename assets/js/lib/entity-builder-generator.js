/**
 * Entity Query Builder - Code Generator Helper for Expression Lab
 *
 * Generates canonical, safe, and idiomatically formatted Elscript code
 * using Entity fluent method chains: Posts, Users, Media.
 * Supported fluent methods: .where(), .or_where(), .order_by(), .limit(), .offset(),
 * and terminal methods: .get(), .count(), .to_sql(), .query().
 */

import {
  escapeElscriptString,
  serializeToElscript,
  parseListValue,
  toMysqlDatetime,
  fromMysqlDatetime,
  toUnixTimestamp,
  fromUnixTimestamp
} from './database-builder-generator.js';

export {
  escapeElscriptString,
  serializeToElscript,
  parseListValue,
  toMysqlDatetime,
  fromMysqlDatetime,
  toUnixTimestamp,
  fromUnixTimestamp
};

export const SUPPORTED_ENTITIES = ['Posts', 'Users', 'Media'];

export const ENTITY_CONFIGS = {
  Posts: {
    name: 'Posts',
    table: 'posts',
    label: 'Posts',
    description: 'WordPress posts, pages, and custom post types',
    baseConditions: null
  },
  Users: {
    name: 'Users',
    table: 'users',
    label: 'Users',
    description: 'WordPress user accounts',
    baseConditions: null
  },
  Media: {
    name: 'Media',
    table: 'posts',
    label: 'Media',
    description: 'WordPress media library attachments',
    baseConditions: [
      { column: 'post_type', operator: '=', value: 'attachment' }
    ]
  }
};

export const ENTITY_OPERATORS = [
  { value: '=', label: '=' },
  { value: '$in', label: 'IN' },
  { value: '$notIn', label: 'NOT IN' },
  { value: '$like', label: 'LIKE' },
  { value: '$notLike', label: 'NOT LIKE' },
  { value: '$between', label: 'BETWEEN' },
  { value: '$gt', label: '>' },
  { value: '$gte', label: '>=' },
  { value: '$lt', label: '<' },
  { value: '$lte', label: '<=' },
  { value: '$ne', label: '!=' }
];

/**
 * Normalizes operator strings to the canonical Elscript format expected by QueryBuilder.
 *
 * @param {string} op
 * @returns {string}
 */
export function normalizeOperator(op) {
  const normalized = (op || '=').trim();
  switch (normalized) {
    case '$in':
    case 'in':
    case 'IN':
      return 'in';
    case '$notIn':
    case 'not in':
    case 'NOT IN':
      return 'not in';
    case '$like':
    case 'like':
    case 'LIKE':
      return 'like';
    case '$notLike':
    case 'not like':
    case 'NOT LIKE':
      return 'not like';
    case '$between':
    case 'between':
    case 'BETWEEN':
      return 'between';
    case '$gt':
    case '>':
      return '>';
    case '$gte':
    case '>=':
      return '>=';
    case '$lt':
    case '<':
      return '<';
    case '$lte':
    case '<=':
      return '<=';
    case '$ne':
    case '!=':
    case '<>':
      return '!=';
    case '=':
    default:
      return '=';
  }
}

/**
 * Converts a plain numeric string to a number if safe integer, preserving strings otherwise.
 *
 * @param {string} raw
 * @returns {number|string}
 */
function toScalarValue(raw) {
  if (typeof raw === 'number') return raw;
  const str = String(raw ?? '').trim();
  if (/^-?(0|[1-9]\d*)$/.test(str) && Number.isSafeInteger(Number(str))) {
    return Number(str);
  }
  return str;
}

/**
 * Formats a single condition into an Elscript method call string (.where or .or_where).
 *
 * @param {string} methodName 'where' | 'or_where'
 * @param {Object} filter
 * @returns {string|null}
 */
export function formatConditionCall(methodName, filter) {
  if (!filter || !filter.enabled) return null;
  const col = (filter.column || '').trim();
  if (!col) return null;

  const escapedCol = escapeElscriptString(col);
  const normOp = normalizeOperator(filter.operator);

  // Range comparison (BETWEEN)
  if (normOp === 'between') {
    const minRaw = (filter.valueBetweenMin ?? '').toString().trim();
    const maxRaw = (filter.valueBetweenMax ?? '').toString().trim();
    if (minRaw === '' && maxRaw === '') return null;
    const minVal = toScalarValue(minRaw);
    const maxVal = toScalarValue(maxRaw);
    const serializedMin = serializeToElscript(minVal);
    const serializedMax = serializeToElscript(maxVal);
    return `${methodName}('${escapedCol}', 'between', [${serializedMin}, ${serializedMax}])`;
  }

  // Set membership (IN / NOT IN)
  if (normOp === 'in' || normOp === 'not in') {
    const list = parseListValue(filter.value);
    if (!list || list.length === 0) return null;
    const serializedList = serializeToElscript(list);
    return `${methodName}('${escapedCol}', '${normOp}', ${serializedList})`;
  }

  // Pattern matching (LIKE / NOT LIKE)
  if (normOp === 'like' || normOp === 'not like') {
    const val = typeof filter.value === 'string' ? filter.value.trim() : String(filter.value ?? '');
    if (val === '') return null;
    return `${methodName}('${escapedCol}', '${normOp}', '${escapeElscriptString(val)}')`;
  }

  // Equality (=)
  if (normOp === '=') {
    const rawVal = (filter.value ?? '').toString().trim();
    if (rawVal === '') return null;
    const val = toScalarValue(rawVal);
    return `${methodName}('${escapedCol}', ${serializeToElscript(val)})`;
  }

  // Explicit relational comparisons (!=, >, >=, <, <=)
  const rawVal = (filter.value ?? '').toString().trim();
  if (rawVal === '') return null;
  const val = toScalarValue(rawVal);
  return `${methodName}('${escapedCol}', '${normOp}', ${serializeToElscript(val)})`;
}

/**
 * Detects duplicate column names within a single filter group.
 *
 * @param {Array} group
 * @returns {Array<string>} List of duplicate column names in lowercase.
 */
export function getDuplicateColumnsInGroup(group) {
  if (!Array.isArray(group)) return [];
  const counts = {};
  for (const f of group) {
    if (!f || !f.enabled) continue;
    const col = (f.column || '').trim().toLowerCase();
    if (!col) continue;
    counts[col] = (counts[col] || 0) + 1;
  }
  return Object.keys(counts).filter((col) => counts[col] > 1);
}

/**
 * Detects duplicate column names within order by clauses.
 *
 * @param {Array} orderBys
 * @returns {Array<string>} List of duplicate column names in lowercase.
 */
export function getDuplicateOrderColumns(orderBys) {
  if (!Array.isArray(orderBys)) return [];
  const counts = {};
  for (const o of orderBys) {
    if (!o) continue;
    const col = (o.column || '').trim().toLowerCase();
    if (!col) continue;
    counts[col] = (counts[col] || 0) + 1;
  }
  return Object.keys(counts).filter((col) => counts[col] > 1);
}

/**
 * Generates canonical, idiomatic Elscript code for an entity query.
 *
 * @param {Object} config
 * @param {string} [config.entity='Posts'] Target service entity ('Posts', 'Users', 'Media')
 * @param {Array} [config.filters=[]] Filter groups (DNF: array of groups, each containing filter rows)
 * @param {Array} [config.filterGroups] Alias for config.filters
 * @param {Array} [config.orderBys=[]] Array of { column, direction }
 * @param {number|null} [config.limit=null] Result limit
 * @param {number|null} [config.offset=null] Result offset
 * @param {Object} [config.terminal={ action: 'get' }] Terminal action { action, isCountSql, querySql }
 * @returns {string} Formatted Elscript code
 */
export function generateElscriptEntityQuery(config = {}) {
  const entity = (config.entity || 'Posts').trim() || 'Posts';

  // Normalize filter groups
  let rawGroups = config.filterGroups ?? config.filters ?? [];
  if (!Array.isArray(rawGroups)) {
    rawGroups = [];
  } else if (rawGroups.length > 0 && !Array.isArray(rawGroups[0])) {
    // Single 1D group provided
    rawGroups = [rawGroups];
  }

  const orderBys = Array.isArray(config.orderBys) ? config.orderBys : [];
  const rawLimit = config.limit;
  const rawOffset = config.offset;
  const terminal = config.terminal || { action: 'get' };

  const segments = [];
  let isFirstConditionOverall = true;

  // 1. Process filter groups with DNF logic
  for (let gIdx = 0; gIdx < rawGroups.length; gIdx++) {
    const group = rawGroups[gIdx];
    if (!Array.isArray(group)) continue;

    let isFirstInThisGroup = true;

    for (const filter of group) {
      if (!filter || !filter.enabled) continue;
      const col = (filter.column || '').trim();
      if (!col) continue;

      // Group 1 uses .where(...)
      // Group 2..N: First valid condition uses .or_where(...), remaining use .where(...)
      const methodName = isFirstConditionOverall
        ? 'where'
        : (isFirstInThisGroup && gIdx > 0 ? 'or_where' : 'where');

      const callStr = formatConditionCall(methodName, filter);
      if (callStr) {
        segments.push(callStr);
        isFirstConditionOverall = false;
        isFirstInThisGroup = false;
      }
    }
  }

  // 2. Sorting (order_by)
  for (const ord of orderBys) {
    if (!ord) continue;
    const col = (ord.column || '').trim();
    if (!col) continue;
    const dir = (ord.direction || 'ASC').toUpperCase() === 'DESC' ? 'DESC' : 'ASC';
    segments.push(`order_by('${escapeElscriptString(col)}', '${dir}')`);
  }

  // 3. Limit
  if (rawLimit !== undefined && rawLimit !== null && rawLimit !== '') {
    const numLimit = Number(rawLimit);
    if (Number.isInteger(numLimit) && numLimit > 0) {
      segments.push(`limit(${numLimit})`);
    }
  }

  // 4. Offset
  if (rawOffset !== undefined && rawOffset !== null && rawOffset !== '') {
    const numOffset = Number(rawOffset);
    if (Number.isInteger(numOffset) && numOffset > 0) {
      segments.push(`offset(${numOffset})`);
    }
  }

  // 5. Terminal execution action
  let terminalCall = 'get()';
  if (terminal.action === 'count') {
    terminalCall = 'count()';
  } else if (terminal.action === 'to_sql') {
    terminalCall = terminal.isCountSql ? 'to_sql(true)' : 'to_sql()';
  } else if (terminal.action === 'query') {
    const rawSql = (terminal.querySql || '').trim();
    if (!rawSql) {
      terminalCall = 'query(\'\')';
    } else if (rawSql.includes('\n')) {
      const escapedSql = rawSql
        .replace(/\\/g, '\\\\')
        .replace(/'/g, '\\\'')
        .replace(/\r/g, '\\r');
      terminalCall = `query('\n${escapedSql}\n')`;
    } else {
      terminalCall = `query('${escapeElscriptString(rawSql)}')`;
    }
  } else {
    terminalCall = 'get()';
  }

  segments.push(terminalCall);

  // Format single line vs chained multiline
  if (segments.length === 1) {
    return `${entity}.${terminalCall}`;
  }

  const [first, ...rest] = segments;
  const chain = rest.map((seg) => `    .${seg}`).join('\n');
  return `${entity}.${first}\n${chain}`;
}
