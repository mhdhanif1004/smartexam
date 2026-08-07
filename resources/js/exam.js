const pad = (n) => String(n).padStart(2, '0');

const TYPE_LABELS = {
    single_choice: 'Pilihan Ganda',
    multiple_choice: 'Pilihan Ganda (banyak)',
    true_false: 'Benar / Salah',
    matching: 'Menjodohkan',
    essay: 'Essay',
};

export function examApp(config) {
    return {
        questions: config.questions,
        answers: config.answers,
        current: 0,
        remaining: Math.max(0, config.deadline - Math.floor(Date.now() / 1000)),
        saving: false,
        submitting: false,
        lastSaved: null,
        timer: null,
        saveTimer: null,
        showConfirm: false,
        toast: '',
        toastVisible: false,
        toastTimer: null,
        lastViolationAt: 0,
        csrfRefreshTimer: null,
        csrfRetried: false,
        statusTimer: null,
        leaving: false,
        windowWidth: window.innerWidth,

        init() {
            this.timer = setInterval(() => {
                this.remaining -= 1;
                if (this.remaining <= 0) {
                    this.submit(true);
                }
            }, 1000);
            this.trackViolations();
            this.csrfRefreshTimer = setInterval(() => this.refreshCsrf(), 15 * 60 * 1000);
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

        trackViolations() {
            const record = (type) => {
                if (this.leaving) return;
                const now = Date.now();
                if (now - this.lastViolationAt < 3000) return;
                this.lastViolationAt = now;
                this.post(config.violationUrl, { violation_type: type })
                    .then((response) => {
                        if (!response.ok) return null;
                        return response.json().catch(() => ({}));
                    })
                    .then((data) => {
                        if (!data || !data.redirect || !data.url) return;
                        this.leaving = true;
                        this.showToast('Terdeteksi aktivitas mencurigakan. Anda akan diarahkan kembali ke dashboard.');
                        setTimeout(() => {
                            window.location.assign(data.url);
                        }, 1500);
                    })
                    .catch(() => {});
            };
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) record('berpindah_tab');
            });
            window.addEventListener('blur', () => record('kehilangan_fokus'));
            window.addEventListener('resize', () => {
                const delta = Math.abs(window.innerWidth - this.windowWidth);
                if (delta >= 120) {
                    this.windowWidth = window.innerWidth;
                    record('resize_jendela');
                }
            });
            document.addEventListener('fullscreenchange', () => {
                if (!document.fullscreenElement) record('keluar_fullscreen');
            });
            this.forceFullscreen();
            this.statusTimer = setInterval(() => this.checkStatus(), 20000);
        },

        forceFullscreen() {
            const request = () => {
                try {
                    const el = document.documentElement;
                    if (el.requestFullscreen && !document.fullscreenElement) {
                        el.requestFullscreen().catch(() => {});
                    }
                } catch (e) {
                    // Browser menolak tanpa gestur pengguna; diabaikan.
                }
            };
            request();
            document.addEventListener('click', function firstClick() {
                request();
                document.removeEventListener('click', firstClick);
            });
        },

        async checkStatus() {
            try {
                const response = await fetch(config.statusUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) return;
                const data = await response.json();
                if (!data || !data.locked) return;
                this.leaving = true;
                this.showToast(data.message || 'Ujian Anda dihentikan oleh Administrator.');
                setTimeout(() => {
                    window.location.assign(config.dashboardUrl);
                }, 1500);
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

        questionClass(q) {
            if (this.isAnswered(q)) return 'bg-emerald-600 text-white';
            if (q.id === this.questions[this.current]?.id) return 'bg-indigo-600 text-white';
            return 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600';
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
            this.saving = true;
            try {
                const response = await this.post(config.saveUrl, { answers: this.answers });
                if (response.status === 422) {
                    this.submit(true);
                    return;
                }
                if (response.ok) {
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
