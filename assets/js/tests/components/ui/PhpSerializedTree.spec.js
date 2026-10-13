import { mount } from '@vue/test-utils';
import PhpSerializedTree from '../../../components/ui/PhpSerializedTree.vue';

describe('PhpSerializedTree.vue', () => {
  const sampleAst = {
    type: 'object',
    className: 'WP_Post',
    propertyCount: 3,
    properties: [
      {
        name: 'ID',
        visibility: 'public',
        value: { type: 'integer', value: 123 },
      },
      {
        name: 'post_title',
        visibility: 'protected',
        value: { type: 'string', value: 'Hello World', byteLength: 11 },
      },
      {
        name: 'user_pass',
        visibility: 'private',
        ownerClass: 'WP_User',
        value: { type: 'string', value: 'secret', byteLength: 6 },
      },
    ],
  };

  const sampleArrayAst = {
    type: 'array',
    count: 2,
    entries: [
      {
        key: { type: 'string', value: 'twentytwentyfive' },
        value: { type: 'boolean', value: true },
      },
      {
        key: { type: 'integer', value: 0 },
        value: { type: 'null', value: null },
      },
    ],
  };

  it('renders root object class name and properties without duplicate headers', () => {
    const wrapper = mount(PhpSerializedTree, {
      props: {
        ast: sampleAst,
      },
    });

    // Object header shows class name once followed by property count
    const className = wrapper.find('.php-class-name');
    expect(className.exists()).toBe(true);
    expect(className.text()).toBe('WP_Post');

    const itemCount = wrapper.find('.php-item-count');
    expect(itemCount.exists()).toBe(true);
    expect(itemCount.text()).toBe('(3)');

    // Ensure no duplicate class name summary (e.g. not "WP_Post WP_Post (3)")
    const headerRow = wrapper.find('.php-node-row');
    expect(headerRow.text()).toBe('WP_Post (3)');

    // Properties rendered without quotes and with .is-property class
    expect(wrapper.text()).toContain('ID:');
    expect(wrapper.text()).toContain('123');
    expect(wrapper.text()).toContain('post_title:');
    expect(wrapper.text()).toContain('"Hello World"');
    expect(wrapper.text()).toContain('user_pass:');

    const propertyKeys = wrapper.findAll('.php-node-key.is-property');
    expect(propertyKeys.length).toBe(3);
    propertyKeys.forEach((keyEl) => {
      expect(keyEl.classes()).not.toContain('is-array-key');
    });
  });

  it('renders compact IDE visibility glyphs (+, #, -) and accessible titles', () => {
    const wrapper = mount(PhpSerializedTree, {
      props: {
        ast: sampleAst,
      },
    });

    const publicBadge = wrapper.find('.php-badge-visibility.public');
    expect(publicBadge.exists()).toBe(true);
    expect(publicBadge.text()).toBe('+');
    expect(publicBadge.attributes('title')).toBe('public');

    const protectedBadge = wrapper.find('.php-badge-visibility.protected');
    expect(protectedBadge.exists()).toBe(true);
    expect(protectedBadge.text()).toBe('#');
    expect(protectedBadge.attributes('title')).toBe('protected');

    const privateBadge = wrapper.find('.php-badge-visibility.private');
    expect(privateBadge.exists()).toBe(true);
    expect(privateBadge.text()).toBe('-');
    expect(privateBadge.attributes('title')).toBe('Declared in WP_User');
  });

  it('renders arrays with summary, quoted string keys, and bracketed numeric keys', () => {
    const wrapper = mount(PhpSerializedTree, {
      props: {
        ast: sampleArrayAst,
      },
    });

    // Array summary header
    const summary = wrapper.find('.php-composite-summary');
    expect(summary.exists()).toBe(true);
    expect(summary.text()).toBe('Array (2)');

    // String key in double quotes with .is-array-key
    const arrayKeys = wrapper.findAll('.php-node-key.is-array-key');
    expect(arrayKeys.length).toBe(2);

    expect(wrapper.text()).toContain('"twentytwentyfive":');
    expect(wrapper.text()).toContain('[0]:');

    const numericKey = wrapper.find('.php-node-key.is-numeric');
    expect(numericKey.exists()).toBe(true);
    expect(numericKey.text()).toBe('[0]:');

    arrayKeys.forEach((keyEl) => {
      expect(keyEl.classes()).not.toContain('is-property');
    });
  });

  it('renders primitive values by syntax and color classes without textual type tags', () => {
    const wrapper = mount(PhpSerializedTree, {
      props: {
        ast: sampleAst,
      },
    });

    // No textual [int], [string], [bool], [null] badges
    expect(wrapper.text()).not.toContain('[int]');
    expect(wrapper.text()).not.toContain('[string]');
    expect(wrapper.text()).not.toContain('[bool]');
    expect(wrapper.text()).not.toContain('[null]');

    expect(wrapper.find('.php-value-scalar.type-integer').text()).toBe('123');
    expect(wrapper.find('.php-value-scalar.type-string').text()).toBe('"Hello World"');
  });

  it('expands and collapses via exposed methods', async () => {
    const wrapper = mount(PhpSerializedTree, {
      props: {
        ast: sampleAst,
      },
    });

    wrapper.vm.collapseAll();
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.defaultExpandedDepth).toBe(0);

    wrapper.vm.expandAll();
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.defaultExpandedDepth).toBe(999);
  });
});
