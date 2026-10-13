<script setup>
import { ref } from 'vue';
import DataTree from './DataTree.vue';

const props = defineProps({
  ast: {
    type: Object,
    default: null,
  },
  data: {
    type: [Object, Array, String, Number, Boolean],
    default: undefined,
  },
  showControls: {
    type: Boolean,
    default: false,
  },
  defaultExpandedDepth: {
    type: Number,
    default: 1,
  },
});

const dataTreeRef = ref(null);
const defaultExpandedDepth = ref(props.defaultExpandedDepth);

const expandAll = () => {
  defaultExpandedDepth.value = 999;
  dataTreeRef.value?.expandAll();
};

const collapseAll = () => {
  defaultExpandedDepth.value = 0;
  dataTreeRef.value?.collapseAll();
};

defineExpose({
  expandAll,
  collapseAll,
  defaultExpandedDepth,
});
</script>

<template>
  <DataTree
    ref="dataTreeRef"
    :ast="props.ast"
    :data="props.data"
    :show-controls="props.showControls"
    :default-expanded-depth="props.defaultExpandedDepth"
  />
</template>

