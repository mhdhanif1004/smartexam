// FCM Web Push — registrasi token ke POST /fcm-token
// Hanya aktif bila: (1) config VITE_FIREBASE_* terisi, (2) layout menyetel <meta name="fcm-enabled" content="1"> (admin/pengawas),
// (3) browser support Notification + ServiceWorker. Gagal/denied = diam (fallback tetap polling).
import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, onMessage } from 'firebase/messaging';

const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

function isFcmEnabledPage() {
    return document.querySelector('meta[name="fcm-enabled"]')?.getAttribute('content') === '1';
}

function getFirebaseConfig() {
    const env = import.meta.env;
    const cfg = {
        apiKey: env.VITE_FIREBASE_API_KEY ?? '',
        authDomain: env.VITE_FIREBASE_AUTH_DOMAIN ?? '',
        projectId: env.VITE_FIREBASE_PROJECT_ID ?? '',
        storageBucket: env.VITE_FIREBASE_STORAGE_BUCKET ?? '',
        messagingSenderId: env.VITE_FIREBASE_MESSAGING_SENDER_ID ?? '',
        appId: env.VITE_FIREBASE_APP_ID ?? '',
    };
    // Minimal: apiKey + projectId + messagingSenderId + appId harus ada
    if (!cfg.apiKey || !cfg.projectId || !cfg.messagingSenderId || !cfg.appId) return null;
    return cfg;
}

async function postToken(token) {
    if (!token || !CSRF) return;
    try {
        await fetch('/fcm-token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ token, device_type: 'web' }),
        });
    } catch (_) {
        // best-effort, jangan ganggu UX
    }
}

async function initFcm() {
    if (!isFcmEnabledPage()) return;
    if (!('Notification' in window) || !('serviceWorker' in navigator)) return;

    const firebaseConfig = getFirebaseConfig();
    if (!firebaseConfig) {
        console.debug('[FCM] VITE_FIREBASE_* belum diisi — lewati registrasi token.');
        return;
    }

    const vapidKey = import.meta.env.VITE_FIREBASE_VAPID_KEY ?? '';
    if (!vapidKey) {
        console.debug('[FCM] VITE_FIREBASE_VAPID_KEY kosong — lewati registrasi token.');
        return;
    }

    let app;
    try {
        app = initializeApp(firebaseConfig);
    } catch (e) {
        console.debug('[FCM] initializeApp gagal', e);
        return;
    }

    let messaging;
    try {
        messaging = getMessaging(app);
    } catch (e) {
        console.debug('[FCM] getMessaging gagal', e);
        return;
    }

    // Daftarkan service worker khusus FCM (harus di root /firebase-messaging-sw.js)
    try {
        if (!navigator.serviceWorker.controller) {
            // registrasi idempoten — bila sudah ada, browser pakai yang existing
            await navigator.serviceWorker.register('/firebase-messaging-sw.js', { scope: '/' });
        }
    } catch (e) {
        console.debug('[FCM] register SW gagal', e);
        // lanjut tetap coba getToken tanpa SW eksplisit
    }

    // Minta izin notifikasi — jangan spam bila sudah denied
    if (Notification.permission === 'denied') return;
    if (Notification.permission === 'default') {
        try {
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') return;
        } catch (_) {
            return;
        }
    }

    // Ambil token & kirim ke server
    try {
        const currentToken = await getToken(messaging, { vapidKey, serviceWorkerRegistration: await navigator.serviceWorker.ready.catch(() => undefined) });
        if (currentToken) {
            await postToken(currentToken);
        }
    } catch (e) {
        console.debug('[FCM] getToken gagal', e);
        return;
    }

    // Foreground message — tampilkan notifikasi native bila tab aktif (opsional, polling tetap ada)
    try {
        onMessage(messaging, (payload) => {
            const title = payload?.notification?.title ?? 'Pelanggaran Ujian Terdeteksi';
            const body = payload?.notification?.body ?? payload?.data?.violation_type ?? 'Ada pelanggaran baru.';
            if (Notification.permission === 'granted') {
                try {
                    new Notification(title, { body, icon: '/favicon.ico' });
                } catch (_) {}
            }
            // Biarkan violationPolling Worker juga bunyi — tidak duplikat berat
        });
    } catch (_) {}
}

// Jalankan setelah DOM siap (agar meta tersedia), sekali saja
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initFcm(), { once: true });
} else {
    initFcm();
}
