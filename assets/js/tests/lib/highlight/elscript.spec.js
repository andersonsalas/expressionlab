import hljs from 'highlight.js/lib/core';
import elscriptLang from '../../../lib/highlight/elscript.js';

describe('elscript highlight.js grammar', () => {
  beforeAll(() => {
    hljs.registerLanguage('elscript', elscriptLang);
  });

  it('highlights special keywords/structures', () => {
    const code = 'prog[set[\'x\', show[1]], var[\'x\'], fn[], args[\'p\'], map[], filter[], isset[\'x\'], unset[\'x\'], reduce[]]';
    const res = hljs.highlight(code, { language: 'elscript' });
    expect(res.value).toContain('<span class="hljs-keyword">prog</span>');
    expect(res.value).toContain('<span class="hljs-keyword">set</span>');
    expect(res.value).toContain('<span class="hljs-keyword">show</span>');
    expect(res.value).toContain('<span class="hljs-keyword">var</span>');
    expect(res.value).toContain('<span class="hljs-keyword">fn</span>');
    expect(res.value).toContain('<span class="hljs-keyword">args</span>');
    expect(res.value).toContain('<span class="hljs-keyword">map</span>');
    expect(res.value).toContain('<span class="hljs-keyword">filter</span>');
    expect(res.value).toContain('<span class="hljs-keyword">isset</span>');
    expect(res.value).toContain('<span class="hljs-keyword">unset</span>');
    expect(res.value).toContain('<span class="hljs-keyword">reduce</span>');
  });

  it('highlights built-in root objects', () => {
    const code = 'Posts.all(), Users.find(1), Database.query(), Options.get(), NetworkOptions.get(), Media.upload(), Files.read(), Http.get(), Console.log(), NetworkSites.all()';
    const res = hljs.highlight(code, { language: 'elscript' });
    expect(res.value).toContain('<span class="hljs-built_in">Posts</span>');
    expect(res.value).toContain('<span class="hljs-built_in">Users</span>');
    expect(res.value).toContain('<span class="hljs-built_in">Database</span>');
    expect(res.value).toContain('<span class="hljs-built_in">Options</span>');
    expect(res.value).toContain('<span class="hljs-built_in">NetworkOptions</span>');
    expect(res.value).toContain('<span class="hljs-built_in">Media</span>');
    expect(res.value).toContain('<span class="hljs-built_in">Files</span>');
    expect(res.value).toContain('<span class="hljs-built_in">Http</span>');
    expect(res.value).toContain('<span class="hljs-built_in">Console</span>');
    expect(res.value).toContain('<span class="hljs-built_in">NetworkSites</span>');
  });

  it('highlights literals, strings, numbers, operators and comments', () => {
    const code = 'true false null \'hello \\\'world\\\'\' "test" 123 45.67 ~ + - * / % == != <= >= < > && || /* block comment */';
    const res = hljs.highlight(code, { language: 'elscript' });
    expect(res.value).toContain('<span class="hljs-literal">true</span>');
    expect(res.value).toContain('<span class="hljs-literal">false</span>');
    expect(res.value).toContain('<span class="hljs-literal">null</span>');
    expect(res.value).toContain('<span class="hljs-string">&#x27;hello \\&#x27;world\\&#x27;&#x27;</span>');
    expect(res.value).toContain('<span class="hljs-string">&quot;test&quot;</span>');
    expect(res.value).toContain('<span class="hljs-number">123</span>');
    expect(res.value).toContain('<span class="hljs-number">45.67</span>');
    expect(res.value).toContain('<span class="hljs-operator">~</span>');
    expect(res.value).toContain('<span class="hljs-comment">/* block comment */</span>');
  });
});
