<x-layouts.guru_mapel title="Edit Soal">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Edit Soal</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Perbarui soal. Anda hanya dapat mengedit soal yang Anda buat sendiri.</p>
        </div>

        @include('admin.partials.flash')

        @include('guru_mapel.questions._form', [
            'subjects' => $subjects,
            'types' => $types,
            'letters' => $letters,
            'question' => $question,
            'action' => route('guru_mapel.questions.update', $question),
            'method' => 'PUT',
            'submitLabel' => 'Perbarui Soal',
        ])
    </div>
</x-layouts.guru_mapel>
