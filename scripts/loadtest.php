<?php

/**
 * Load testing sederhana untuk SmartExam.
 *
 * Mode "login" (default): mensimulasikan N pengguna login bersamaan ke /login.
 *   - Setiap pengguna: GET /login (ambil CSRF + cookie), lalu POST /login.
 *   - Butuh akun peserta; untuk 300-600 login bersamaan, buat dulu akun uji:
 *       php artisan exam:create-test-students 600
 *   - Catatan: Laravel membatasi 5 percobaan/menit per email|ip, jadi pakai
 *     akun berbeda untuk tiap pengguna simulasi (bukan dipakai ulang).
 *
 * Mode "page": menembak GET ke path tertentu secara bersamaan (tanpa CSRF),
 * berguna untuk mengukur throughput dasar server.
 *
 * Catatan web server:
 *   - php artisan serve HANYA memproses PHP_CLI_SERVER_WORKERS (default 4)
 *     permintaan bersamaan -> jangan dipakai untuk load test skala penuh.
 *   - Apache XAMPP: vhost pertama di httpd-vhosts.conf adalah default, jadi
 *     akses via http://<IP-LAN>/ otomatis masuk ke aplikasi. Untuk menembak
 *     Apache dari mesin yang sama tanpa ubah file hosts, tambahkan --host
 *     (mis. --host=smartexam.test) supaya Apache memilih vhost yang benar.
 *
 * Contoh:
 *   php scripts/loadtest.php --url=http://127.0.0.1 --host=smartexam.test --users=300 --concurrency=50
 *   php scripts/loadtest.php --url=http://192.168.1.10 --users=600 --concurrency=100
 *   php scripts/loadtest.php --mode=page --users=600 --concurrency=100 --path=/login
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z0-9-]+)=(.*)$/i', $arg, $m)) {
        $args[$m[1]] = $m[2];
    }
}

$baseUrl = rtrim($args['url'] ?? 'http://127.0.0.1:8000', '/');
$users = max(1, (int) ($args['users'] ?? 300));
$workers = max(1, (int) ($args['concurrency'] ?? 50));
$mode = $args['mode'] ?? 'login';
$path = $args['path'] ?? '/login';
$password = $args['password'] ?? 'password';
$timeout = max(5, (int) ($args['timeout'] ?? 30));
$host = $args['host'] ?? null;

if ($mode === 'login') {
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $emails = DB::table('users')
        ->where('role', 'peserta')
        ->where('is_active', true)
        ->where('email', 'like', '%@test.local')
        ->pluck('email')
        ->all();

    if (count($emails) === 0) {
        fwrite(STDERR, "Tidak ada akun peserta. Buat dulu: php artisan exam:create-test-students 600\n");
        exit(1);
    }

    if (count($emails) < $users) {
        fwrite(STDERR, sprintf(
            "PERINGATAN: akun peserta hanya %d, tapi --users=%d. Akun akan dipakai ulang dan rate limiter (5/menit/email) akan menggagalkan sebagian login. Buat akun lebih banyak: php artisan exam:create-test-students %d\n",
            count($emails), $users, $users
        ));
    }
}

echo sprintf("Mode: %s | URL: %s | users: %d | concurrency: %d\n", $mode, $baseUrl, $users, $workers);
if ($host !== null && $host !== '') {
    echo "Host header: {$host}\n";
}

function makeGetHandle(string $url, string $cookieFile, int $timeout): CurlHandle
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_USERAGENT, 'SmartExam-LoadTest');
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    return $ch;
}

function applyHostHeader(CurlHandle $ch, ?string $host): void
{
    if ($host !== null && $host !== '') {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Host: '.$host]);
    }
}

function extractCookies(string $headerBlock): array
{
    if (preg_match_all('/^Set-Cookie:\s*([^;]+);/mi', $headerBlock, $m)) {
        return $m[1];
    }

    return [];
}

$mh = curl_multi_init();
curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, $workers);

$tasks = [];
$handles = []; // task index => CurlHandle
$startAll = microtime(true);

if ($mode === 'page') {
    for ($i = 0; $i < $users; $i++) {
        $tasks[] = ['stage' => 'done', 'start' => microtime(true), 'lat' => 0.0, 'code' => 0, 'ok' => false, 'cookie' => ''];
        $ch = makeGetHandle($baseUrl.$path, '', $timeout);
        applyHostHeader($ch, $host);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }
} else {
    for ($i = 0; $i < $users; $i++) {
        $tasks[] = [
            'stage' => 'get',
            'email' => $emails[$i % count($emails)],
            'start' => microtime(true),
            'getCode' => 0,
            'postCode' => 0,
            'finalUrl' => '',
            'ok' => false,
            'error' => null,
            'token' => null,
            'cookies' => [],
        ];
        $ch = makeGetHandle($baseUrl.'/login', '', $timeout);
        applyHostHeader($ch, $host);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }
}

$running = null;
$doneCount = 0;

do {
    $status = curl_multi_exec($mh, $running);
    if ($running > 0) {
        curl_multi_select($mh, 1.0);
    }

    while (($info = curl_multi_info_read($mh)) !== false) {
        $ch = $info['handle'];
        $idx = array_search($ch, $handles, true);
        if ($idx === null || $idx === false) {
            continue;
        }
        unset($handles[$idx]);
        curl_multi_remove_handle($mh, $ch);

        $body = curl_multi_getcontent($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $latency = microtime(true) - $tasks[$idx]['start'];
        curl_close($ch);

        $task = &$tasks[$idx];

        if ($mode === 'page') {
            $task['lat'] = $latency;
            $task['code'] = $http;
            $task['ok'] = $errno === 0 && $http >= 200 && $http < 400;
            $doneCount++;

            continue;
        }

        if ($task['stage'] === 'get') {
            $task['getCode'] = $http;
            if ($errno !== 0 || $http >= 400) {
                $task['stage'] = 'done';
                $task['error'] = $err !== '' ? $err : 'HTTP '.$http;
                $doneCount++;

                continue;
            }

            [$headerBlock, $page] = array_pad(explode("\r\n\r\n", $body, 2), 2, '');
            $task['cookies'] = extractCookies($headerBlock);
            if (preg_match('/name="_token"\s+value="([^"]+)"/', $page, $m)) {
                $task['token'] = $m[1];
            }

            $post = curl_init($baseUrl.'/login');
            curl_setopt($post, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($post, CURLOPT_HEADER, true);
            curl_setopt($post, CURLOPT_USERAGENT, 'SmartExam-LoadTest');
            curl_setopt($post, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($post, CURLOPT_CONNECTTIMEOUT, 5);
            applyHostHeader($post, $host);
            curl_setopt($post, CURLOPT_POST, true);
            curl_setopt($post, CURLOPT_POSTFIELDS, http_build_query([
                '_token' => $task['token'],
                'email' => $task['email'],
                'password' => $password,
            ]));
            if ($task['cookies'] !== []) {
                curl_setopt($post, CURLOPT_COOKIE, implode('; ', $task['cookies']));
            }

            $task['stage'] = 'post';
            $task['start'] = microtime(true);
            curl_multi_add_handle($mh, $post);
            $handles[$idx] = $post;
        } elseif ($task['stage'] === 'post') {
            $task['postCode'] = $http;
            [$headerBlock] = array_pad(explode("\r\n\r\n", $body, 2), 2, '');
            $task['cookies'] = extractCookies($headerBlock);
            preg_match('/^Location:\s*(.*)$/mi', $headerBlock, $lm);
            $location = isset($lm[1]) ? trim($lm[1]) : '';

            if ($errno !== 0 || $http >= 400 || strpos($location, '/login') !== false) {
                $task['stage'] = 'done';
                $task['finalUrl'] = $location !== '' ? $location : $finalUrl;
                $task['error'] = $err !== ''
                    ? $err
                    : ($http === 419 ? 'HTTP 419 (CSRF)' : 'HTTP '.$http);
                $task['lat'] = $latency;
                $doneCount++;

                continue;
            }

            $locationPath = (string) parse_url($location, PHP_URL_PATH);
            $locationQuery = parse_url($location, PHP_URL_QUERY);
            $followUrl = $baseUrl.$locationPath.($locationQuery !== null ? '?'.$locationQuery : '');

            $follow = curl_init($followUrl);
            curl_setopt($follow, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($follow, CURLOPT_HEADER, false);
            curl_setopt($follow, CURLOPT_USERAGENT, 'SmartExam-LoadTest');
            curl_setopt($follow, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($follow, CURLOPT_CONNECTTIMEOUT, 5);
            applyHostHeader($follow, $host);
            if ($task['cookies'] !== []) {
                curl_setopt($follow, CURLOPT_COOKIE, implode('; ', $task['cookies']));
            }

            $task['stage'] = 'final';
            $task['start'] = microtime(true);
            curl_multi_add_handle($mh, $follow);
            $handles[$idx] = $follow;
        } else {
            $task['stage'] = 'done';
            $task['finalUrl'] = $finalUrl;
            $task['lat'] = $latency;
            $task['ok'] = $errno === 0
                && $http >= 200
                && $http < 400
                && strpos($finalUrl, '/login') === false;
            if ($errno !== 0) {
                $task['error'] = $err;
            }
            $doneCount++;
        }
    }
} while ($running > 0);

curl_multi_close($mh);

$totalSecs = max(0.0001, microtime(true) - $startAll);
$oks = 0;
$lats = [];
foreach ($tasks as $t) {
    if ($t['ok']) {
        $oks++;
    }
    if (isset($t['lat'])) {
        $lats[] = $t['lat'];
    }
}

$fails = $users - $oks;

function percentile(array $values, float $p): float
{
    if ($values === []) {
        return 0.0;
    }
    sort($values);
    $i = (int) ceil($p / 100 * count($values)) - 1;
    $i = max(0, min(count($values) - 1, $i));

    return $values[$i];
}

echo "\n===== HASIL LOAD TEST =====\n";
echo sprintf("Total pengguna simulasi : %d\n", $users);
echo sprintf("Concurrency maks         : %d\n", $workers);
echo sprintf("Durasi total             : %.2f detik\n", $totalSecs);
echo sprintf("Sukses                   : %d (%.1f%%)\n", $oks, $oks / $users * 100);
echo sprintf("Gagal                    : %d (%.1f%%)\n", $fails, $fails / $users * 100);
echo sprintf("Throughput (permintaan berhasil/detik): %.1f/s\n", $oks / $totalSecs);

if ($lats !== []) {
    echo "\nLatensi (detik):\n";
    echo sprintf("  min   : %.3f\n", min($lats));
    echo sprintf("  p50   : %.3f\n", percentile($lats, 50));
    echo sprintf("  p90   : %.3f\n", percentile($lats, 90));
    echo sprintf("  p95   : %.3f\n", percentile($lats, 95));
    echo sprintf("  p99   : %.3f\n", percentile($lats, 99));
    echo sprintf("  max   : %.3f\n", max($lats));
}

if ($mode === 'login' && $fails > 0) {
    echo "\nKemungkinan penyebab gagal: akun dipakai berulang (rate limiter 5/menit/email), server kewalahan, atau MySQL tidak sanggup.\n";
}
