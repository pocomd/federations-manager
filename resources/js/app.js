import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;
import Collapse from '@alpinejs/collapse';

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(Collapse);
});