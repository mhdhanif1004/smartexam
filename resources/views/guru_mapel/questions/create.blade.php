<x-layouts.guru_mapel title="Tambah Soal">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Tambah Soal</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Buat soal untuk mapel yang Anda ampu. Kelas target dibatasi pada kelas yang Anda ampu untuk mapel tersebut.</p>
        </div>

        @include('admin.partials.flash')

        @include('guru_mapel.questions._form', [
            'subjects' => $subjects,
            'types' => $types,
            'letters' => $letters,
            'action' => route('guru_mapel.questions.store'),
            'submitLabel' => 'Simpan Soal',
        ])
    </div>
</x-layouts.guru_mapel>
