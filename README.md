# inv.saji.id

Mockup Inventory Saji untuk uji coba jarak jauh di https://inv.saji.id/sandbox/. Hanya data contoh, bukan data Saji.

Isi: `index.html` (mockup satu file), `.htaccess` (noindex, UTF-8), `.cpanel.yml` (salin ke `public_html/inv/sandbox/`), `publish.sh` (salin, commit, push).

Update: `bash publish.sh`, lalu di cPanel Git Version Control > Manage > Update from Remote > Deploy HEAD Commit.

## Infrastruktur (tahap 1)

`api/` adalah backend PHP + MySQL (login per orang, peran, Item, Supplier, jejak perubahan). Disalin ke `public_html/inv/api/` oleh `.cpanel.yml`.

- Penyiapan sekali: buka `/api/setup.php`, isi database MySQL dan akun Superadmin pertama. Konfigurasi ditulis ke `~/inv-config/config.php` (di luar folder web).
- Cadangan harian: cron `php /home/sajiid/public_html/inv/api/backup.php`, hasil di `~/backup-inventory/` (30 file terakhir).
- Jalur: `auth/login|logout|me|password`, `users`, `items`, `suppliers`, `items/import`, `audit`, `health`. Tulis/ubah wajib header `X-Saji: 1`; ubah data wajib kirim `ver` (409 bila sudah diubah orang lain).
