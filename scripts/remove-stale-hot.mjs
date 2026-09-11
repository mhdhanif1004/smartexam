#!/usr/bin/env node

/**
 * Hapus file `public/hot` yang basi sebelum Vite dev/build dimulai.
 *
 * Kenapa perlu: saat Vite dev server di-kill paksa (Windows: window terminal
 * ditutup tanpa Ctrl+C — `composer dev` via concurrently ikut membunuh semua
 * child), file `public/hot` TIDAK sempat dibersihkan oleh Vite. File orphan
 * itu tetap menunjuk port lama dan membuat Laravel gagal memuat CSS/JS.
 *
 * Dijalankan otomatis dari:
 *   - `predev`  — sebelum `npm run dev` (vite akan menulis hot baru)
 *   - `build`   — sebelum `vite build` (fix cepat 1-perintah, tanpa perlu
 *                 `del public\hot` manual dulu)
 */
import { existsSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';

const hotFile = resolve('public/hot');

if (existsSync(hotFile)) {
    rmSync(hotFile);
    console.log('[remove-stale-hot] public/hot lama dihapus (vite akan buat yang baru).');
} else {
    console.log('[remove-stale-hot] public/hot tidak ada — bersih.');
}