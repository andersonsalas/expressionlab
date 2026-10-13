/**
 * Standalone PHP Serialization Parser in JavaScript.
 *
 * Operates over a Uint8Array buffer to accurately handle UTF-8 byte lengths
 * (e.g. multibyte characters, accents, emojis) in strict alignment with PHP Zend Engine.
 *
 * The parser never executes or instantiates anything: it only produces a typed,
 * acyclic AST. References (`r:N;` / `R:N;`) are kept as leaf nodes and annotated
 * with metadata about their target slot, so cyclic structures can be rendered safely.
 */

const textEncoder = new TextEncoder();
const textDecoder = new TextDecoder('utf-8');

// ASCII byte constants
const CHAR_COLON = 58;       // ':'
const CHAR_SEMICOLON = 59;   // ';'
const CHAR_LBRACE = 123;     // '{'
const CHAR_RBRACE = 125;     // '}'
const CHAR_QUOTE = 34;       // '"'
const CHAR_NULL = 0;         // '\0'

/**
 * Maximum nesting depth accepted by the parser. Deeper payloads are rejected with a
 * PhpParseError instead of risking a "Maximum call stack size exceeded" RangeError.
 */
export const MAX_PARSE_DEPTH = 512;

/**
 * Non-enumerable brand attached to every node produced by this parser. It lets the
 * data-tree adapter distinguish genuine AST nodes from arbitrary JSON objects that
 * happen to contain keys such as `type` or `value`.
 */
export const PHP_AST_BRAND = Symbol.for('expressionlab.phpSerializedAst');

const UNSIGNED_INT_REGEX = /^\d+$/;
const SIGNED_INT_REGEX = /^[+-]?\d+$/;
const DOUBLE_REGEX = /^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/;

export class PhpParseError extends Error {
  constructor(message, offset) {
    super(`${message} at byte offset ${offset}`);
    this.name = 'PhpParseError';
    this.offset = offset;
  }
}

/**
 * Attaches the non-enumerable AST brand to a node.
 *
 * @param {object} node
 * @returns {object} The same node.
 */
function brand(node) {
  Object.defineProperty(node, PHP_AST_BRAND, { value: true, enumerable: false });
  return node;
}

/**
 * State container for recursive descent parsing.
 */
class PhpBufferReader {
  constructor(bytes) {
    this.bytes = bytes;
    this.pos = 0;
    this.length = bytes.length;
    // 1-indexed (slot N lives at index N - 1) table of values that can be referenced.
    this.slots = [];
    // Reference nodes collected during parsing, resolved once the whole payload is read.
    this.references = [];
  }

  peek() {
    return this.pos < this.length ? this.bytes[this.pos] : null;
  }

  next() {
    if (this.pos >= this.length) {
      throw new PhpParseError('Unexpected end of input', this.pos);
    }
    return this.bytes[this.pos++];
  }

  expect(expectedByte, charLabel) {
    const actual = this.next();
    if (actual !== expectedByte) {
      const charStr = charLabel || String.fromCharCode(expectedByte);
      throw new PhpParseError(`Expected '${charStr}', got '${String.fromCharCode(actual)}'`, this.pos - 1);
    }
  }

  readUntil(delimiterByte) {
    const start = this.pos;
    while (this.pos < this.length && this.bytes[this.pos] !== delimiterByte) {
      this.pos++;
    }
    if (this.pos >= this.length) {
      throw new PhpParseError(`Delimiter '${String.fromCharCode(delimiterByte)}' not found before end of input`, start);
    }
    const chunk = this.bytes.subarray(start, this.pos);
    this.pos++; // Skip delimiter
    return textDecoder.decode(chunk);
  }

  /**
   * Reads an unsigned decimal integer terminated by the given delimiter
   * (used for lengths, counts and reference indexes).
   */
  readLength(delimiterByte, label) {
    const start = this.pos;
    const raw = this.readUntil(delimiterByte);
    if (!UNSIGNED_INT_REGEX.test(raw)) {
      throw new PhpParseError(`Invalid ${label}: '${raw}'`, start);
    }
    const value = Number(raw);
    if (!Number.isSafeInteger(value)) {
      throw new PhpParseError(`Invalid ${label}: '${raw}'`, start);
    }
    return value;
  }

  readBytes(count) {
    if (!Number.isInteger(count) || count < 0) {
      throw new PhpParseError(`Invalid byte count ${count}`, this.pos);
    }
    if (this.pos + count > this.length) {
      throw new PhpParseError(`Unexpected end of input reading ${count} bytes`, this.pos);
    }
    const chunk = this.bytes.subarray(this.pos, this.pos + count);
    this.pos += count;
    return chunk;
  }
}

/**
 * Parses a string value encoded as s:<length>:"<content>";
 */
function parseString(reader) {
  reader.expect(CHAR_COLON);
  const byteLength = reader.readLength(CHAR_COLON, 'string byte length');

  reader.expect(CHAR_QUOTE);
  const stringBytes = reader.readBytes(byteLength);
  reader.expect(CHAR_QUOTE);
  reader.expect(CHAR_SEMICOLON);

  return {
    type: 'string',
    value: textDecoder.decode(stringBytes),
    byteLength
  };
}

/**
 * Parses an integer value encoded as i:<number>;
 *
 * Integers outside the IEEE-754 safe range (e.g. PHP_INT_MAX on 64-bit builds)
 * keep their exact textual representation in `raw` to avoid silent precision loss.
 */
function parseInteger(reader) {
  reader.expect(CHAR_COLON);
  const start = reader.pos;
  const rawNum = reader.readUntil(CHAR_SEMICOLON);
  if (!SIGNED_INT_REGEX.test(rawNum)) {
    throw new PhpParseError(`Invalid integer: '${rawNum}'`, start);
  }
  const value = Number(rawNum);
  if (!Number.isSafeInteger(value)) {
    return { type: 'integer', value, raw: rawNum.replace(/^\+/, '') };
  }
  return { type: 'integer', value };
}

/**
 * Parses a double/float value encoded as d:<number>;
 */
function parseDouble(reader) {
  reader.expect(CHAR_COLON);
  const start = reader.pos;
  const rawNum = reader.readUntil(CHAR_SEMICOLON);
  let value;
  if (rawNum === 'NAN') {
    value = NaN;
  } else if (rawNum === 'INF') {
    value = Infinity;
  } else if (rawNum === '-INF') {
    value = -Infinity;
  } else if (DOUBLE_REGEX.test(rawNum)) {
    value = Number(rawNum);
  } else {
    throw new PhpParseError(`Invalid double: '${rawNum}'`, start);
  }
  return { type: 'double', value, raw: rawNum };
}

/**
 * Parses a boolean value encoded as b:<0|1>;
 */
function parseBoolean(reader) {
  reader.expect(CHAR_COLON);
  const start = reader.pos;
  const rawVal = reader.readUntil(CHAR_SEMICOLON);
  if (rawVal !== '0' && rawVal !== '1') {
    throw new PhpParseError(`Invalid boolean: '${rawVal}'`, start);
  }
  return { type: 'boolean', value: rawVal === '1' };
}

/**
 * Parses null encoded as N;
 */
function parseNull(reader) {
  reader.expect(CHAR_SEMICOLON);
  return { type: 'null', value: null };
}

/**
 * Parses reference encoded as r:<index>; (object handle reuse) or R:<index>; (PHP `&` reference).
 */
function parseReference(reader, isAlias) {
  reader.expect(CHAR_COLON);
  const start = reader.pos;
  const refIndex = reader.readLength(CHAR_SEMICOLON, 'reference index');
  if (refIndex < 1) {
    throw new PhpParseError(`Invalid reference index: ${refIndex}`, start);
  }
  const node = { type: 'reference', refIndex, isAlias };
  reader.references.push(node);
  return node;
}

/**
 * Parses an array key or object property name. PHP only allows integer or string keys,
 * and keys never occupy a reference slot.
 */
function parseKey(reader) {
  const offset = reader.pos;
  const typeChar = String.fromCharCode(reader.next());
  if (typeChar === 'i') {
    return brand(parseInteger(reader));
  }
  if (typeChar === 's') {
    return brand(parseString(reader));
  }
  throw new PhpParseError(`Invalid key type '${typeChar}' (only integer and string keys are allowed)`, offset);
}

/**
 * Parses an array encoded as a:<count>:{<elements>}
 */
function parseArray(reader, depth) {
  reader.expect(CHAR_COLON);
  const count = reader.readLength(CHAR_COLON, 'array count');

  reader.expect(CHAR_LBRACE);
  const entries = [];

  for (let i = 0; i < count; i++) {
    const key = parseKey(reader);
    const value = parseValue(reader, depth + 1);
    entries.push({ key, value });
  }

  reader.expect(CHAR_RBRACE);
  return {
    type: 'array',
    count,
    entries
  };
}

/**
 * Decodes property visibility from PHP serialized property names:
 * \0*\0property -> protected
 * \0ClassName\0property -> private
 * property -> public
 */
function extractPropertyMetadata(propNameString) {
  if (typeof propNameString !== 'string' || propNameString.charCodeAt(0) !== CHAR_NULL) {
    return { name: propNameString, visibility: 'public' };
  }

  const parts = propNameString.split('\0');
  if (parts.length >= 3) {
    const scope = parts[1];
    const name = parts.slice(2).join('\0');
    if (scope === '*') {
      return { name, visibility: 'protected' };
    }
    return { name, visibility: 'private', ownerClass: scope };
  }

  return { name: propNameString, visibility: 'public' };
}

/**
 * Reads a quoted class name preceded by its byte length: <len>:"<ClassName>"
 */
function readClassName(reader) {
  const classLen = reader.readLength(CHAR_COLON, 'class name length');
  reader.expect(CHAR_QUOTE);
  const classBytes = reader.readBytes(classLen);
  reader.expect(CHAR_QUOTE);
  return textDecoder.decode(classBytes);
}

/**
 * Parses an object encoded as O:<len>:"<ClassName>":<propCount>:{<props>}
 */
function parseObject(reader, depth) {
  reader.expect(CHAR_COLON);
  const className = readClassName(reader);

  reader.expect(CHAR_COLON);
  const propertyCount = reader.readLength(CHAR_COLON, 'property count');

  reader.expect(CHAR_LBRACE);
  const properties = [];

  for (let i = 0; i < propertyCount; i++) {
    const rawKeyNode = parseKey(reader);
    const rawKey = rawKeyNode.type === 'string' ? rawKeyNode.value : String(rawKeyNode.value);
    const meta = extractPropertyMetadata(rawKey);
    const valueNode = parseValue(reader, depth + 1);

    properties.push({
      ...meta,
      rawKeyNode,
      value: valueNode
    });
  }

  reader.expect(CHAR_RBRACE);

  return {
    type: 'object',
    className,
    propertyCount,
    properties
  };
}

/**
 * Parses custom serialized object (C:<len>:"<ClassName>":<dataLen>:{<data>})
 */
function parseCustomObject(reader) {
  reader.expect(CHAR_COLON);
  const className = readClassName(reader);

  reader.expect(CHAR_COLON);
  const dataLen = reader.readLength(CHAR_COLON, 'custom object data length');

  reader.expect(CHAR_LBRACE);
  const dataBytes = reader.readBytes(dataLen);
  reader.expect(CHAR_RBRACE);

  return {
    type: 'custom_object',
    className,
    rawData: textDecoder.decode(dataBytes)
  };
}

/**
 * Parses PHP 8.1 Enum (E:<len>:"<ClassName>:<Case>";)
 */
function parseEnum(reader) {
  reader.expect(CHAR_COLON);
  const len = reader.readLength(CHAR_COLON, 'enum length');

  reader.expect(CHAR_QUOTE);
  const start = reader.pos;
  const enumBytes = reader.readBytes(len);
  reader.expect(CHAR_QUOTE);
  reader.expect(CHAR_SEMICOLON);

  const enumPath = textDecoder.decode(enumBytes);
  const separator = enumPath.indexOf(':');
  if (separator <= 0 || separator === enumPath.length - 1) {
    throw new PhpParseError(`Invalid enum identifier: '${enumPath}'`, start);
  }

  return {
    type: 'enum',
    className: enumPath.slice(0, separator),
    caseName: enumPath.slice(separator + 1),
    raw: enumPath
  };
}

/**
 * Main dispatcher for parsing any single PHP serialized value.
 *
 * Every value except `R:` occupies a slot in the 1-indexed reference table, assigned
 * in pre-order (before children are parsed), mirroring php_var_unserialize().
 */
function parseValue(reader, depth = 0) {
  if (depth > MAX_PARSE_DEPTH) {
    throw new PhpParseError(`Maximum nesting depth of ${MAX_PARSE_DEPTH} exceeded`, reader.pos);
  }

  const offset = reader.pos;
  const typeByte = reader.next();
  const typeChar = String.fromCharCode(typeByte);

  let slotIndex = -1;
  if (typeChar !== 'R') {
    slotIndex = reader.slots.length;
    reader.slots.push(null);
  }

  let node;
  switch (typeChar) {
    case 'i':
      node = parseInteger(reader);
      break;
    case 'd':
      node = parseDouble(reader);
      break;
    case 'b':
      node = parseBoolean(reader);
      break;
    case 's':
      node = parseString(reader);
      break;
    case 'a':
      node = parseArray(reader, depth);
      break;
    case 'O':
      node = parseObject(reader, depth);
      break;
    case 'C':
      node = parseCustomObject(reader);
      break;
    case 'E':
      node = parseEnum(reader);
      break;
    case 'N':
      node = parseNull(reader);
      break;
    case 'r':
      node = parseReference(reader, false);
      break;
    case 'R':
      node = parseReference(reader, true);
      break;
    default:
      throw new PhpParseError(`Unknown PHP serialized token type '${typeChar}'`, offset);
  }

  if (slotIndex >= 0) {
    reader.slots[slotIndex] = node;
  }

  return brand(node);
}

/**
 * Annotates reference nodes with a summary of the slot they point to. The target node
 * itself is never embedded, so the AST stays acyclic even for self-referencing payloads.
 */
function resolveReferences(reader) {
  for (const ref of reader.references) {
    const target = reader.slots[ref.refIndex - 1];
    if (!target) {
      // Unknown slot (e.g. a slot created inside an opaque C: payload). Kept as-is.
      ref.resolved = false;
      continue;
    }
    ref.resolved = true;
    ref.targetType = target.type;
    if (target.className) {
      ref.targetClassName = target.className;
    }
  }
}

/**
 * Parses a complete PHP serialized string into an AST.
 *
 * @param {string} input - Raw serialized string.
 * @returns {object} Root AST node.
 * @throws {PhpParseError} If the string is invalid or not fully consumed.
 */
export function parsePhpSerialized(input) {
  if (typeof input !== 'string') {
    throw new TypeError('Input must be a string');
  }

  const bytes = textEncoder.encode(input.trim());
  const reader = new PhpBufferReader(bytes);
  const result = parseValue(reader, 0);

  if (reader.pos < reader.length) {
    throw new PhpParseError(`Trailing characters after serialized payload (consumed ${reader.pos} of ${reader.length} bytes)`, reader.pos);
  }

  resolveReferences(reader);

  return result;
}

/**
 * Validates whether a given string is a valid PHP serialized payload.
 *
 * @param {string} input - Raw string.
 * @returns {boolean}
 */
export function isValidPhpSerialized(input) {
  try {
    parsePhpSerialized(input);
    return true;
  } catch {
    return false;
  }
}
