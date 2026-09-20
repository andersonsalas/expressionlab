import { mount } from '@vue/test-utils';
import embed from 'vega-embed';
import countries110m from 'world-atlas/countries-110m.json';
import land110m from 'world-atlas/land-110m.json';
import states10m from 'us-atlas/states-10m.json';
import GraphVisualization from '../../../../components/console/visualizations/GraphVisualization.vue';
import { handleDownload, handleOpenTab } from '../../../../lib/api/client.js';

jest.mock('vega-embed', () => jest.fn());
jest.mock('vega-interpreter', () => ({
  expressionInterpreter: {},
}));
jest.mock('../../../../lib/api/client.js', () => ({
  handleDownload: jest.fn(),
  handleOpenTab: jest.fn(),
}));

describe('GraphVisualization.vue', () => {
  let mockFinalize;
  let mockView;
  let mockObserve;
  let mockDisconnect;
  let observerCallback;

  const flushAsync = () => new Promise((resolve) => setTimeout(resolve, 10));

  beforeEach(() => {
    jest.clearAllMocks();

    mockFinalize = jest.fn();
    mockView = {
      toSVG: jest.fn().mockResolvedValue('<svg>mock</svg>'),
      toImageURL: jest.fn().mockResolvedValue('blob:mock-png'),
    };

    embed.mockResolvedValue({
      finalize: mockFinalize,
      view: mockView,
      vgSpec: { $schema: 'https://vega.github.io/schema/vega/v5.json' },
    });

    mockObserve = jest.fn().mockImplementation(() => {
      if (observerCallback) {
        observerCallback([{ isIntersecting: true }]);
      }
    });
    mockDisconnect = jest.fn();

    global.IntersectionObserver = jest.fn().mockImplementation((callback) => {
      observerCallback = callback;
      return {
        observe: mockObserve,
        disconnect: mockDisconnect,
        unobserve: jest.fn(),
      };
    });

    Object.defineProperty(HTMLElement.prototype, 'offsetParent', {
      get() {
        return this.parentNode || document.body;
      },
      configurable: true,
    });
  });

  afterEach(() => {
    delete global.IntersectionObserver;
    document.body.innerHTML = '';
  });

  const mountGraph = async (props = {}) => {
    const wrapper = mount(GraphVisualization, {
      props: {
        data: {
          type: 'graph',
          data: {
            mark: 'bar',
            data: { values: [{ x: 1, y: 10 }] },
          },
        },
        ...props,
      },
      attachTo: document.body,
    });

    await wrapper.vm.$nextTick();
    await flushAsync();
    return wrapper;
  };

  it('renders the visualization container element', async () => {
    const wrapper = await mountGraph({
      props: {
        data: {
          type: 'graph',
          data: {
            $schema: 'https://vega.github.io/schema/vega-lite/v5.json',
            mark: 'bar',
            data: { values: [{ a: 'A', b: 28 }] },
          },
        },
      },
    });

    expect(wrapper.find('.graph-visualization-container').exists()).toBe(true);
    wrapper.unmount();
  });

  it('initializes IntersectionObserver and observes the chart container on mount', async () => {
    const wrapper = await mountGraph();

    expect(global.IntersectionObserver).toHaveBeenCalled();
    expect(mockObserve).toHaveBeenCalled();
    wrapper.unmount();
  });

  it('does not draw chart if element is not visible until intersection occurs', async () => {
    let visible = false;
    Object.defineProperty(HTMLElement.prototype, 'offsetParent', {
      get() {
        return visible ? document.body : null;
      },
      configurable: true,
    });

    const wrapper = mount(GraphVisualization, {
      props: {
        data: {
          type: 'graph',
          data: {
            mark: 'bar',
            data: { values: [{ x: 1 }] },
          },
        },
      },
      attachTo: document.body,
    });

    await wrapper.vm.$nextTick();
    await flushAsync();

    expect(embed).not.toHaveBeenCalled();

    visible = true;
    if (observerCallback) {
      observerCallback([{ isIntersecting: true }]);
    }
    await flushAsync();

    expect(embed).toHaveBeenCalledTimes(1);
    wrapper.unmount();
  });

  it('calls vega-embed with typography theme configuration', async () => {
    const wrapper = await mountGraph({
      data: {
        type: 'graph',
        data: {
          mark: 'bar',
          data: { values: [{ x: 1, y: 10 }] },
        },
      },
    });

    expect(embed).toHaveBeenCalledTimes(1);
    const [, , options] = embed.mock.calls[0];

    expect(options.renderer).toBe('svg');
    expect(options.ast).toBe(true);
    expect(options.config).toBeDefined();
    expect(options.config.mark.color).toBe('#3858e9');
    expect(options.config.axisBottom.labelAngle).toBe(-45);
    expect(options.config.title.font).toBe('Roboto Slab, serif');
    expect(options.config.title.fontWeight).toBe('bold');
    expect(options.config.axis.titleFont).toBe('Roboto Slab, serif');
    expect(options.config.axis.titleFontWeight).toBe('bold');
    expect(options.config.axis.labelFont).toBe('Cascadia Mono, monospace');
    expect(options.config.axis.gridColor).toBe('#E5E5E5');
    expect(options.config.axis.tickColor).toBe('#888888');
    expect(options.config.axis.domainColor).toBe('#888888');
    expect(options.config.legend.titleFont).toBe('Roboto Slab, serif');
    expect(options.config.legend.titleFontWeight).toBe('bold');
    expect(options.config.legend.labelFont).toBe('Cascadia Mono, monospace');
    wrapper.unmount();
  });

  it('resolves world-atlas countries dataset in-memory and removes external url', async () => {
    const wrapper = await mountGraph({
      data: {
        type: 'graph',
        data: {
          data: { url: 'https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json' },
          mark: 'geoshape',
        },
      },
    });

    expect(embed).toHaveBeenCalled();
    const [, passedSpec] = embed.mock.calls[0];
    expect(passedSpec.data.url).toBeUndefined();
    expect(passedSpec.data.values).toEqual(countries110m);
    wrapper.unmount();
  });

  it('resolves world-atlas land dataset in-memory', async () => {
    const wrapper = await mountGraph({
      data: {
        type: 'graph',
        data: {
          data: { url: 'land-110m.json' },
          mark: 'geoshape',
        },
      },
    });

    const [, passedSpec] = embed.mock.calls[0];
    expect(passedSpec.data.url).toBeUndefined();
    expect(passedSpec.data.values).toEqual(land110m);
    wrapper.unmount();
  });

  it('resolves us-atlas states dataset in-memory', async () => {
    const wrapper = await mountGraph({
      data: {
        type: 'graph',
        data: {
          data: { url: 'states-10m.json' },
          mark: 'geoshape',
        },
      },
    });

    const [, passedSpec] = embed.mock.calls[0];
    expect(passedSpec.data.url).toBeUndefined();
    expect(passedSpec.data.values).toEqual(states10m);
    wrapper.unmount();
  });

  it('recursively resolves local datasets in nested layer specifications', async () => {
    const wrapper = await mountGraph({
      data: {
        type: 'graph',
        data: {
          layer: [
            {
              data: { url: 'world-atlas' },
              mark: { type: 'geoshape', fill: '#e2e8f0' },
            },
            {
              data: { url: 'us-atlas' },
              mark: { type: 'geoshape', fill: '#3b82f6' },
            },
          ],
        },
      },
    });

    const [, passedSpec] = embed.mock.calls[0];
    expect(passedSpec.layer[0].data.url).toBeUndefined();
    expect(passedSpec.layer[0].data.values).toEqual(countries110m);
    expect(passedSpec.layer[1].data.url).toBeUndefined();
    expect(passedSpec.layer[1].data.values).toEqual(states10m);
    wrapper.unmount();
  });

  it('skips __proto__, constructor, and prototype keys in resolveLocalDatasets', async () => {
    const wrapper = await mountGraph({
      data: {
        type: 'graph',
        data: {
          mark: 'bar',
          __proto__: { url: 'evil' },
          constructor: { url: 'evil' },
          prototype: { url: 'evil' },
        },
      },
    });

    expect(({}).url).toBeUndefined();
    expect(embed).toHaveBeenCalled();
    wrapper.unmount();
  });

  it('does not crash on deeply nested specs beyond depth limit', async () => {
    const spec = { mark: 'bar', data: { values: [{ x: 1 }] } };
    let current = spec;
    for (let i = 0; i < 50; i++) {
      current.nested = { layer: [{ data: { url: 'countries-110m.json' } }] };
      current = current.nested.layer[0];
    }
    const wrapper = await mountGraph({ data: { type: 'graph', data: spec } });
    expect(embed).toHaveBeenCalled();
    wrapper.unmount();
  });

  describe('CSP Sandbox Loader', () => {
    it('resolves internal datasets asynchronously through loader.load()', async () => {
      const wrapper = await mountGraph();

      const [, , options] = embed.mock.calls[0];
      const loader = options.loader;

      await expect(loader.load('countries-110m.json')).resolves.toBe(JSON.stringify(countries110m));
      await expect(loader.load('land-110m')).resolves.toBe(JSON.stringify(land110m));
      await expect(loader.load('states-10m.json')).resolves.toBe(JSON.stringify(states10m));
      await expect(loader.http('us-atlas')).resolves.toBe(JSON.stringify(states10m));
      wrapper.unmount();
    });

    it('blocks external URLs under CSP sandbox rules with a rejected promise', async () => {
      const wrapper = await mountGraph();

      const [, , options] = embed.mock.calls[0];
      const loader = options.loader;

      await expect(loader.load('https://evil.com/malicious.json')).rejects.toThrow(
        'External URL blocked by sandbox CSP: https://evil.com/malicious.json'
      );
      await expect(loader.sanitize('https://safe.local/data.json')).resolves.toEqual({
        url: 'https://safe.local/data.json',
      });
      wrapper.unmount();
    });

    it('blocks javascript:, data:, blob:, and protocol-relative URLs', async () => {
      const wrapper = await mountGraph();

      const [, , options] = embed.mock.calls[0];
      const loader = options.loader;

      await expect(loader.load('javascript:alert(1)')).rejects.toThrow('External URL blocked');
      await expect(loader.load('data:text/html,<script>alert(1)</script>')).rejects.toThrow('External URL blocked');
      await expect(loader.load('blob:http://evil.com/uuid')).rejects.toThrow('External URL blocked');
      await expect(loader.load('//evil.com/data.json')).rejects.toThrow('External URL blocked');
      wrapper.unmount();
    });
  });

  it('re-renders chart reactively when props.data changes', async () => {
    const wrapper = await mountGraph({
      data: {
        type: 'graph',
        data: { mark: 'bar', data: { values: [1] } },
      },
    });

    expect(embed).toHaveBeenCalledTimes(1);

    await wrapper.setProps({
      data: {
        type: 'graph',
        data: { mark: 'line', data: { values: [2] } },
      },
    });
    await flushAsync();

    expect(embed).toHaveBeenCalledTimes(2);
    const [, updatedSpec] = embed.mock.calls[1];
    expect(updatedSpec.mark).toBe('line');
    wrapper.unmount();
  });

  it('renders an error container when vega-embed throws an exception', async () => {
    const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    embed.mockRejectedValueOnce(new Error('Invalid Vega specification field: "foo"'));

    const wrapper = await mountGraph({
      data: {
        type: 'graph',
        data: { mark: 'bar' },
      },
    });

    expect(consoleErrorSpy).toHaveBeenCalled();
    const errorDiv = wrapper.find('.text-red-500');
    expect(errorDiv.exists()).toBe(true);
    expect(errorDiv.text()).toContain('Vega-Lite error: Invalid Vega specification field: "foo"');

    consoleErrorSpy.mockRestore();
    wrapper.unmount();
  });

  it('intercepts Vega action links for Save as SVG and View Compiled Vega', async () => {
    embed.mockImplementation(async (el, spec, options) => {
      const actionsDiv = document.createElement('div');
      actionsDiv.className = 'vega-actions';

      const svgLink = document.createElement('a');
      svgLink.textContent = 'Save as SVG';
      actionsDiv.appendChild(svgLink);

      const vegaLink = document.createElement('a');
      vegaLink.textContent = 'View Compiled Vega';
      actionsDiv.appendChild(vegaLink);

      el.appendChild(actionsDiv);

      return {
        finalize: mockFinalize,
        view: mockView,
        vgSpec: { $schema: 'compiled-vega-schema' },
      };
    });

    const wrapper = await mountGraph({
      data: {
        type: 'graph',
        data: { mark: 'bar' },
      },
    });

    const links = wrapper.findAll('.vega-actions a');
    expect(links.length).toBe(2);

    // Trigger Save as SVG
    await links[0].trigger('click');
    expect(mockView.toSVG).toHaveBeenCalled();
    expect(handleDownload).toHaveBeenCalledWith('<svg>mock</svg>', 'visualization.svg', 'image/svg+xml');

    // Trigger View Compiled Vega
    await links[1].trigger('click');
    expect(handleOpenTab).toHaveBeenCalledWith(
      JSON.stringify({ $schema: 'compiled-vega-schema' }, null, 2),
      'application/json'
    );
    wrapper.unmount();
  });

  it('sanitizes SVG before download export, stripping script tags and onload handlers', async () => {
    const maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><foreignObject>evil</foreignObject><g onload="steal()"><text>safe</text></g></svg>';
    const mockMaliciousView = {
      toSVG: jest.fn().mockResolvedValue(maliciousSvg),
      toImageURL: jest.fn(),
    };

    embed.mockImplementationOnce(async (el) => {
      const actionsDiv = document.createElement('div');
      actionsDiv.className = 'vega-actions';

      const svgLink = document.createElement('a');
      svgLink.textContent = 'Save as SVG';
      actionsDiv.appendChild(svgLink);
      el.appendChild(actionsDiv);

      return {
        finalize: mockFinalize,
        view: mockMaliciousView,
        vgSpec: {},
      };
    });

    const wrapper = await mountGraph({
      data: {
        type: 'graph',
        data: { mark: 'bar' },
      },
    });

    const link = wrapper.find('.vega-actions a');
    await link.trigger('click');

    expect(mockMaliciousView.toSVG).toHaveBeenCalled();
    expect(handleDownload).toHaveBeenCalled();
    const [downloadedSvg, filename, mimeType] = handleDownload.mock.calls[handleDownload.mock.calls.length - 1];
    expect(filename).toBe('visualization.svg');
    expect(mimeType).toBe('image/svg+xml');
    expect(downloadedSvg).not.toContain('<script');
    expect(downloadedSvg).not.toContain('<foreignObject');
    expect(downloadedSvg).not.toContain('onload');
    expect(downloadedSvg).toContain('<text>safe</text>');
    wrapper.unmount();
  });

  it('cleans up observer and view resources on unmount', async () => {
    const wrapper = await mountGraph();

    wrapper.unmount();

    expect(mockDisconnect).toHaveBeenCalled();
    expect(mockFinalize).toHaveBeenCalled();
  });
});
