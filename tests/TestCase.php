<?php

namespace Tests;

use App\Models\Classroom;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use ReflectionProperty;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Classroom::getGradeCounts() memakai cache static per-request. Tanpa
        // reset, cache dari test sebelumnya bocor ke test berikutnya dalam satu
        // proses PHP (RefreshDatabase tidak menyentuh static property), sehingga
        // deteksi "tingkat penuh" Edge (summarizeTargetParts) bisa keliru.
        $prop = new ReflectionProperty(Classroom::class, 'gradeCountsCache');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    /**
     * Verifikasi browser-grade bahwa atribut Alpine (mis. x-data) TIDAK bocor.
     *
     * String assertion biasa tidak dapat menangkap bug kelas ini: kutip ganda
     * literal di dalam nilai atribut double-quoted membuat parser HTML menutup
     * atribut lebih awal, lalu sisa body fungsi JS ter-render sebagai teks —
     * padahal string HTML mentahnya tetap mengandung satu kutip utuh. Jadi
     * periksa HASIL PARSE (DOM), bukan string mentah.
     *
     * @param  string  $html  HTML hasil render server.
     * @param  string[]  $leakMarkers  Fragmen JS yang hanya boleh ada di dalam
     *                                 atribut — kemunculan sebagai text node =
     *                                 atribut pecah.
     */
    protected function assertNoAlpineAttributeLeak(string $html, array $leakMarkers = []): void
    {
        $leakMarkers = $leakMarkers ?: [
            'this.target',
            'this.selected',
            'splice(0',
            'takenMapFor',
            'toggleLevel',
            'clear()',
            'push(id)',
        ];

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        // 1) Objek x-data harus utuh. Bila atribut terpotong oleh kutip literal,
        //    nilai hasil parse TIDAK pernah berakhir '}' — pasti terdeteksi di
        //    sini tanpa bergantung pada marker mana yang kebetulan tersisa.
        $xpath = new DOMXPath($dom);
        $objectData = 0;
        foreach ($xpath->query('//*[@x-data]') as $el) {
            $val = trim($el->getAttribute('x-data'));
            if (! str_starts_with($val, '{')) {
                continue; // bentuk ekspresi Alpine (bukan object literal) — di luar cakupan.
            }
            $objectData++;
            $this->assertStringEndsWith('}', $val, 'Atribut x-data terpotong oleh kutip literal (nilai berakhir: '.mb_substr($val, -120).').');
        }
        $this->assertGreaterThan(0, $objectData, 'Tidak ada atribut x-data object-literal untuk diperiksa — render mencurigakan.');

        // 2) Tidak boleh ada text node berisi fragmen JS (gejala atribut pecah
        //    yang parah: body fungsi bocor sebagai teks yang terlihat).
        $leaked = [];
        foreach ($xpath->query('//text()') as $node) {
            $v = trim($node->nodeValue);
            if ($v === '') {
                continue;
            }
            foreach ($leakMarkers as $marker) {
                if (str_contains($v, $marker)) {
                    $leaked[] = mb_substr($v, 0, 160);
                    break;
                }
            }
        }

        $this->assertSame([], $leaked, 'Kode JS bocor sebagai teks pada halaman (atribut Alpine terpotong oleh kutip literal): '.implode(' || ', $leaked));
    }
}
