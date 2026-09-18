import { test } from 'node:test';
import assert from 'node:assert/strict';

import { examApp } from '../../resources/js/exam.js';

// examApp perlu window.innerWidth saat instansiasi (property windowWidth).
globalThis.window = { innerWidth: 1024 };

function vm() {
    return examApp({
        questions: [],
        answers: {},
        doubtful: {},
        deadline: 0,
        remainingSession: 0,
        isFinalMapel: false,
    });
}

// --- optionText: dua bentuk data (string murni lama + objek {text,image} baru) ---

test('optionText: string murni (soal lama) dikembalikan apa adanya', () => {
    assert.equal(vm().optionText('Merah'), 'Merah');
});

test('optionText: objek {text,image} mengambil field text', () => {
    assert.equal(vm().optionText({ text: 'Biru', image: 'question-images/x.webp' }), 'Biru');
});

test('optionText: objek tanpa text mengembalikan string kosong (tidak crash)', () => {
    assert.equal(vm().optionText({ image: 'question-images/y.webp' }), '');
});

test('optionText: null/undefined aman (fallback kosong)', () => {
    assert.equal(vm().optionText(null), '');
    assert.equal(vm().optionText(undefined), '');
});

// --- optionImage: hanya objek yang punya gambar ---

test('optionImage: string murni tidak punya gambar (null)', () => {
    assert.equal(vm().optionImage('Merah'), null);
});

test('optionImage: objek dengan image mengembalikan path', () => {
    assert.equal(vm().optionImage({ text: 'A', image: 'question-images/a.webp' }), 'question-images/a.webp');
});

test('optionImage: objek tanpa image mengembalikan null', () => {
    assert.equal(vm().optionImage({ text: 'A', image: null }), null);
});

// --- verifikasi work.blade bisa iterate options lama sebagai object ---
// x-for="(option, letter) in q.options" → letter = key, option = value.

test('options string murni (object {A,B,C}) menghasilkan teks opsi per key', () => {
    const view = vm();
    const options = { A: 'Merah', B: 'Biru', C: 'Hijau' };

    assert.equal(view.optionText(options['A']), 'Merah');
    assert.equal(view.optionText(options['B']), 'Biru');
    assert.equal(view.optionText(options['C']), 'Hijau');
    assert.deepEqual(Object.keys(options), ['A', 'B', 'C']);
});

test('options campuran string + objek (mixed-shape) tetap menghasilkan teks benar', () => {
    const view = vm();
    const options = {
        A: 'Merah',                                   // string murni (soal lama)
        B: { text: 'Biru', image: 'question-images/b.webp' }, // objek (soal baru)
    };

    assert.equal(view.optionText(options['A']), 'Merah');
    assert.equal(view.optionText(options['B']), 'Biru');
    assert.equal(view.optionImage(options['A']), null);
    assert.equal(view.optionImage(options['B']), 'question-images/b.webp');
});

// --- matching: elemen string dan objek di list left/right ---

test('matching options: left/right campur string & objek diekstrak teksnya dengan index tetap', () => {
    const view = vm();
    const left = [
        { text: 'Satu', image: 'question-images/satu.webp' },
        'Dua',
    ];
    const right = ['1', '2'];

    assert.equal(view.optionText(left[0]), 'Satu');
    assert.equal(view.optionText(left[1]), 'Dua');
    assert.equal(view.optionText(right[0]), '1');
    assert.equal(view.optionText(right[1]), '2');
    assert.equal(view.optionImage(left[0]), 'question-images/satu.webp');
    assert.equal(view.optionImage(left[1]), null);
});

// --- true_false: options null (soal lama) → fallback label di view ---

test('true_false: q.options null tidak membuat optionText crash (fallback label)', () => {
    const view = vm();
    const q = { options: null };

    assert.equal(view.optionText(q.options?.['true'] ?? 'Benar'), 'Benar');
    assert.equal(view.optionText(q.options?.['false'] ?? 'Salah'), 'Salah');
    assert.equal(view.optionImage(q.options?.['true']), null);
});