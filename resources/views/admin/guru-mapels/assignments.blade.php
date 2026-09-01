<x-layouts.admin :title="'Kelola Mapel Guru - '.($guruMapel->user?->name ?? 'Guru Mapel')">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Kelola Mata Pelajaran</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $guruMapel->user?->name }} &middot; tentukan mata pelajaran dan kelas yang diampu guru ini. Bisa diubah kapan saja.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.guru-mapels.show', $guruMapel) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Kembali</a>
            </div>
        </div>

        @include('admin.partials.flash')

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Tambah Mapel</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih mapel yang diampu guru ini, lalu simpan. Setelah mapel ditambahkan, Anda bisa memilih kelasnya pada tabel di bawah.</p>

            <form method="POST" action="{{ route('admin.guru-mapels.assignments.store', $guruMapel) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf

                <div class="flex-1">
                    <x-input-label for="subject_id" :value="__('Mata Pelajaran')" />
                    <select id="subject_id" name="subject_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">-- Pilih Mapel --</option>
                        @foreach ($subjects as $subject)
                            @if (! in_array($subject->id, $assignedSubjectIds->all(), true))
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                </div>

                <x-primary-button>Tambah Mapel</x-primary-button>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-800">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Mapel Diampu</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Mapel yang sedang diampu guru ini. Klik mapel untuk memilih kelas.</p>
                </div>
                <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ count($assignments) }} mapel</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Mata Pelajaran</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kelas Diampu</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    {{-- Satu <tbody x-data> per mapel membungkus dua <tr> agar keduanya berbagi
                         scope Alpine yang sama (open), sehingga tombol Pilih Kelas/Tutup benar-benar
                         men-toggle visibilitas baris Kelas Target. <tbody> menjaga struktur baris tabel
                         tetap valid (tidak collapse seperti <template>). --}}
                        @forelse ($assignments as $index => $assignment)
                            <tbody x-data="{ open: {{ $index === 0 ? 'true' : 'false' }} }" class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 align-top text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                    <td class="px-4 py-3 align-top text-sm font-medium text-gray-900 dark:text-gray-100">{{ $assignment['subject']?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 align-top text-sm text-gray-700 dark:text-gray-300">
                                        @if ($assignment['classroom_ids']->isEmpty())
                                            <span class="text-amber-600 dark:text-amber-400">Belum ada kelas</span>
                                        @else
                                            {{ \App\Models\Classroom::summarizeTargets($assignment['classroom_ids']) }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 align-top text-right text-sm">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" @click="open = !open" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                                <span x-show="! open">Pilih Kelas</span>
                                                <span x-show="open" x-cloak>Tutup</span>
                                            </button>
                                            <form method="POST" action="{{ route('admin.guru-mapels.assignments.destroy-subject', [$guruMapel, $assignment['subject']]) }}" onsubmit="return confirm('Hapus mapel ini (beserta kelasnya) dari guru?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="open" x-cloak class="bg-gray-50 dark:bg-gray-800/40">
                                    <td colspan="4" class="px-4 py-4">
                                        <form method="POST" action="{{ route('admin.guru-mapels.assignments.classrooms', [$guruMapel, $assignment['subject']]) }}">
                                            @csrf
                                            <x-questions.classroom-picker
                                                mode="all"
                                                :classrooms="$classrooms"
                                                :selected="$assignment['classroom_ids']"
                                                :exclusive-notes="$exclusiveBySubject[$assignment['subject']->id] ?? []"
                                                description="Pilih kelas untuk mapel ini. Kelas yang dipilih menjadi cakupan Nilai &amp; Absensi guru pada mapel tersebut."
                                            />
                                            <div class="mt-4 flex justify-end">
                                                <x-primary-button>Simpan Kelas</x-primary-button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            </tbody>
                        @empty
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada mapel yang diampu untuk guru ini.</td>
                                </tr>
                            </tbody>
                        @endforelse
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Cakupan Kelas</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ringkasan kelas yang dapat diisi nilai/absensi guru ini per mapel, sesuai penugasan yang tersimpan. Kelola lewat tabel "Mapel Diampu" di atas.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($classScope as $subjectId => $rooms)
                    @php($targetParts = \App\Models\Classroom::summarizeTargetParts(collect($rooms)->pluck('id')))
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/40">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ \App\Models\Subject::query()->find($subjectId)?->name ?? 'Mapel #'.$subjectId }}</p>
                        <div class="mt-2 flex flex-wrap gap-1">
                            @forelse ($targetParts as $part)
                                <span class="inline-flex rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
                                    @if ($part['type'] === 'grade')
                                        {{ $part['label'] }} (Semua Kelas)
                                    @else
                                        {{ $part['label'] }}
                                    @endif
                                </span>
                            @empty
                                <span class="text-xs text-gray-500 dark:text-gray-400">Belum ada kelas</span>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400 sm:col-span-2 lg:col-span-3">Belum ada kelas yang tercakup. Pilih kelas pada tabel "Mapel Diampu" di atas.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.admin>
