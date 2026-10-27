<?php
// API Inventory Saji. Semua alamat: /api/<jalur>. Kirim JSON; tulis/ubah wajib header X-Saji: 1.
require __DIR__ . '/lib.php';
$method = $_SERVER['REQUEST_METHOD'];
$path = trim(isset($_GET['_p']) ? $_GET['_p'] : '', '/');
$seg = $path === '' ? array() : explode('/', $path);
$body = array();
if ($method !== 'GET') {
    need_csrf();
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 6000000) fail(413, 'Data terlalu besar.');
    if ($raw !== '') { $body = json_decode($raw, true); if (!is_array($body)) fail(400, 'Permintaan tidak valid.'); }
}
$a = isset($seg[0]) ? $seg[0] : '';
$b = isset($seg[1]) ? $seg[1] : '';

if ($a === 'health') out(200, array('ok' => true, 'time' => now(), 'seq' => (int)q("SELECT v FROM meta WHERE k='seq'")->fetch()['v']));

// ---------- masuk / keluar ----------
if ($a === 'auth') {
    if ($b === 'login' && $method === 'POST') {
        $email = strtolower(trim((string)(isset($body['email']) ? $body['email'] : '')));
        $pass = (string)(isset($body['password']) ? $body['password'] : '');
        $since = gmdate('Y-m-d H:i:s', time() - 900);
        $n = (int)q('SELECT COUNT(*) c FROM login_attempts WHERE ts>? AND (ip=? OR email=?)', array($since, client_ip(), $email))->fetch()['c'];
        if ($n >= 8) fail(429, 'Terlalu banyak percobaan. Coba lagi 15 menit lagi.');
        $u = q('SELECT * FROM users WHERE email=? AND active=1', array($email))->fetch();
        if (!$u || !password_verify($pass, $u['pass_hash'])) {
            q('INSERT INTO login_attempts(ip,email,ts) VALUES(?,?,?)', array(client_ip(), $email, now()));
            if (!$u) password_verify($pass, '$2y$10$abcdefghijklmnopqrstuuMqWqkq1Q6tq3m6bYy7nWl2r5b6kXy0e');
            fail(401, 'Email atau password salah.');
        }
        q('DELETE FROM login_attempts WHERE email=?', array($email));
        q('DELETE FROM sessions WHERE expires_at<?', array(now()));
        q('UPDATE users SET last_login=? WHERE id=?', array(now(), $u['id']));
        start_session($u['id']);
        audit($u, 'masuk', 'user', $u['id'], 'Masuk');
        out(200, array('ok' => true, 'user' => pub_user($u)));
    }
    if ($b === 'logout' && $method === 'POST') { end_session(); out(200, array('ok' => true)); }
    if ($b === 'me' && $method === 'GET') { $u = me(false); out(200, array('ok' => true, 'user' => $u ? pub_user($u) : null, 'roles' => ROLES)); }
    if ($b === 'password' && $method === 'POST') {
        $u = me();
        if (!password_verify((string)(isset($body['old']) ? $body['old'] : ''), $u['pass_hash'])) fail(403, 'Password lama salah.');
        $new = (string)(isset($body['new']) ? $body['new'] : '');
        if ($m = check_password($new)) fail(422, $m);
        q('UPDATE users SET pass_hash=?, must_change=0 WHERE id=?', array(password_hash($new, PASSWORD_DEFAULT), $u['id']));
        q('DELETE FROM sessions WHERE user_id=? AND tok<>?', array($u['id'], hash('sha256', $_COOKIE[COOKIE])));
        audit($u, 'ubah-password', 'user', $u['id'], 'Ganti password');
        out(200, array('ok' => true));
    }
    fail(404, 'Tidak ditemukan.');
}

// ---------- pengguna (hanya Superadmin) ----------
if ($a === 'users') {
    $u = need_role(array('superadmin'));
    if ($method === 'GET' && $b === '') {
        out(200, array('ok' => true, 'users' => array_map('pub_user', q('SELECT * FROM users ORDER BY name')->fetchAll())));
    }
    if ($method === 'POST' && $b === '') {
        $email = strtolower(trim((string)(isset($body['email']) ? $body['email'] : '')));
        $name = trim((string)(isset($body['name']) ? $body['name'] : ''));
        $role = (string)(isset($body['role']) ? $body['role'] : '');
        $pass = (string)(isset($body['password']) ? $body['password'] : '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Email tidak valid.');
        if ($name === '') fail(422, 'Nama wajib diisi.');
        if (!isset(ROLES[$role])) fail(422, 'Peran tidak dikenal.');
        if ($m = check_password($pass)) fail(422, $m);
        if (q('SELECT id FROM users WHERE email=?', array($email))->fetch()) fail(409, 'Email sudah terdaftar.');
        q('INSERT INTO users(email,name,role,pass_hash,active,must_change,created_at) VALUES(?,?,?,?,1,1,?)',
            array($email, $name, $role, password_hash($pass, PASSWORD_DEFAULT), now()));
        $id = (int)db()->lastInsertId();
        audit($u, 'tambah', 'user', $id, "Akun $name ($role)");
        out(200, array('ok' => true, 'id' => $id));
    }
    if ($method === 'PUT' && ctype_digit($b)) {
        $t = q('SELECT * FROM users WHERE id=?', array($b))->fetch();
        if (!$t) fail(404, 'Akun tidak ditemukan.');
        $name = isset($body['name']) ? trim((string)$body['name']) : $t['name'];
        $role = isset($body['role']) ? (string)$body['role'] : $t['role'];
        $act = isset($body['active']) ? ($body['active'] ? 1 : 0) : (int)$t['active'];
        if (!isset(ROLES[$role]) || $name === '') fail(422, 'Data tidak valid.');
        if ((int)$t['id'] === (int)$u['id'] && ($role !== 'superadmin' || !$act)) fail(422, 'Anda tidak bisa menurunkan atau menonaktifkan akun sendiri.');
        q('UPDATE users SET name=?, role=?, active=? WHERE id=?', array($name, $role, $act, $b));
        if (!empty($body['password'])) {
            if ($m = check_password((string)$body['password'])) fail(422, $m);
            q('UPDATE users SET pass_hash=?, must_change=1 WHERE id=?', array(password_hash((string)$body['password'], PASSWORD_DEFAULT), $b));
        }
        if (!$act || !empty($body['password'])) q('DELETE FROM sessions WHERE user_id=?', array($b));
        audit($u, 'ubah', 'user', $b, "Akun {$t['email']}: $role" . ($act ? '' : ' (nonaktif)') . (!empty($body['password']) ? ' (reset password)' : ''));
        out(200, array('ok' => true));
    }
    fail(404, 'Tidak ditemukan.');
}

// ---------- data induk: item & supplier ----------
$ENT = array('items' => array('items', 'item'), 'suppliers' => array('suppliers', 'supplier'));
if (isset($ENT[$a])) {
    $tbl = $ENT[$a][0]; $label = $ENT[$a][1];
    $u = me();
    $row = function ($r) { $d = json_decode($r['data'], true); if (!is_array($d)) $d = array(); $d['id'] = (int)$r['id']; $d['ver'] = (int)$r['ver'];
        $d['seq'] = (int)$r['seq']; $d['updatedAt'] = $r['updated_at']; $d['updatedBy'] = $r['updated_by']; return $d; };
    $can = function ($data) use ($u, $a) {
        if (in_array($u['role'], WRITE_MASTER, true)) return true;
        if ($a === 'items' && isset(HEAD_DEPT[$u['role']])) {
            $depts = isset($data['depts']) && is_array($data['depts']) ? $data['depts'] : array();
            return in_array(HEAD_DEPT[$u['role']], $depts, true);
        }
        return false;
    };
    if ($method === 'GET') {
        $since = isset($_GET['since']) ? (int)$_GET['since'] : 0;
        $rows = q("SELECT * FROM $tbl WHERE seq>? ORDER BY id", array($since))->fetchAll();
        $seq = (int)q("SELECT v FROM meta WHERE k='seq'")->fetch()['v'];
        out(200, array('ok' => true, 'seq' => $seq, 'full' => $since === 0, 'rows' => array_map($row, $rows)));
    }
    $clean = function ($d) { unset($d['id'], $d['ver'], $d['seq'], $d['updatedAt'], $d['updatedBy']); return $d; };
    if ($method === 'POST' && $b === '') {            // tambah baru
        $d = isset($body['data']) && is_array($body['data']) ? $clean($body['data']) : null;
        if (!$d || trim((string)(isset($d['name']) ? $d['name'] : '')) === '') fail(422, 'Nama wajib diisi.');
        if (!in_array($u['role'], WRITE_MASTER, true)) fail(403, 'Peran Anda tidak boleh menambah data induk.');
        db()->beginTransaction();
        $id = (int)q("SELECT COALESCE(MAX(id),0)+1 n FROM $tbl")->fetch()['n'];
        $seq = next_seq();
        q("INSERT INTO $tbl(id,name,status,data,ver,seq,updated_at,updated_by) VALUES(?,?,?,?,1,?,?,?)",
            array($id, $d['name'], isset($d['status']) ? $d['status'] : 'Aktif', json_encode($d, JSON_UNESCAPED_UNICODE), $seq, now(), $u['name']));
        audit($u, 'tambah', $label, $id, $d['name']);
        db()->commit();
        out(200, array('ok' => true, 'id' => $id, 'ver' => 1, 'seq' => $seq));
    }
    if ($method === 'PUT' && ctype_digit($b)) {       // ubah, dengan nomor versi
        $d = isset($body['data']) && is_array($body['data']) ? $clean($body['data']) : null;
        $ver = isset($body['ver']) ? (int)$body['ver'] : -1;
        if (!$d || trim((string)(isset($d['name']) ? $d['name'] : '')) === '') fail(422, 'Nama wajib diisi.');
        db()->beginTransaction();
        $cur = q("SELECT * FROM $tbl WHERE id=?", array($b))->fetch();
        if (!$cur) { db()->rollBack(); fail(404, 'Data tidak ditemukan.'); }
        $old = json_decode($cur['data'], true);
        if (!$can($old) || !$can($d)) { db()->rollBack(); fail(403, 'Peran Anda tidak boleh mengubah data ini.'); }
        if ((int)$cur['ver'] !== $ver) { db()->rollBack(); fail(409, 'Data sudah diubah orang lain. Muat ulang dulu.', array('current' => $row($cur))); }
        $seq = next_seq();
        q("UPDATE $tbl SET name=?, status=?, data=?, ver=ver+1, seq=?, updated_at=?, updated_by=? WHERE id=?",
            array($d['name'], isset($d['status']) ? $d['status'] : 'Aktif', json_encode($d, JSON_UNESCAPED_UNICODE), $seq, now(), $u['name'], $b));
        $ch = array();
        foreach ($d as $k => $v) if (!isset($old[$k]) || $old[$k] !== $v) $ch[] = $k;
        audit($u, 'ubah', $label, $b, $d['name'] . ' [' . implode(',', array_slice($ch, 0, 8)) . ']');
        db()->commit();
        out(200, array('ok' => true, 'ver' => $ver + 1, 'seq' => $seq));
    }
    if ($method === 'POST' && $b === 'import') {      // impor awal / massal, hanya Superadmin
        need_role(array('superadmin'));
        $list = isset($body['rows']) && is_array($body['rows']) ? $body['rows'] : array();
        if (!$list || count($list) > 3000) fail(422, 'Daftar kosong atau terlalu banyak.');
        $replace = !empty($body['replace']);
        db()->beginTransaction();
        if ($replace) q("DELETE FROM $tbl");
        else if ((int)q("SELECT COUNT(*) c FROM $tbl")->fetch()['c'] > 0) { db()->rollBack(); fail(409, 'Tabel sudah berisi. Kirim replace:true bila memang ingin menimpa.'); }
        $seq = next_seq(); $n = 0;
        foreach ($list as $d) {
            if (!is_array($d) || empty($d['id']) || trim((string)(isset($d['name']) ? $d['name'] : '')) === '') continue;
            $id = (int)$d['id']; $d = $clean($d);
            q("INSERT INTO $tbl(id,name,status,data,ver,seq,updated_at,updated_by) VALUES(?,?,?,?,1,?,?,?)",
                array($id, $d['name'], isset($d['status']) ? $d['status'] : 'Aktif', json_encode($d, JSON_UNESCAPED_UNICODE), $seq, now(), $u['name']));
            $n++;
        }
        audit($u, 'impor', $label, '-', "$n data" . ($replace ? ' (menimpa)' : ''));
        db()->commit();
        out(200, array('ok' => true, 'imported' => $n, 'seq' => $seq));
    }
    fail(404, 'Tidak ditemukan.');
}

// ---------- jejak perubahan ----------
if ($a === 'audit' && $method === 'GET') {
    need_role(array('superadmin', 'cfo', 'manager'));
    $lim = min(500, max(1, isset($_GET['limit']) ? (int)$_GET['limit'] : 100));
    out(200, array('ok' => true, 'rows' => q("SELECT * FROM audit ORDER BY id DESC LIMIT $lim")->fetchAll()));
}
fail(404, 'Tidak ditemukan.');
