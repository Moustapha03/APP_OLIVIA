import chart from './chart';
import dismissible from './dismissible';
import drawer from './drawer';
import modal from './modal';
import networkStatus from './network-status';
import scanner from './scanner';

export default function registerComponents(Alpine) {
    Alpine.data('dismissible', dismissible);
    Alpine.data('drawer', drawer);
    Alpine.data('chart', chart);
    Alpine.data('modal', modal);
    Alpine.data('networkStatus', networkStatus);
    Alpine.data('scanner', scanner);
}
