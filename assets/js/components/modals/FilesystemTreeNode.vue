<script setup>
import { computed } from 'vue';

const props = defineProps({
  node: {
    type: Object,
    required: true,
  },
  selectedPath: {
    type: String,
    default: '',
  },
  targetOrigin: {
    type: String,
    default: '',
  },
  isExpanded: {
    type: Function,
    required: true,
  },
  depth: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits(['select', 'toggle-expand']);

const handleRowClick = () => {
  emit('select', props.node);
  if (props.node.type === 'dir' && props.node.children && props.node.children.length > 0) {
    emit('toggle-expand', props.node.path);
  }
};

const ownerGroupText = computed(() => {
  if (props.node.owner && props.node.group) {
    return `${props.node.owner}:${props.node.group}`;
  }
  if (props.node.owner) {
    return props.node.owner;
  }
  return '';
});

const nodeTooltip = computed(() => {
  const parts = [];
  parts.push(props.node.path);
  if (ownerGroupText.value) {
    parts.push(ownerGroupText.value);
  }
  if (props.node.perms) {
    parts.push(props.node.perms);
  }
  if (props.node.size) {
    parts.push(props.node.size);
  }
  return parts.join(' | ');
});

const nodeIconClass = computed(() => {
  if (props.node.type === 'dir') {
    return props.isExpanded(props.node.path) ? 'codicon-folder-opened' : 'codicon-folder';
  }
  return 'codicon-file';
});
</script>

<template>
  <!-- Directory Node -->
  <li
    v-if="node.type === 'dir'"
    class="tree-node dir-node"
  >
    <div
      class="tree-node-row"
      :class="{ active: selectedPath === node.path && targetOrigin === 'tree' }"
      @click="handleRowClick"
    >
      <div
        class="node-main-col"
        :style="{ paddingLeft: `${depth * 16}px` }"
      >
        <button
          v-if="node.children && node.children.length > 0"
          type="button"
          class="tree-expand-btn"
          :class="{ expanded: isExpanded(node.path) }"
          tabindex="-1"
          aria-label="Toggle directory"
          @click.stop="handleRowClick"
        >
          <span
            class="codicon"
            :class="isExpanded(node.path) ? 'codicon-chevron-down' : 'codicon-chevron-right'"
          />
        </button>
        <span
          v-else
          class="tree-expand-placeholder"
        />
        <span
          class="node-icon codicon"
          :class="nodeIconClass"
        />
        <span
          class="node-name"
          :title="node.name"
        >{{ node.name }}</span>
      </div>

      <div class="node-meta-cols">
        <span class="node-col node-size-col">
          {{ node.size || '' }}
        </span>
        <span
          class="node-col node-owner-col"
        >
          {{ ownerGroupText }}
        </span>
        <span class="node-col node-perms-col">
          {{ node.perms || '' }}
        </span>
      </div>
    </div>

    <!-- Recursive Subtree if expanded -->
    <ul
      v-if="isExpanded(node.path) && node.children && node.children.length > 0"
      class="tree-node-list subtree"
    >
      <FilesystemTreeNode
        v-for="child in node.children"
        :key="child.path"
        :node="child"
        :selected-path="selectedPath"
        :target-origin="targetOrigin"
        :is-expanded="isExpanded"
        :depth="depth + 1"
        @select="$emit('select', $event)"
        @toggle-expand="$emit('toggle-expand', $event)"
      />
    </ul>
  </li>

  <!-- File Node -->
  <li
    v-else
    class="tree-node file-node"
  >
    <div
      class="tree-node-row"
      :class="{ active: selectedPath === node.path && targetOrigin === 'tree' }"
      @click="handleRowClick"
    >
      <div
        class="node-main-col"
        :style="{ paddingLeft: `${depth * 16}px` }"
      >
        <span class="tree-expand-placeholder" />
        <span
          class="node-icon codicon"
          :class="nodeIconClass"
        />
        <span
          class="node-name"
        >{{ node.name }}</span>
      </div>

      <div class="node-meta-cols">
        <span class="node-col node-size-col">
          {{ node.size || '' }}
        </span>
        <span
          class="node-col node-owner-col"
        >
          {{ ownerGroupText }}
        </span>
        <span class="node-col node-perms-col">
          {{ node.perms || '' }}
        </span>
      </div>
    </div>
  </li>
</template>
