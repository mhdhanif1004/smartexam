<?php

namespace Tests;

use App\Models\Classroom;
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
}
