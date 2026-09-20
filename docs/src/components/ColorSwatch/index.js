import React from 'react';

/**
 * Inline color swatch preview component for documentation and markdown tables.
 *
 * @param {Object} props
 * @param {string} props.color - CSS color string (hex, rgb, hsl, name).
 * @param {number|string} [props.size=16] - Width and height in pixels (or CSS unit).
 * @param {number|string} [props.width] - Optional width override.
 * @param {number|string} [props.height] - Optional height override.
 * @param {number|string} [props.radius=3] - Border radius in pixels.
 * @param {string} [props.border='1px solid #4b5563'] - Custom border style.
 * @param {Object} [props.style] - Additional inline styles.
 */
export default function ColorSwatch({
  color = '#000000',
  size = 16,
  width,
  height,
  radius = 3,
  border = '1px solid #4b5563',
  style = {},
  title,
  ...props
}) {
  const w = width ?? size;
  const h = height ?? size;

  return (
    <span
      role="img"
      aria-label={title || `Color: ${color}`}
      title={title || color}
      style={{
        display: 'inline-block',
        width: typeof w === 'number' ? `${w}px` : w,
        height: typeof h === 'number' ? `${h}px` : h,
        backgroundColor: color,
        borderRadius: typeof radius === 'number' ? `${radius}px` : radius,
        border,
        verticalAlign: 'middle',
        boxSizing: 'border-box',
        flexShrink: 0,
        ...style,
      }}
      {...props}
    />
  );
}
