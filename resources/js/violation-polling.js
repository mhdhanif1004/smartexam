const POLL_INTERVAL = 10000;

// UNTUK GANTI SUARA NOTIFIKASI: ganti path di bawah ini dengan file
// audio baru yang ditaruh di public/audios/
const NOTIFICATION_SOUND_PATH = '/audios/mixkit-software-interface-back-2575.wav';

// localStorage key untuk melacak "pelanggaran terakhir yang sudah dilihat"
// user ini. Scope per user (role + id) supaya admin A & pengawas B saling
// independen. Nilai disimpan sebagai ID pelanggaran tertinggi yang sudah
// pernah diberitakan — bertahan antar halaman/refresh.
function seenKey(userKey) {
    return `smartexam.last-seen.${userKey}`;
}

function readLastSeenId(userKey) {
    try {
        const val = Number(window.localStorage.getItem(seenKey(userKey)));
        return Number.isFinite(val) && val > 0 ? val : 0;
    } catch (e) {
        return 0;
    }
}

function writeLastSeenId(userKey, id) {
    try {
        window.localStorage.setItem(seenKey(userKey), String(id));
    } catch (e) {
        // localStorage tidak tersedia; lewati.
    }
}

// --- Singleton Web Worker ---
let sharedWorker = null;
let sharedConfig = null;
let sharedListeners = [];
let panelOwnerSet = false;
let soundEmitter = null;

function initWorker(config) {
    if (!sharedWorker) {
        try {
            sharedWorker = new Worker('/js/violation-worker.js');

            sharedWorker.onmessage = (event) => {
                const msg = event.data;

                if (msg.type === 'newViolations' || msg.type === 'unhandledCount') {
                    sharedListeners.forEach((fn) => fn(msg));
                } else if (msg.type === 'csrfRefreshed') {
                    if (sharedConfig) sharedConfig.csrf = msg.csrf;
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.content = msg.csrf;
                }
            };

            sharedWorker.onerror = () => {};
        } catch (e) {
            return;
        }
    }

    sharedConfig = config;

    // Baca lastSeenId PERSISTEN dari localStorage (bukan hardcode 0),
    // lalu beri tahu Worker sampai ID berapa yang sudah pernah dilihat.
    const lastSeenId = readLastSeenId(config.userKey);

    sharedWorker.postMessage({
        type: 'init',
        endpoint: config.endpoint,
        csrf: config.csrf,
        csrfUrl: config.csrfUrl || '/csrf-token',
        lastSeenId,
        pollInterval: POLL_INTERVAL,
    });

    // Sinkronkan CSRF dari meta tag secara berkala
    setInterval(() => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && sharedConfig && meta.content !== sharedConfig.csrf) {
            sharedConfig.csrf = meta.content;
            sharedWorker.postMessage({ type: 'updateCsrf', csrf: meta.content });
        }
    }, 5 * 60 * 1000);
}

export function violationPolling(config) {
    return {
        violations: config.initialViolations || [],
        lastSeenId: readLastSeenId(config.userKey),
        loading: false,
        permissionStatus: 'default',
        badgeCount: 0,
        hasPanel: false,
        seenIds: new Set(),

        init() {
            // pastikan state awalnya punya ID terbaru yang sudah dikenal
            if (this.violations.length > 0) {
                const max = Math.max(...this.violations.map((v) => v.id));
                this.lastSeenId = Math.max(this.lastSeenId, max);
            }

            if ('Notification' in window) {
                this.permissionStatus = Notification.permission;
            }

            this.hasPanel = this.$el.querySelector('[data-violation-list]') !== null;

            if (this.hasPanel || !panelOwnerSet) {
                soundEmitter = this;
                panelOwnerSet = true;
            }

            this._listener = (msg) => {
                if (msg.type === 'newViolations') {
                    this.handleNewViolations(msg.violations, msg.unhandled_count);
                } else if (msg.type === 'unhandledCount') {
                    this.applyBadgeFromServer(msg.unhandled_count);
                }
            };
            sharedListeners.push(this._listener);

            // Init Worker (instance pertama membuat Worker, berikutnya berbagi)
            initWorker(config);

            if (this.hasPanel) {
                this.refreshBadgeFromServer();
            }

            // Auto mark-seen: kalau halaman ini adalah halaman Riwayat
            // Pelanggaran, tandai semua sebagai sudah dilihat & reset badge.
            // (Pelanggaran yang diterima Worker di halaman ini dikonsumsi
            //  senyap — tanpa suara — dan lastSeenId ikut maju.)
            if (config.isHistoryPage) {
                this.markAllSeen();
            }
        },

        async refreshBadgeFromServer() {
            try {
                const res = await fetch(`${config.endpoint}?since=${this.lastSeenId}&count_only=1`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (typeof data.unhandled_count === 'number') {
                    this.applyBadgeFromServer(data.unhandled_count);
                }
            } catch (e) { /* lewati; badge tetap pakai nilai sebelumnya */ }
        },

        applyBadgeFromServer(count) {
            if (typeof count !== 'number') return;
            if (!this.hasPanel) return;
            // Badge selalu bersumber dari count database (unhandled_count),
            // bukan akumulasi client → angka selalu akurat & tidak menggelembung.
            this.badgeCount = count;
        },

        handleNewViolations(fresh, unhandledCount) {
            // Di halaman Riwayat Pelanggaran, semua data yang masuk dikonsumsi
            // senyap (tanpa suara/badge) dan lastSeenId dimajukan, karena user
            // sudah melihat langsung daftar lengkap pelanggaran di sini.
            if (config.isHistoryPage) {
                this.markAllSeen(fresh);
                return;
            }

            // Sinkronkan badge dari jumlah server (sumber kebenaran)
            if (unhandledCount !== null && unhandledCount !== undefined) {
                this.applyBadgeFromServer(unhandledCount);
            }

            if (!fresh || fresh.length === 0) return;

            // Hanya pelanggaran yang BENAR-BENAR baru (id > lastSeenId) yang
            // diproses; ini mencegah duplikasi/barang lama terpicu ulang.
            const genuinelyNew = fresh.filter((v) => v.id > this.lastSeenId && !this.seenIds.has(v.id));
            if (genuinelyNew.length === 0) return;

            genuinelyNew.forEach((v) => this.seenIds.add(v.id));

            const newMax = Math.max(this.lastSeenId, ...genuinelyNew.map((v) => v.id));

            if (this.hasPanel) {
                // tambahkan ke daftar panel
                this.violations = [...genuinelyNew, ...this.violations].slice(0, 25);
            }

            // Persist lastSeenId ke localStorage (bertahan antar halaman)
            if (newMax > this.lastSeenId) {
                this.lastSeenId = newMax;
                writeLastSeenId(config.userKey, newMax);
            }

            // Hanya satu instance (soundEmitter) yang bunyi + notify
            if (this === soundEmitter) {
                this.playNotificationSound();
                this.showBrowserNotification(genuinelyNew);
            }
        },

        playNotificationSound() {
            try {
                const audio = new Audio(NOTIFICATION_SOUND_PATH);
                audio.volume = 0.7;
                audio.play().catch(() => {});
            } catch (e) { /* lewati */ }
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

        async dismissBadge() {
            this.badgeCount = 0;
            await this.markAllSeen();
        },

        markAllSeen(freshItems) {
            // Pelanggaran yang terlihat di halaman ini sudah "dilihat" oleh
            // user. Update lastSeenId di localStorage (soal suara tidak
            // terpicu ulang) dan reset badge klien ke 0.
            // Catatan: tidak mengubah handled_by_supervisor di DB.
            let max = this.violations.length > 0
                ? Math.max(...this.violations.map((v) => v.id))
                : this.lastSeenId;

            if (freshItems && freshItems.length > 0) {
                max = Math.max(max, ...freshItems.map((v) => v.id));
            }

            if (max > this.lastSeenId) {
                this.lastSeenId = max;
                writeLastSeenId(config.userKey, this.lastSeenId);
            }
            this.badgeCount = 0;
        },

        refreshPoll() {
            if (sharedWorker) {
                this.loading = true;
                sharedWorker.postMessage({ type: 'poll' });
                setTimeout(() => { this.loading = false; }, 1000);
            }
        },

        markHandled(id) {
            if (!config.handleUrl) return;
            if (!sharedConfig) return;
            fetch(config.handleUrl.replace('__ID__', id), {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': sharedConfig.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then((res) => {
                if (res.ok) {
                    const item = this.violations.find((v) => v.id === id);
                    if (item) item.handled = true;
                } else if (res.status === 419) {
                    window.location.reload();
                }
            });
        },

        destroy() {
            sharedListeners = sharedListeners.filter((fn) => fn !== this._listener);
            if (soundEmitter === this) soundEmitter = null;
            if (this.hasPanel && panelOwnerSet) panelOwnerSet = false;
        },
    };
}
