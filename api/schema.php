<?php
// Membuat tabel (aman dijalankan ulang). Dipakai setup.php.
function create_schema() {
    $sq = is_sqlite();
    $pk = $sq ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $big = $sq ? 'TEXT' : 'LONGTEXT';
    $ci = $sq ? '' : ' COLLATE utf8mb4_unicode_ci';
    $eng = $sq ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $t = array(
        "CREATE TABLE IF NOT EXISTS meta(k VARCHAR(40) PRIMARY KEY, v BIGINT NOT NULL)$eng",
        "CREATE TABLE IF NOT EXISTS users(id $pk, email VARCHAR(190) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, role VARCHAR(30) NOT NULL,
            pass_hash VARCHAR(255) NOT NULL, active TINYINT NOT NULL DEFAULT 1, must_change TINYINT NOT NULL DEFAULT 0,
            created_at VARCHAR(19) NOT NULL, last_login VARCHAR(19) NULL)$eng",
        "CREATE TABLE IF NOT EXISTS sessions(tok CHAR(64) PRIMARY KEY, user_id INT NOT NULL, created_at VARCHAR(19) NOT NULL, expires_at VARCHAR(19) NOT NULL)$eng",
        "CREATE TABLE IF NOT EXISTS login_attempts(id $pk, ip VARCHAR(64) NOT NULL, email VARCHAR(190) NOT NULL, ts VARCHAR(19) NOT NULL)$eng",
        "CREATE TABLE IF NOT EXISTS items(id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Aktif',
            data $big NOT NULL, ver INT NOT NULL DEFAULT 1, seq BIGINT NOT NULL DEFAULT 0, updated_at VARCHAR(19) NOT NULL, updated_by VARCHAR(120) NULL)$eng",
        "CREATE TABLE IF NOT EXISTS suppliers(id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Aktif',
            data $big NOT NULL, ver INT NOT NULL DEFAULT 1, seq BIGINT NOT NULL DEFAULT 0, updated_at VARCHAR(19) NOT NULL, updated_by VARCHAR(120) NULL)$eng",
        "CREATE TABLE IF NOT EXISTS audit(id $pk, ts VARCHAR(19) NOT NULL, user_id INT NULL, user_name VARCHAR(120) NOT NULL, action VARCHAR(30) NOT NULL,
            entity VARCHAR(20) NOT NULL, entity_id VARCHAR(40) NOT NULL, summary VARCHAR(255) NOT NULL)$eng",
    );
    foreach ($t as $sql) db()->exec($sql);
    foreach (array('CREATE INDEX idx_items_seq ON items(seq)', 'CREATE INDEX idx_sup_seq ON suppliers(seq)', 'CREATE INDEX idx_audit_ts ON audit(ts)',
        'CREATE INDEX idx_la ON login_attempts(ip,ts)') as $ix) { try { db()->exec($ix); } catch (Exception $e) {} }
    $has = q("SELECT COUNT(*) c FROM meta WHERE k='seq'")->fetch();
    if (!(int)$has['c']) q("INSERT INTO meta(k,v) VALUES('seq',0)");
}
