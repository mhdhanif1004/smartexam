@props(['semesters' => [], 'selectedSemesterId' => 0])

@if ($semesters->isNotEmpty())
    <form method="POST" action="{{ route('wali_kelas.set-semester') }}" class="inline-flex items-center gap-2">
        @csrf
        <label for="wali_semester_select" class="text-sm font-medium text-gray-500 dark:text-gray-400">Semester</label>
        <select
            id="wali_semester_select"
            name="semester_id"
            onchange="this.form.submit()"
            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
        >
            @foreach ($semesters as $semester)
                <option value="{{ $semester->id }}" {{ $semester->id == $selectedSemesterId ? 'selected' : '' }}>
                    {{ $semester->nama_lengkap }}{{ $semester->is_active ? ' (Aktif)' : '' }}
                </option>
            @endforeach
        </select>
    </form>
@else
    <p class="text-sm text-gray-400 dark:text-gray-500 italic">Belum ada semester. Admin perlu membuat semester terlebih dahulu.</p>
@endif
