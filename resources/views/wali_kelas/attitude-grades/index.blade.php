<x-layouts.wali_kelas title="Nilai Sikap">
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Nilai Sikap</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Kelas <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $wali->classroom?->name ?? '-' }}</span> — kelola nilai sikap siswa.
                    </p>
                </div>
                <x-semester-selector :semesters="$semesters" :selected-semester-id="$selectedSemesterId" />
            </div>
        </div>

        @php
            $attitudeData = $attitudeGrades + ['semesters' => $semesters, 'selectedSemesterId' => $selectedSemesterId];
        @endphp
        @include('wali_kelas.attitude-grades.partials._tab', $attitudeData)
    </div>
</x-layouts.wali_kelas>