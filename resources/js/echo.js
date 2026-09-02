import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Reverb realtime: guarded agar tidak crash bila kredensial belum diisi
// (otomatis fallback ke polling 10s di violation-polling.js).
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbEnabled = !!reverbKey && reverbKey !== '${REVERB_APP_KEY}';

window.SMARTEXAM_REVERB_ENABLED = reverbEnabled;

if (reverbEnabled) {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
            // auth untuk private channel
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            },
        });
    } catch (e) {
        window.SMARTEXAM_REVERB_ENABLED = false;
        console.warn('[SmartExam] Reverb Echo init gagal, fallback polling aktif.', e);
    }
}
