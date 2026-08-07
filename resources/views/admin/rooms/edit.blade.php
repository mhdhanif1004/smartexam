<x-layouts.admin title="Edit Ruangan">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Edit Ruangan</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Perbarui informasi ruangan, pengawas, dan daftar siswa yang ditempatkan di ruangan ini.</p>
        </div>

        @include('admin.rooms._form')
    </div>
</x-layouts.admin>
