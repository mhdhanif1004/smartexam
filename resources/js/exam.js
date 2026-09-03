const pad = (n) => String(n).padStart(2, '0');

const TYPE_LABELS = {
    single_choice: 'Pilihan Ganda',
    multiple_choice: 'Pilihan Ganda (banyak)',
    true_false: 'Benar / Salah',
    matching: 'Menjodohkan',
    essay: 'Essay',
};

const FULLSCREEN_CHANGE_EVENTS = ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'];

const FULLSCREEN_ERROR_EVENTS = ['fullscreenerror', 'webkitfullscreenerror'];

export function examApp(config) {
    return {
        questions: config.questions,
        answers: config.answers,
        doubtful: config.doubtful || {},
        current: 0,
        remaining: Math.max(0, config.deadline - Math.floor(Date.now() / 1000)),
        remainingSesi: Math.max(0, config.remainingSession || 0),
        remainingGrace: Math.max(0, config.remainingGrace || 0),
        totalSessionSeconds: config.totalSessionSeconds || 0,
        isFinalMapel: config.isFinalMapel || false,
        saving: false,
        saveQueued: false,
        submitting: false,
        lastSaved: null,
        timer: null,
        saveTimer: null,
        showConfirm: false,
        resetConfirmId: null,
        zoomImage: null,
        toast: '',
        toastVisible: false,
        toastTimer: null,
        lastViolationAt: 0,
        csrfRefreshTimer: null,
        csrfRetried: false,
        statusTimer: null,
        leaving: false,
        windowWidth: window.innerWidth,
        started: false,
        fullscreenLost: false,
        hasEnteredFullscreen: false,
        violationListeners: [],
        mapelWarningShown: false,
        showMapelWarning: false,
        sesiWarningShown: false,
        graceWarningShown: false,
        attendanceRevoked: !!config.attendanceRevoked,
        attendanceWarning: config.attendanceWarning || null,

        dismissMapelWarning() {
            this.showMapelWarning = false;
        },

        get inGracePeriod() {
            // Tahap 2: waktu resmi (periodEnd) habis, tapi masa toleransi (grace) masih berjalan.
            return this.remainingSesi <= 0 && this.remainingGrace > 0;
        },

        init() {
            window.__smartExamApp = this;
            this.trackViolations();
            this.csrfRefreshTimer = setInterval(() => this.refreshCsrf(), 15 * 60 * 1000);
            // Tampilkan peringatan absensi dicabut saat load awal (delay agar tidak bentrok toast lain).
            if (this.attendanceRevoked && this.attendanceWarning) {
                setTimeout(() => this.showToast(this.attendanceWarning), 800);
            }
        },

        /**
         * Mulai ujian: jalankan timer + aktifkan mode layar penuh.
         * Dipanggil dari tombol "Mulai Ujian" di overlay.
         */
        startExam() {
            if (this.started) return;
            this.started = true;
            this.remaining = Math.max(0, config.deadline - Math.floor(Date.now() / 1000));
            this.remainingSesi = Math.max(0, config.remainingSession || 0);
            this.remainingGrace = Math.max(0, config.remainingGrace || 0);
            this.timer = setInterval(() => {
                // Waktu mapel dihitung ulang dari timestamp deadline server (anti-drift):
                // tetap akurat walau browser disembunyikan / CPU sibuk / interval di-throttle.
                this.remaining = Math.max(0, config.deadline - Math.floor(Date.now() / 1000));
                this.remainingSesi = Math.max(0, this.remainingSesi - 1);
                this.remainingGrace = Math.max(0, this.remainingGrace - 1);

                if (this.remaining <= 300 && !this.isFinalMapel && !this.mapelWarningShown) {
                    this.mapelWarningShown = true;
                    this.showMapelWarning = true;
                }
                if (this.remainingSesi === 300 && !this.sesiWarningShown) {
                    this.sesiWarningShown = true;
                    this.showToast('Sisa waktu sesi tinggal 5 menit.');
                }

                if (this.inGracePeriod && !this.graceWarningShown) {
                    this.graceWarningShown = true;
                    this.showToast('Waktu resmi sudah berakhir. Anda dalam masa toleransi.');
                }

                // Auto-submit hanya saat waktu resmi (periodEnd) DAN masa toleransi (grace) sama-sama habis.
                if (this.remainingSesi <= 0 && this.remainingGrace <= 0) {
                    this.submit(true);
                }
            }, 1000);

            if (!this.isWebViewApp() && !this.requestFullscreen()) {
                this.showToast('Browser/perangkat ini tidak mendukung mode layar penuh. Ujian tetap dapat dikerjakan.');
            }
        },

        requestFullscreen() {
            const el = document.documentElement;
            const request = el.requestFullscreen
                || el.webkitRequestFullscreen
                || el.msRequestFullscreen
                || el.mozRequestFullScreen;
            if (!request || this.fullscreenElement()) return true;

            try {
                const promise = request.call(el);
                if (promise && typeof promise.catch === 'function') {
                    promise.catch(() => {
                        if (this.fullscreenLost) {
                            this.showToast('Gagal mengaktifkan mode layar penuh. Silakan coba lagi.');
                        } else {
                            this.showToast('Mode layar penuh ditolak browser. Ujian tetap berjalan.');
                        }
                    });
                }
                return true;
            } catch (e) {
                return false;
            }
        },

        returnToFullscreen() {
            if (this.fullscreenElement()) {
                this.fullscreenLost = false;
                return;
            }
            if (!this.requestFullscreen()) {
                this.showToast('Browser/perangkat ini tidak mendukung mode layar penuh.');
            }
        },

        async refreshCsrf() {
            try {
                const response = await fetch(config.csrfUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (response.ok) {
                    const data = await response.json();
                    this.applyCsrf(data && data.csrf_token ? data.csrf_token : null);
                }
            } catch (e) {
                // Abaikan; token lama tetap dipakai hingga refresh berikutnya.
            }
        },

        applyCsrf(token) {
            if (!token) return;
            config.csrf = token;
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.setAttribute('content', token);
        },

        async reportViolation(type, options = {}) {
            if (this.leaving || !this.started) return;
            const now = Date.now();
            if (now - this.lastViolationAt < 3000) return;
            this.lastViolationAt = now;
            try {
                const response = await this.post(config.violationUrl, { violation_type: type });
                if (!response.ok) return;
                const data = await response.json().catch(() => ({}));
                if (options.block) return;
                if (!data.redirect || !data.url) return;
                this.leaving = true;
                this.showToast('Terdeteksi aktivitas mencurigakan. Anda akan diarahkan kembali ke dashboard.');
                setTimeout(() => window.location.assign(data.url), 1500);
            } catch (e) {}
        },

        trackViolations() {
            const onVisibilityChange = () => {
                if (document.hidden) this.reportViolation('berpindah_tab');
            };
            const onWindowBlur = () => this.reportViolation('kehilangan_fokus');
            const onResize = () => {
                const delta = Math.abs(window.innerWidth - this.windowWidth);
                if (delta >= 120) {
                    this.windowWidth = window.innerWidth;
                    this.reportViolation('resize_jendela');
                }
            };
            const onFullscreenChange = () => {
                if (this.fullscreenElement()) {
                    this.hasEnteredFullscreen = true;
                    this.fullscreenLost = false;
                    return;
                }
                if (this.hasEnteredFullscreen && !this.leaving) {
                    this.showConfirm = false;
                    this.fullscreenLost = true;
                    this.reportViolation('keluar_fullscreen', { block: true });
                }
            };
            const onFullscreenError = () => {
                console.warn('[SmartExam] Mode layar penuh gagal diaktifkan oleh browser; ujian tetap berjalan.');
            };

            const isWebView = this.isWebViewApp();

            this.violationListeners = [
                [document, 'visibilitychange', onVisibilityChange],
                [window, 'blur', onWindowBlur],
                [window, 'resize', onResize],
            ];

            if (!isWebView) {
                FULLSCREEN_CHANGE_EVENTS.forEach((type) => {
                    this.violationListeners.push([document, type, onFullscreenChange]);
                });
                FULLSCREEN_ERROR_EVENTS.forEach((type) => {
                    this.violationListeners.push([document, type, onFullscreenError]);
                });
            }

            this.violationListeners.forEach(([target, type, handler]) => target.addEventListener(type, handler));

            this.statusTimer = setInterval(() => this.checkStatus(), 10000);
        },

        teardownViolationListeners() {
            if (!this.violationListeners.length) return;
            this.violationListeners.forEach(([target, type, handler]) => target.removeEventListener(type, handler));
            this.violationListeners = [];
        },

        fullscreenElement() {
            return document.fullscreenElement
                || document.webkitFullscreenElement
                || document.msFullscreenElement
                || null;
        },

        isWebViewApp() {
            return navigator.userAgent && navigator.userAgent.includes('SmartExamApp');
        },

        async checkStatus() {
            try {
                const response = await fetch(config.statusUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) return;
                const data = await response.json();
                if (!data) return;
                if (data.locked) {
                    this.leaving = true;
                    this.showToast(data.message || 'Ujian Anda dihentikan oleh Administrator.');
                    setTimeout(() => {
                        window.location.assign(config.dashboardUrl);
                    }, 1500);
                    return;
                }
                // Sinkron banner absensi dicabut (reaktif via polling 10 detik).
                if (typeof data.attendance_revoked !== 'undefined') {
                    const was = this.attendanceRevoked;
                    this.attendanceRevoked = !!data.attendance_revoked;
                    this.attendanceWarning = data.attendance_revoked_message || this.attendanceWarning;
                    if (this.attendanceRevoked && !was) this.showToast(this.attendanceWarning);
                }
                if (data.mapel) {
                    const serverRemaining = data.mapel.remaining_seconds;
                    const drift = Math.abs(this.remaining - serverRemaining);
                    if (drift > 3) {
                        this.remaining = serverRemaining;
                    }
                    this.isFinalMapel = data.mapel.is_final;
                }
                if (data.sesi) {
                    // Tahap 1: sisa waktu resmi (periodEnd). Server mengirim nilai mentah (bisa negatif saat grace).
                    const serverPeriod = data.sesi.remaining_seconds;
                    if (Math.abs(this.remainingSesi - serverPeriod) > 3) {
                        this.remainingSesi = Math.max(0, serverPeriod);
                    }
                    // Tahap 2: sisa masa toleransi.
                    if (typeof data.sesi.remaining_grace === 'number') {
                        const serverGrace = data.sesi.remaining_grace;
                        if (Math.abs(this.remainingGrace - serverGrace) > 3) {
                            this.remainingGrace = Math.max(0, serverGrace);
                        }
                    }
                }
            } catch (e) {
                // Gangguan jaringan; polling berikutnya akan mencoba lagi.
            }
        },

        showToast(message) {
            this.toast = message;
            this.toastVisible = true;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => {
                this.toastVisible = false;
            }, 4000);
        },

        total() {
            return this.questions.length;
        },

        typeLabel(type) {
            return TYPE_LABELS[type] ?? type;
        },

        letter(index) {
            return String.fromCharCode(65 + index);
        },

        formatTime(seconds) {
            const s = Math.max(0, seconds);
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = s % 60;
            return (h > 0 ? pad(h) + ':' : '') + pad(m) + ':' + pad(sec);
        },

        answerFor(q) {
            return this.answers[q.id];
        },

        isAnswered(q) {
            const a = this.answers[q.id];
            if (Array.isArray(a)) return a.length > 0;
            if (typeof a === 'boolean') return true;
            if (typeof a === 'string') return a.trim() !== '';
            if (a && typeof a === 'object') return Object.keys(a).length > 0;
            return a !== null && a !== undefined;
        },

        answeredCount() {
            return this.questions.filter((q) => this.isAnswered(q)).length;
        },

        isDoubtful(q) {
            return !!this.doubtful[q.id];
        },

        doubtfulCount() {
            return this.questions.filter((q) => this.isDoubtful(q)).length;
        },

        questionClass(q) {
            if (this.isAnswered(q)) return 'bg-emerald-600 text-white';
            if (this.isDoubtful(q)) return 'bg-amber-400 text-amber-950';
            if (q.id === this.questions[this.current]?.id) return 'bg-indigo-600 text-white';
            return 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600';
        },

        async toggleDoubtful(q) {
            try {
                const response = await this.post(config.doubtUrl.replace(':question', q.id), {});
                if (response.status === 422) {
                    this.submit(true);
                    return;
                }
                if (response.ok) {
                    const data = await response.json().catch(() => ({}));
                    this.doubtful[q.id] = !!data.is_doubtful;
                    // Cek peringatan absensi dicabut.
                    if (data.warning) {
                        this.attendanceRevoked = true;
                        this.attendanceWarning = data.warning;
                        this.showToast(data.warning);
                    }
                }
            } catch (e) {
                this.showToast('Gagal menyimpan status ragu-ragu. Coba lagi.');
            }
        },

        emptyAnswer(q) {
            if (q.type === 'multiple_choice') return [];
            if (q.type === 'matching') return {};
            if (q.type === 'essay') return '';
            return null;
        },

        openReset(q) {
            this.resetConfirmId = q.id;
            this.$dispatch('open-modal', 'reset-answer');
        },

        confirmReset() {
            const id = this.resetConfirmId;
            this.resetConfirmId = null;
            this.$dispatch('close-modal', 'reset-answer');

            const q = this.questions.find((item) => item.id === id);
            if (!q) return;

            this.answers[q.id] = this.emptyAnswer(q);
            if (this.saveTimer) {
                clearTimeout(this.saveTimer);
                this.saveTimer = null;
            }
            this.saveAnswer();
        },

        goTo(index) {
            this.flushSave();
            this.current = index;
        },

        prev() {
            if (this.current > 0) {
                this.flushSave();
                this.current -= 1;
            }
        },

        next() {
            if (this.current < this.questions.length - 1) {
                this.flushSave();
                this.current += 1;
            }
        },

        selectValue(q, value) {
            this.answers[q.id] = q.type === 'true_false' ? value === 'true' : value;
            this.scheduleSave();
        },

        toggleOption(q, value) {
            const list = Array.isArray(this.answers[q.id]) ? [...this.answers[q.id]] : [];
            const index = list.indexOf(value);
            if (index >= 0) {
                list.splice(index, 1);
            } else {
                list.push(value);
            }
            this.answers[q.id] = list;
            this.scheduleSave();
        },

        setMatching(q, key, value) {
            this.answers[q.id] = { ...(this.answers[q.id] || {}), [key]: value };
            this.scheduleSave();
        },

        scheduleSave() {
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.saveAnswer(), 600);
        },

        flushSave() {
            if (this.saveTimer) {
                clearTimeout(this.saveTimer);
                this.saveTimer = null;
                this.saveAnswer();
            }
        },

        async saveAnswer() {
            if (this.submitting) return;
            if (this.saving) {
                this.saveQueued = true;
                return;
            }
            this.saving = true;
            try {
                const response = await this.post(config.saveUrl, { answers: this.answers });
                if (response.status === 422) {
                    this.submit(true);
                    return;
                }
                if (response.ok) {
                    // Cek peringatan absensi dicabut tanpa merusak lastSaved.
                    try {
                        const data = await (typeof response.clone === 'function' ? response.clone().json() : response.json());
                        if (data && data.warning) {
                            this.attendanceRevoked = true;
                            this.attendanceWarning = data.warning;
                            this.showToast(data.warning);
                        }
                    } catch (_) {
                        // Bukan JSON atau body kosong — abaikan, tetap set lastSaved.
                    }
                    this.lastSaved = new Date().toLocaleTimeString('id-ID', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                    });
                }
            } catch (e) {
                // Gangguan jaringan: jawaban tetap disimpan lokal dan akan dikirim ulang.
            } finally {
                this.saving = false;
                if (this.saveQueued) {
                    this.saveQueued = false;
                    this.saveAnswer();
                }
            }
        },

        async post(url, body) {
            let response = await fetch(url, this.requestOptions(body));

            if (response.status === 419) {
                const data = await response.json().catch(() => ({}));
                this.applyCsrf(data && data.csrf_token ? data.csrf_token : null);

                if (this.csrfRetried) {
                    // Sesi benar-benar hilang; arahkan kembali ke login.
                    window.location.assign(config.loginUrl || '/login');
                    return response;
                }

                this.csrfRetried = true;
                if (!config.csrf) await this.refreshCsrf();
                response = await fetch(url, this.requestOptions(body));
            }

            if (response.ok || response.status === 422) {
                this.csrfRetried = false;
            }

            return response;
        },

        requestOptions(body) {
            return {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            };
        },

        async submit(auto = false) {
            if (this.submitting || this.questions.length === 0) return;
            if (!auto && !this.showConfirm) {
                this.showConfirm = true;
                return;
            }
            this.showConfirm = false;
            this.submitting = true;
            this.leaving = true;
            this.teardownViolationListeners();
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
            if (this.csrfRefreshTimer) {
                clearInterval(this.csrfRefreshTimer);
                this.csrfRefreshTimer = null;
            }
            if (this.statusTimer) {
                clearInterval(this.statusTimer);
                this.statusTimer = null;
            }
            try {
                await this.saveAnswer();
                const response = await this.post(config.submitUrl, { answers: this.answers });
                if (response.redirected) {
                    window.location.assign(response.url);
                } else {
                    window.location.assign(config.finishedUrl);
                }
            } catch (e) {
                this.submitting = false;
            }
        },
    };
}
