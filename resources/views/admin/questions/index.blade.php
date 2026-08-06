<x-layouts.admin title="Bank Soal">
    <div x-data="{ deleteUrl: '' }" class="space-y-6">
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

        <x-table :headers="['No', 'Mata Pelajaran', 'Pertanyaan', 'Jenis', 'Bobot', 'Aksi']">
            @forelse ($questions as $index => $question)
                <tr class="transition hover:bg-gray-50">
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
            @endforelse
        </x-table>

        <div>{{ $questions->links() }}</div>

        @include('admin.partials.delete-modal')
    </div>
</x-layouts.admin>
