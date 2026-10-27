<?php
function saji_home() { $d = __DIR__; while (basename($d) !== 'public_html' && dirname($d) !== $d) $d = dirname($d); return dirname($d); }
// Sinkron data sandbox antar perangkat. GET ?r=ruang&since=N  |  POST {r, base, force, d:"<json>"}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function out($code, $json) { http_response_code($code); echo $json; exit; }
function err($code, $msg) { out($code, json_encode(array('ok' => false, 'error' => $msg))); }

$dir = saji_home() . '/sync-inventory';
if (!is_dir($dir)) {
    @mkdir($dir, 0700, true);
    @file_put_contents($dir . '/.htaccess', "Require all denied\n");
}
if (!is_dir($dir)) err(500, 'Folder data tidak bisa dibuat.');

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') { $r = isset($_GET['r']) ? $_GET['r'] : ''; $since = isset($_GET['since']) ? (int)$_GET['since'] : 0; }
elseif ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 4000000) err(413, 'Data terlalu besar.');
    $in = json_decode($raw, true);
    if (!is_array($in)) err(400, 'Permintaan tidak valid.');
    $r = isset($in['r']) ? $in['r'] : '';
} else err(405, 'Metode tidak didukung.');

$r = strtolower((string)$r);
if (!preg_match('/^[a-z0-9-]{1,24}$/', $r)) err(422, 'Nama ruang hanya huruf kecil, angka, dan strip (maksimal 24).');
$file = $dir . '/' . $r . '.json';
$exists = is_file($file);
if (!$exists && $method === 'POST' && count(glob($dir . '/*.json')) >= 60) err(429, 'Terlalu banyak ruang.');

$fh = fopen($file, 'c+');
if (!$fh) err(500, 'Tidak bisa membuka data ruang.');
flock($fh, LOCK_EX);
$content = stream_get_contents($fh);
$rev = 0; $data = '';
if ($content !== '' && ($p = strpos($content, "\n")) !== false) {
    $rev = (int)substr($content, 0, $p);
    $data = substr($content, $p + 1);
}

if ($method === 'GET') {
    flock($fh, LOCK_UN); fclose($fh);
    if ($rev === 0) out(200, '{"ok":true,"rev":0}');
    if ($since === $rev) out(200, '{"ok":true,"rev":' . $rev . ',"same":true}');
    out(200, '{"ok":true,"rev":' . $rev . ',"data":' . $data . '}');
}

$d = isset($in['d']) && is_string($in['d']) ? $in['d'] : '';
if ($d === '' || json_decode($d) === null) { flock($fh, LOCK_UN); fclose($fh); err(422, 'Data kosong atau rusak.'); }
$base = isset($in['base']) ? (int)$in['base'] : 0;
$force = !empty($in['force']);
if (!$force && $base !== $rev) {
    flock($fh, LOCK_UN); fclose($fh);
    out(200, '{"ok":false,"conflict":true,"rev":' . $rev . ',"data":' . $data . '}');
}
$rev++;
ftruncate($fh, 0); rewind($fh);
fwrite($fh, $rev . "\n" . $d);
fflush($fh); flock($fh, LOCK_UN); fclose($fh);
out(200, '{"ok":true,"rev":' . $rev . '}');
