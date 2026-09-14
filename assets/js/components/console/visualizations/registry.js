import { shallowRef } from 'vue';
import TableVisualization from './TableVisualization.vue';
import GraphVisualization from './GraphVisualization.vue';

export const visualizationHandlers = shallowRef({
  'table': TableVisualization,
  'graph': GraphVisualization,
});

export const registerVisualization = (type, component) => {
    visualizationHandlers.value[type] = component;
};
