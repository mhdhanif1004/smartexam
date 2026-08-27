const POLL_INTERVAL = 10000;

// UNTUK GANTI SUARA NOTIFIKASI: ganti path di bawah ini dengan file
// audio baru yang ditaruh di public/audios/
const NOTIFICATION_SOUND_PATH = '/audios/mixkit-software-interface-back-2575.wav';

export function violationPolling(config) {
    return {
        violations: config.initialViolations || [],
        lastSeenId: 0,
        loading: false,
        worker: null,
        permissionStatus: 'default',
        badgeCount: 0,

        init() {
            if (this.violations.length > 0) {
                this.lastSeenId = Math.max(...this.violations.map((v) => v.id));
            }

            if ('Notification' in window) {
                this.permissionStatus = Notification.permission;
            }

            this.startWorker();

            // Sinkronkan CSRF token dari meta tag secara berkala
            // (mengatasi token expired / refresh dari tab lain)
            setInterval(() => {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta && meta.content !== config.csrf) {
                    config.csrf = meta.content;
                    if (this.worker) {
                        this.worker.postMessage({ type: 'updateCsrf', csrf: meta.content });
                    }
                }
            }, 5 * 60 * 1000);
        },

        startWorker() {
            try {
                this.worker = new Worker('/js/violation-worker.js');

                this.worker.onmessage = (event) => {
                    const msg = event.data;

                    switch (msg.type) {
                        case 'started':
                            this.loading = false;
                            break;

                        case 'newViolations':
                            this.loading = true;
                            try {
                                const fresh = msg.violations || [];
                                if (fresh.length === 0) return;

                                this.violations = [...fresh, ...this.violations].slice(0, 25);
                                this.lastSeenId = Math.max(...fresh.map((v) => v.id));
                                this.badgeCount += fresh.length;

                                this.playNotificationSound();
                                this.showBrowserNotification(fresh);
                            } finally {
                                this.loading = false;
                            }
                            break;

                        case 'csrfRefreshed':
                            config.csrf = msg.csrf;
                            break;
                    }
                };

                this.worker.onerror = () => {
                    // Worker error — fallback: polling tidak aktif.
                    // Dalam produksi bisa ditambahkan retry logic di sini.
                };

                // Kirim konfigurasi awal ke Worker
                this.worker.postMessage({
                    type: 'init',
                    endpoint: config.endpoint,
                    csrf: config.csrf,
                    csrfUrl: config.csrfUrl || '/csrf-token',
                    lastSeenId: this.lastSeenId,
                    pollInterval: POLL_INTERVAL,
                });
            } catch (e) {
                // Web Worker tidak didukung — polling tidak aktif
            }
        },

        playNotificationSound() {
            try {
                const audio = new Audio(NOTIFICATION_SOUND_PATH);
                audio.volume = 0.7;
                audio.play().catch(() => {});
            } catch (e) {
                // Audio tidak didukung; lewati.
            }
        },

        showBrowserNotification(newViolations) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;

            const count = newViolations.length;
            const title = 'Pelanggaran Terdeteksi';
            let body;
            if (count === 1) {
                const v = newViolations[0];
                body = `${v.student_name} \u2014 ${v.violation_label} (${v.room_name})`;
            } else {
                const names = newViolations.slice(0, 3).map((v) => v.student_name).join(', ');
                const extra = count > 3 ? ` dan ${count - 3} lainnya` : '';
                body = `${count} pelanggaran baru: ${names}${extra}. Klik untuk detail.`;
            }

            try {
                new Notification(title, { body, icon: '/favicon.ico', tag: 'smartexam-violation' });
            } catch (e) { /* lewati */ }
        },

        async requestPermission() {
            if (!('Notification' in window)) return;
            const result = await Notification.requestPermission();
            this.permissionStatus = result;
        },

        dismissBadge() {
            this.badgeCount = 0;
        },

        refreshPoll() {
            if (this.worker) {
                this.loading = true;
                this.worker.postMessage({ type: 'poll' });
                setTimeout(() => { this.loading = false; }, 1000);
            }
        },

        markHandled(id) {
            if (!config.handleUrl) return;
            fetch(config.handleUrl.replace('__ID__', id), {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then((res) => {
                if (res.ok) {
                    const item = this.violations.find((v) => v.id === id);
                    if (item) item.handled = true;
                } else if (res.status === 419) {
                    // CSRF expired — reload halaman
                    window.location.reload();
                }
            });
        },

        destroy() {
            if (this.worker) {
                this.worker.postMessage({ type: 'stop' });
                this.worker.terminate();
                this.worker = null;
            }
        },
    };
}
