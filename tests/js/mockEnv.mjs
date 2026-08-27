// Mock lingkungan browser minimum untuk menguji violation-polling.js di Node.
// Menyediakan window.localStorage, global.Notification & Worker palsu.

export function createMockLocalStorage() {
    const store = new Map();
    return {
        getItem(key) {
            return store.has(key) ? store.get(key) : null;
        },
        setItem(key, value) {
            store.set(key, String(value));
        },
        removeItem(key) {
            store.delete(key);
        },
        clear() {
            store.clear();
        },
        _store: store,
    };
}

export function installMockEnv(localStorage) {
    globalThis.window = {
        localStorage,
    };
    globalThis.Notification = {
        permission: 'default',
        async requestPermission() {
            return 'granted';
        },
    };
    // Worker & Audio dibikin no-op agar import/init tidak gagal.
    class FakeWorker {
        constructor() {
            this._onmessage = null;
        }
        postMessage() {}
        set onmessage(fn) { this._onmessage = fn; }
        get onmessage() { return this._onmessage; }
        set onerror(fn) {}
        get onerror() { return null; }
        terminate() {}
    }
    globalThis.Worker = FakeWorker;
    globalThis.Audio = class {
        constructor() {}
        play() { return Promise.resolve(); }
        set volume(v) {}
    };
    // jsdom tidak ada; hanya butuh window.localStorage yang sudah terpasang.
    globalThis.document = {
        querySelector() { return null; },
    };
}

export function resetMockEnv() {
    delete globalThis.window;
    delete globalThis.Notification;
    delete globalThis.Worker;
    delete globalThis.Audio;
    delete globalThis.document;
}
