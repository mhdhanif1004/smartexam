{{--
    Tabel soal untuk satu mata pelajaran.
    Dipakai pada dua kondisi:
      - preload server (halaman Bank Soal dengan filter aktif), dan
      - lazy-load via AJAX (endpoint admin.questions.by-subject) saat accordion dibuka.
    Variabel: $questions (Collection), $subject (Subject), $search (string, keyword pencarian untuk highlight).
--}}
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th scope="col" class="px-4 py-3">
                    <input
                        type="checkbox"
                        :checked="(listQuestionIds[{{ $subject->id }}] ?? []).length > 0 && (listQuestionIds[{{ $subject->id }}] ?? []).every((id) => selected.includes(id))"
                        :disabled="(listQuestionIds[{{ $subject->id }}] ?? []).length === 0"
                        @change="selectAllInSubject({{ $subject->id }})"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800"
                    />
                </th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pertanyaan</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jenis</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bobot</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
            @forelse ($questions as $index => $question)
                @php
                    $questionText = e($question->question_text);
                    if ($search !== '') {
                        $questionText = preg_replace(
                            '~('.preg_quote(e($search), '~').')~iu',
                            '<mark class="rounded bg-amber-100 px-0.5 text-amber-900 dark:bg-amber-500/30 dark:text-amber-200">$1</mark>',
                            $questionText
                        );
                    }
                @endphp
                <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $question->is_active ? '' : 'opacity-60' }}">
                    <td class="px-4 py-3">
                        <input type="checkbox" :checked="selected.includes({{ $question->id }})" @change="toggleSelect({{ $question->id }})" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" />
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                    <td class="max-w-xs px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                        <p class="truncate">{!! $questionText !!}</p>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/20 dark:bg-gray-700/60 dark:text-gray-300 dark:ring-gray-400/30">
                            {{ $question->typeLabel() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ rtrim(rtrim($question->score_weight, '0'), '.') }} poin</td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex items-center gap-2">
                            <x-badge-status :status="$question->is_active ? 'aktif' : 'nonaktif'" />
                            <form method="POST" action="{{ route('admin.questions.toggle-active', $question) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" title="{{ $question->is_active ? 'Nonaktifkan soal' : 'Aktifkan soal' }}" class="text-gray-400 transition hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="preview = @js(['id' => $question->id, 'subject' => ['name' => $subject->name], 'type' => $question->type, 'type_label' => $question->typeLabel(), 'question_text' => $question->question_text, 'image_url' => $question->imageUrl(), 'options' => $question->options, 'answer_key' => $question->answer_key, 'score_weight' => $question->score_weight]); $dispatch('open-modal', 'preview-question')" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-gray-700/60 dark:text-gray-300 dark:hover:bg-gray-600">Lihat</button>
                            <a href="{{ route('admin.questions.edit', $question) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Edit</a>
                            <form method="POST" action="{{ route('admin.questions.duplicate', $question) }}" class="inline">
                                @csrf
                                <button type="submit" title="Duplikasi soal" class="rounded-md bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 transition hover:bg-sky-100 dark:bg-sky-500/10 dark:text-sky-300 dark:hover:bg-sky-500/20">Duplikat</button>
                            </form>
                            <button type="button" @click="deleteUrl = '{{ route('admin.questions.destroy', $question) }}'; $dispatch('open-modal', 'confirm-delete')" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Hapus</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada soal untuk mata pelajaran ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
