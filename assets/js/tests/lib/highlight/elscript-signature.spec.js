import hljs from 'highlight.js/lib/core';
import elscriptSignatureLang from '../../../lib/highlight/elscript-signature.js';

describe('elscript-signature highlight.js grammar', () => {
  beforeAll(() => {
    hljs.registerLanguage('elscript-signature', elscriptSignatureLang);
  });

  it('highlights method signatures with types, parameters, and return types', () => {
    const sig = 'Database.mirror(string:table, [array|null:where = []], [array|null:options = null]) => Database';
    const res = hljs.highlight(sig, { language: 'elscript-signature' });

    expect(res.value).toContain('<span class="hljs-title class_">Database</span>');
    expect(res.value).toContain('<span class="hljs-title function_">mirror</span>');
    expect(res.value).toContain('<span class="hljs-type">string</span>');
    expect(res.value).toContain('<span class="hljs-variable">table</span>');
    expect(res.value).toContain('<span class="hljs-type">array</span>');
    expect(res.value).toContain('<span class="hljs-literal">null</span>');
    expect(res.value).toContain('<span class="hljs-variable">where</span>');
    expect(res.value).toContain('<span class="hljs-variable">options</span>');
    expect(res.value).toContain('<span class="hljs-operator">=&gt;</span>');
    expect(res.value).toContain('<span class="hljs-type">Database</span>');
  });

  it('highlights standalone function signatures', () => {
    const sig = 'format_date(string:format, [int|null:timestamp = null]) => string';
    const res = hljs.highlight(sig, { language: 'elscript-signature' });

    expect(res.value).toContain('<span class="hljs-title function_">format_date</span>');
    expect(res.value).toContain('<span class="hljs-type">string</span>');
    expect(res.value).toContain('<span class="hljs-variable">format</span>');
    expect(res.value).toContain('<span class="hljs-type">int</span>');
    expect(res.value).toContain('<span class="hljs-literal">null</span>');
    expect(res.value).toContain('<span class="hljs-variable">timestamp</span>');
  });

  it('highlights constant signatures with :: separator', () => {
    const sig = 'Posts::STATUS_PUBLISHED';
    const res = hljs.highlight(sig, { language: 'elscript-signature' });

    expect(res.value).toContain('<span class="hljs-title class_">Posts</span>');
    expect(res.value).toContain('<span class="hljs-variable constant_">STATUS_PUBLISHED</span>');
  });

  it('highlights property signatures with dot notation', () => {
    const sig = 'Posts.total';
    const res = hljs.highlight(sig, { language: 'elscript-signature' });

    expect(res.value).toContain('<span class="hljs-title class_">Posts</span>');
    expect(res.value).toContain('<span class="hljs-property">total</span>');
  });

  it('highlights rest parameters (...args)', () => {
    const sig = 'Console.log([mixed:...args]) => void';
    const res = hljs.highlight(sig, { language: 'elscript-signature' });

    expect(res.value).toContain('<span class="hljs-title class_">Console</span>');
    expect(res.value).toContain('<span class="hljs-title function_">log</span>');
    expect(res.value).toContain('<span class="hljs-type">mixed</span>');
    expect(res.value).toContain('<span class="hljs-variable">...args</span>');
    expect(res.value).toContain('<span class="hljs-type">void</span>');
  });
});
