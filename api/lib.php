<?php
// Fondasi API Inventory Saji: konfigurasi, database, sesi, hak akses, audit.
function saji_home() { $d = __DIR__; while (basename($d) !== 'public_html' && dirname($d) !== $d) $d = dirname($d); return dirname($d); }
function cfg_path() { return saji_home() . '/inv-config/config.php'; }
function cfg() {
    static $c = null;
    if ($c) return $c;
    $p = cfg_path();
    return $c = (is_file($p) ? require $p : false);
}
function out($code, $data) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail($code, $msg, $extra = array()) { out($code, array_merge(array('ok' => false, 'error' => $msg), $extra)); }

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = cfg();
    if (!$c) fail(503, 'Server belum disiapkan. Jalankan setup dulu.');
    if (isset($c['sqlite'])) $pdo = new PDO('sqlite:' . $c['sqlite']);
    else $pdo = new PDO('mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4', $c['user'], $c['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if (isset($c['sqlite'])) $pdo->exec('PRAGMA foreign_keys=ON');
    return $pdo;
}
function is_sqlite() { return db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'; }
function q($sql, $args = array()) { $s = db()->prepare($sql); $s->execute($args); return $s; }
function now() { return gmdate('Y-m-d H:i:s'); }

const ROLES = array(
    'cfo' => 'CFO', 'purchasing' => 'Staff Purchasing', 'manager' => 'Manager',
    'head_kitchen' => 'Head Kitchen', 'head_bar' => 'Head Bar', 'head_exp' => 'Head Experience',
    'staff_kitchen' => 'Staff Kitchen', 'staff_wh' => 'Staff Warehouse', 'auditor' => 'Auditor (staf Exp)',
);
const HEAD_DEPT = array('head_kitchen' => 'Kitchen', 'head_bar' => 'Bar', 'head_exp' => 'Experience');
const WRITE_MASTER = array('cfo', 'purchasing', 'manager');

// ---- sesi ----
const COOKIE = 'saji_s';
function client_ip() { return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''; }
function start_session($uid) {
    $tok = bin2hex(random_bytes(32));
    q('INSERT INTO sessions(tok,user_id,created_at,expires_at) VALUES(?,?,?,?)',
        array(hash('sha256', $tok), $uid, now(), gmdate('Y-m-d H:i:s', time() + 14 * 86400)));
    setcookie(COOKIE, $tok, array('expires' => time() + 14 * 86400, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true, 'samesite' => 'Lax'));
}
function end_session() {
    if (!empty($_COOKIE[COOKIE])) q('DELETE FROM sessions WHERE tok=?', array(hash('sha256', $_COOKIE[COOKIE])));
    setcookie(COOKIE, '', array('expires' => time() - 3600, 'path' => '/'));
}
function me($required = true) {
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_COOKIE[COOKIE])) {
            $r = q('SELECT u.* FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.tok=? AND s.expires_at>? AND u.active=1',
                array(hash('sha256', $_COOKIE[COOKIE]), now()))->fetch();
            if ($r) $u = $r;
        }
    }
    if ($required && !$u) fail(401, 'Silakan masuk dulu.');
    return $u;
}
function pub_user($u) {
    return array('id' => (int)$u['id'], 'email' => $u['email'], 'name' => $u['name'], 'role' => $u['role'],
        'roleLabel' => ROLES[$u['role']], 'active' => (int)$u['active'], 'mustChange' => (int)$u['must_change'],
        'lastLogin' => $u['last_login']);
}
function need_csrf() {
    if (empty($_SERVER['HTTP_X_SAJI']) || $_SERVER['HTTP_X_SAJI'] !== '1') fail(403, 'Permintaan ditolak.');
}
function need_role($roles) { $u = me(); if (!in_array($u['role'], $roles, true)) fail(403, 'Peran Anda tidak boleh melakukan ini.'); return $u; }
function check_password($p) {
    if (strlen($p) < 10) return 'Password minimal 10 karakter.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/[0-9]/', $p)) return 'Password harus berisi huruf dan angka.';
    return '';
}

// ---- audit & urutan perubahan ----
function audit($u, $action, $entity, $id, $summary) {
    q('INSERT INTO audit(ts,user_id,user_name,action,entity,entity_id,summary) VALUES(?,?,?,?,?,?,?)',
        array(now(), $u ? $u['id'] : null, $u ? $u['name'] : '-', $action, $entity, (string)$id, mb_substr($summary, 0, 250)));
}
function next_seq() {
    q("UPDATE meta SET v=v+1 WHERE k='seq'");
    return (int)q("SELECT v FROM meta WHERE k='seq'")->fetch()['v'];
}
