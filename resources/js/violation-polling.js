const POLL_INTERVAL = 10000;

export function violationPolling(config) {
    return {
        violations: config.initialViolations || [],
        lastSeenId: 0,
        loading: false,
        timer: null,
        permissionStatus: 'default',
        badgeCount: 0,

        init() {
            if (this.violations.length > 0) {
                this.lastSeenId = Math.max(...this.violations.map((v) => v.id));
            }

            if ('Notification' in window) {
                this.permissionStatus = Notification.permission;
            }

            this.timer = setInterval(() => this.poll(), POLL_INTERVAL);
        },

        async poll() {
            this.loading = true;
            try {
                const res = await fetch(`${config.endpoint}?since=${this.lastSeenId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                const fresh = data.violations || [];
                if (fresh.length === 0) return;

                this.violations = [...fresh, ...this.violations].slice(0, 25);
                this.lastSeenId = Math.max(...fresh.map((v) => v.id));
                this.badgeCount += fresh.length;

                this.playBeep();
                this.showBrowserNotification(fresh);
            } finally {
                this.loading = false;
            }
        },

        playBeep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.value = 880;
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.5);
            } catch (e) { /* Web Audio tidak didukung; lewati. */ }
        },

        showBrowserNotification(newViolations) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;

            const count = newViolations.length;
            const title = 'Pelanggaran Terdeteksi';
            let body;
            if (count === 1) {
                const v = newViolations[0];
                body = `${v.student_name} — ${v.violation_label} (${v.room_name})`;
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
                }
            });
        },

        destroy() {
            if (this.timer) clearInterval(this.timer);
        },
    };
}
