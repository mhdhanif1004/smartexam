import './bootstrap';
import './echo';

// instant.page: prefetch link saat hover agar navigasi antar halaman terasa
// instan (tanpa reload/pertukaran DOM, murni pre-cache HTML target).
// Link dengan atribut `data-no-instant` (mis. tombol masuk ujian peserta)
// otomatis dikecualikan dari prefetch oleh pustaka ini.
import 'instant.page/instantpage.js';

import Chart from 'chart.js/auto';

window.Chart = Chart;

import Alpine from 'alpinejs';
import { examApp } from './exam';
import { selectionManager } from './selection';
import { cardSettingsPreview } from './admin/card-settings-preview';
import { violationPolling } from './violation-polling';

window.Alpine = Alpine;

Alpine.store('sidebar', { open: false });

Alpine.data('examApp', examApp);
Alpine.data('selectionManager', selectionManager);
Alpine.data('cardSettingsPreview', cardSettingsPreview);
Alpine.data('violationPolling', violationPolling);

Alpine.start();

// Turbo Drive: navigasi instan (swap <body> tanpa reload) — gate + Alpine re-init.
// Turbo dimuat DINAMIS dan HANYA jika layout menyetel window.SMARTEXAM_TURBO_ENABLED
// (baca dari config('app.turbo_enabled')). Rollback cepat: set TURBO_ENABLED=false di
// .env => flag false => Turbo tidak pernah dimuat => perilaku full-reload normal.
//
// Catatan sidebar permanent (fix flicker lintas navigasi):
// Alpine 3.4.2 -> destroyTree(root, walker=walk) hanya 2 param, TIDAK ada predicate
// filter. Jadi tidak bisa Alpine.destroyTree(body, el=>!el.closest('[data-turbo-permanent]')).
// Fallback aman: destroy scoped HANYA ke <main> (konten halaman). Elemen
// [data-turbo-permanent] (sidebar + overlay) tidak dihancurkan -> tidak slide-in ulang.
// Sidebar sendiri pakai global Alpine.store('sidebar') + $store.sidebar.open.
if (window.SMARTEXAM_TURBO_ENABLED === true) {
    import('@hotwired/turbo').then(() => {
        // Hancurkan tree Alpine lama SEBELUM body diganti Turbo, tapi HANYA
        // di <main> agar [data-turbo-permanent] (sidebar/overlay) tetap hidup.
        document.addEventListener('turbo:before-render', () => {
            document.querySelectorAll('main').forEach((el) => {
                try { Alpine.destroyTree(el); } catch (e) {}
            });
        });

        // Setelah body baru dipasang, re-scan DOM baru. initTree(body) aman:
        // elemen permanent sudah ter-init akan di-skip Alpine (via _x_dataStack).
        document.addEventListener('turbo:render', () => {
            Alpine.initTree(document.body);
        });
    });
}

