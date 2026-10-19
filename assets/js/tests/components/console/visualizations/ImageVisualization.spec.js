import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import ImageVisualization from '../../../../components/console/visualizations/ImageVisualization.vue';

describe('ImageVisualization.vue', () => {
  // 1x1 transparent PNG base64
  const sampleBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

  const mockImageData = {
    type: 'image',
    title: 'Image: sample.png',
    filename: 'sample.png',
    path: 'wp-content/uploads/2026/08/sample.png',
    mime: 'image/png',
    base64: sampleBase64,
    dimensions: { width: 100, height: 100 },
    bytes: 75,
    size_human: '75 B',
    size_name: 'medium',
    attachment_id: 42,
  };

  let wrapper;

  const mountComponent = (props = {}) => {
    const pinia = createTestingPinia({
      stubActions: false,
    });

    wrapper = mount(ImageVisualization, {
      props: {
        data: mockImageData,
        ...props,
      },
      global: {
        plugins: [pinia],
      },
      attachTo: document.body,
    });
    return wrapper;
  };

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount();
      wrapper = null;
    }
    document.body.innerHTML = '';
  });

  it('renders image viewport directly with no top toolbar', () => {
    const w = mountComponent();

    expect(w.find('.image-toolbar').exists()).toBe(false);
    expect(w.find('.image-viewport').exists()).toBe(true);
    expect(w.find('img.rendered-image').exists()).toBe(true);
    expect(w.find('img.rendered-image').attributes('src')).toBe(`data:image/png;base64,${sampleBase64}`);
  });

  it('renders footer with dimensions, size and format segments and omits medium badge', () => {
    const w = mountComponent();

    const footer = w.find('.image-footer');
    expect(footer.exists()).toBe(true);
    expect(footer.find('.segment-dimensions').text()).toBe('100 × 100 px');
    expect(footer.find('.segment-size').text()).toBe('75 B');
    expect(footer.find('.segment-format').text()).toBe('PNG');

    // Should omit thumbnail size name badge
    expect(footer.text()).not.toContain('medium');

    // Should not have Data URI or Download action buttons
    expect(footer.find('button[title*="Data URI"]').exists()).toBe(false);
    expect(footer.find('button[title*="Download"]').exists()).toBe(false);
  });

  it('handles zoom in, zoom out, and reset controls', async () => {
    const w = mountComponent();

    const zoomOutBtn = w.find('button[title="Zoom out"]');
    const zoomInBtn = w.find('button[title="Zoom in"]');
    const zoomReset = w.find('.zoom-level');

    expect(zoomReset.text()).toBe('100%');

    await zoomInBtn.trigger('click');
    expect(w.find('.zoom-level').text()).toBe('125%');

    await zoomOutBtn.trigger('click');
    expect(w.find('.zoom-level').text()).toBe('100%');

    await zoomInBtn.trigger('click');
    await zoomInBtn.trigger('click');
    expect(w.find('.zoom-level').text()).toBe('150%');

    await zoomReset.trigger('click');
    expect(w.find('.zoom-level').text()).toBe('100%');
  });

  it('does not render checkerboard toggle button', () => {
    const w = mountComponent();
    expect(w.find('.btn-checkerboard').exists()).toBe(false);
  });

  it('renders error state when image fails to load', async () => {
    const w = mountComponent();

    const img = w.find('img.rendered-image');
    await img.trigger('error');

    expect(w.find('.image-error-state').exists()).toBe(true);
    expect(w.text()).toContain('Failed to load or decode image.');
  });

  it('renders zoom to fit button with screen-full codicon', async () => {
    const w = mountComponent();

    const zoomFitBtn = w.find('button.btn-zoom-fit');
    expect(zoomFitBtn.exists()).toBe(true);
    expect(zoomFitBtn.find('.codicon-screen-full').exists()).toBe(true);
    expect(zoomFitBtn.attributes('title')).toBe('Zoom to fit');
  });

  it('calculates fit zoom when viewport dimensions are present and applies it on click', async () => {
    // Mock viewport client dimensions
    jest.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(800);
    jest.spyOn(HTMLElement.prototype, 'clientHeight', 'get').mockReturnValue(500);

    // Image 1000x1000 px, available viewport is (800-48)=752 x (500-48)=452 px
    // Expected fit zoom = floor(min(452/1000, 752/1000) * 100) / 100 = 0.45 -> 45%
    const w = mountComponent({
      data: {
        ...mockImageData,
        dimensions: { width: 1000, height: 1000 },
      },
    });
    await w.vm.$nextTick();

    // Initial render scale should automatically fit to container (45%)
    expect(w.find('.zoom-level').text()).toBe('45%');
    expect(w.find('button.btn-zoom-fit').classes()).toContain('active');

    // Zooming in deactivates fit active state
    await w.find('button[title="Zoom in"]').trigger('click');
    expect(w.find('button.btn-zoom-fit').classes()).not.toContain('active');

    // Clicking zoom to fit button restores fit scale
    await w.find('button.btn-zoom-fit').trigger('click');
    expect(w.find('.zoom-level').text()).toBe('45%');
    expect(w.find('button.btn-zoom-fit').classes()).toContain('active');

    jest.restoreAllMocks();
  });

  it('renders minimap navigator when image overflows viewport and allows lens dragging', async () => {
    jest.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(400);
    jest.spyOn(HTMLElement.prototype, 'clientHeight', 'get').mockReturnValue(300);

    const w = mountComponent({
      data: {
        ...mockImageData,
        dimensions: { width: 1200, height: 800 },
      },
    });

    // Reset zoom to 100% so it definitely overflows (1200 > 400, 800 > 300)
    await w.find('.zoom-level').trigger('click');
    expect(w.find('.zoom-level').text()).toBe('100%');

    // Minimap should be rendered
    const minimap = w.find('.image-minimap');
    expect(minimap.exists()).toBe(true);
    expect(minimap.find('.minimap-thumb').exists()).toBe(true);
    expect(minimap.find('.minimap-lens').exists()).toBe(true);
    expect(w.find('.image-viewport').classes()).toContain('is-pannable');

    // Test mouse down on lens and dragging
    const lens = minimap.find('.minimap-lens');
    await lens.trigger('mousedown', { clientX: 50, clientY: 50, button: 0 });

    // Trigger global mousemove
    window.dispatchEvent(new MouseEvent('mousemove', { clientX: 60, clientY: 60 }));
    // Trigger global mouseup
    window.dispatchEvent(new MouseEvent('mouseup'));

    // Should still exist and wrapper transformed
    expect(w.find('.image-wrapper').exists()).toBe(true);

    jest.restoreAllMocks();
  });

  it('handles mouse wheel for zooming with ctrlKey and smart scroll passthrough without ctrlKey', async () => {
    jest.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(400);
    jest.spyOn(HTMLElement.prototype, 'clientHeight', 'get').mockReturnValue(300);

    const w = mountComponent({
      data: {
        ...mockImageData,
        dimensions: { width: 1000, height: 1000 },
      },
    });

    const viewportEl = w.find('.image-viewport').element;

    // 1. Initial state is zoom-to-fit (isAutoFit is true). Normal wheel should NOT preventDefault.
    const fitWheelEvent = new WheelEvent('wheel', {
      ctrlKey: false,
      deltaX: 0,
      deltaY: 100,
      cancelable: true,
      bubbles: true,
    });
    viewportEl.dispatchEvent(fitWheelEvent);
    await w.vm.$nextTick();
    expect(fitWheelEvent.defaultPrevented).toBe(false);

    // 2. Ctrl + Wheel down zooms in/out and DOES preventDefault
    const ctrlWheelEvent = new WheelEvent('wheel', {
      ctrlKey: true,
      deltaY: 100,
      cancelable: true,
      bubbles: true,
    });
    viewportEl.dispatchEvent(ctrlWheelEvent);
    await w.vm.$nextTick();
    expect(ctrlWheelEvent.defaultPrevented).toBe(true);

    // Reset zoom to 100% (now isAutoFit is false, 1000px > 400px and 1000px > 300px)
    await w.find('.zoom-level').trigger('click');
    expect(w.find('.zoom-level').text()).toBe('100%');

    // 3. Zoomed in: Normal wheel within bounds pans image and DOES preventDefault
    const panEvent = new WheelEvent('wheel', {
      ctrlKey: false,
      deltaX: 0,
      deltaY: 50,
      cancelable: true,
      bubbles: true,
    });
    viewportEl.dispatchEvent(panEvent);
    await w.vm.$nextTick();
    expect(panEvent.defaultPrevented).toBe(true);

    // Pan all the way down beyond the overscroll boundary:
    // With 1000x1000 in 400x300, maxPanY = (1000 - 300)/2 + 24 = 374.
    // Dispatching a huge deltaY to hit the bottom boundary:
    const hugePanDown = new WheelEvent('wheel', {
      ctrlKey: false,
      deltaX: 0,
      deltaY: 1000,
      cancelable: true,
      bubbles: true,
    });
    viewportEl.dispatchEvent(hugePanDown);
    await w.vm.$nextTick();
    expect(hugePanDown.defaultPrevented).toBe(true);

    // 4. Once at boundary, subsequent wheel down cannot pan further and must NOT preventDefault (allowing console scroll)
    const boundaryWheelEvent = new WheelEvent('wheel', {
      ctrlKey: false,
      deltaX: 0,
      deltaY: 50,
      cancelable: true,
      bubbles: true,
    });
    viewportEl.dispatchEvent(boundaryWheelEvent);
    await w.vm.$nextTick();
    expect(boundaryWheelEvent.defaultPrevented).toBe(false);

    // 5. Wheeling in the opposite direction (up) CAN move, so it DOES preventDefault again
    const reverseWheelEvent = new WheelEvent('wheel', {
      ctrlKey: false,
      deltaX: 0,
      deltaY: -50,
      cancelable: true,
      bubbles: true,
    });
    viewportEl.dispatchEvent(reverseWheelEvent);
    await w.vm.$nextTick();
    expect(reverseWheelEvent.defaultPrevented).toBe(true);

    jest.restoreAllMocks();
  });

  it('automatically calculates and applies fit zoom when viewport expands after CSS animation (Scratchpad mode)', async () => {
    jest.useFakeTimers();

    let currentWidth = 0;
    let currentHeight = 0;
    jest.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockImplementation(() => currentWidth);
    jest.spyOn(HTMLElement.prototype, 'clientHeight', 'get').mockImplementation(() => currentHeight);

    // Mock ResizeObserver
    let roCallback = null;
    global.ResizeObserver = class {
      constructor(cb) {
        roCallback = cb;
      }
      observe() {}
      disconnect() {}
    };

    const w = mountComponent({
      data: {
        ...mockImageData,
        dimensions: { width: 1000, height: 1000 },
      },
    });
    await w.vm.$nextTick();

    // Initially collapsed (0px height during animation start), zoom remains 100%
    expect(w.find('.zoom-level').text()).toBe('100%');

    // Scratchpad animation completes (now 800x500 px)
    currentWidth = 800;
    currentHeight = 500;

    // Advance timers so delayed check or ResizeObserver fires
    jest.advanceTimersByTime(300);
    if (roCallback) roCallback();
    await w.vm.$nextTick();

    // Now it should have automatically adjusted to 45% fit zoom without any user clicks!
    expect(w.find('.zoom-level').text()).toBe('45%');
    expect(w.find('button.btn-zoom-fit').classes()).toContain('active');

    delete global.ResizeObserver;
    jest.useRealTimers();
    jest.restoreAllMocks();
  });
});
