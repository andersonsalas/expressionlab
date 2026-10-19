import { shallowRef } from 'vue';
import TableVisualization from './TableVisualization.vue';
import GraphVisualization from './GraphVisualization.vue';
import FileVisualization from './FileVisualization.vue';
import ImageVisualization from './ImageVisualization.vue';

export const visualizationHandlers = shallowRef({
  'table': TableVisualization,
  'graph': GraphVisualization,
  'file': FileVisualization,
  'image': ImageVisualization,
});

export const registerVisualization = (type, component) => {
    visualizationHandlers.value[type] = component;
};
