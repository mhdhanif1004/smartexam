import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import { examApp } from './exam';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.data('examApp', examApp);

Alpine.start();
