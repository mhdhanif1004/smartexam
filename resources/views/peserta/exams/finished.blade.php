<x-layouts.peserta :title="'Selesai - '.($session->examSchedule->subject?->name ?? 'Ujian')">
    <div class="mx-auto max-w-lg space-y-6">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-8 text-center shadow-sm">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-600">
                <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="mt-4 text-2xl font-bold text-gray-900">Ujian Berhasil Dikumpulkan</h2>
            <p class="mt-1 text-sm text-emerald-700">Jawabanmu sudah diterima. Jangan lupa berdoa untuk hasil terbaik!</p>
        </div>

        @include('admin.partials.flash')

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="border-b border-gray-100 pb-3 text-base font-bold text-gray-900">Ringkasan Ujian</h3>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Mata Pelajaran</dt>
                    <dd class="font-semibold text-gray-900">{{ $session->examSchedule->subject?->name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Ruangan</dt>
                    <dd class="font-semibold text-gray-900">{{ $session->examSchedule->room?->name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Waktu Pengerjaan</dt>
                    <dd class="font-semibold text-gray-900">{{ \Illuminate\Support\Carbon::now()->startOfDay()->addSeconds($workingSeconds)->format('H:i:s') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Soal Dijawab</dt>
                    <dd class="font-semibold text-gray-900">{{ $answeredCount }} soal</dd>
                </div>
                @if ($result !== null)
                    <div class="flex justify-between gap-4 border-t border-gray-100 pt-3">
                        <dt class="text-gray-500">Skor Sementara</dt>
                        <dd class="font-semibold {{ $result->is_passed ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ number_format((float) $result->total_score, 2) }}
                        </dd>
                    </div>
                @endif
            </dl>
            <p class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">
                Nilai soal essay akan dikoreksi manual oleh guru/pengawas sebelum hasil akhir diumumkan.
            </p>
        </div>

        <div class="text-center">
            <a href="{{ route('peserta.dashboard') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
                Kembali ke Dashboard
            </a>
        </div>
    </div>
</x-layouts.peserta>
