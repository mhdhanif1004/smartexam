<x-layouts.admin title="Bank Soal">
    <div
        x-data="selectionManager({
            sessionKey: 'smartexam_selected_questions',
            visibleIds: @js($questions->map(fn ($question) => $question->id)->values()),
            bulkDeleteSuccess: @js(str_contains((string) session('success'), 'soal berhasil dihapus.')),
            bulkDeleteUrl: @js(route('admin.questions.bulk-delete')),
        })"
        class="space-y-6"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Bank Soal</h2>
                <p class="mt-1 text-sm text-gray-500">Kelola soal ujian untuk setiap mata pelajaran.</p>
            </div>
            <a href="{{ route('admin.questions.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Soal
            </a>
        </div>

        @include('admin.partials.flash')

        <form method="GET" action="{{ route('admin.questions.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex-1">
                <x-text-input type="search" name="search" value="{{ request('search') }}" placeholder="Cari pertanyaan..." class="block w-full" />
            </div>
            <div>
                <select name="subject_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:w-auto">
                    <option value="">Semua Mata Pelajaran</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:w-auto">
                    <option value="">Semua Jenis Soal</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Cari</button>
                @if (request('search') || request('subject_id') || request('type'))
                    <a href="{{ route('admin.questions.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
                @endif
            </div>
        </form>

        <div x-show="selected.length > 0" x-transition class="flex items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
            <p class="text-sm font-medium text-indigo-800">
                <span x-text="selected.length" class="font-bold"></span> soal dipilih <span class="text-xs text-indigo-500">(dari semua halaman)</span>
            </p>
            <div class="flex items-center gap-3">
                <button type="button" @click="clearSelection()" class="text-sm font-medium text-gray-600 transition hover:text-gray-800">Reset Pilihan</button>
                <button type="button" @click="$dispatch('open-modal', 'confirm-bulk-delete')" class="inline-flex items-center gap-1.5 rounded-md bg-rose-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-rose-500">
                    Hapus Massal
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3">
                                <input type="checkbox" :checked="visibleIds.length > 0 && visibleIds.every((id) => selected.includes(id))" :disabled="visibleIds.length === 0" @change="selectAll()" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Mata Pelajaran</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Pertanyaan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Jenis</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Bobot</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($questions as $index => $question)
                            <tr class="transition hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <input type="checkbox" :checked="selected.includes({{ $question->id }})" @change="toggleSelect({{ $question->id }})" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $questions->firstItem() + $index }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $question->subject?->name }}</td>
                                <td class="max-w-xs px-4 py-3 text-sm text-gray-700">
                                    <p class="truncate">{{ $question->question_text }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/20">
                                        {{ $types[$question->type] ?? ucwords(str_replace('_', ' ', $question->type)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ rtrim(rtrim($question->score_weight, '0'), '.') }} poin</td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.questions.edit', $question) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">Edit</a>
                                        <button type="button" @click="deleteUrl = '{{ route('admin.questions.destroy', $question) }}'; $dispatch('open-modal', 'confirm-delete')" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada soal.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $questions->links() }}</div>

        @include('admin.partials.delete-modal')

        <x-modal name="confirm-bulk-delete" maxWidth="sm" focusable>
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100">
                        <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Konfirmasi Hapus Massal</h2>
                        <p class="mt-1 text-sm text-gray-500">Yakin ingin menghapus <span x-text="selected.length" class="font-bold"></span> soal yang dipilih? Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <form method="POST" :action="bulkDeleteUrl" class="inline">
                        @csrf
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="ids[]" :value="id">
                        </template>
                        <x-danger-button>Hapus</x-danger-button>
                    </form>
                </div>
            </div>
        </x-modal>
    </div>
</x-layouts.admin>
