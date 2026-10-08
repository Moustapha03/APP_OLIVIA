import './bootstrap';
import './forms';
import './print';
import './share';
import './pwa';

// CSP build: no eval(), so the strict Content-Security-Policy (step 9)
// does not need 'unsafe-eval'. Keep inline expressions simple; put logic
// in Alpine.data() components (resources/js/alpine).
import Alpine from '@alpinejs/csp';
import focus from '@alpinejs/focus';

import registerComponents from './alpine';

Alpine.plugin(focus);
registerComponents(Alpine);

window.Alpine = Alpine;
Alpine.start();
