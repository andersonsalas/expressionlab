import {
  parsePhpSerialized,
  isValidPhpSerialized,
  PhpParseError,
  PHP_AST_BRAND,
  MAX_PARSE_DEPTH,
} from '../../lib/php-serializer/parser.js';

describe('php-serializer/parser.js', () => {
  describe('scalars and null', () => {
    it('parses null correctly', () => {
      const result = parsePhpSerialized('N;');
      expect(result).toEqual({ type: 'null', value: null });
    });

    it('parses booleans correctly', () => {
      expect(parsePhpSerialized('b:1;')).toEqual({ type: 'boolean', value: true });
      expect(parsePhpSerialized('b:0;')).toEqual({ type: 'boolean', value: false });
    });

    it('parses positive and negative integers', () => {
      expect(parsePhpSerialized('i:42;')).toEqual({ type: 'integer', value: 42 });
      expect(parsePhpSerialized('i:-99;')).toEqual({ type: 'integer', value: -99 });
      expect(parsePhpSerialized('i:0;')).toEqual({ type: 'integer', value: 0 });
    });

    it('parses double / floats including special values', () => {
      const resFloat = parsePhpSerialized('d:3.14159;');
      expect(resFloat.type).toBe('double');
      expect(resFloat.value).toBeCloseTo(3.14159);

      const resNan = parsePhpSerialized('d:NAN;');
      expect(resNan.type).toBe('double');
      expect(Number.isNaN(resNan.value)).toBe(true);

      const resInf = parsePhpSerialized('d:INF;');
      expect(resInf.type).toBe('double');
      expect(resInf.value).toBe(Infinity);

      const resNegInf = parsePhpSerialized('d:-INF;');
      expect(resNegInf.type).toBe('double');
      expect(resNegInf.value).toBe(-Infinity);
    });
  });

  describe('strings and UTF-8 multibyte byte lengths', () => {
    it('parses standard ASCII string', () => {
      const res = parsePhpSerialized('s:11:"hello world";');
      expect(res).toEqual({
        type: 'string',
        value: 'hello world',
        byteLength: 11
      });
    });

    it('parses empty string', () => {
      const res = parsePhpSerialized('s:0:"";');
      expect(res).toEqual({
        type: 'string',
        value: '',
        byteLength: 0
      });
    });

    it('accurately parses UTF-8 multibyte accents (5 characters, 10 bytes)', () => {
      // "áéíóú" has 5 chars in JS but 10 bytes in UTF-8
      const res = parsePhpSerialized('s:10:"áéíóú";');
      expect(res.type).toBe('string');
      expect(res.value).toBe('áéíóú');
      expect(res.byteLength).toBe(10);
    });

    it('accurately parses UTF-8 emoji (1 emoji, 4 bytes)', () => {
      // 🚀 is 4 bytes in UTF-8
      const res = parsePhpSerialized('s:4:"🚀";');
      expect(res.type).toBe('string');
      expect(res.value).toBe('🚀');
      expect(res.byteLength).toBe(4);
    });
  });

  describe('arrays', () => {
    it('parses empty array', () => {
      const res = parsePhpSerialized('a:0:{}');
      expect(res).toEqual({
        type: 'array',
        count: 0,
        entries: []
      });
    });

    it('parses sequential indexed array', () => {
      const res = parsePhpSerialized('a:2:{i:0;s:3:"foo";i:1;s:3:"bar";}');
      expect(res.type).toBe('array');
      expect(res.count).toBe(2);
      expect(res.entries.length).toBe(2);
      expect(res.entries[0].key).toEqual({ type: 'integer', value: 0 });
      expect(res.entries[0].value).toEqual({ type: 'string', value: 'foo', byteLength: 3 });
      expect(res.entries[1].key).toEqual({ type: 'integer', value: 1 });
      expect(res.entries[1].value).toEqual({ type: 'string', value: 'bar', byteLength: 3 });
    });

    it('parses associative mixed array with non-contiguous keys', () => {
      const res = parsePhpSerialized('a:2:{s:4:"site";s:14:"Expression Lab";i:42;b:1;}');
      expect(res.type).toBe('array');
      expect(res.count).toBe(2);
      expect(res.entries[0].key.value).toBe('site');
      expect(res.entries[0].value.value).toBe('Expression Lab');
      expect(res.entries[1].key.value).toBe(42);
      expect(res.entries[1].value.value).toBe(true);
    });
  });

  describe('objects and property visibility', () => {
    it('parses object with public properties', () => {
      const res = parsePhpSerialized('O:8:"stdClass":1:{s:5:"title";s:4:"test";}');
      expect(res.type).toBe('object');
      expect(res.className).toBe('stdClass');
      expect(res.propertyCount).toBe(1);
      expect(res.properties[0]).toEqual({
        name: 'title',
        visibility: 'public',
        rawKeyNode: expect.anything(),
        value: { type: 'string', value: 'test', byteLength: 4 }
      });
    });

    it('parses protected and private properties encoded with null bytes', () => {
      // \0*\0protectedProp -> 1 + 1 + 1 + 13 = 16 bytes
      // \0WP_User\0privateProp -> 1 + 7 + 1 + 11 = 20 bytes
      const serialized = 'O:7:"WP_User":2:{s:16:"\0*\0protectedProp";s:9:"protected";s:20:"\0WP_User\0privateProp";s:7:"private";}';
      const res = parsePhpSerialized(serialized);

      expect(res.type).toBe('object');
      expect(res.className).toBe('WP_User');
      expect(res.propertyCount).toBe(2);

      const propProtected = res.properties[0];
      expect(propProtected.name).toBe('protectedProp');
      expect(propProtected.visibility).toBe('protected');
      expect(propProtected.value.value).toBe('protected');

      const propPrivate = res.properties[1];
      expect(propPrivate.name).toBe('privateProp');
      expect(propPrivate.visibility).toBe('private');
      expect(propPrivate.ownerClass).toBe('WP_User');
      expect(propPrivate.value.value).toBe('private');
    });
  });

  describe('references and special structures', () => {
    it('parses pointer reference (r:1;) and alias reference (R:2;)', () => {
      const res1 = parsePhpSerialized('r:1;');
      expect(res1).toMatchObject({ type: 'reference', refIndex: 1, isAlias: false });

      const res2 = parsePhpSerialized('R:2;');
      expect(res2).toMatchObject({ type: 'reference', refIndex: 2, isAlias: true, resolved: false });
    });

    it('parses custom serializable object (C:...)', () => {
      const res = parsePhpSerialized('C:9:"ArrayData":4:{test}');
      expect(res.type).toBe('custom_object');
      expect(res.className).toBe('ArrayData');
      expect(res.rawData).toBe('test');
    });
  });

  describe('error handling and validation', () => {
    it('throws error for invalid input types', () => {
      expect(() => parsePhpSerialized(123)).toThrow(TypeError);
      expect(() => parsePhpSerialized(null)).toThrow(TypeError);
    });

    it('throws PhpParseError when string length does not match byte buffer', () => {
      // Specifies 10 bytes but content has only 3
      expect(() => parsePhpSerialized('s:10:"abc";')).toThrow();
    });

    it('throws PhpParseError on trailing characters', () => {
      expect(() => parsePhpSerialized('i:42;extra_garbage')).toThrow();
    });

    it('isValidPhpSerialized returns true for valid payloads and false for corrupt/plain text', () => {
      expect(isValidPhpSerialized('a:1:{s:3:"key";s:5:"value";}')).toBe(true);
      expect(isValidPhpSerialized('O:8:"stdClass":0:{}')).toBe(true);
      expect(isValidPhpSerialized('Hello World')).toBe(false);
      expect(isValidPhpSerialized('{"json": "not php"}')).toBe(false);
      expect(isValidPhpSerialized('a:1:{broken')).toBe(false);
    });
  });

  describe('audit 019: UTF-8 offsets across scripts', () => {
    const family = '\u{1F468}\u200D\u{1F469}\u200D\u{1F467}'; // 3 emoji + 2 ZWJ = 18 bytes

    it('keeps offsets aligned after Cyrillic, CJK and ZWJ sequences (values and keys)', () => {
      const payload = `a:3:{i:0;s:12:"Привет";i:1;s:9:"日本語";s:18:"${family}";s:3:"end";}`;
      const res = parsePhpSerialized(payload);
      expect(res.entries[0].value.value).toBe('Привет');
      expect(res.entries[1].value.value).toBe('日本語');
      expect(res.entries[2].key.value).toBe(family);
      expect(res.entries[2].value.value).toBe('end');
    });

    it('rejects character counts used instead of byte lengths', () => {
      // "áéíóú" is 5 characters but 10 bytes.
      expect(() => parsePhpSerialized('s:5:"áéíóú";')).toThrow(PhpParseError);
    });
  });

  describe('audit 019: visibility, classes and enums', () => {
    it('extracts namespaced private owners', () => {
      const key = '\0App\\Models\\User\0secret';
      const bytes = new TextEncoder().encode(key).length;
      const res = parsePhpSerialized(`O:15:"App\\Models\\User":1:{s:${bytes}:"${key}";i:1;}`);
      expect(res.className).toBe('App\\Models\\User');
      expect(res.properties[0]).toMatchObject({ name: 'secret', visibility: 'private', ownerClass: 'App\\Models\\User' });
    });

    it('preserves __PHP_Incomplete_Class payloads verbatim', () => {
      const res = parsePhpSerialized('O:22:"__PHP_Incomplete_Class":1:{s:27:"__PHP_Incomplete_Class_Name";s:7:"Missing";}');
      expect(res.className).toBe('__PHP_Incomplete_Class');
      expect(res.properties[0].value.value).toBe('Missing');
    });

    it('parses PHP 8.1 enums and rejects malformed identifiers', () => {
      expect(parsePhpSerialized('E:11:"Suit:Hearts";')).toMatchObject({ type: 'enum', className: 'Suit', caseName: 'Hearts' });
      expect(() => parsePhpSerialized('E:4:"Suit";')).toThrow(PhpParseError);
    });

    it('reads C: payloads by length even when they contain delimiters', () => {
      const res = parsePhpSerialized('C:11:"ArrayObject":21:{x:i:0;a:0:{};m:a:0:{}}');
      expect(res).toMatchObject({ type: 'custom_object', className: 'ArrayObject', rawData: 'x:i:0;a:0:{};m:a:0:{}' });
    });
  });

  describe('audit 019: keys and numbers', () => {
    it('supports negative, empty and delimiter-laden keys', () => {
      const res = parsePhpSerialized('a:3:{i:-5;s:1:"n";s:0:"";s:1:"e";s:6:"a;b:{}";s:1:"d";}');
      expect(res.entries.map((e) => e.key.value)).toEqual([-5, '', 'a;b:{}']);
    });

    it('rejects non-scalar keys', () => {
      expect(() => parsePhpSerialized('a:1:{a:0:{}i:1;}')).toThrow(PhpParseError);
      expect(() => parsePhpSerialized('a:1:{N;i:1;}')).toThrow(PhpParseError);
    });

    it('keeps the exact text of integers beyond the JS safe range', () => {
      expect(parsePhpSerialized('i:9223372036854775807;')).toMatchObject({ type: 'integer', raw: '9223372036854775807' });
    });

    it('accepts exponent doubles and rejects garbage numbers / booleans', () => {
      expect(parsePhpSerialized('d:1.0E+25;').value).toBe(1e25);
      expect(() => parsePhpSerialized('i:12abc;')).toThrow(PhpParseError);
      expect(() => parsePhpSerialized('d:1.5xyz;')).toThrow(PhpParseError);
      expect(() => parsePhpSerialized('b:2;')).toThrow(PhpParseError);
    });
  });

  describe('audit 019: reference table', () => {
    it('resolves self references without embedding cycles', () => {
      const res = parsePhpSerialized('O:8:"stdClass":1:{s:4:"self";r:1;}');
      const ref = res.properties[0].value;
      expect(ref).toMatchObject({ type: 'reference', refIndex: 1, resolved: true, targetType: 'object', targetClassName: 'stdClass' });
      expect(ref.target).toBeUndefined();
      expect(() => JSON.stringify(res)).not.toThrow();
    });

    it('numbers slots in pre-order and does not assign a slot to R:', () => {
      // Slots: 1 = array, 2 = "x", (R:2 takes none), 3 = Foo, 4 = r:3
      const res = parsePhpSerialized('a:4:{i:0;s:1:"x";i:1;R:2;i:2;O:3:"Foo":0:{}i:3;r:3;}');
      expect(res.entries[1].value).toMatchObject({ isAlias: true, resolved: true, targetType: 'string' });
      expect(res.entries[3].value).toMatchObject({ isAlias: false, resolved: true, targetClassName: 'Foo' });
    });

    it('marks out-of-range references as unresolved and rejects index 0', () => {
      expect(parsePhpSerialized('a:1:{i:0;r:9;}').entries[0].value.resolved).toBe(false);
      expect(() => parsePhpSerialized('a:1:{i:0;r:0;}')).toThrow(PhpParseError);
    });
  });

  describe('audit 019: malformed payload resilience', () => {
    it('reports WordPress search-replace corruption with an offset', () => {
      let error;
      try {
        parsePhpSerialized('a:1:{s:3:"url";s:19:"https://new-domain.example";}');
      } catch (e) {
        error = e;
      }
      expect(error).toBeInstanceOf(PhpParseError);
      expect(typeof error.offset).toBe('number');
      expect(error.message).toMatch(/at byte offset \d+/);
    });

    it('rejects negative lengths instead of rewinding the cursor (former infinite recursion)', () => {
      expect(() => parsePhpSerialized('O:1:"A":1:{s:1:"k";O:-20:"}')).toThrow(PhpParseError);
    });

    it('rejects payloads nested deeper than MAX_PARSE_DEPTH without a stack overflow', () => {
      const levels = MAX_PARSE_DEPTH + 10;
      const payload = 'a:1:{i:0;'.repeat(levels) + 'N;' + '}'.repeat(levels);
      expect(() => parsePhpSerialized(payload)).toThrow(/Maximum nesting depth/);
    });

    it('rejects truncated payloads', () => {
      expect(() => parsePhpSerialized('a:2:{i:0;s:1:"a";')).toThrow(PhpParseError);
      expect(() => parsePhpSerialized('O:8:"stdClass":1:{s:1:"a";')).toThrow(PhpParseError);
    });
  });

  describe('audit 019: AST brand', () => {
    it('brands every node with a non-enumerable marker', () => {
      const res = parsePhpSerialized('a:1:{s:1:"k";O:8:"stdClass":0:{}}');
      expect(res[PHP_AST_BRAND]).toBe(true);
      expect(res.entries[0].value[PHP_AST_BRAND]).toBe(true);
      expect(Object.getOwnPropertySymbols(res).every((s) => !Object.prototype.propertyIsEnumerable.call(res, s))).toBe(true);
      expect(Object.keys(res)).toEqual(['type', 'count', 'entries']);
    });
  });
});
