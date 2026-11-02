<script setup>
import { ref } from 'vue';

const props = defineProps({
  node: {
    type: Object,
    required: true,
  },
  depth: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits(['select']);

const isExpanded = ref(props.node?.expanded !== false);
</script>

<template>
  <li class="outline-tree-item">
    <div
      class="outline-item heading-item"
      :style="{ paddingLeft: `${depth * 12 + 8}px` }"
      @click="emit('select', node)"
    >
      <span
        v-if="node.children && node.children.length > 0"
        class="codicon chevron-icon"
        :class="isExpanded ? 'codicon-chevron-down' : 'codicon-chevron-right'"
        @click.stop="isExpanded = !isExpanded"
      />
      <span
        v-else
        class="codicon-spacer"
      />
      <span
        class="codicon codicon-bookmark heading-icon"
      />
      <span
        class="heading-label"
        :title="node.label"
      >{{ node.label }}</span>
    </div>

    <ul
      v-if="node.children && node.children.length > 0 && isExpanded"
      class="outline-tree-children"
      :style="{ '--guide-left': `${depth * 12 + 16}px` }"
    >
      <NotebookOutlineItem
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
        @select="(n) => emit('select', n)"
      />
    </ul>
  </li>
</template>
