import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import FileVisualization from '../../../../components/console/visualizations/FileVisualization.vue';

describe('FileVisualization.vue', () => {
  const mockFileData = {
    type: 'file',
    title: 'File: wp-config.php',
    filename: 'wp-config.php',
    path: 'wp-config.php',
    content: '<?php\ndefine("DB_NAME", "wordpress");\ndefine("DB_USER", "root");\n',
    bytes: 65,
    size_human: '65 B',
    mime: 'application/x-php',
    ext: 'php',
    line_count: 4,
  };

  let wrapper;

  const mountComponent = (props = {}) => {
    const pinia = createTestingPinia({
      stubActions: false,
    });

    wrapper = mount(FileVisualization, {
      props: {
        data: mockFileData,
        ...props,
      },
      global: {
        plugins: [pinia],
      },
      attachTo: document.body,
    });
    return wrapper;
  };

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount();
      wrapper = null;
    }
    document.body.innerHTML = '';
  });

  it('renders editor viewport directly with no top toolbar', () => {
    const w = mountComponent();

    expect(w.find('.file-toolbar').exists()).toBe(false);
    expect(w.find('.editor-viewport').exists()).toBe(true);
  });

  it('renders footer with search input, lines, size and word wrap toggle', () => {
    const w = mountComponent();

    const footer = w.find('.file-footer');
    expect(footer.exists()).toBe(true);
    expect(footer.find('.file-search-input').exists()).toBe(true);
    expect(footer.text()).toContain('4 lines');
    expect(footer.text()).toContain('65 B');
    expect(footer.find('.btn-word-wrap').exists()).toBe(true);
    expect(footer.find('.btn-word-wrap').text()).toContain('Word Wrap');
  });

  it('toggles word wrap on button click', async () => {
    const w = mountComponent();

    const wrapBtn = w.find('.btn-word-wrap');
    expect(wrapBtn.classes()).not.toContain('active');

    await wrapBtn.trigger('click');
    expect(wrapBtn.classes()).toContain('active');

    await wrapBtn.trigger('click');
    expect(wrapBtn.classes()).not.toContain('active');
  });

  it('performs search and updates match status badge', async () => {
    const w = mountComponent();

    const searchInput = w.find('.file-search-input');
    await searchInput.setValue('define');
    await searchInput.trigger('input');

    const badge = w.find('.search-match-badge');
    expect(badge.exists()).toBe(true);
    expect(badge.text()).toContain('2');

    const nextBtn = w.findAll('.btn-search-nav')[1];
    expect(nextBtn.attributes('disabled')).toBeUndefined();
    await nextBtn.trigger('click');

    const prevBtn = w.findAll('.btn-search-nav')[0];
    await prevBtn.trigger('click');
  });

  it('displays no results when search query has no matches', async () => {
    const w = mountComponent();

    const searchInput = w.find('.file-search-input');
    await searchInput.setValue('nonexistent_string_12345');
    await searchInput.trigger('input');

    const badge = w.find('.search-match-badge');
    expect(badge.exists()).toBe(true);
    expect(badge.text()).toContain('No results');

    const navBtns = w.findAll('.btn-search-nav');
    expect(navBtns[0].attributes('disabled')).toBeDefined();
    expect(navBtns[1].attributes('disabled')).toBeDefined();
  });

  it('clears search on clear button click', async () => {
    const w = mountComponent();

    const searchInput = w.find('.file-search-input');
    await searchInput.setValue('define');
    await searchInput.trigger('input');

    const clearBtn = w.find('.search-clear-btn');
    expect(clearBtn.exists()).toBe(true);

    await clearBtn.trigger('click');
    expect(w.find('.file-search-input').element.value).toBe('');
    expect(w.find('.search-match-badge').exists()).toBe(false);
  });
});
