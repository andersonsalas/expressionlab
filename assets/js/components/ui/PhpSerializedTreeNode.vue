<script setup>
import { ref, computed } from 'vue';
import { __, sprintf } from '../../lib/helpers.js';

const props = defineProps({
  node: {
    type: Object,
    required: true,
  },
  label: {
    type: [String, Number],
    default: null,
  },
  visibility: {
    type: String,
    default: null, // 'public' | 'protected' | 'private'
  },
  ownerClass: {
    type: String,
    default: null,
  },
  isProperty: {
    type: Boolean,
    default: false,
  },
  isArrayKey: {
    type: Boolean,
    default: false,
  },
  isNumericKey: {
    type: Boolean,
    default: false,
  },
  defaultExpandedDepth: {
    type: Number,
    default: 1,
  },
  currentDepth: {
    type: Number,
    default: 0,
  },
  showControls: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['expand-all', 'collapse-all']);

const isComposite = computed(() => {
  return props.node && (props.node.type === 'array' || props.node.type === 'object');
});

const isPropertyNode = computed(() => {
  return props.isProperty || Boolean(props.visibility);
});

const isArrayKeyNode = computed(() => {
  return props.isArrayKey || (props.label !== null && !isPropertyNode.value);
});

const isNumericKeyNode = computed(() => {
  if (props.isNumericKey) return true;
  if (typeof props.label === 'string') {
    return /^\[\d+\]$/.test(props.label);
  }
  return typeof props.label === 'number';
});

const visibilityGlyph = computed(() => {
  if (!props.visibility) return '';
  switch (props.visibility) {
    case 'public':
      return '+';
    case 'protected':
      return '#';
    case 'private':
      return '-';
    default:
      return props.visibility;
  }
});

const isOpen = ref(props.currentDepth < props.defaultExpandedDepth);

const toggleOpen = () => {
  if (isComposite.value) {
    isOpen.value = !isOpen.value;
  }
};

const headerSummary = computed(() => {
  if (!props.node) return '';
  if (props.node.type === 'array') {
    return `Array (${props.node.count})`;
  }
  if (props.node.type === 'object') {
    return `(${props.node.propertyCount})`;
  }
  return '';
});

const formatScalar = (node) => {
  if (!node) return 'null';
  switch (node.type) {
    case 'string':
      return `"${node.value}"`;
    case 'integer':
      return node.raw ?? String(node.value);
    case 'double':
      return node.raw || String(node.value);
    case 'boolean':
      return node.value ? 'true' : 'false';
    case 'null':
      return 'null';
    case 'reference': {
      const prefix = node.isAlias ? '&ref' : 'ref';
      const target = node.targetClassName || node.targetType;
      return target ? `${prefix}(#${node.refIndex} → ${target})` : `${prefix}(#${node.refIndex})`;
    }
    case 'enum':
      return `${node.className}::${node.caseName}`;
    case 'custom_object':
      return `${node.className} { ${node.rawData} }`;
    default:
      return String(node.value ?? '');
  }
};

const getEntryLabel = (entry) => {
  if (!entry || !entry.key) return '';
  return entry.key.type === 'string' ? `"${entry.key.value}"` : `[${entry.key.value}]`;
};
</script>

<template>
  <div
    class="php-tree-node"
    :class="{ 'is-composite': isComposite }"
  >
    <!-- Node Line -->
    <div
      class="php-node-row"
      @click="toggleOpen"
    >
      <!-- Toggle Icon for Composites -->
      <span
        v-if="isComposite"
        class="php-toggle-icon"
      >
        <span :class="['codicon', isOpen ? 'codicon-chevron-down' : 'codicon-chevron-right']" />
      </span>
      <span
        v-else
        class="php-toggle-spacer"
      />

      <!-- Visibility Badge (for Object Properties) -->
      <span
        v-if="visibility"
        :class="['php-badge-visibility', visibility]"
        :title="ownerClass ? sprintf(__('Declared in %s'), ownerClass) : visibility"
      >
        {{ visibilityGlyph }}
      </span>

      <!-- Label / Key / Property Name -->
      <span
        v-if="label !== null"
        :class="[
          'php-node-key',
          {
            'is-property': isPropertyNode,
            'is-array-key': isArrayKeyNode,
            'is-numeric': isNumericKeyNode,
          },
        ]"
      >
        {{ label }}:
      </span>

      <!-- Value Rendering -->
      <template v-if="isComposite">
        <template v-if="node.type === 'object'">
          <span class="php-class-name">{{ node.className }}</span> <span class="php-item-count">({{ node.propertyCount }})</span>
        </template>
        <span
          v-else-if="node.type === 'array'"
          class="php-composite-summary"
        >
          {{ headerSummary }}
        </span>
      </template>

      <span
        v-else
        :class="['php-value-scalar', `type-${node.type}`]"
      >
        {{ formatScalar(node) }}
      </span>

      <!-- Root Node Inline Controls (Embedded Console Mode) -->
      <span
        v-if="showControls && currentDepth === 0 && isComposite && ((node.count > 0) || (node.propertyCount > 0))"
        class="php-root-controls"
        @click.stop
      >
        <button
          type="button"
          class="php-root-btn btn-expand-all"
          :title="__('Expand all')"
          @click.stop="emit('expand-all')"
        >
          <span class="codicon codicon-add" />
          <span>{{ __('Expand all') }}</span>
        </button>
        <button
          type="button"
          class="php-root-btn btn-collapse-all"
          :title="__('Collapse all')"
          @click.stop="emit('collapse-all')"
        >
          <span class="codicon codicon-remove" />
          <span>{{ __('Collapse all') }}</span>
        </button>
      </span>
    </div>

    <!-- Children (if expanded) -->
    <div
      v-if="isComposite && isOpen"
      class="php-node-children"
    >
      <!-- Array Elements -->
      <template v-if="node.type === 'array'">
        <PhpSerializedTreeNode
          v-for="(entry, idx) in node.entries"
          :key="idx"
          :node="entry.value"
          :label="getEntryLabel(entry)"
          :is-array-key="true"
          :is-numeric-key="entry.key?.type === 'integer'"
          :current-depth="currentDepth + 1"
          :default-expanded-depth="defaultExpandedDepth"
        />
      </template>

      <!-- Object Properties -->
      <template v-if="node.type === 'object'">
        <PhpSerializedTreeNode
          v-for="(prop, idx) in node.properties"
          :key="idx"
          :node="prop.value"
          :label="prop.name"
          :visibility="prop.visibility"
          :owner-class="prop.ownerClass"
          :is-property="true"
          :current-depth="currentDepth + 1"
          :default-expanded-depth="defaultExpandedDepth"
        />
      </template>
    </div>
  </div>
</template>
