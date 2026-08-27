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

// --- rintisan (skip) ---

resetMockEnv();
