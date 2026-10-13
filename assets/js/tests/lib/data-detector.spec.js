import { detectDataType, detectSyntaxLanguage } from '../../lib/data-detector.js';

describe('data-detector.js', () => {
  describe('detectDataType()', () => {
    it('detects valid JSON object as "json" format', () => {
      const jsonStr = '{"name": "Expression Lab", "active": true, "version": 2}';
      const result = detectDataType(jsonStr);
      expect(result.format).toBe('json');
      expect(result.data).toEqual({ name: 'Expression Lab', active: true, version: 2 });
      expect(result.suggestedLang).toBe('json');
    });

    it('detects valid JSON array as "json" format', () => {
      const jsonArr = '[1, 2, "three", {"nested": true}]';
      const result = detectDataType(jsonArr);
      expect(result.format).toBe('json');
      expect(result.data).toHaveLength(4);
      expect(result.suggestedLang).toBe('json');
    });

    it('detects in-memory object as "json" format', () => {
      const obj = { foo: 'bar' };
      const result = detectDataType(obj);
      expect(result.format).toBe('json');
      expect(result.data).toBe(obj);
    });

    it('detects valid PHP serialized array as "php-serialized" format', () => {
      const phpStr = 'a:2:{s:4:"host";s:9:"localhost";s:4:"port";i:3306;}';
      const result = detectDataType(phpStr);
      expect(result.format).toBe('php-serialized');
      expect(result.data.type).toBe('array');
      expect(result.data.count).toBe(2);
      expect(result.suggestedLang).toBe('php');
    });

    it('detects valid PHP serialized object as "php-serialized" format', () => {
      const phpObj = 'O:8:"stdClass":1:{s:5:"title";s:4:"test";}';
      const result = detectDataType(phpObj);
      expect(result.format).toBe('php-serialized');
      expect(result.data.type).toBe('object');
      expect(result.data.className).toBe('stdClass');
      expect(result.suggestedLang).toBe('php');
    });

    it('categorizes PHP scalar serialized string as "none" (ineligible for Preview tree)', () => {
      // s:11:"hello world"; is serialized scalar, not a tree structure
      const phpScalar = 's:11:"hello world";';
      const result = detectDataType(phpScalar);
      expect(result.format).toBe('none');
    });

    it('categorizes plain text, numbers, and booleans as "none"', () => {
      expect(detectDataType('Just regular text').format).toBe('none');
      expect(detectDataType('12345').format).toBe('none');
      expect(detectDataType('https://example.com/site').format).toBe('none');
      expect(detectDataType(null).format).toBe('none');
      expect(detectDataType(undefined).format).toBe('none');
    });

    it('categorizes corrupted PHP serialization as "none" gracefully', () => {
      const brokenPhp = 'a:10:{s:4:"test";broken';
      const result = detectDataType(brokenPhp);
      expect(result.format).toBe('none');
    });

    it('detects quoted string literal containing PHP serialized array', () => {
      const quotedPhp = '\'a:2:{s:4:"host";s:9:"localhost";s:4:"port";i:3306;}\'';
      const result = detectDataType(quotedPhp);
      expect(result.format).toBe('php-serialized');
      expect(result.data.type).toBe('array');
      expect(result.unwrappedString).toBe('a:2:{s:4:"host";s:9:"localhost";s:4:"port";i:3306;}');
      expect(result.suggestedLang).toBe('php');
    });

    it('detects quoted string literal containing JSON composite', () => {
      const quotedJson = '\'{"site": "ExpressionLab", "active": true}\'';
      const result = detectDataType(quotedJson);
      expect(result.format).toBe('json');
      expect(result.data).toEqual({ site: 'ExpressionLab', active: true });
      expect(result.suggestedLang).toBe('json');
    });
  });

  describe('detectSyntaxLanguage()', () => {
    it('detects SQL statements', () => {
      expect(detectSyntaxLanguage('SELECT * FROM wp_options WHERE option_name = "siteurl"')).toBe('sql');
      expect(detectSyntaxLanguage('INSERT INTO wp_posts (post_title) VALUES ("Test")')).toBe('sql');
      expect(detectSyntaxLanguage('UPDATE wp_users SET user_email = "a@b.com"')).toBe('sql');
    });

    it('detects HTML markup', () => {
      expect(detectSyntaxLanguage('<div class="notice"><p>Hello</p></div>')).toBe('html');
      expect(detectSyntaxLanguage('<!DOCTYPE html><html><body></body></html>')).toBe('html');
    });

    it('detects PHP code tags', () => {
      expect(detectSyntaxLanguage('<?php echo "Hello World";')).toBe('php');
    });

    it('detects JavaScript code patterns', () => {
      expect(detectSyntaxLanguage('const x = () => { return 42; };')).toBe('javascript');
      expect(detectSyntaxLanguage('function calculateTotal() {}')).toBe('javascript');
    });

    it('detects CSS rules', () => {
      expect(detectSyntaxLanguage('.header-container { display: flex; }')).toBe('css');
    });

    it('defaults to "auto" when no syntax pattern matches', () => {
      expect(detectSyntaxLanguage('Some arbitrary non-code note')).toBe('auto');
      expect(detectSyntaxLanguage('')).toBe('auto');
      expect(detectSyntaxLanguage(null)).toBe('auto');
    });
  });
});
