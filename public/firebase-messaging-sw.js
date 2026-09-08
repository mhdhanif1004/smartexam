// Firebase Messaging Service Worker — background push untuk pelanggaran ujian
// Ditaruh di /public agar URL-nya /firebase-messaging-sw.js (scope root, wajib untuk getToken).
// Config diambil runtime via fetch /firebase-config (public, berisi apiKey dkk — aman untuk web SDK).
// Jika /firebase-config belum diisi (VITE_FIREBASE_* kosong), SW tetap terpasang tapi tidak init — polling tetap jalan.

/* eslint-disable no-undef */
importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js');

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

fetch('/firebase-config')
    .then((r) => (r.ok ? r.json() : null))
    .then((cfg) => {
        if (!cfg || !cfg.apiKey || !cfg.projectId) return;
        try {
            firebase.initializeApp(cfg);
            const messaging = firebase.messaging();
            messaging.onBackgroundMessage((payload) => {
                const title = payload?.notification?.title || 'Pelanggaran Ujian Terdeteksi';
                const body = payload?.notification?.body || payload?.data?.violation_type || 'Ada pelanggaran baru.';
                const url = payload?.data?.click_action || '/';
                return self.registration.showNotification(title, {
                    body,
                    icon: '/favicon.ico',
                    badge: '/favicon.ico',
                    data: { url },
                });
            });
        } catch (_) {}
    })
    .catch(() => {});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification?.data?.url || '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const c of clients) {
                if (c.url.includes(url) && 'focus' in c) return c.focus();
            }
            if (self.clients.openWindow) return self.clients.openWindow(url);
        }),
    );
});
