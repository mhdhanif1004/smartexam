<x-legal-layout
    title="Syarat & Ketentuan Penggunaan"
    subtitle="Ketentuan yang berlaku bagi seluruh pengguna SmartExam pada pelaksanaan ujian sekolah."
    icon="gavel"
    :toc="[
        ['id' => 'tujuan', 'icon' => 'school', 'label' => 'Tujuan Penggunaan'],
        ['id' => 'kewajiban', 'icon' => 'assignment_ind', 'label' => 'Kewajiban Pengguna'],
        ['id' => 'larangan', 'icon' => 'block', 'label' => 'Larangan Kecurangan'],
        ['id' => 'konsekuensi', 'icon' => 'report', 'label' => 'Konsekuensi Pelanggaran'],
        ['id' => 'perubahan', 'icon' => 'published_with_changes', 'label' => 'Perubahan Ketentuan'],
    ]">

    <p>Dengan menggunakan SmartExam, Anda dianggap telah membaca dan menyetujui seluruh ketentuan berikut. Ketentuan ini
        berlaku untuk seluruh pengguna sistem, baik admin, guru mata pelajaran, pengawas, maupun peserta.</p>

    <section id="tujuan"
        class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg md:p-xl shadow-sm scroll-mt-24">
        <h2 class="font-title-md text-title-md text-on-surface mb-sm flex items-center gap-sm">
            <span class="material-symbols-outlined text-[20px] text-primary">school</span>
            1. Tujuan Penggunaan
        </h2>
        <p>SmartExam diperuntukkan bagi <strong>kegiatan ujian resmi sekolah</strong> yang diselenggarakan secara
            terjadwal. Sistem ini bukan sarana latihan pribadi yang bebas digunakan tanpa pengawasan sekolah.</p>
    </section>

    <section id="kewajiban"
        class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg md:p-xl shadow-sm scroll-mt-24">
        <h2 class="font-title-md text-title-md text-on-surface mb-sm flex items-center gap-sm">
            <span class="material-symbols-outlined text-[20px] text-primary">assignment_ind</span>
            2. Kewajiban Pengguna
        </h2>
        <ul class="list-disc pl-md space-y-xs">
            <li>Menjaga kerahasiaan akun dan password masing-masing; tidak membagikannya kepada orang lain;</li>
            <li>Tidak melakukan tindakan kecurangan selama ujian berlansung;</li>
            <li>Mengikuti aturan dan arahan pengawas selama pelaksanaan ujian.</li>
        </ul>
    </section>

    <section id="larangan"
        class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg md:p-xl shadow-sm scroll-mt-24">
        <h2 class="font-title-md text-title-md text-on-surface mb-sm flex items-center gap-sm">
            <span class="material-symbols-outlined text-[20px] text-primary">block</span>
            3. Larangan Kecurangan
        </h2>
        <p>SmartExam dilengkapi fitur pencegahan kecurangan. Perilaku berikut akan <strong>tercatat sebagai
                pelanggaran</strong> oleh sistem:</p>
        <ul class="list-disc pl-md space-y-xs mt-sm">
            <li>Aktivitas pindah tab/membuka jendela lain (lose focus) saat ujian berlangsung;</li>
            <li>Keluar dari mode layar penuh (fullscreen) saat ujian;</li>
            <li>Upaya lain yang mengindikasikan kecurangan, yang akan direkam pada log aktivitas.</li>
        </ul>
    </section>

    <section id="konsekuensi"
        class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg md:p-xl shadow-sm scroll-mt-24">
        <h2 class="font-title-md text-title-md text-on-surface mb-sm flex items-center gap-sm">
            <span class="material-symbols-outlined text-[20px] text-primary">report</span>
            4. Konsekuensi Pelanggaran
        </h2>
        <p>Apabila terindikasi melanggar aturan, pengawas atau admin berwenang untuk:</p>
        <ul class="list-disc pl-md space-y-xs mt-sm">
            <li>Mencatat pelanggaran pada rekam jejak peserta;</li>
            <li><strong>Menonaktifkan sementara</strong> sesi ujian peserta (locked by admin) untuk pemeriksaan lebih
                lanjut.</li>
        </ul>
    </section>

    <section id="perubahan"
        class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg md:p-xl shadow-sm scroll-mt-24">
        <h2 class="font-title-md text-title-md text-on-surface mb-sm flex items-center gap-sm">
            <span class="material-symbols-outlined text-[20px] text-primary">published_with_changes</span>
            5. Perubahan Ketentuan
        </h2>
        <p>Sekolah berhak mengubah, memperbarui, atau menyempurnakan isi ketentuan ini sewaktu-waktu tanpa pemberitahuan
            terlebih dahulu. Perubahan akan berlaku sejak diperbarui pada sistem.</p>
    </section>
</x-legal-layout>
