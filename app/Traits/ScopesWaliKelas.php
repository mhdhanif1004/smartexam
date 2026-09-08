<?php

namespace App\Traits;

use App\Models\WaliKelas;

trait ScopesWaliKelas
{
    /**
     * Profil wali kelas dari pengguna yang sedang login. Melakukan
     * aborsi 403 bila akun pengguna bukan wali kelas atau belum punya
     * profil (harus sudah ditugaskan oleh admin).
     */
    protected function currentWaliKelas(): WaliKelas
    {
        $wali = auth()->user()?->waliKelas;

        abort_unless($wali instanceof WaliKelas, 403, 'Anda tidak terdaftar sebagai wali kelas.');

        return $wali;
    }
}
