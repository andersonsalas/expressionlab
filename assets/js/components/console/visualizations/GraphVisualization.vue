<script setup>
import { onMounted, ref, watch, onUnmounted, nextTick } from 'vue';
import embed from 'vega-embed';
import { expressionInterpreter } from 'vega-interpreter';
import { handleDownload, handleOpenTab } from '../../../lib/api/client.js';
import countries110m from 'world-atlas/countries-110m.json';
import land110m from 'world-atlas/land-110m.json';
import states10m from 'us-atlas/states-10m.json';

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

/**
 * Traverses a Vega specification recursively and replaces known external
 * dataset URLs (such as world-atlas TopoJSON files) with local, in-memory copies
 * to guarantee 100% offline rendering under strict CSP sandbox rules.
 *
 * @param {object} obj Vega specification or nested object.
 */
const resolveLocalDatasets = (obj) => {
    if (!obj || typeof obj !== 'object') return;

    if (obj.data && typeof obj.data === 'object' && obj.data.url) {
        const url = String(obj.data.url);
        if (url.includes('countries-110m.json') || url === 'countries-110m' || url === 'world-atlas') {
            delete obj.data.url;
            obj.data.values = countries110m;
        } else if (url.includes('land-110m.json') || url === 'land-110m') {
            delete obj.data.url;
            obj.data.values = land110m;
        } else if (url.includes('states-10m.json') || url === 'states-10m' || url === 'us-atlas') {
            delete obj.data.url;
            obj.data.values = states10m;
        }
    }

    for (const key of Object.keys(obj)) {
        if (typeof obj[key] === 'object' && obj[key] !== null) {
            resolveLocalDatasets(obj[key]);
        }
    }
};

const drawChart = async () => {
    if (!props.data || !props.data.data || !chartRef.value) return;

    if (!isVisible()) {
        isPending.value = true;
        return;
    }

    // Create a deep, plain copy (without Vue Proxies) to avoid the structuredClone error
    const spec = JSON.parse(JSON.stringify(props.data.data));

    // Resolve pre-bundled datasets locally (avoiding external network fetch under CSP)
    resolveLocalDatasets(spec);

    // Expression Lab default typography and aesthetic theme.
    const defaultThemeConfig = {
        font: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        mark: {
            color: '#3858e9',
        },
        title: {
            font: 'Roboto Slab, serif',
            fontSize: 16,
            fontWeight: 'bold',
            anchor: 'start',
            color: '#1e293b',
        },
        axis: {
            titleFont: 'Roboto Slab, serif',
            titleFontSize: 13,
            titleFontWeight: 'bold',
            labelFont: 'Cascadia Mono, monospace',
            labelFontSize: 12,
            gridColor: '#E5E5E5',
            tickColor: '#888888',
            domainColor: '#888888',
        },
        axisBottom: {
            labelAngle: -45,
        },
        legend: {
            titleFont: 'Roboto Slab, serif',
            titleFontSize: 13,
            titleFontWeight: 'bold',
            labelFont: 'Cascadia Mono, monospace',
            labelFontSize: 12,
        },
        view: {
            stroke: 'transparent',
        },
    };

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
        expr: expressionInterpreter,
        config: defaultThemeConfig,
        loader: {
            load(url) {
                const urlStr = String(url);
                if (urlStr.includes('countries-110m.json') || urlStr === 'countries-110m' || urlStr === 'world-atlas') {
                    return Promise.resolve(JSON.stringify(countries110m));
                }
                if (urlStr.includes('land-110m.json') || urlStr === 'land-110m') {
                    return Promise.resolve(JSON.stringify(land110m));
                }
                if (urlStr.includes('states-10m.json') || urlStr === 'states-10m' || urlStr === 'us-atlas') {
                    return Promise.resolve(JSON.stringify(states10m));
                }
                return Promise.reject(new Error(`External URL blocked by sandbox CSP: ${url}`));
            },
            sanitize(url) {
                return Promise.resolve({ url });
            },
            http(url) {
                return this.load(url);
            }
        }
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