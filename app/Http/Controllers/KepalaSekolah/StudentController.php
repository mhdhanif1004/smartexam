<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    /**
     * Daftar siswa read-only untuk Kepala Sekolah.
     * Mendukung pencarian (NISN, Kelas, Nama, Username) dan filter kelas.
     */
    public function index(Request $request): View
    {
        $students = Student::query()
            ->with(['user', 'room'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->where(function ($builder) use ($search) {
                    $builder->where('nisn', 'like', "%{$search}%")
                        ->orWhere('class_name', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($user) use ($search) {
                            $user->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('class'), fn ($query) => $query->where('class_name', $request->string('class')))
            ->orderBy('class_name')
            ->orderBy('nisn')
            ->paginate(10)
            ->withQueryString();

        $classes = Student::query()->distinct()->orderBy('class_name')->pluck('class_name');

        return view('kepala_sekolah.students.index', compact('students', 'classes'));
    }
}
