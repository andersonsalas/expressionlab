<script setup>
import { onMounted, ref, watch, onUnmounted, nextTick } from 'vue';
import embed from 'vega-embed';
import { expressionInterpreter } from 'vega-interpreter';
import { handleDownload, handleOpenTab } from '../../../lib/api/client.js';

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const chartRef = ref(null);
let viewCleanup = null;
let observer = null;
const isPending = ref(true);

const isVisible = () => chartRef.value && chartRef.value.offsetParent !== null;

const drawChart = async () => {
    if (!props.data || !props.data.data || !chartRef.value) return;

    if (!isVisible()) {
        isPending.value = true;
        return;
    }

    // Create a deep, plain copy (without Vue Proxies) to avoid the structuredClone error
    const spec = JSON.parse(JSON.stringify(props.data.data));

    // Graph config.
    const options = {
        actions: {
          export: true,
          source: false,
          compiled: true,
          editor: false, 
        },
        renderer: 'svg',
        ast: true,
        expr: expressionInterpreter
    };

    try {
        if (viewCleanup) viewCleanup();
        const result = await embed(chartRef.value, spec, options);
        viewCleanup = result.finalize; 
        isPending.value = false;

        // Custom action interceptor for Vega Embed menu
        const vegaActions = chartRef.value.querySelector('.vega-actions');
        if (vegaActions) {
            const links = vegaActions.querySelectorAll('a');
            links.forEach(link => {
                const text = link.textContent.trim();
                
                if (text === 'Save as SVG' || text === 'Save as PNG' || text === 'View Compiled Vega') {
                    link.addEventListener('click', async (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        if (text === 'Save as SVG') {
                            const svg = await result.view.toSVG();
                            handleDownload(svg, 'visualization.svg', 'image/svg+xml');
                        } else if (text === 'Save as PNG') {
                            const url = await result.view.toImageURL('png');
                            const res = await fetch(url);
                            const buffer = await res.arrayBuffer();
                            handleDownload(buffer, 'visualization.png', 'image/png');
                        } else if (text === 'View Compiled Vega') {
                            const json = JSON.stringify(result.vgSpec, null, 2);
                            handleOpenTab(json, 'application/json');
                        }
                    });
                }
            });
        }
    } catch (error) {
        console.error('Vega-Lite error:', error);
        if (chartRef.value) {
            chartRef.value.textContent = '';
            const errDiv = document.createElement('div');
            errDiv.className = 'text-red-500';
            errDiv.textContent = `Vega-Lite error: ${error.message}`;
            chartRef.value.appendChild(errDiv);
        }
    }
};

watch(() => props.data, () => {
    isPending.value = true;
    drawChart();
}, { deep: true });

onMounted(async () => {
    await nextTick();
    
    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && isPending.value) {
            drawChart();
        }
    });

    if (chartRef.value) {
        observer.observe(chartRef.value);
    }
});

onUnmounted(() => {
    if (observer) observer.disconnect();
    if (viewCleanup) viewCleanup();
});
</script>

<template>
  <div
    ref="chartRef"
    class="graph-visualization-container"
  />
</template>

<style scoped>
.graph-visualization-container {
    width: 100%;
    min-height: 200px; 
}
</style>