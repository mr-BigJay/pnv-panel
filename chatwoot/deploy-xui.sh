#!/usr/bin/env bash
set -euo pipefail

ROOT="${1:-/var/www/html}"
COMMIT="${DEPLOY_COMMIT:?Set DEPLOY_COMMIT to the git SHA}"

echo "Deploying XUI automation files to $ROOT (commit $COMMIT)"

curl -fL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}/xui_lib.php" -o "$ROOT/xui_lib.php"
curl -fL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}/telegram_xui.php" -o "$ROOT/telegram_xui.php"
curl -fL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}/telegram_lib.php" -o "$ROOT/telegram_lib.php"
curl -fL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}/telegram_poll.php" -o "$ROOT/telegram_poll.php"
curl -fL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}/buy.php" -o "$ROOT/buy.php"
curl -fL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}/renew.php" -o "$ROOT/renew.php"
curl -fL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}/admin/xui-servers.php" -o "$ROOT/admin/xui-servers.php"
curl -fL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}/admin/payments.php" -o "$ROOT/admin/payments.php"
curl -fL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}/admin/renews.php" -o "$ROOT/admin/renews.php"
curl -fL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}/admin/renews2.php" -o "$ROOT/admin/renews2.php"

python3 - "$ROOT" <<'PY'
from pathlib import Path
import sys
root = Path(sys.argv[1])
index = root / 'admin' / 'index.php'
if index.exists():
    text = index.read_text(encoding='utf-8', errors='ignore')
    if 'xui-servers.php' not in text:
        needle = 'مدیریت دانلودها'
        marker = "downloads.php"
        # Insert after downloads menu entry block if present
        insert = '''
<a href="<?php echo htmlspecialchars(function_exists('pnvAdminUrl') ? pnvAdminUrl('xui-servers.php') : 'xui-servers.php', ENT_QUOTES, 'UTF-8'); ?>">

سرورهای 3x-ui

</a>

'''
        pos = text.find(marker)
        if pos != -1:
            # find closing </a> after downloads link
            close = text.find('</a>', pos)
            if close != -1:
                text = text[:close+4] + insert + text[close+4:]
                index.write_text(text, encoding='utf-8')
                print('Added 3x-ui menu link to admin/index.php')
            else:
                print('Could not find downloads </a>; add menu link manually')
        else:
            print('downloads.php not found in admin/index.php; add menu link manually')
    else:
        print('admin/index.php already has xui-servers link')
PY

if [[ -d "$ROOT/bigjay_controller" ]]; then
  cat > "$ROOT/bigjay_controller/xui-servers.php" <<'PHP'
<?php

require dirname(__DIR__) . '/admin/' . basename(__FILE__);
PHP
fi

if [[ ! -f "$ROOT/db/xui_servers.json" ]]; then
  curl -fL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}/db/xui_servers.example.json" -o "$ROOT/db/xui_servers.json"
  echo "Created db/xui_servers.json from example — paste API tokens and enable in admin."
fi

chmod 640 "$ROOT/db/xui_servers.json" 2>/dev/null || true
chown www-data:www-data "$ROOT/db/xui_servers.json" 2>/dev/null || true

echo "Files deployed."
echo "Next: open /bigjay_controller/xui-servers.php , paste tokens, enable, test each server."
echo "Then restart telegram-bot if needed: systemctl restart telegram-bot"
