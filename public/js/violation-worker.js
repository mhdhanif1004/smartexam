/**
 * Web Worker untuk polling pelanggaran real-time.
 *
 * Worker ini menjalankan setInterval + fetch di background thread, sehingga
 * polling TIDAK terkena throttling browser saat tab tidak aktif / minimized.
 *
 * Semua operasi DOM (Notification API, Audio, Alpine.js) tetap dilakukan
 * di main thread — Worker hanya mengirim data pelanggaran baru via postMessage().
 */

let timerId = null;
let lastSeenId = 0;
let pollInterval = 10000;
let config = null;

function poll() {
    if (!config) return;

    const url = `${config.endpoint}?since=${lastSeenId}`;

    fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    })
        .then((res) => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then((data) => {
            // Sertakan jumlah pelanggaran belum ditangani (dari server) agar
            // main thread bisa memperbarui badge secara akurat (sumber kebenaran
            // = database, bukan akumulasi client).
            const unhandledCount = typeof data.unhandled_count === 'number'
                ? data.unhandled_count
                : null;

            const fresh = data.violations || [];
            if (fresh.length === 0) {
                // Tetap kirim unhandled_count walau tidak ada pelanggaran baru,
                // supaya badge selalu sinkron dengan database.
                if (unhandledCount !== null) {
                    self.postMessage({ type: 'unhandledCount', unhandled_count: unhandledCount });
                }
                return;
            }

            lastSeenId = Math.max(lastSeenId, ...fresh.map((v) => v.id));

            // Kirim data pelanggaran baru ke main thread
            self.postMessage({ type: 'newViolations', violations: fresh, unhandled_count: unhandledCount });
        })
        .catch(() => {
            // Fetch error — diam saja, polling akan coba lagi di interval berikutnya
        });
}

function refreshCsrf() {
    if (!config || !config.csrfUrl) return;

    fetch(config.csrfUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
    })
        .then((res) => res.json())
        .then((data) => {
            if (data.csrf_token) {
                config.csrf = data.csrf_token;
                self.postMessage({ type: 'csrfRefreshed', csrf: data.csrf_token });
            }
        })
        .catch(() => {});
}

self.onmessage = function (event) {
    const msg = event.data;

    switch (msg.type) {
        case 'init':
            config = {
                endpoint: msg.endpoint,
                csrf: msg.csrf || '',
                csrfUrl: msg.csrfUrl || '',
            };
            lastSeenId = msg.lastSeenId || 0;
            pollInterval = msg.pollInterval || 10000;

            // Poll segera saat pertama kali inisialisasi
            poll();

            // Mulai polling berkala
            timerId = setInterval(poll, pollInterval);

            // Refresh CSRF token setiap 15 menit
            setInterval(refreshCsrf, 15 * 60 * 1000);

            self.postMessage({ type: 'started' });
            break;

        case 'poll':
            // Manual poll dari main thread (tombol Refresh)
            poll();
            break;

        case 'updateCsrf':
            if (config) config.csrf = msg.csrf;
            break;

        case 'stop':
            if (timerId) {
                clearInterval(timerId);
                timerId = null;
            }
            break;
    }
};
