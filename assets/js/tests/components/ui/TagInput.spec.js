import { mount } from '@vue/test-utils';
import TagInput from '../../../components/ui/TagInput.vue';

describe('TagInput.vue', () => {
  it('renders existing tags as chips', () => {
    const wrapper = mount(TagInput, {
      props: {
        modelValue: ['d3', 'charts', 'map']
      }
    });

    const chips = wrapper.findAll('.tag-chip');
    expect(chips).toHaveLength(3);
    expect(chips[0].text()).toContain('d3');
    expect(chips[1].text()).toContain('charts');
    expect(chips[2].text()).toContain('map');
  });

  it('adds a tag on Enter key', async () => {
    const wrapper = mount(TagInput, {
      props: {
        modelValue: ['d3']
      }
    });

    const input = wrapper.find('.tag-inline-input');
    await input.setValue('visualization');
    await input.trigger('keydown', { key: 'Enter' });

    expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    expect(wrapper.emitted('update:modelValue')[0][0]).toEqual(['d3', 'visualization']);
    expect(wrapper.emitted('change')).toBeTruthy();
  });

  it('adds a tag on Comma key', async () => {
    const wrapper = mount(TagInput, {
      props: {
        modelValue: []
      }
    });

    const input = wrapper.find('.tag-inline-input');
    await input.setValue('analytics');
    await input.trigger('keydown', { key: ',' });

    expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    expect(wrapper.emitted('update:modelValue')[0][0]).toEqual(['analytics']);
  });

  it('does not add duplicate tags (case-insensitive)', async () => {
    const wrapper = mount(TagInput, {
      props: {
        modelValue: ['Chart']
      }
    });

    const input = wrapper.find('.tag-inline-input');
    await input.setValue('chart');
    await input.trigger('keydown', { key: 'Enter' });

    expect(wrapper.emitted('update:modelValue')).toBeFalsy();
  });

  it('removes tag on click close button', async () => {
    const wrapper = mount(TagInput, {
      props: {
        modelValue: ['first', 'second']
      }
    });

    const removeBtns = wrapper.findAll('.tag-remove-btn');
    await removeBtns[0].trigger('click');

    expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    expect(wrapper.emitted('update:modelValue')[0][0]).toEqual(['second']);
  });

  it('removes last tag on Backspace when input is empty', async () => {
    const wrapper = mount(TagInput, {
      props: {
        modelValue: ['tagA', 'tagB']
      }
    });

    const input = wrapper.find('.tag-inline-input');
    await input.setValue('');
    await input.trigger('keydown', { key: 'Backspace' });

    expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    expect(wrapper.emitted('update:modelValue')[0][0]).toEqual(['tagA']);
  });

  it('shows autocomplete suggestions from allTags and selects on click', async () => {
    const wrapper = mount(TagInput, {
      props: {
        modelValue: ['existing'],
        allTags: ['existing', 'graph', 'graphic', 'other']
      }
    });

    const input = wrapper.find('.tag-inline-input');
    await input.trigger('focus');
    await input.setValue('graph');

    const dropdown = wrapper.find('.tag-suggestions-dropdown');
    expect(dropdown.exists()).toBe(true);

    const suggestions = wrapper.findAll('.tag-suggestion-item');
    expect(suggestions).toHaveLength(2); // 'graph', 'graphic' (existing is omitted)
    expect(suggestions[0].text()).toContain('graph');
    expect(suggestions[1].text()).toContain('graphic');

    await suggestions[0].trigger('click');
    expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    expect(wrapper.emitted('update:modelValue')[0][0]).toEqual(['existing', 'graph']);
  });
});
