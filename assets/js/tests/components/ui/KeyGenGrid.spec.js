import { mount } from '@vue/test-utils';
import KeyGenGrid from '../../../components/ui/KeyGenGrid.vue';

const COLS = 38;
const ROWS = 10;
const TOTAL_CELLS = COLS * ROWS;

const levelCells = (wrapper) => wrapper.findAll('.keygen-cell').filter((c) => /level-[1-4]/.test(c.classes().join(' ')));

describe('KeyGenGrid.vue', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  it('renders the full grid with an accessible status role', () => {
    const wrapper = mount(KeyGenGrid);
    const grid = wrapper.find('.keygen-grid');

    expect(grid.attributes('role')).toBe('status');
    expect(grid.attributes('aria-label')).toBe('Generating cryptographic keys...');
    expect(wrapper.findAll('.keygen-cell')).toHaveLength(TOTAL_CELLS);
    wrapper.unmount();
  });

  it('paints an initial frame on mount and keeps animating at ~30 FPS', () => {
    const setIntervalSpy = jest.spyOn(global, 'setInterval');
    const wrapper = mount(KeyGenGrid);

    expect(setIntervalSpy).toHaveBeenCalledTimes(1);
    expect(setIntervalSpy).toHaveBeenCalledWith(expect.any(Function), 33);
    expect(levelCells(wrapper).length).toBeGreaterThan(0);

    const snapshot = () => wrapper.findAll('.keygen-cell').map((c) => c.element.className).join('|');
    const before = snapshot();
    jest.advanceTimersByTime(33 * 30);
    expect(snapshot()).not.toBe(before);

    // Only the 5 known class states are ever produced.
    wrapper.findAll('.keygen-cell').forEach((cell) => {
      expect(cell.element.className).toMatch(/^keygen-cell( level-[1-4])?$/);
    });
    wrapper.unmount();
  });

  it('clears its interval on unmount and leaves no pending timers', () => {
    const clearIntervalSpy = jest.spyOn(global, 'clearInterval');
    const wrapper = mount(KeyGenGrid);
    expect(jest.getTimerCount()).toBe(1);

    wrapper.unmount();

    expect(clearIntervalSpy).toHaveBeenCalledTimes(1);
    expect(jest.getTimerCount()).toBe(0);
    expect(() => jest.advanceTimersByTime(1000)).not.toThrow();
  });

  it('keeps independent state and timers per instance', () => {
    const a = mount(KeyGenGrid);
    const b = mount(KeyGenGrid);
    expect(jest.getTimerCount()).toBe(2);

    a.unmount();
    expect(jest.getTimerCount()).toBe(1);
    expect(() => jest.advanceTimersByTime(330)).not.toThrow();
    expect(b.findAll('.keygen-cell')).toHaveLength(TOTAL_CELLS);

    b.unmount();
    expect(jest.getTimerCount()).toBe(0);
  });

  it('survives long runs (Game of Life reseeding) without errors', () => {
    const wrapper = mount(KeyGenGrid);
    expect(() => jest.advanceTimersByTime(33 * 600)).not.toThrow();
    expect(levelCells(wrapper).length).toBeGreaterThan(0);
    wrapper.unmount();
  });

  it('remains stable with degenerate random sources', () => {
    jest.spyOn(Math, 'random').mockReturnValue(0);
    const zero = mount(KeyGenGrid);
    expect(() => jest.advanceTimersByTime(33 * 200)).not.toThrow();
    zero.unmount();

    Math.random.mockReturnValue(0.999999);
    const one = mount(KeyGenGrid);
    expect(() => jest.advanceTimersByTime(33 * 200)).not.toThrow();
    one.unmount();
    expect(jest.getTimerCount()).toBe(0);
  });
});
