<template>
  <button
    type="button"
    role="switch"
    :aria-checked="value"
    :disabled="locked"
    class="el-toggler"
    :class="{ 'is-on': value, 'is-locked': locked }"
    @click="toggle"
  >
    <span
      v-if="locked"
      class="el-toggler-icon"
    >
      <svg
        viewBox="0 0 24 24"
        fill="currentColor"
        width="100%"
        height="100%"
      >
        <path d="M17 8V6a5 5 0 0 0-10 0v2H5v14h14V8h-2zm-5 9a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm3-9H9V6a3 3 0 0 1 6 0v2z" />
      </svg>
    </span>
    <span class="el-toggler-thumb" />
  </button>
</template>

<script setup>
const props = defineProps({
  value: {
    type: Boolean,
    default: false
  },
  locked: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:value']);

function toggle() {
  if (!props.locked) {
    emit('update:value', !props.value);
  }
}
</script>