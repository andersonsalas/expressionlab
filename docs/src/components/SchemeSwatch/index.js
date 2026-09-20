import React from 'react';

/**
 * Standard Vega/D3 color schemes definition.
 * Sequential and perceptual schemes contain gradient stops.
 * Discrete schemes contain an array of distinct categorical hex colors.
 */
const SCHEME_DEFINITIONS = {
  // Sequential single-hue schemes
  blues: {
    type: 'continuous',
    stops: [
      'rgb(207, 225, 242)',
      'rgb(168, 206, 229)',
      'rgb(116, 178, 215)',
      'rgb(69, 146, 198)',
      'rgb(32, 111, 178)',
      'rgb(10, 74, 144)',
    ],
  },
  reds: {
    type: 'continuous',
    stops: [
      'rgb(253, 201, 180)',
      'rgb(252, 158, 128)',
      'rgb(250, 112, 81)',
      'rgb(236, 63, 47)',
      'rgb(200, 27, 29)',
      'rgb(151, 11, 19)',
    ],
  },
  greens: {
    type: 'continuous',
    stops: [
      'rgb(211, 238, 205)',
      'rgb(171, 221, 165)',
      'rgb(123, 199, 125)',
      'rgb(70, 171, 94)',
      'rgb(32, 137, 67)',
      'rgb(3, 100, 41)',
    ],
  },
  purples: {
    type: 'continuous',
    stops: [
      'rgb(226, 225, 239)',
      'rgb(196, 197, 224)',
      'rgb(163, 160, 204)',
      'rgb(130, 124, 185)',
      'rgb(104, 78, 162)',
      'rgb(80, 31, 140)',
    ],
  },
  oranges: {
    type: 'continuous',
    stops: [
      'rgb(253, 216, 179)',
      'rgb(253, 184, 123)',
      'rgb(252, 146, 68)',
      'rgb(240, 107, 24)',
      'rgb(209, 73, 4)',
      'rgb(159, 51, 3)',
    ],
  },

  // Perceptual uniform schemes
  viridis: {
    type: 'continuous',
    stops: [
      'rgb(68, 1, 84)',
      'rgb(65, 68, 135)',
      'rgb(42, 120, 142)',
      'rgb(34, 168, 132)',
      'rgb(122, 209, 81)',
      'rgb(253, 231, 37)',
    ],
  },
  inferno: {
    type: 'continuous',
    stops: [
      'rgb(0, 0, 4)',
      'rgb(66, 10, 104)',
      'rgb(147, 38, 103)',
      'rgb(221, 81, 58)',
      'rgb(252, 165, 10)',
      'rgb(252, 255, 164)',
    ],
  },
  magma: {
    type: 'continuous',
    stops: [
      'rgb(0, 0, 4)',
      'rgb(59, 15, 112)',
      'rgb(140, 41, 129)',
      'rgb(222, 73, 104)',
      'rgb(254, 159, 109)',
      'rgb(252, 253, 191)',
    ],
  },

  // Discrete categorical schemes (10 distinct hues)
  category10: {
    type: 'discrete',
    colors: [
      '#1f77b4',
      '#ff7f0e',
      '#2ca02c',
      '#d62728',
      '#9467bd',
      '#8c564b',
      '#e377c2',
      '#7f7f7f',
      '#bcbd22',
      '#17becf',
    ],
  },
  tableau10: {
    type: 'discrete',
    colors: [
      '#4c78a8',
      '#f58518',
      '#e45756',
      '#72b7b2',
      '#54a24b',
      '#eeca3b',
      '#b279a2',
      '#ff9da6',
      '#9d755d',
      '#bab0ac',
    ],
  },
};

/**
 * Inline preview component for Vega-Lite continuous gradients and categorical color schemes.
 *
 * @param {Object} props
 * @param {string} props.scheme - Name of the scheme (e.g. 'blues', 'viridis', 'tableau10').
 * @param {number|string} [props.width=120] - Total width of the preview bar.
 * @param {number|string} [props.height=16] - Height of the preview bar.
 * @param {number|string} [props.radius=3] - Border radius in pixels.
 * @param {string} [props.border='1px solid #4b5563'] - Custom border style.
 * @param {Object} [props.style] - Additional inline styles.
 */
export default function SchemeSwatch({
  scheme,
  width = 120,
  height = 16,
  radius = 3,
  border = '1px solid #4b5563',
  style = {},
  ...props
}) {
  const normalizedKey = (scheme || '').toLowerCase().trim();
  const def = SCHEME_DEFINITIONS[normalizedKey];

  const w = typeof width === 'number' ? `${width}px` : width;
  const h = typeof height === 'number' ? `${height}px` : height;
  const r = typeof radius === 'number' ? `${radius}px` : radius;

  const baseStyle = {
    display: 'inline-flex',
    width: w,
    height: h,
    borderRadius: r,
    border,
    overflow: 'hidden',
    verticalAlign: 'middle',
    boxSizing: 'border-box',
    flexShrink: 0,
    ...style,
  };

  if (!def) {
    return (
      <span
        style={{
          ...baseStyle,
          backgroundColor: '#e5e7eb',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: '10px',
          color: '#6b7280',
        }}
        title={`Unknown scheme: ${scheme}`}
        {...props}
      >
        {scheme}
      </span>
    );
  }

  // Discrete categorical scheme: segmented bar of 10 distinct color blocks
  if (def.type === 'discrete') {
    return (
      <span
        style={baseStyle}
        role="img"
        aria-label={`${scheme} scheme (${def.colors.length} colors)`}
        title={`${scheme} (${def.colors.length} colors)`}
        {...props}
      >
        {def.colors.map((color, index) => (
          <span
            key={index}
            style={{
              flex: 1,
              height: '100%',
              backgroundColor: color,
            }}
            title={`${color} (#${index + 1})`}
          />
        ))}
      </span>
    );
  }

  // Continuous gradient bar for sequential and perceptual schemes
  const gradient = `linear-gradient(to right, ${def.stops.join(', ')})`;

  return (
    <span
      style={{
        ...baseStyle,
        background: gradient,
      }}
      role="img"
      aria-label={`${scheme} gradient`}
      title={`${scheme} gradient`}
      {...props}
    />
  );
}
