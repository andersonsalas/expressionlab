import React, { useEffect, useRef, useState } from 'react';
import BrowserOnly from '@docusaurus/BrowserOnly';
import countries110m from 'world-atlas/countries-110m.json';
import land110m from 'world-atlas/land-110m.json';
import states10m from 'us-atlas/states-10m.json';
import styles from './styles.module.css';

/**
 * Traverses a Vega/Vega-Lite specification recursively and replaces known TopoJSON
 * URLs with bundled in-memory datasets to ensure 100% offline rendering without
 * external network requests. Also sanitizes tooltip channel usage.
 */
function normalizeSpec(obj, isDark = false) {
  if (!obj || typeof obj !== 'object') return;

  // Resolve local TopoJSON datasets
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

  // Sanitize encoding tooltip boolean warning in Vega-Lite v6
  if (obj.encoding && obj.encoding.tooltip === true) {
    delete obj.encoding.tooltip;
    if (obj.mark) {
      if (typeof obj.mark === 'string') {
        obj.mark = { type: obj.mark, tooltip: true };
      } else if (typeof obj.mark === 'object') {
        obj.mark.tooltip = true;
      }
    }
  }

  // Adapt boxplot whisker rules for high contrast in dark/light themes
  if (obj.mark) {
    const isBoxplot = obj.mark === 'boxplot' || (typeof obj.mark === 'object' && obj.mark.type === 'boxplot');
    if (isBoxplot) {
      if (typeof obj.mark === 'string') {
        obj.mark = { type: 'boxplot' };
      }
      if (typeof obj.mark === 'object' && obj.mark !== null) {
        if (!obj.mark.rule || obj.mark.rule === true) {
          obj.mark.rule = { color: isDark ? '#cbd5e1' : '#000000' };
        } else if (typeof obj.mark.rule === 'object') {
          const ruleColor = String(obj.mark.rule.color || obj.mark.rule.stroke || '').toLowerCase().trim();
          if (isDark && (ruleColor === 'black' || ruleColor === '#000' || ruleColor === '#000000' || ruleColor === '#1e293b' || ruleColor === '')) {
            obj.mark.rule.color = '#cbd5e1';
          } else if (!isDark && (ruleColor === '#cbd5e1' || ruleColor === '#e2e8f0')) {
            obj.mark.rule.color = '#000000';
          }
        }
      }
    } else if (typeof obj.mark === 'object' && obj.mark !== null && obj.mark.type === 'rule') {
      const ruleColor = String(obj.mark.color || obj.mark.stroke || '').toLowerCase().trim();
      if (isDark && (ruleColor === 'black' || ruleColor === '#000' || ruleColor === '#000000')) {
        obj.mark.color = '#cbd5e1';
      }
    }
  }

  for (const key of Object.keys(obj)) {
    if (typeof obj[key] === 'object' && obj[key] !== null) {
      normalizeSpec(obj[key], isDark);
    }
  }
}

/**
 * Inner chart component that executes strictly in browser context.
 */
function VegaLiteBrowser({ spec, actions = false, className = '' }) {
  const chartRef = useRef(null);
  const [error, setError] = useState(null);
  const [isDark, setIsDark] = useState(() => {
    if (typeof document !== 'undefined') {
      return document.documentElement.getAttribute('data-theme') === 'dark';
    }
    return false;
  });

  // Track Docusaurus theme changes via HTML data-theme attribute
  useEffect(() => {
    if (typeof document === 'undefined') return;

    const updateTheme = () => {
      const dark = document.documentElement.getAttribute('data-theme') === 'dark';
      setIsDark(dark);
    };

    updateTheme();

    const observer = new MutationObserver((mutations) => {
      for (const mutation of mutations) {
        if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
          updateTheme();
        }
      }
    });

    observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['data-theme'],
    });

    return () => observer.disconnect();
  }, []);

  // Use primitive string key to prevent infinite re-render loops on object prop reference changes
  const specKey = typeof spec === 'string' ? spec : JSON.stringify(spec);

  useEffect(() => {
    let view = null;
    let isCancelled = false;

    async function draw() {
      if (!chartRef.current) return;
      const container = chartRef.current;

      try {
        setError(null);

        // Dynamically import Vega-Embed client-side
        const embedModule = await import('vega-embed');
        const embed = embedModule.default || embedModule;

        if (isCancelled) return;

        let parsedSpec;
        if (typeof spec === 'string') {
          parsedSpec = JSON.parse(spec);
        } else if (typeof spec === 'object' && spec !== null) {
          parsedSpec = JSON.parse(JSON.stringify(spec));
        } else {
          throw new Error('Invalid specification: expected object or JSON string.');
        }

        // Deep copy and normalize spec (resolve TopoJSON URLs, sanitize tooltip channel, adapt theme contrast)
        normalizeSpec(parsedSpec, isDark);

        // Apply Expression Lab typography and design tokens matching GraphVisualization.vue
        const themeConfig = {
          background: 'transparent',
          font: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
          mark: {
            color: '#3858e9',
          },
          rule: {
            color: isDark ? '#cbd5e1' : '#000000',
          },
          title: {
            font: 'Roboto Slab, serif',
            fontSize: 16,
            fontWeight: 'bold',
            anchor: 'start',
            color: isDark ? '#f1f5f9' : '#1e293b',
          },
          axis: {
            titleFont: 'Roboto Slab, serif',
            titleFontSize: 13,
            titleFontWeight: 'bold',
            titleColor: isDark ? '#f1f5f9' : '#000000',
            labelFont: 'Cascadia Mono, monospace',
            labelFontSize: 12,
            labelColor: isDark ? '#e2e8f0' : '#000000',
            gridColor: isDark ? '#334155' : '#E5E5E5',
            tickColor: isDark ? '#64748b' : '#888888',
            domainColor: isDark ? '#64748b' : '#888888',
          },
          axisBottom: {
            labelAngle: -45,
          },
          legend: {
            titleFont: 'Roboto Slab, serif',
            titleFontSize: 13,
            titleFontWeight: 'bold',
            titleColor: isDark ? '#f1f5f9' : '#000000',
            labelFont: 'Cascadia Mono, monospace',
            labelFontSize: 12,
            labelColor: isDark ? '#e2e8f0' : '#000000',
          },
          view: {
            stroke: 'transparent',
          },
        };

        const embedOptions = {
          actions: actions ? { export: true, source: false, compiled: false, editor: false } : false,
          renderer: 'svg',
          tooltip: { theme: isDark ? 'dark' : 'light' },
          config: themeConfig,
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
            },
          },
        };

        container.innerHTML = '';
        const res = await embed(container, parsedSpec, embedOptions);
        if (isCancelled) {
          if (res && res.view) res.view.finalize();
          return;
        }
        view = res.view;
      } catch (err) {
        if (!isCancelled) {
          console.error('VegaLite rendering error:', err);
          setError(err.message || String(err));
        }
      }
    }

    draw();

    return () => {
      isCancelled = true;
      if (view && typeof view.finalize === 'function') {
        view.finalize();
      }
      if (chartRef.current) {
        chartRef.current.innerHTML = '';
      }
    };
  }, [specKey, isDark, actions]);

  return (
    <div className={`${styles.chartWrapper} ${className}`}>
      {error && <div className={styles.error}>Vega-Lite error: {error}</div>}
      <div ref={chartRef} className={styles.chartContainer} />
    </div>
  );
}

/**
 * Universal Docusaurus Vega-Lite component.
 *
 * Safe for SSR: wraps Vega runtime execution in BrowserOnly.
 */
export default function VegaLite(props) {
  return (
    <BrowserOnly fallback={<div className={styles.chartWrapper}><div className={styles.chartContainer} /></div>}>
      {() => <VegaLiteBrowser {...props} />}
    </BrowserOnly>
  );
}
