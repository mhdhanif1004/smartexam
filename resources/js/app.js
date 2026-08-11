import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import { examApp } from './exam';
import { selectionManager } from './selection';
import { cardSettingsPreview } from './admin/card-settings-preview';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.data('examApp', examApp);
Alpine.data('selectionManager', selectionManager);
Alpine.data('cardSettingsPreview', cardSettingsPreview);

Alpine.start();
