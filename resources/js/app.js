import './bootstrap';
import './echo';

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

// Turbo Drive: navigasi instan (swap <body> tanpa reload) — gate + Alpine re-init.
// Turbo dimuat DINAMIS dan HANYA jika layout menyetel window.SMARTEXAM_TURBO_ENABLED
// (baca dari config('app.turbo_enabled')). Rollback cepat: set TURBO_ENABLED=false di
// .env => flag false => Turbo tidak pernah dimuat => perilaku full-reload normal.
if (window.SMARTEXAM_TURBO_ENABLED === true) {
    import('@hotwired/turbo').then(() => {
        // Hancurkan tree Alpine lama SEBELUM body diganti Turbo, sehingga hook
        // `destroy()` pada tiap komponen (mis. pembersih setInterval) ikut berjalan.
        document.addEventListener('turbo:before-render', () => {
            Alpine.destroyTree(document.body);
        });

        // Setelah body baru dipasang, pastikan semua komponen Alpine ter-init ulang.
        // Alpine.start() hanya berjalan sekali, jadi kami re-scan DOM baru secara eksplisit.
        document.addEventListener('turbo:render', () => {
            Alpine.initTree(document.body);
        });
    });
}

