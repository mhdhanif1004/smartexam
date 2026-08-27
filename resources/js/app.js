import './bootstrap';

// instant.page: prefetch link saat hover agar navigasi antar halaman terasa
// instan (tanpa reload/pertukaran DOM, murni pre-cache HTML target).
// Link dengan atribut `data-no-instant` (mis. tombol masuk ujian peserta)
// otomatis dikecualikan dari prefetch oleh pustaka ini.
import 'instant.page/instantpage.js';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import { examApp } from './exam';
import { selectionManager } from './selection';
import { cardSettingsPreview } from './admin/card-settings-preview';
import { violationPolling } from './violation-polling';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.data('examApp', examApp);
Alpine.data('selectionManager', selectionManager);
Alpine.data('cardSettingsPreview', cardSettingsPreview);
Alpine.data('violationPolling', violationPolling);

Alpine.start();
