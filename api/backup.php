<?php
// Cadangan harian ke JSON, jalankan dari cron (CLI): php /home/<user>/public_html/inv/api/backup.php
// Hasil di <home>/backup-inventory/, 30 file terakhir disimpan. Tidak bisa dibuka lewat web.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/lib.php';
$dir = saji_home() . '/backup-inventory';
if (!is_dir($dir)) mkdir($dir, 0700, true);
$dump = array('time' => now(), 'seq' => (int)q("SELECT v FROM meta WHERE k='seq'")->fetch()['v']);
foreach (array('users', 'items', 'suppliers', 'audit') as $t) $dump[$t] = q("SELECT * FROM $t")->fetchAll();
$f = $dir . '/inv-' . gmdate('Ymd-His') . '.json.gz';
file_put_contents($f, gzencode(json_encode($dump, JSON_UNESCAPED_UNICODE), 9));
chmod($f, 0600);
$all = glob($dir . '/inv-*.json.gz'); sort($all);
foreach (array_slice($all, 0, max(0, count($all) - 30)) as $old) unlink($old);
echo "OK $f\n";
