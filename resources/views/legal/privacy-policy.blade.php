<x-legal-layout
    title="Kebijakan Privasi"
    subtitle="Cara Sekolah mengelola dan melindungi data pribadi pada pelaksanaan ujian berbasis komputer."
    icon="lock"
    :toc="[
        ['id' => 'data', 'icon' => 'database', 'label' => 'Data yang Dikumpulkan'],
        ['id' => 'tujuan', 'icon' => 'task_alt', 'label' => 'Tujuan Penggunaan'],
        ['id' => 'akses', 'icon' => 'manage_accounts', 'label' => 'Akses Data'],
        ['id' => 'penyimpanan', 'icon' => 'storage', 'label' => 'Penyimpanan Data'],
        ['id' => 'hak', 'icon' => 'verified_user', 'label' => 'Hak Pengguna'],
    ]">

    <p>Halaman ini menjelaskan bagaimana Sekolah menggunakan SmartExam untuk mengelola data pribadi dalam
        pelaksanaan Ujian Sekolah berbasis komputer (Computer Based Test / CBT). Mohon dibaca dengan saksama.</p>

    <section id="data"
        class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg md:p-xl shadow-sm scroll-mt-24">
        <h2 class="font-title-md text-title-md text-on-surface mb-sm flex items-center gap-sm">
            <span class="material-symbols-outlined text-[20px] text-primary">database</span>
            1. Data yang Dikumpulkan
        </h2>
        <p>Saat menggunakan SmartExam, sistem mengumpulkan data berikut:</p>
        <ul class="list-disc pl-md space-y-xs mt-sm">
            <li>Data identitas: nama lengkap, NISN, dan email (untuk pengawas & guru mata pelajaran);</li>
            <li>Akun pengguna: username dan password <strong>yang disimpan dalam keadaan terenkripsi</strong>;</li>
            <li>Data ujian: jawaban yang dipilih peserta, hasil/nilai ujian, dan status kehadiran;</li>
            <li>Log aktivitas: catatan waktu masuk/keluar, aktivitas selama ujian, serta catatan pelanggaran selama
                ujian (misalnya pindah tab atau keluar dari layar penuh).</li>
        </ul>
    </section>

    <section id="tujuan"
        class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg md:p-xl shadow-sm scroll-mt-24">
        <h2 class="font-title-md text-title-md text-on-surface mb-sm flex items-center gap-sm">
            <span class="material-symbols-outlined text-[20px] text-primary">task_alt</span>
            2. Tujuan Penggunaan Data
        </h2>
        <p>Data yang dikumpulkan digunakan semata-mata untuk:</p>
        <ul class="list-disc pl-md space-y-xs mt-sm">
            <li>Keperluan administrasi ujian di sekolah;</li>
            <li>Proses penilaian dan pengolahan nilai;</li>
            <li>Penyusunan laporan hasil ujian untuk disampaikan kepada pihak sekolah dan orang tua/wali.</li>
        </ul>
    </section>

    <section id="akses"
        class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg md:p-xl shadow-sm scroll-mt-24">
        <h2 class="font-title-md text-title-md text-on-surface mb-sm flex items-center gap-sm">
            <span class="material-symbols-outlined text-[20px] text-primary">manage_accounts</span>
            3. Akses Data
        </h2>
        <p>Akses terhadap data dibatasi sesuai peran masing-masing pengguna:</p>
        <ul class="list-disc pl-md space-y-xs mt-sm">
            <li><strong>Admin sekolah</strong> — akses penuh untuk pengelolaan data dan laporan;</li>
            <li><strong>Guru mata pelajaran</strong> — akses hanya untuk kelas/mata pelajaran yang diampu;</li>
            <li><strong>Pengawas</strong> — akses hanya untuk ruang ujian yang ditugaskan;</li>
            <li><strong>Peserta</strong> — hanya dapat melihat data ujian miliknya sendiri.</li>
        </ul>
    </section>

    <section id="penyimpanan"
        class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg md:p-xl shadow-sm scroll-mt-24">
        <h2 class="font-title-md text-title-md text-on-surface mb-sm flex items-center gap-sm">
            <span class="material-symbols-outlined text-[20px] text-primary">storage</span>
            4. Penyimpanan Data
        </h2>
        <p>Data disimpan pada server yang dikelola pihak sekolah (bersifat lokal). SmartExam <strong>tidak membagikan
                data</strong> kepada pihak ketiga di luar keperluan resmi sekolah, serta tidak memperjualbelikan data
            pengguna.</p>
    </section>

    <section id="hak"
        class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg md:p-xl shadow-sm scroll-mt-24">
        <h2 class="font-title-md text-title-md text-on-surface mb-sm flex items-center gap-sm">
            <span class="material-symbols-outlined text-[20px] text-primary">verified_user</span>
            5. Hak Pengguna
        </h2>
        <p>Pengguna dapat mengajukan permintaan untuk melihat atau memperbaiki data pribadinya kepada admin sekolah
            sesuai ketentuan yang berlaku.</p>
    </section>
</x-legal-layout>
