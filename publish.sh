#!/usr/bin/env bash
# Jalankan di laptop: bash publish.sh
# Menyalin mockup terbaru ke index.html, membuat halaman LIVE, commit, lalu push. Setelah itu di cPanel: Git Version Control > Manage > Update from Remote > Deploy HEAD Commit.
set -euo pipefail
SRCDIR="$HOME/Saji Digital Ecosystem/inventory-saji/sandbox"
SRC="${1:-$SRCDIR/index.html}"
cd "$(dirname "$0")"
[ -f "$SRC" ] || { echo "Sumber tidak ditemukan: $SRC"; exit 1; }
cp "$SRC" index.html
for f in feedback.php feedback-admin.php sync.php; do [ -f "$SRCDIR/$f" ] && cp "$SRCDIR/$f" "$f"; done
python3 build-live.py
# Data awal untuk impor Superadmin (disalin cPanel ke ~/inv-config, bukan folder web)
if command -v node >/dev/null; then node tools/export_seed.js index.html seed/items.json; fi
git add api seed tools index.html feedback.php feedback-admin.php sync.php live build-live.py .cpanel.yml publish.sh
git diff --cached --quiet && { echo "Tidak ada perubahan."; exit 0; }
git commit -m "Update mockup $(date +%F)"
git push
