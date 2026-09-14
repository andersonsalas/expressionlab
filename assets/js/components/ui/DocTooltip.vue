<script setup>
import { computed } from 'vue';
import { renderDocMarkdown, highlightSignature } from '../../lib/doc-markdown.js';
import { __, renderSafeInlineMarkdown } from '../../lib/helpers.js';
import { handleOpenUrl } from '../../lib/api/client.js';

const props = defineProps({
  doc: {
    type: Object,
    default: null,
  },
});

const highlightedSignature = computed(() => {
  return highlightSignature(props.doc?.signature);
});

const renderedDescription = computed(() => {
  return renderDocMarkdown(props.doc?.description);
});

const isUrl = (ref) => {
  try {
    new URL(ref);
    return true;
  } catch {
    return false;
  }
};

const openLink = (url, event) => {
  event?.preventDefault();
  handleOpenUrl(url);
};

const handleContainerClick = (event) => {
  const link = event.target?.closest?.('a[href]');
  if (link && !link.classList.contains('doc-link')) {
    const href = link.getAttribute('href');
    if (href) {
      event.preventDefault();
      event.stopPropagation();
      handleOpenUrl(href);
    }
  }
};
</script>

<template>
  <!-- eslint-disable vue/no-v-html -->
  <div
    v-if="doc"
    class="doc-tooltip"
    @click="handleContainerClick"
  >
    <!-- Summary -->
    <p
      v-if="doc.summary"
      class="cm-docblock summary"
    >
      {{ doc.summary }}
    </p>

    <pre
      v-if="doc.signature"
      class="cm-docblock signature hljs"
      v-html="highlightedSignature"
    />
    <div
      v-if="doc.description"
      class="cm-docblock description-wrapper"
    >
      <div
        class="cm-docblock _description"
        v-html="renderedDescription"
      />
    </div>

    <!-- Tags / Sections -->
    <div class="cm-docblock tags">
      <!-- Parameters -->
      <div
        v-if="doc.parameters && doc.parameters.length > 0"
        class="cm-docblock doc-section doc-parameters"
      >
        <div class="cm-docblock doc-section-title">
          {{ __('Parameters:') }}
        </div>
        <ul class="cm-docblock doc-list">
          <li
            v-for="(param, i) in doc.parameters"
            :key="'p-' + i"
            class="cm-docblock doc-item"
          >
            <span
              :class="[
                'codicon',
                'codicon-symbol-field',
                param.required !== false ? 'param-required' : 'param-optional'
              ]"
            />
            <span
              v-if="param.name"
              class="cm-docblock variable"
            >{{ param.name }}</span>{{ param.name && param.type ? ' ' : '' }}
            <span
              v-if="param.type"
              class="cm-docblock type"
            >({{ param.type }})</span>
            <template v-if="param.description">
              <span class="cm-docblock doc-separator"> - </span>
              <span
                class="cm-docblock doc-desc"
                v-html="renderSafeInlineMarkdown(param.description)"
              />
            </template>
          </li>
        </ul>
      </div>

      <!-- Return -->
      <div
        v-if="doc.return"
        class="cm-docblock doc-section doc-returns"
      >
        <span class="cm-docblock doc-section-title">{{ __('Returns:') }}</span>{{ ' ' }}
        <span
          v-if="doc.return.type"
          class="cm-docblock type"
        >{{ doc.return.type }}</span>
        <template v-if="doc.return.description">
          <span class="cm-docblock doc-separator"> - </span>
          <span
            class="cm-docblock doc-desc"
            v-html="renderSafeInlineMarkdown(doc.return.description)"
          />
        </template>
      </div>

      <!-- See -->
      <div
        v-if="doc.see && doc.see.length > 0"
        class="cm-docblock doc-section doc-see"
      >
        <div class="cm-docblock doc-section-title">
          {{ __('See also:') }}
        </div>
        <ul class="cm-docblock doc-list">
          <li
            v-for="(ref, i) in doc.see"
            :key="'s-' + i"
            class="cm-docblock doc-item"
          >
            <a
              v-if="isUrl(ref)"
              class="cm-docblock doc-link"
              :href="ref"
              target="_blank"
              rel="noopener noreferrer"
              @mousedown.stop
              @click.stop="openLink(ref, $event)"
            ><span class="codicon codicon-link" />{{ ref }}</a>
            <template v-else>
              <span class="codicon codicon-link" />
              <span class="cm-docblock doc-ref">{{ ref }}</span>
            </template>
          </li>
        </ul>
      </div>

      <!-- Type (constants/properties) -->
      <div
        v-if="doc.type && !doc.parameters"
        class="cm-docblock doc-section doc-type"
      >
        <span class="cm-docblock doc-section-title">{{ __('Type:') }}</span>{{ ' ' }}
        <span class="cm-docblock type">{{ doc.type }}</span>
      </div>

      <!-- Value (constants) -->
      <div
        v-if="doc.valueStr !== undefined"
        class="cm-docblock doc-section doc-value"
      >
        <span class="cm-docblock doc-section-title">{{ __('Value:') }}</span>{{ ' ' }}
        <span class="cm-docblock doc-desc"><code>{{ doc.valueStr }}</code></span>
      </div>

      <!-- Default (properties) -->
      <div
        v-if="doc.default !== undefined"
        class="cm-docblock doc-section doc-default"
      >
        <span class="cm-docblock doc-section-title">{{ __('Default:') }}</span>{{ ' ' }}
        <span class="cm-docblock doc-desc"><code>{{ doc.default }}</code></span>
      </div>
    </div>
  </div>
  <!-- eslint-enable vue/no-v-html -->
</template>
