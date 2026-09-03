@echo off
REM ============================================================
REM  start-testing.bat
REM  Menjalankan SEMUA proses smartexam SEPERTI composer dev,
REM  TAPI TANPA npm run dev (Vite HMR).
REM  Dipakai khusus saat testing lewat ngrok / HP.
REM
REM  composer.json Anda TIDAK diubah. composer dev yang asli
REM  tetap bisa dipakai seperti biasa untuk ngoding sehari-hari.
REM ============================================================

REM cd otomatis ke folder tempat file .bat ini berada
cd /d "%~dp0"

echo.
echo ============================================
echo   Pastikan asset sudah di-build dulu!
echo   Jika belum pernah/baru ubah tampilan,
echo   jalankan dulu: npm run build
echo ============================================
echo.
pause

npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#34d399" ^
 "php artisan serve --host=0.0.0.0 --port=8000" ^
 "php artisan queue:listen --tries=1" ^
 "php artisan schedule:work" ^
 "php artisan pail --timeout=0" ^
 "php artisan reverb:start --host=0.0.0.0 --port=8080 --hostname=127.0.0.1" ^
 --names=server,queue,scheduler,logs,reverb

pause
