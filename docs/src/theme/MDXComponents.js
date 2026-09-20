import MDXComponents from '@theme-original/MDXComponents';
import VegaLite from '@site/src/components/VegaLite';
import ColorSwatch from '@site/src/components/ColorSwatch';
import SchemeSwatch from '@site/src/components/SchemeSwatch';

export default {
  ...MDXComponents,
  VegaLite,
  VegaChart: VegaLite,
  ColorSwatch,
  ColorBox: ColorSwatch,
  SchemeSwatch,
  SchemePreview: SchemeSwatch,
};
