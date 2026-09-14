<script setup>
import { computed } from 'vue';
import { renderSafeInlineMarkdown } from '../../lib/helpers';

const props = defineProps({
  text: {
    type: [String, Number, Boolean],
    default: '',
  },
});

const formattedHtml = computed(() => {
  return renderSafeInlineMarkdown(props.text);
});
</script>

<template>
  <!-- eslint-disable vue/no-v-html -->
  <span
    class="safe-formatted-text"
    v-html="formattedHtml"
  />
  <!-- eslint-enable vue/no-v-html -->
</template>
