const POLL_INTERVAL = 10000;

// UNTUK GANTI SUARA NOTIFIKASI: ganti path di bawah ini dengan file
// audio baru yang ditaruh di public/audios/
const NOTIFICATION_SOUND_PATH = '/audios/mixkit-software-interface-back-2575.wav';

// Preload audio sekali agar tidak decode tiap pelanggaran (fallback tetap buat baru bila gagal)
let cachedAudio = null;
try {
    cachedAudio = new Audio(NOTIFICATION_SOUND_PATH);
    cachedAudio.preload = 'auto';
    cachedAudio.volume = 0.7;
} catch (e) { /* lewati */ }

// localStorage key untuk melacak "pelanggaran terakhir yang sudah dilihat"
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

// --- Singleton Web Worker (fallback polling) ---
let sharedWorker = null;
let sharedConfig = null;
let sharedListeners = [];
let panelOwnerSet = false;
let soundEmitter = null;
let sharedCsrfInterval = null;
let workerPollingActive = false;

// --- Singleton Reverb state ---
let sharedReverbChannel = null;
let sharedReverbChannelName = null;
let sharedReverbConnected = false;
let sharedReverbRetryTimer = null;

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

    const lastSeenId = readLastSeenId(config.userKey);

    sharedWorker.postMessage({
        type: 'init',
        endpoint: config.endpoint,
        csrf: config.csrf,
        csrfUrl: config.csrfUrl || '/csrf-token',
        lastSeenId,
        pollInterval: POLL_INTERVAL,
    });
    workerPollingActive = true;

    if (sharedCsrfInterval === null) {
        sharedCsrfInterval = setInterval(() => {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta && sharedConfig && meta.content !== sharedConfig.csrf) {
                sharedConfig.csrf = meta.content;
                if (sharedWorker) sharedWorker.postMessage({ type: 'updateCsrf', csrf: meta.content });
            }
        }, 5 * 60 * 1000);
    }
}

function pauseWorkerPolling() {
    if (sharedWorker && workerPollingActive) {
        try { sharedWorker.postMessage({ type: 'stop' }); } catch (e) {}
        workerPollingActive = false;
    }
}

function resumeWorkerPolling(config) {
    if (!sharedWorker) {
        initWorker(config);
        return;
    }
    if (workerPollingActive) return;
    const lastSeenId = readLastSeenId(config.userKey);
    try {
        sharedWorker.postMessage({
            type: 'init',
            endpoint: config.endpoint,
            csrf: config.csrf,
            csrfUrl: config.csrfUrl || '/csrf-token',
            lastSeenId,
            pollInterval: POLL_INTERVAL,
        });
        workerPollingActive = true;
    } catch (e) {}
}

function tryInitReverb(config) {
    // Guard: Reverb harus enabled & Echo tersedia
    if (!window.SMARTEXAM_REVERB_ENABLED || !window.Echo) return false;
    // Sudah subscribe channel yang sama — tidak perlu lagi
    const isAdmin = !!config.isAdmin;
    const roomId = config.roomId ?? null;
    let channelName = null;
    if (isAdmin) {
        channelName = 'violations.admin';
    } else if (roomId) {
        channelName = `violations.room.${roomId}`;
    } else {
        return false; // tidak tahu channel mana, tetap polling
    }

    if (sharedReverbChannel && sharedReverbChannelName === channelName) {
        return true;
    }

    try {
        const channel = window.Echo.private(channelName);
        sharedReverbChannel = channel;
        sharedReverbChannelName = channelName;

        channel.listen('.ViolationCreated', (e) => {
            const payload = e.violation ?? e;
            if (!payload || !payload.id) return;
            const fresh = [payload];
            // Broadcast ke semua listener (sama seperti Worker)
            // unhandled_count tidak ada di event -> biarkan listener refresh badge jika perlu
            sharedListeners.forEach((fn) => fn({ type: 'newViolations', violations: fresh, unhandled_count: null }));
        });

        // Deteksi koneksi: saat terhubung → pause polling, saat putus → resume
        const pusher = window.Echo.connector?.pusher;
        if (pusher && pusher.connection) {
            pusher.connection.bind('connected', () => {
                sharedReverbConnected = true;
                pauseWorkerPolling();
                if (sharedReverbRetryTimer) { clearTimeout(sharedReverbRetryTimer); sharedReverbRetryTimer = null; }
            });
            pusher.connection.bind('disconnected', () => {
                sharedReverbConnected = false;
                // jangan spam resume, tunggu 1s
                if (!sharedReverbRetryTimer) {
                    sharedReverbRetryTimer = setTimeout(() => {
                        resumeWorkerPolling(sharedConfig || config);
                        sharedReverbRetryTimer = null;
                    }, 1000);
                }
            });
            pusher.connection.bind('failed', () => {
                sharedReverbConnected = false;
                resumeWorkerPolling(sharedConfig || config);
            });
            pusher.connection.bind('unavailable', () => {
                sharedReverbConnected = false;
                resumeWorkerPolling(sharedConfig || config);
            });

            // Jika sudah connected saat init, langsung pause polling
            if (pusher.connection.state === 'connected') {
                sharedReverbConnected = true;
                pauseWorkerPolling();
            }
        } else {
            // Fallback: anggap Reverb siap, pause polling
            pauseWorkerPolling();
        }

        // Error subscription → fallback polling
        channel.error(() => {
            sharedReverbConnected = false;
            resumeWorkerPolling(sharedConfig || config);
        });

        return true;
    } catch (e) {
        return false;
    }
}

function teardownReverbIfLastListener() {
    if (sharedListeners.length === 0 && sharedReverbChannel) {
        try {
            const name = sharedReverbChannelName;
            if (name) window.Echo.leave(name.replace('private-', ''));
        } catch (e) {}
        sharedReverbChannel = null;
        sharedReverbChannelName = null;
        sharedReverbConnected = false;
    }
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

            // Fallback polling via Worker (selalu aktif dulu)
            initWorker(config);

            // Coba Reverb realtime — jika berhasil, polling auto-pause saat connected
            // Jika Reverb down / kredensial kosong / channel tidak diketahui, tetap polling
            tryInitReverb(config);

            if (this.hasPanel) {
                this.refreshBadgeFromServer();
            }

            // Auto mark-seen: kalau halaman ini adalah halaman Riwayat
            // Pelanggaran, tandai semua sebagai sudah dilihat & reset badge.
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

            // SEMUA item yang dikirim server dirender ke daftar panel
            // (dedupe by id, terbaru di atas, max 25). `since` di sisi server
            // hanya menandai `new`, BUKAN menyaring apa yang boleh tampil.
            this.mergeIntoPanel(fresh);

            // HANYA yang benar-benar baru (new === true && id > lastSeenId &&
            // belum pernah dilihat) yang memicu suara + notifikasi browser.
            // Bila server menyediakan flag `new` (true/false), itu yang jadi
            // penentu suara; fallback `id > lastSeenId` hanya untuk payload
            // lama tanpa flag `new`.
            const genuinelyNew = fresh.filter((v) => {
                const sounds = typeof v.new === 'boolean' ? v.new === true : v.id > this.lastSeenId;
                return sounds && !this.seenIds.has(v.id);
            });

            // Majukan lastSeenId ke ID tertinggi di antara SEMUA item yang
            // dirender (mereka sudah "dilihat" lewat daftar panel).
            const newMax = Math.max(this.lastSeenId, ...fresh.map((v) => v.id));
            if (newMax > this.lastSeenId) {
                this.lastSeenId = newMax;
                // Persist lastSeenId ke localStorage (bertahan antar halaman)
                writeLastSeenId(config.userKey, newMax);
            }

            if (genuinelyNew.length === 0) return;

            genuinelyNew.forEach((v) => this.seenIds.add(v.id));

            // Hanya satu instance (soundEmitter) yang bunyi + notify
            if (this === soundEmitter) {
                this.playNotificationSound();
                this.showBrowserNotification(genuinelyNew);
            }
        },

        mergeIntoPanel(items) {
            const byId = new Map();
            [...items, ...this.violations].forEach((v) => {
                if (!byId.has(v.id)) byId.set(v.id, v);
            });
            this.violations = Array.from(byId.values())
                .sort((a, b) => b.id - a.id)
                .slice(0, 25);
        },

        playNotificationSound() {
            try {
                if (cachedAudio) {
                    cachedAudio.currentTime = 0;
                    cachedAudio.play().catch(() => {
                        const a = new Audio(NOTIFICATION_SOUND_PATH);
                        a.volume = 0.7;
                        a.play().catch(() => {});
                    });
                    return;
                }
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
            // Jika tidak ada listener tersisa: bersihkan Reverb & worker
            teardownReverbIfLastListener();
            if (sharedListeners.length === 0) {
                if (sharedWorker && workerPollingActive) {
                    try { sharedWorker.postMessage({ type: 'stop' }); } catch (e) {}
                    workerPollingActive = false;
                }
                if (sharedCsrfInterval) { clearInterval(sharedCsrfInterval); sharedCsrfInterval = null; }
                if (sharedReverbRetryTimer) { clearTimeout(sharedReverbRetryTimer); sharedReverbRetryTimer = null; }
            }
        },
    };
}
