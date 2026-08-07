{{--
    Inisialisasi tema tunggal (single source of truth) untuk seluruh portal:
    Admin, Pengawas, Peserta, Guest, dan App/Profil.
    Mode tersimpan di localStorage dengan kunci "theme" dan diterapkan ke
    elemen <html> SEBELUM first paint agar tidak terjadi flicker/kedip.
--}}
<script>
    (function () {
        try {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {
            // localStorage tidak tersedia; tetap gunakan mode terang.
        }
    })();
</script>
