<?php
// feedback.php: menerima masukan penguji dari mockup Inventory Saji dan menyimpannya
// DI LUAR folder web (/home/<akun>/feedback-inventory). Tidak ada rahasia di file ini.
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

function out($code, $arr) { http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function clean($s, $max) {
    $s = is_string($s) ? trim($s) : '';
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, array('ok' => false, 'error' => 'Metode tidak didukung.'));
$raw = file_get_contents('php://input');
if (strlen($raw) > 700000) out(413, array('ok' => false, 'error' => 'Data terlalu besar.'));
$in = json_decode($raw, true);
if (!is_array($in)) out(400, array('ok' => false, 'error' => 'Format data tidak valid.'));
if (!empty($in['hp'])) out(200, array('ok' => true, 'id' => 'FB-0'));   // kolom jebakan untuk bot

$JENIS = array('Kesalahan (bug)', 'Saran perbaikan', 'Pertanyaan', 'Kebingungan / istilah', 'Fitur belum ada', 'Pujian');
$TINGKAT = array('Tinggi (menghalangi pekerjaan)', 'Sedang (mengganggu)', 'Rendah (kosmetik)', '-');

$nama = clean(isset($in['nama']) ? $in['nama'] : '', 80);
$jenis = clean(isset($in['jenis']) ? $in['jenis'] : '', 60);
$tingkat = clean(isset($in['tingkat']) ? $in['tingkat'] : '', 60);
$terjadi = clean(isset($in['terjadi']) ? $in['terjadi'] : '', 4000);
if ($nama === '' || !in_array($jenis, $JENIS, true) || !in_array($tingkat, $TINGKAT, true) || strlen($terjadi) < 3) {
    out(422, array('ok' => false, 'error' => 'Nama, jenis, tingkat, dan uraian wajib diisi.'));
}

$DATA = dirname(__DIR__, 3) . '/feedback-inventory';
if (!is_dir($DATA) && !@mkdir($DATA, 0700, true)) out(500, array('ok' => false, 'error' => 'Folder penyimpanan tidak bisa dibuat.'));
@file_put_contents($DATA . '/.htaccess', "Require all denied\n");
if (!is_dir($DATA . '/shots')) @mkdir($DATA . '/shots', 0700, true);
$kf = $DATA . '/admin-key.txt';
if (!file_exists($kf)) @file_put_contents($kf, bin2hex(random_bytes(12)));

// batasi: 20 kiriman per 10 menit per alamat IP
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'x';
$rf = $DATA . '/rate-' . md5($ip) . '.txt';
$now = time(); $hits = array();
if (file_exists($rf)) foreach (explode("\n", (string)file_get_contents($rf)) as $t) if ((int)$t > $now - 600) $hits[] = (int)$t;
if (count($hits) >= 20) out(429, array('ok' => false, 'error' => 'Terlalu banyak kiriman. Coba lagi beberapa menit lagi.'));
$hits[] = $now; @file_put_contents($rf, implode("\n", $hits));

$id = 'FB-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

$foto = null;
if (!empty($in['foto']) && is_string($in['foto']) && preg_match('#^data:image/(jpeg|png);base64,([A-Za-z0-9+/=]+)$#', $in['foto'], $m)) {
    $bin = base64_decode($m[2], true);
    if ($bin !== false && strlen($bin) <= 450000 && @getimagesizefromstring($bin)) {
        $foto = $id . ($m[1] === 'png' ? '.png' : '.jpg');
        if (@file_put_contents($DATA . '/shots/' . $foto, $bin) === false) $foto = null;
    }
}

$c = isset($in['ctx']) && is_array($in['ctx']) ? $in['ctx'] : array();
$ctx = array();
foreach (array('halaman', 'layar_id', 'peran', 'perangkat', 'peramban', 'ukuran', 'url', 'versi', 'dialog', 'aktivitas', 'waktu_klien') as $k) $ctx[$k] = clean(isset($c[$k]) ? $c[$k] : '', 600);

$rec = array(
    'id' => $id, 'waktu' => date('c'), 'nama' => $nama, 'jenis' => $jenis, 'tingkat' => $tingkat,
    'skenario' => clean(isset($in['skenario']) ? $in['skenario'] : '', 20),
    'lakukan' => clean(isset($in['lakukan']) ? $in['lakukan'] : '', 4000),
    'terjadi' => $terjadi,
    'harapan' => clean(isset($in['harapan']) ? $in['harapan'] : '', 4000),
    'usulan' => clean(isset($in['usulan']) ? $in['usulan'] : '', 4000),
    'ctx' => $ctx, 'foto' => $foto, 'status' => 'Baru',
);
if (@file_put_contents($DATA . '/feedback.jsonl', json_encode($rec, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX) === false) {
    out(500, array('ok' => false, 'error' => 'Masukan tidak bisa disimpan.'));
}
out(200, array('ok' => true, 'id' => $id));
