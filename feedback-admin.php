<?php
// feedback-admin.php: daftar masukan penguji. Dilindungi kunci (file admin-key.txt di luar folder web).
error_reporting(0);
ini_set('display_errors', '0');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$DATA = dirname(__DIR__, 3) . '/feedback-inventory';
$kf = $DATA . '/admin-key.txt';
$given = isset($_GET['k']) ? (string)$_GET['k'] : '';
$real = file_exists($kf) ? trim((string)file_get_contents($kf)) : '';
if ($real === '' || !hash_equals($real, $given)) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo "Akses ditolak.\n"; exit; }

$rows = array();
if (file_exists($DATA . '/feedback.jsonl')) {
    foreach (explode("\n", (string)file_get_contents($DATA . '/feedback.jsonl')) as $ln) {
        $r = json_decode($ln, true); if (is_array($r)) $rows[] = $r;
    }
}
$rows = array_reverse($rows);
$fmt = isset($_GET['format']) ? $_GET['format'] : 'html';

if (isset($_GET['shot'])) {
    $f = basename((string)$_GET['shot']);
    $p = $DATA . '/shots/' . $f;
    if (!preg_match('/^FB-[0-9A-Z-]+\.(jpg|png)$/', $f) || !file_exists($p)) { http_response_code(404); exit; }
    header('Content-Type: ' . (substr($f, -4) === '.png' ? 'image/png' : 'image/jpeg'));
    readfile($p); exit;
}
if ($fmt === 'json') { header('Content-Type: application/json; charset=utf-8'); echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); exit; }
if ($fmt === 'csv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="feedback-inventory-saji.csv"');
    echo "\xEF\xBB\xBF"; $o = fopen('php://output', 'w');
    fputcsv($o, array('ID', 'Waktu', 'Nama', 'Peran', 'Halaman', 'Jenis', 'Tingkat', 'ID skenario', 'Yang dilakukan', 'Yang terjadi', 'Yang diharapkan', 'Usulan', 'Perangkat', 'Peramban', 'Ukuran layar', 'Versi', 'Dialog terbuka', 'Aktivitas terakhir', 'Foto', 'Status'));
    foreach ($rows as $r) { $c = isset($r['ctx']) ? $r['ctx'] : array();
        fputcsv($o, array($r['id'], $r['waktu'], $r['nama'], @$c['peran'], @$c['halaman'], $r['jenis'], $r['tingkat'], $r['skenario'], $r['lakukan'], $r['terjadi'], $r['harapan'], $r['usulan'], @$c['perangkat'], @$c['peramban'], @$c['ukuran'], @$c['versi'], @$c['dialog'], @$c['aktivitas'], $r['foto'], $r['status'])); }
    exit;
}
header('Content-Type: text/html; charset=utf-8');
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$k = urlencode($given);
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Masukan penguji</title>
<style>
body{font-family:Outfit,system-ui,sans-serif;font-weight:300;color:#444;margin:0;padding:20px;background:#fff}
h1{font-family:"Shippori Mincho",Georgia,serif;font-weight:500;color:#792116;margin:0 0 4px}
.top{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:10px 0 16px}
a.btn,select,input{border:1px solid #d9d6d4;border-radius:9px;padding:6px 12px;font:inherit;color:#444;background:#fff;text-decoration:none}
a.btn{color:#A94438}
table{border-collapse:collapse;width:100%;font-size:13.5px}
th{background:#F8ECEA;color:#792116;text-align:left;font-weight:400;font-size:12px;text-transform:uppercase;letter-spacing:.06em;padding:8px 10px;position:sticky;top:0}
td{border-top:1px solid #e5e2e0;padding:8px 10px;vertical-align:top}
.t{color:#792116}.m{color:#6f6f6f;font-size:12.5px}
.b{display:inline-block;border-radius:999px;padding:1px 9px;background:#FCC56866;font-size:12px}
.hid{display:none}
</style></head><body>
<h1>Masukan penguji</h1>
<div class="m"><?= count($rows) ?> masukan. Data tersimpan di luar folder web.</div>
<div class="top"><input id="q" placeholder="Cari kata..." oninput="f()"><select id="fj" onchange="f()"><option value="">Semua jenis</option><?php foreach (array('Kesalahan (bug)', 'Saran perbaikan', 'Pertanyaan', 'Kebingungan / istilah', 'Fitur belum ada', 'Pujian') as $j) echo '<option>' . h($j) . '</option>'; ?></select><select id="fp" onchange="f()"><option value="">Semua peran</option><?php $ps = array(); foreach ($rows as $r) { $p = isset($r['ctx']['peran']) ? $r['ctx']['peran'] : ''; if ($p && !in_array($p, $ps)) { $ps[] = $p; echo '<option>' . h($p) . '</option>'; } } ?></select>
<a class="btn" href="?k=<?= $k ?>&format=csv">Unduh CSV</a><a class="btn" href="?k=<?= $k ?>&format=json">JSON</a></div>
<table id="tb"><thead><tr><th>Waktu</th><th>Penguji</th><th>Halaman</th><th>Jenis</th><th>Isi</th><th>Konteks</th></tr></thead><tbody>
<?php foreach ($rows as $r): $c = isset($r['ctx']) ? $r['ctx'] : array(); ?>
<tr data-j="<?= h($r['jenis']) ?>" data-p="<?= h(@$c['peran']) ?>"><td><?= h(substr($r['waktu'], 0, 16)) ?><div class="m"><?= h($r['id']) ?></div></td>
<td><?= h($r['nama']) ?><div class="m"><?= h(@$c['peran']) ?></div></td>
<td><?= h(@$c['halaman']) ?><?php if (!empty($r['skenario'])) echo '<div class="m">Skenario ' . h($r['skenario']) . '</div>'; ?></td>
<td><span class="b"><?= h($r['jenis']) ?></span><div class="m"><?= h($r['tingkat']) ?></div></td>
<td><div><?= nl2br(h($r['terjadi'])) ?></div><?php foreach (array('lakukan' => 'Dilakukan', 'harapan' => 'Diharapkan', 'usulan' => 'Usulan') as $kk => $lb) if (!empty($r[$kk])) echo '<div class="m"><span class="t">' . $lb . ':</span> ' . nl2br(h($r[$kk])) . '</div>'; ?>
<?php if (!empty($r['foto'])) echo '<div><a class="btn" target="_blank" href="?k=' . $k . '&shot=' . urlencode($r['foto']) . '">Tangkapan layar</a></div>'; ?></td>
<td class="m"><?= h(@$c['perangkat']) ?> · <?= h(@$c['peramban']) ?> · <?= h(@$c['ukuran']) ?> · <?= h(@$c['versi']) ?><?php if (!empty($c['dialog'])) echo '<br>Dialog: ' . h($c['dialog']); if (!empty($c['aktivitas'])) echo '<br>Aktivitas: ' . h($c['aktivitas']); ?></td></tr>
<?php endforeach; ?></tbody></table>
<script>
function f(){var q=document.getElementById("q").value.toLowerCase(),j=document.getElementById("fj").value,p=document.getElementById("fp").value;
document.querySelectorAll("#tb tbody tr").forEach(function(r){var ok=(!q||r.textContent.toLowerCase().indexOf(q)>-1)&&(!j||r.dataset.j===j)&&(!p||r.dataset.p===p);r.classList.toggle("hid",!ok);});}
</script></body></html>
