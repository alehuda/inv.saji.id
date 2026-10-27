<?php
// Penyiapan sekali jalan: buka https://inv.saji.id/api/setup.php, isi data database dan akun CFO pertama.
// Setelah berhasil, file konfigurasi tersimpan di luar folder web dan halaman ini menolak dipakai lagi.
require __DIR__ . '/lib.php';
require __DIR__ . '/schema.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function page($body) {
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Setup Inventory Saji</title>
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:520px;margin:32px auto;padding:0 16px;color:#444}h1{color:#792116;font-size:22px}label{display:block;margin:12px 0 4px;font-weight:600}
input{width:100%;padding:10px;border:1px solid #bbb;border-radius:8px;font:inherit;box-sizing:border-box}button{margin-top:18px;padding:12px 20px;background:#A94438;color:#fff;border:0;border-radius:8px;font:inherit;cursor:pointer}
.e{background:#fde8e6;border:1px solid #A94438;padding:10px;border-radius:8px}.o{background:#e6f1e6;border:1px solid #325234;padding:10px;border-radius:8px}small{color:#777}</style><h1>Setup Inventory Saji</h1>' . $body;
    exit;
}
if (cfg()) page('<p class="o">Sudah disiapkan. Halaman ini tidak bisa dipakai lagi. Untuk menyiapkan ulang, hapus file <code>inv-config/config.php</code> di server.</p>');
$err = '';
$v = array('host' => 'localhost', 'name' => '', 'user' => '', 'ename' => '', 'email' => '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) if (isset($_POST[$k])) $v[$k] = trim($_POST[$k]);
    $pass = (string)(isset($_POST['pass']) ? $_POST['pass'] : '');
    $apass = (string)(isset($_POST['apass']) ? $_POST['apass'] : '');
    $v['host'] = 'localhost'; // hanya database di server yang sama
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $v['name']) || !preg_match('/^[A-Za-z0-9_]{1,32}$/', $v['user'])) $err = 'Nama database atau user tidak valid.';
    elseif (!filter_var(strtolower($v['email']), FILTER_VALIDATE_EMAIL) || $v['ename'] === '') $err = 'Nama dan email CFO wajib diisi dengan benar.';
    elseif ($m = check_password($apass)) $err = $m;
    else {
        try {
            $GLOBALS['__cfg'] = array('host' => $v['host'], 'name' => $v['name'], 'user' => $v['user'], 'pass' => $pass);
            $pdo = new PDO('mysql:host=localhost;dbname=' . $v['name'] . ';charset=utf8mb4', $v['user'], $pass);
        } catch (Exception $e) { $err = 'Tidak bisa terhubung ke database. Periksa nama database, user, dan password.'; }
    }
    if (!$err) {
        $dir = dirname(cfg_path());
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        $php = "<?php\nreturn " . var_export($GLOBALS['__cfg'], true) . ";\n";
        if (@file_put_contents(cfg_path(), $php) === false) $err = 'Tidak bisa menulis file konfigurasi di ' . h($dir);
        else {
            @chmod(cfg_path(), 0600);
            @file_put_contents($dir . '/.htaccess', "Require all denied\n");
            try {
                create_schema();
                q('INSERT INTO users(email,name,role,pass_hash,active,must_change,created_at) VALUES(?,?,?,?,1,0,?)',
                    array(strtolower($v['email']), $v['ename'], 'cfo', password_hash($apass, PASSWORD_DEFAULT), now()));
                audit(null, 'setup', 'sistem', '-', 'Penyiapan awal, akun CFO ' . $v['email']);
                page('<p class="o">Selesai. Database siap dan akun CFO dibuat. Halaman ini sekarang terkunci.</p><p><a href="/">Ke Inventory Saji</a></p>');
            } catch (Exception $e) { @unlink(cfg_path()); $err = 'Gagal membuat tabel: ' . h($e->getMessage()); }
        }
    }
}
page(($err ? '<p class="e">' . h($err) . '</p>' : '') . '<p><small>Buat dulu database dan user MySQL di cPanel (MySQL Databases), tambahkan user ke database dengan semua hak, lalu isi di sini. Password ini hanya ditulis ke file konfigurasi di luar folder web.</small></p>
<form method="post" autocomplete="off"><label>Nama database</label><input name="name" value="' . h($v['name']) . '" required>
<label>User database</label><input name="user" value="' . h($v['user']) . '" required>
<label>Password database</label><input name="pass" type="password" required>
<hr><label>Nama CFO (akun pertama)</label><input name="ename" value="' . h($v['ename']) . '" required>
<label>Email CFO</label><input name="email" type="email" value="' . h($v['email']) . '" required>
<label>Password CFO <small>(min. 10 karakter, huruf dan angka)</small></label><input name="apass" type="password" required>
<button>Siapkan</button></form>');
