<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { __ } from '../../../lib/helpers.js';

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['rendered']);

const viewportRef = ref(null);
const minimapRef = ref(null);

const zoom = ref(1);
const pan = ref({ x: 0, y: 0 });
const imageError = ref(false);
const naturalDimensions = ref({ width: 0, height: 0 });

const viewportWidth = ref(0);
const viewportHeight = ref(0);

const PAN_PADDING = 24;

const isDraggingCanvas = ref(false);
const isDraggingLens = ref(false);
let dragStart = null;
let resizeObserver = null;

const filename = computed(() => {
  return props.data?.filename || props.data?.path || 'image';
});

const mimeType = computed(() => {
  return props.data?.mime || 'image/png';
});

const imageSrc = computed(() => {
  if (!props.data?.base64) return '';
  return `data:${mimeType.value};base64,${props.data.base64}`;
});

const dimensions = computed(() => {
  if (props.data?.dimensions?.width && props.data?.dimensions?.height) {
    return props.data.dimensions;
  }
  return naturalDimensions.value;
});

const dimensionsLabel = computed(() => {
  const { width, height } = dimensions.value;
  if (width > 0 && height > 0) {
    return `${width} × ${height} px`;
  }
  return null;
});

const formattedSize = computed(() => {
  return props.data?.size_human || (props.data?.bytes ? `${props.data.bytes} B` : null);
});

const formatLabel = computed(() => {
  const mime = mimeType.value.toLowerCase();
  if (mime.includes('png')) return 'PNG';
  if (mime.includes('jpeg') || mime.includes('jpg')) return 'JPEG';
  if (mime.includes('gif')) return 'GIF';
  if (mime.includes('webp')) return 'WebP';
  if (mime.includes('avif')) return 'AVIF';
  if (mime.includes('svg')) return 'SVG';
  return mime.replace(/^image\//, '').toUpperCase();
});

const zoomPercent = computed(() => {
  return `${Math.round(zoom.value * 100)}%`;
});

const displayedWidth = computed(() => {
  const w = dimensions.value.width || naturalDimensions.value.width || 0;
  return Math.round(w * zoom.value);
});

const displayedHeight = computed(() => {
  const h = dimensions.value.height || naturalDimensions.value.height || 0;
  return Math.round(h * zoom.value);
});

const isOverflowing = computed(() => {
  if (isAutoFit.value) return false;
  if (!viewportWidth.value || !viewportHeight.value) return false;
  const maxPanX = Math.max(0, (displayedWidth.value - viewportWidth.value) / 2 + PAN_PADDING);
  const maxPanY = Math.max(0, (displayedHeight.value - viewportHeight.value) / 2 + PAN_PADDING);
  return maxPanX > 0 || maxPanY > 0;
});

const clampPan = (x, y) => {
  if (isAutoFit.value) {
    return { x: 0, y: 0 };
  }

  const vpW = viewportWidth.value;
  const vpH = viewportHeight.value;
  const dispW = displayedWidth.value;
  const dispH = displayedHeight.value;

  const maxPanX = Math.max(0, (dispW - vpW) / 2 + PAN_PADDING);
  const maxPanY = Math.max(0, (dispH - vpH) / 2 + PAN_PADDING);

  return {
    x: Math.max(-maxPanX, Math.min(maxPanX, x)),
    y: Math.max(-maxPanY, Math.min(maxPanY, y)),
  };
};

const updateViewportSize = () => {
  if (viewportRef.value) {
    viewportWidth.value = viewportRef.value.clientWidth;
    viewportHeight.value = viewportRef.value.clientHeight;
  }
};

const isAutoFit = ref(true);
let initialZoomApplied = false;
let resizeTimeouts = [];

const calculateFitZoom = () => {
  const imgW = dimensions.value.width || naturalDimensions.value.width;
  const imgH = dimensions.value.height || naturalDimensions.value.height;
  if (!imgW || !imgH) return 1;

  const vpW = viewportWidth.value || viewportRef.value?.clientWidth || 0;
  const vpH = viewportHeight.value || viewportRef.value?.clientHeight || 0;

  const padding = PAN_PADDING * 2; // 24px each side
  const availW = Math.max(0, vpW - padding);
  const availH = Math.max(0, vpH - padding);

  if (availH <= 0 || availW <= 0) return 1;

  const scale = Math.min(availH / imgH, availW / imgW);
  return Math.max(0.05, Math.floor(scale * 100) / 100);
};

const isFitZoomActive = computed(() => {
  const imgW = dimensions.value.width || naturalDimensions.value.width;
  const imgH = dimensions.value.height || naturalDimensions.value.height;
  if (!imgW || !imgH || !viewportWidth.value || !viewportHeight.value) return false;
  const fit = calculateFitZoom();
  return isAutoFit.value || Math.abs(zoom.value - fit) < 0.01;
});

const computedViewportHeight = computed(() => {
  const imgH = dimensions.value.height || naturalDimensions.value.height || 0;
  if (!imgH) return 368;

  const minImgH = 320;
  const maxVpH = 520;
  const minVpH = Math.min(minImgH + PAN_PADDING * 2, maxVpH); // 368px

  if (imgH < minImgH) {
    return Math.max(220, Math.min(maxVpH, imgH + PAN_PADDING * 2));
  }

  return Math.min(maxVpH, Math.max(minVpH, imgH + PAN_PADDING * 2));
});

const visualizationStyle = computed(() => {
  return {
    '--image-viewport-height': `${computedViewportHeight.value}px`,
  };
});

let lastMeasuredW = 0;
let lastMeasuredH = 0;

const onViewportResize = () => {
  updateViewportSize();
  const vpW = viewportWidth.value;
  const vpH = viewportHeight.value;

  // Viewport not ready yet (e.g. collapsed panel, 0 height during initial render / animation)
  if (vpW <= 48 || vpH <= 48) return;

  const imgW = dimensions.value.width || naturalDimensions.value.width;
  const imgH = dimensions.value.height || naturalDimensions.value.height;
  if (!imgW || !imgH) return;

  if (initialZoomApplied && vpW === lastMeasuredW && vpH === lastMeasuredH) {
    return;
  }
  lastMeasuredW = vpW;
  lastMeasuredH = vpH;

  if (isAutoFit.value) {
    const fit = calculateFitZoom();
    zoom.value = initialZoomApplied ? fit : Math.min(1, fit);
    initialZoomApplied = true;
    pan.value = { x: 0, y: 0 };
  } else {
    pan.value = clampPan(pan.value.x, pan.value.y);
  }
};

const scheduleDelayedFitChecks = () => {
  resizeTimeouts.forEach(clearTimeout);
  resizeTimeouts = [];

  [50, 150, 300, 500].forEach((delay) => {
    const timer = setTimeout(() => {
      if (isAutoFit.value) {
        onViewportResize();
      }
    }, delay);
    resizeTimeouts.push(timer);
  });
};

const zoomToFit = () => {
  isAutoFit.value = true;
  initialZoomApplied = true;
  zoom.value = calculateFitZoom();
  pan.value = { x: 0, y: 0 };
};

const zoomIn = () => {
  isAutoFit.value = false;
  if (zoom.value < 5) {
    const next = zoom.value < 0.5 ? zoom.value + 0.1 : zoom.value + 0.25;
    zoom.value = Math.min(5, Math.round(next * 100) / 100);
    pan.value = clampPan(pan.value.x, pan.value.y);
  }
};

const zoomOut = () => {
  isAutoFit.value = false;
  if (zoom.value > 0.05) {
    const next = zoom.value <= 0.5 ? zoom.value - 0.1 : zoom.value - 0.25;
    zoom.value = Math.max(0.05, Math.round(next * 100) / 100);
    pan.value = clampPan(pan.value.x, pan.value.y);
  }
};

const resetZoom = () => {
  isAutoFit.value = false;
  zoom.value = 1;
  pan.value = clampPan(pan.value.x, pan.value.y);
};

// Minimap calculations
const MAX_MINIMAP_W = 120;
const MAX_MINIMAP_H = 80;

const minimapSize = computed(() => {
  const imgW = dimensions.value.width || naturalDimensions.value.width || 1;
  const imgH = dimensions.value.height || naturalDimensions.value.height || 1;
  const aspect = imgW / imgH;

  if (aspect >= MAX_MINIMAP_W / MAX_MINIMAP_H) {
    return {
      width: MAX_MINIMAP_W,
      height: Math.max(20, Math.round(MAX_MINIMAP_W / aspect)),
    };
  }
  return {
    width: Math.max(20, Math.round(MAX_MINIMAP_H * aspect)),
    height: MAX_MINIMAP_H,
  };
});

const minimapStyle = computed(() => {
  return {
    width: `${minimapSize.value.width}px`,
    height: `${minimapSize.value.height}px`,
  };
});

const lensSize = computed(() => {
  const mW = minimapSize.value.width;
  const mH = minimapSize.value.height;
  const dispW = displayedWidth.value || 1;
  const dispH = displayedHeight.value || 1;
  const vpW = viewportWidth.value || 1;
  const vpH = viewportHeight.value || 1;

  const w = Math.min(mW, Math.max(12, Math.round(mW * (vpW / dispW))));
  const h = Math.min(mH, Math.max(12, Math.round(mH * (vpH / dispH))));

  return { width: w, height: h };
});

const lensPosition = computed(() => {
  const mW = minimapSize.value.width;
  const mH = minimapSize.value.height;
  const lW = lensSize.value.width;
  const lH = lensSize.value.height;
  const dispW = displayedWidth.value || 1;
  const dispH = displayedHeight.value || 1;

  const centerLensX = (mW - lW) / 2;
  const centerLensY = (mH - lH) / 2;

  const rawX = centerLensX - (pan.value.x / dispW) * mW;
  const rawY = centerLensY - (pan.value.y / dispH) * mH;

  const clampedX = Math.max(0, Math.min(mW - lW, Math.round(rawX)));
  const clampedY = Math.max(0, Math.min(mH - lH, Math.round(rawY)));

  return { x: clampedX, y: clampedY };
});

const lensStyle = computed(() => {
  return {
    width: `${lensSize.value.width}px`,
    height: `${lensSize.value.height}px`,
    transform: `translate3d(${lensPosition.value.x}px, ${lensPosition.value.y}px, 0)`,
    transition: isDraggingLens.value ? 'none' : 'transform 0.15s ease-out',
  };
});

const wrapperStyle = computed(() => {
  return {
    transform: `translate3d(${pan.value.x}px, ${pan.value.y}px, 0)`,
    transition: isDraggingCanvas.value || isDraggingLens.value ? 'none' : 'transform 0.15s ease-out',
  };
});

const imageStyle = computed(() => {
  if (dimensions.value.width > 0 && dimensions.value.height > 0) {
    return {
      width: `${displayedWidth.value}px`,
      height: `${displayedHeight.value}px`,
    };
  }
  return {
    maxWidth: '100%',
    height: 'auto',
  };
});

const onCanvasMouseDown = (e) => {
  if (e.button !== 0 || !isOverflowing.value) return;
  if (minimapRef.value && minimapRef.value.contains(e.target)) return;

  isDraggingCanvas.value = true;
  dragStart = {
    mouseX: e.clientX,
    mouseY: e.clientY,
    panX: pan.value.x,
    panY: pan.value.y,
    mode: 'canvas',
  };

  window.addEventListener('mousemove', onGlobalMouseMove);
  window.addEventListener('mouseup', onGlobalMouseUp);
};

const onLensMouseDown = (e) => {
  if (e.button !== 0) return;
  e.stopPropagation();
  e.preventDefault();

  isDraggingLens.value = true;
  dragStart = {
    mouseX: e.clientX,
    mouseY: e.clientY,
    panX: pan.value.x,
    panY: pan.value.y,
    mode: 'lens',
  };

  window.addEventListener('mousemove', onGlobalMouseMove);
  window.addEventListener('mouseup', onGlobalMouseUp);
};

const onMinimapMouseDown = (e) => {
  if (e.button !== 0 || !minimapRef.value) return;
  e.stopPropagation();
  e.preventDefault();

  const rect = minimapRef.value.getBoundingClientRect();
  const clickX = e.clientX - rect.left;
  const clickY = e.clientY - rect.top;

  const mW = minimapSize.value.width;
  const mH = minimapSize.value.height;
  const lW = lensSize.value.width;
  const lH = lensSize.value.height;
  const dispW = displayedWidth.value || 1;
  const dispH = displayedHeight.value || 1;

  const centerLensX = (mW - lW) / 2;
  const centerLensY = (mH - lH) / 2;

  const targetLensX = Math.max(0, Math.min(mW - lW, clickX - lW / 2));
  const targetLensY = Math.max(0, Math.min(mH - lH, clickY - lH / 2));

  const deltaX = targetLensX - centerLensX;
  const deltaY = targetLensY - centerLensY;

  pan.value = clampPan(-deltaX * (dispW / mW), -deltaY * (dispH / mH));

  isDraggingLens.value = true;
  dragStart = {
    mouseX: e.clientX,
    mouseY: e.clientY,
    panX: pan.value.x,
    panY: pan.value.y,
    mode: 'lens',
  };

  window.addEventListener('mousemove', onGlobalMouseMove);
  window.addEventListener('mouseup', onGlobalMouseUp);
};

const onGlobalMouseMove = (e) => {
  if (!dragStart) return;

  const dx = e.clientX - dragStart.mouseX;
  const dy = e.clientY - dragStart.mouseY;

  if (dragStart.mode === 'canvas') {
    pan.value = clampPan(dragStart.panX + dx, dragStart.panY + dy);
  } else if (dragStart.mode === 'lens') {
    const mW = minimapSize.value.width;
    const mH = minimapSize.value.height;
    const dispW = displayedWidth.value || 1;
    const dispH = displayedHeight.value || 1;

    const panDx = -dx * (dispW / mW);
    const panDy = -dy * (dispH / mH);
    pan.value = clampPan(dragStart.panX + panDx, dragStart.panY + panDy);
  }
};

const onGlobalMouseUp = () => {
  isDraggingCanvas.value = false;
  isDraggingLens.value = false;
  dragStart = null;
  window.removeEventListener('mousemove', onGlobalMouseMove);
  window.removeEventListener('mouseup', onGlobalMouseUp);
};

const onWheel = (e) => {
  if (e.ctrlKey || e.metaKey) {
    e.preventDefault();
    isAutoFit.value = false;
    if (e.deltaY < 0) {
      zoomIn();
    } else {
      zoomOut();
    }
    return;
  }

  if (isAutoFit.value || !isOverflowing.value) {
    return;
  }

  const newPan = clampPan(pan.value.x - e.deltaX, pan.value.y - e.deltaY);
  const changedX = Math.abs(newPan.x - pan.value.x) > 0.01;
  const changedY = Math.abs(newPan.y - pan.value.y) > 0.01;

  if (changedX || changedY) {
    e.preventDefault();
    pan.value = newPan;
  }
};

const onImageLoad = (event) => {
  if (event.target) {
    naturalDimensions.value = {
      width: event.target.naturalWidth || 0,
      height: event.target.naturalHeight || 0,
    };
  }
  imageError.value = false;
  onViewportResize();
  scheduleDelayedFitChecks();
  emit('rendered');
};

const onImageError = () => {
  imageError.value = true;
  emit('rendered');
};

watch(() => props.data, () => {
  imageError.value = false;
  initialZoomApplied = false;
  isAutoFit.value = true;
  lastMeasuredW = 0;
  lastMeasuredH = 0;
  nextTick(() => {
    onViewportResize();
    scheduleDelayedFitChecks();
  });
});

onMounted(() => {
  updateViewportSize();
  if (dimensions.value.width > 0 && dimensions.value.height > 0) {
    onViewportResize();
  }
  scheduleDelayedFitChecks();
  emit('rendered');

  if (typeof ResizeObserver !== 'undefined' && viewportRef.value) {
    resizeObserver = new ResizeObserver(() => {
      onViewportResize();
    });
    resizeObserver.observe(viewportRef.value);
  }
});

onUnmounted(() => {
  if (resizeObserver) {
    resizeObserver.disconnect();
  }
  resizeTimeouts.forEach(clearTimeout);
  resizeTimeouts = [];
  window.removeEventListener('mousemove', onGlobalMouseMove);
  window.removeEventListener('mouseup', onGlobalMouseUp);
});
</script>

<template>
  <div
    class="image-visualization"
    :style="visualizationStyle"
  >
    <!-- Image Canvas Viewport (Starts directly with image) -->
    <div
      ref="viewportRef"
      class="image-viewport"
      :class="{
        'is-pannable': isOverflowing,
        'is-dragging': isDraggingCanvas,
      }"
      @mousedown="onCanvasMouseDown"
      @wheel="onWheel"
    >
      <div
        v-if="imageError"
        class="image-error-state"
      >
        <span class="codicon codicon-error error-icon" />
        <span class="error-text">{{ __('Failed to load or decode image.') }}</span>
      </div>

      <div
        v-else-if="!imageSrc"
        class="image-empty-state"
      >
        <span class="codicon codicon-info info-icon" />
        <span class="info-text">{{ __('No image data available.') }}</span>
      </div>

      <div
        v-else
        class="image-wrapper"
        :style="wrapperStyle"
      >
        <img
          :src="imageSrc"
          :alt="filename"
          class="rendered-image"
          :style="imageStyle"
          draggable="false"
          @load="onImageLoad"
          @error="onImageError"
        >
      </div>

      <!-- Minimap Navigator Control in bottom-right corner -->
      <div
        v-if="isOverflowing && imageSrc && !imageError"
        ref="minimapRef"
        class="image-minimap"
        :style="minimapStyle"
        :title="__('Click or drag to navigate')"
        @mousedown="onMinimapMouseDown"
      >
        <img
          :src="imageSrc"
          class="minimap-thumb"
          alt=""
          draggable="false"
        >
        <div
          class="minimap-lens"
          :class="{ 'is-dragging': isDraggingLens }"
          :style="lensStyle"
          @mousedown="onLensMouseDown"
        />
      </div>
    </div>

    <!-- Bottom Footer Bar -->
    <div class="image-footer">
      <!-- Left: Image metadata segments -->
      <div class="image-info-segments">
        <div
          v-if="dimensionsLabel"
          class="footer-segment segment-dimensions"
        >
          {{ dimensionsLabel }}
        </div>

        <div
          v-if="formattedSize"
          class="footer-segment segment-size"
        >
          {{ formattedSize }}
        </div>

        <div
          v-if="formatLabel"
          class="footer-segment segment-format"
        >
          {{ formatLabel }}
        </div>
      </div>

      <!-- Right: Controls group -->
      <div class="image-controls-group">
        <!-- Zoom to fit button -->
        <button
          type="button"
          class="footer-btn btn-zoom-fit"
          :class="{ active: isFitZoomActive }"
          :title="__('Zoom to fit')"
          @click="zoomToFit"
        >
          <span class="codicon codicon-screen-full" />
        </button>

        <!-- Zoom Controls -->
        <div class="zoom-controls">
          <button
            type="button"
            class="zoom-btn"
            :disabled="zoom <= 0.05"
            :title="__('Zoom out')"
            @click="zoomOut"
          >
            <span class="codicon codicon-zoom-out" />
          </button>
          <span
            class="zoom-level"
            :title="__('Click to reset zoom to 100%')"
            @click="resetZoom"
          >
            {{ zoomPercent }}
          </span>
          <button
            type="button"
            class="zoom-btn"
            :disabled="zoom >= 5"
            :title="__('Zoom in')"
            @click="zoomIn"
          >
            <span class="codicon codicon-zoom-in" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
