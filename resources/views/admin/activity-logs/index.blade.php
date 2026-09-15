<x-layouts.admin title="Log Aktivitas">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Log Aktivitas</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Riwayat audit 30 aksi MVP lintas 6 role — immutable, append-only. Filter untuk investigasi insiden.</p>
        </div>

        @include('admin.partials.flash')

        <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <label for="action" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Aksi</label>
                    <select name="action" id="action" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">Semua Aksi</option>
                        @foreach ($groupedActions as $group => $actions)
                            <optgroup label="{{ ucfirst(str_replace('_', ' ', $group)) }}">
                                @foreach ($actions as $val)
                                    <option value="{{ $val }}" @selected($filters['action'] === $val)>{{ \App\Enums\ActivityAction::label($val) }} ({{ $val }})</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="causer_role" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Peran Pelaku</label>
                    <select name="causer_role" id="causer_role" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">Semua Peran</option>
                        @foreach ($roleOptions as $role)
                            <option value="{{ $role }}" @selected($filters['causer_role'] === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_from" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Dari Tanggal</label>
                    <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                </div>
                <div>
                    <label for="date_to" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Sampai Tanggal</label>
                    <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                </div>
            </div>
            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto]">
                <div>
                    <label for="search" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Cari (deskripsi / nama pelaku / email / username / IP)</label>
                    <input type="text" name="search" id="search" value="{{ $filters['search'] }}" placeholder="mis. hapus siswa, Budi, 192.168..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Terapkan</button>
                    @if (array_filter($filters))
                        <a href="{{ route('admin.activity-logs.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                    @endif
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Tips: gunakan Cepat — Hari Ini / Minggu Ini / Bulan Ini via parameter date_from &amp; date_to di URL. Pagination mempertahankan filter (withQueryString).</p>
        </form>

        <x-table :headers="['No', 'Waktu', 'Pelaku', 'Peran', 'Aksi', 'Deskripsi', 'IP', 'Detail']"
                 x-data="{ expanded: null, toggleRow(id) { this.expanded = (this.expanded === id) ? null : id; } }">
            @forelse ($logs as $index => $log)
                <tr class="border-t border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50">
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $logs->firstItem() + $index }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $log->created_at?->format('d M Y H:i:s') ?? '-' }}</td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $log->causer?->name ?? '— sistem —' }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $log->causer?->email ?? $log->causer?->username ?? '' }}</div>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $log->causer_role }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300" title="{{ $log->action }}">{{ \App\Enums\ActivityAction::label($log->action) }}</span>
                        <div class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ $log->action }}</div>
                    </td>
                    <td class="max-w-[28ch] truncate px-4 py-3 text-sm text-gray-700 dark:text-gray-300" title="{{ $log->description }}">{{ $log->description }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $log->ip_address ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm">
                        @if (!empty($log->properties))
                            <button type="button" @click="toggleRow({{ $log->id }})" class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                    :class="expanded === {{ $log->id }} ? 'bg-gray-800 text-white dark:bg-gray-700' : ''">
                                <span x-text="expanded === {{ $log->id }} ? 'Tutup' : 'Lihat'"></span>
                            </button>
                        @else
                            <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                        @endif
                    </td>
                </tr>
                @if (!empty($log->properties))
                    <tr x-show="expanded === {{ $log->id }}" x-cloak class="bg-gray-50/70 dark:bg-gray-800/40">
                        <td colspan="8" class="px-4 py-0">
                            <div class="overflow-hidden p-4"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 -translate-y-1">
                                <div class="mb-2 flex items-center gap-2">
                                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Properties</h4>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">subject: {{ $log->subject_type ? class_basename($log->subject_type).'#'.$log->subject_id : '—' }} · UA: {{ \Illuminate\Support\Str::limit($log->user_agent ?? '—', 80) }}</span>
                                </div>
                                <pre class="max-h-64 overflow-auto rounded-lg bg-gray-900 p-3 text-xs leading-relaxed text-gray-100 dark:bg-gray-950">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                <p class="mt-2 text-[11px] text-gray-400 dark:text-gray-500">Nilai sensitif otomatis [REDACTED] oleh ActivityLogger — tidak pernah menyimpan password / plain_password / token_code / token / remember_token / student_answer.</p>
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <svg class="h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Belum ada log aktivitas.</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Log akan muncul setelah aksi MVP tercatat (hapus siswa, kunci pelanggaran, kumpul ujian, dsb).</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-table>

        <div>{{ $logs->links() }}</div>
    </div>
</x-layouts.admin>
