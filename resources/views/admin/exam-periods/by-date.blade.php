<x-layouts.admin title="Sesi Ujian">
    <div x-data="{
        periodDelete: {
            id: null,
            name: '',
            deleteUrl: '',
            preview: null,
            busy: false,
            acknowledged: false,
            message: '',
            async open(id, name, deleteUrl) {
                this.id = id;
                this.name = name;
                this.deleteUrl = deleteUrl;
                this.preview = null;
                this.message = '';
                this.busy = true;
                try {
                    const response = await fetch('{{ route('admin.exam-periods.delete-preview', '__ID__') }}'.replace('__ID__', id), {
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    this.preview = await response.json();
                } catch {
                    this.preview = { schedules_count: 0, room_assignments_count: 0, tokens_count: 0, started_sessions_count: 0, active_sessions_count: 0 };
                }
                this.acknowledged = false;
                this.busy = false;
                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'delete-period' }));
            },
            get mode() {
                if (!this.preview) return 'simple';
                if (this.preview.started_sessions_count > 0) return 'blocked';
                if (this.preview.schedules_count > 0 || this.preview.room_assignments_count > 0 || this.preview.tokens_count > 0) return 'warning';
                return 'simple';
            },
            confirm() {
                this.busy = true;
                this.message = '';
                fetch(this.deleteUrl, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then((response) => {
                        if (response.ok || response.redirected) { window.location.reload(); return; }
                        return response.json().then((data) => { this.message = data.message || data.error || 'Gagal menghapus sesi ujian.'; });
                    })
                    .catch(() => { this.message = 'Terjadi kesalahan saat menghapus.'; })
                    .finally(() => { this.busy = false; });
            },
        },
    }" class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Sesi Ujian</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Sesi ujian pada tanggal
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ \Illuminate\Support\Carbon::parse($examDate)->locale('id')->translatedFormat('l, d F Y') }}</span>.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.exam-periods.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Kembali ke Daftar Tanggal
                </a>
            </div>
        </div>

        @include('admin.partials.flash')

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama Sesi</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jam</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jumlah Jadwal</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($periods as $index => $period)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $period->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ \Illuminate\Support\Str::substr($period->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($period->end_time, 0, 5) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $period->schedules_count }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('admin.exam-periods.show', $period) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Kelola</a>
                                        <a href="{{ route('admin.exam-periods.groups.create', $period) }}" class="rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">Tambah Kelompok</a>
                                        <button type="button" @click.stop="periodDelete.open({{ $period->id }}, @js($period->name), '{{ route('admin.exam-periods.destroy', $period) }}')" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada sesi ujian pada tanggal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-modal name="delete-period" maxWidth="lg">
            <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Hapus Sesi Ujian</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Konfirmasi penghapusan <span x-text="periodDelete.name" class="font-semibold"></span>.</p>
                    </div>
                    <button type="button" @click="$dispatch('close')" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Loading state --}}
                <div x-show="periodDelete.busy && !periodDelete.preview" class="mt-6 flex items-center justify-center gap-2 py-6 text-sm text-gray-500 dark:text-gray-400">
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-600 dark:border-gray-600 dark:border-t-indigo-400"></span>
                    Memeriksa data terkait...
                </div>

                {{-- Mode A: SISWA SUDAH MENGERJAKAN (warning dengan opsi override) --}}
                <div x-show="periodDelete.preview && periodDelete.mode === 'blocked'" x-transition class="mt-5">
                    {{-- A1: Masih ada siswa SEDANG AKTIF mengerjakan (paling berisiko) --}}
                    <div x-show="periodDelete.preview?.active_sessions_count > 0" class="flex items-start gap-3 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-500/10 dark:text-rose-300">
                        <svg class="h-5 w-5 shrink-0 text-rose-500 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <div>
                            <p class="font-semibold">⚠️ PERINGATAN KERAS: Ada <strong x-text="periodDelete.preview?.active_sessions_count"></strong> siswa yang SEDANG AKTIF mengerjakan ujian pada sesi ini SEKARANG.</p>
                            <p class="mt-1">Menghapus sesi ini akan MENGGANGGU ujian mereka yang sedang berlangsung (timer dan validasi token akan gagal). Sangat disarankan TIDAK menghapus sampai semua siswa selesai.</p>
                            <p class="mt-2 text-xs text-rose-600 dark:text-rose-400">Catatan: bila tetap dihapus, jadwal akan menjadi legacy tanpa sesi (histori jawaban &amp; nilai siswa tetap terjaga), tapi konfigurasi (token, penugasan ruangan/pengawas) dihapus permanen.</p>
                            <label class="mt-3 flex cursor-pointer items-start gap-2 text-xs font-medium">
                                <input type="checkbox" x-model="periodDelete.acknowledged" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-rose-600 focus:ring-rose-500 dark:border-gray-600 dark:bg-gray-800">
                                <span>Saya paham risiko ini dan tetap ingin menghapus sesi ini.</span>
                            </label>
                        </div>
                    </div>

                    {{-- A2: Semua sudah selesai (tak ada yang aktif) --}}
                    <div x-show="periodDelete.preview?.active_sessions_count === 0" class="flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-500/10 dark:text-rose-300">
                        <svg class="h-5 w-5 shrink-0 text-rose-500 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <div>
                            <p class="font-semibold">Sudah terdapat <strong x-text="periodDelete.preview?.started_sessions_count"></strong> siswa yang mengerjakan ujian pada sesi ini.</p>
                            <p class="mt-1">Data histori tetap dijaga (jadwal akan menjadi legacy tanpa sesi, history jawaban &amp; nilai tetap utuh), tapi konfigurasi (token, penugasan ruangan/pengawas) akan dihapus permanen. Lanjutkan?</p>
                        </div>
                    </div>
                </div>

                {{-- Mode B: WARNING (ada data terkait, belum ada siswa mengerjakan) --}}
                <div x-show="periodDelete.preview && periodDelete.mode === 'warning'" x-transition class="mt-5">
                    <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                        <svg class="h-5 w-5 shrink-0 text-amber-500 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <div>
                            <p class="font-semibold">Tindakan ini akan menghapus data terkait secara permanen.</p>
                            <ul class="mt-1 list-inside list-disc text-amber-700 dark:text-amber-400">
                                <li x-show="periodDelete.preview?.schedules_count > 0"><span x-text="periodDelete.preview?.schedules_count"></span> jadwal ujian dihapus permanen</li>
                                <li x-show="periodDelete.preview?.room_assignments_count > 0"><span x-text="periodDelete.preview?.room_assignments_count"></span> penempatan siswa dihapus permanen</li>
                                <li x-show="periodDelete.preview?.tokens_count > 0"><span x-text="periodDelete.preview?.tokens_count"></span> token ujian dihapus permanen</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Mode C: SIMPLE (tidak ada data terkait) --}}
                <div x-show="periodDelete.preview && periodDelete.mode === 'simple'" x-transition class="mt-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Sesi ujian ini belum memiliki jadwal, penempatan, atau token. Hapus permanen?</p>
                </div>

                <div x-show="periodDelete.message !== ''" x-transition class="mt-4 flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-500/10 dark:text-rose-300">
                    <p x-text="periodDelete.message"></p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <button
                        type="button"
                        @click="periodDelete.confirm()"
                        :disabled="periodDelete.busy || (periodDelete.mode === 'blocked' && periodDelete.preview?.active_sessions_count > 0 && !periodDelete.acknowledged)"
                        :title="periodDelete.mode === 'blocked' && periodDelete.preview?.active_sessions_count > 0 && !periodDelete.acknowledged ? 'Centang kotak untuk mengonfirmasi pemahaman risiko' : ''"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="periodDelete.mode === 'warning' ? 'bg-amber-600 hover:bg-amber-500' : 'bg-rose-600 hover:bg-rose-500'"
                    >
                        <span x-show="periodDelete.busy" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </x-modal>
    </div>
</x-layouts.admin>
