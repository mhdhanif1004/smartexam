import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';

import { createMockLocalStorage, installMockEnv, resetMockEnv } from './mockEnv.mjs';
import { violationPolling } from '../../resources/js/violation-polling.js';

const STORE_KEY = (key) => `smartexam.last-seen.${key}`;

let localStorage;
let config;

beforeEach(() => {
    localStorage = createMockLocalStorage();
    installMockEnv(localStorage);
    config = {
        endpoint: '/admin/violations/polling',
        userKey: 'admin-1',
        initialViolations: [],
        csrf: 'token',
    };
});

test('baca lastSeenId dari localStorage saat objek dibuat (bukan hardcode 0)', () => {
    localStorage.setItem(STORE_KEY('admin-1'), '42');
    const vm = violationPolling(config);
    assert.equal(vm.lastSeenId, 42);
});

test('lastSeenId default 0 jika localStorage kosong / tidak valid', () => {
    const vm = violationPolling(config);
    assert.equal(vm.lastSeenId, 0);
});

test('lastSeenId di-persist ke localStorage scoped per user (admin-1)', () => {
    const vm = violationPolling(config);
    vm.hasPanel = true;
    vm.lastSeenId = 10;
    vm.handleNewViolations([{ id: 11 }, { id: 12 }], 2);
    assert.equal(Number(localStorage.getItem(STORE_KEY('admin-1'))), 12);
    // user lain tidak terpengaruh
    assert.equal(localStorage.getItem(STORE_KEY('pengawas-5')), null);
});

test('lastSeenId scoped per user: admin-1 & pengawas-5 independen', () => {
    localStorage.setItem(STORE_KEY('admin-1'), '100');
    const admin = violationPolling({ ...config, userKey: 'admin-1' });
    const pengawas = violationPolling({ ...config, userKey: 'pengawas-5' });
    assert.equal(admin.lastSeenId, 100);
    assert.equal(pengawas.lastSeenId, 0);
});

test('badge diambil dari unhandled_count server, bukan akumulasi client', () => {
    const vm = violationPolling(config);
    vm.hasPanel = true;
    vm.applyBadgeFromServer(4);
    assert.equal(vm.badgeCount, 4);
});

test('badge tidak bertambah dua kali untuk data yang sama (tidak menggelembung)', () => {
    const vm = violationPolling(config);
    vm.hasPanel = true;
    // Pesan pertama: server melaporkan 4 pelanggaran belum ditangani.
    vm.handleNewViolations([{ id: 1 }], 4);
    assert.equal(vm.badgeCount, 4);
    // Data lama (id <= lastSeenId) tidak boleh menambah badge.
    vm.handleNewViolations([{ id: 1 }], 4);
    assert.equal(vm.badgeCount, 4);
});

test('pelanggaran lama (id <= lastSeenId) tidak memicu proses ulang/suara', () => {
    localStorage.setItem(STORE_KEY('admin-1'), '50');
    const vm = violationPolling(config);
    vm.hasPanel = true;
    let sounds = 0;
    vm.playNotificationSound = () => { sounds += 1; };
    // since=50 → server mengembalikan unhandled_count 0 untuk id 40.
    vm.handleNewViolations([{ id: 40 }], 0);
    assert.equal(sounds, 0);
    assert.equal(vm.badgeCount, 0);
    assert.equal(Number(localStorage.getItem(STORE_KEY('admin-1'))), 50);
});

test('pelanggaran baru (id > lastSeenId) dengan badge dari server', () => {
    const vm = violationPolling(config);
    vm.hasPanel = true;
    let sounds = 0;
    vm.playNotificationSound = () => { sounds += 1; };
    vm.handleNewViolations([{ id: 5 }], 1);
    assert.equal(vm.badgeCount, 1);
    assert.equal(Number(localStorage.getItem(STORE_KEY('admin-1'))), 5);
    // Sound hanya dipicu untuk instance soundEmitter; di sini null → tidak bunyi
    assert.equal(sounds, 0);
});

test('markAllSeen memajukan lastSeenId dan me-reset badge (tidak menyentuh DB)', () => {
    const vm = violationPolling(config);
    vm.violations = [{ id: 7 }, { id: 9 }];
    vm.badgeCount = 3;
    vm.markAllSeen();
    assert.equal(vm.badgeCount, 0);
    assert.equal(Number(localStorage.getItem(STORE_KEY('admin-1'))), 9);
});

test('halaman Riwayat Pelanggaran: konsumsi data senyap + reset badge', () => {
    const historyConfig = { ...config, isHistoryPage: true };
    const vm = violationPolling(historyConfig);
    vm.hasPanel = false;
    let sounds = 0;
    vm.playNotificationSound = () => { sounds += 1; };
    vm.badgeCount = 3;
    vm.handleNewViolations([{ id: 20 }, { id: 21 }], 2);
    assert.equal(sounds, 0);
    assert.equal(Number(localStorage.getItem(STORE_KEY('admin-1'))), 21);
    assert.equal(vm.badgeCount, 0);
});

test('initialViolations dirender sebagai baseline panel saat dibuka', () => {
    const baseline = [
        { id: 70, student_name: 'A', new: false },
        { id: 71, student_name: 'B', new: false },
    ];
    const vm = violationPolling({ ...config, initialViolations: baseline });
    assert.equal(vm.violations.length, 2);
    assert.equal(vm.violations[0].id, 70);
    assert.equal(vm.violations[1].id, 71);
});

test('pelanggaran lama (id <= lastSeenId) tetap dirender panel tapi tidak berbunyi', () => {
    localStorage.setItem(STORE_KEY('admin-1'), '50');
    const vm = violationPolling(config);
    vm.hasPanel = true;
    let sounds = 0;
    vm.playNotificationSound = () => { sounds += 1; };
    vm.handleNewViolations([{ id: 40, new: false }], 0);
    // TERENDER ke daftar panel...
    assert.ok(vm.violations.some((v) => v.id === 40), 'item lama harus muncul di panel');
    // ...tapi tidak memicu suara
    assert.equal(sounds, 0);
    assert.equal(vm.badgeCount, 0);
    assert.equal(Number(localStorage.getItem(STORE_KEY('admin-1'))), 50);
});

test('pelanggaran baru (new=true) dirender ke panel dan dianggap baru (maju lastSeenId)', () => {
    const vm = violationPolling(config);
    vm.hasPanel = true;
    let sounds = 0;
    vm.playNotificationSound = () => { sounds += 1; };
    // soundEmitter belum diset → suara tidak dibunyikan, tapi item lolos gate "baru".
    vm.handleNewViolations([{ id: 5, new: true }, { id: 3, new: false }], 2);
    // keduanya dirender, item baru di atas
    assert.ok(vm.violations.some((v) => v.id === 5), 'item baru harus dirender');
    assert.ok(vm.violations.some((v) => v.id === 3), 'item lama juga harus dirender');
    assert.equal(vm.violations[0].id, 5, 'item baru harus di atas daftar');
    assert.equal(Number(localStorage.getItem(STORE_KEY('admin-1'))), 5);
    assert.equal(sounds, 0);
});

test('item baru ditambahkan DI ATAS daftar baseline yang sudah ada (tidak menggantikan)', () => {
    const baseline = [{ id: 70, student_name: 'lama', new: false }];
    const vm = violationPolling({ ...config, initialViolations: baseline });
    vm.handleNewViolations([{ id: 71, student_name: 'baru', new: true }], 1);
    assert.equal(vm.violations.length, 2);
    assert.equal(vm.violations[0].id, 71);
    assert.equal(vm.violations[1].id, 70);
});

test('badge konsisten: unhandled_count dari server, daftar tidak kosong', () => {
    const vm = violationPolling(config);
    vm.hasPanel = true;
    vm.handleNewViolations([{ id: 5, new: true }], 1);
    assert.equal(vm.badgeCount, 1);
    assert.ok(vm.violations.some((v) => v.id === 5));
});

// --- rintisan (skip) ---

resetMockEnv();