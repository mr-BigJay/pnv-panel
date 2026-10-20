# probe با `row_count: 0`

اگر `statement_probe.json` عنوان **«همراه بانک تجارت»** و `rows=0` دارد:

1. **نشست منقضی** یا هنوز روی صفحه ورود/داشبورد هستید — `/account/log` لزوماً جدول گردش نیست.
2. دوباره با **root + VNC** وارد شوید و **دقیقاً روی صفحه گردش حساب** Enter بزنید:

```bash
sudo bash -lc '
  set -a; source /etc/pnv/tejarat-worker-test.env; set +a
  export PNV_WORKER_STORAGE=/opt/tejarat-browser-test/session
  export TEJARAT_HEADLESS=false DISPLAY=:91
  export TEJARAT_STATEMENT_URL=https://hb.tejaratbank.ir/account/log
  cd /opt/tejarat-browser-test/services/tejarat-browser-worker
  PYTHONPATH=$PWD /opt/tejarat-browser-test/venv/bin/python -m worker.manual_login
'
sudo cp -a /data/storage/playwright_storage.json /opt/tejarat-browser-test/session/ 2>/dev/null || true
sudo chown tejarat-browser:tejarat-browser /opt/tejarat-browser-test/session/playwright_storage.json
```

3. اگر بعد از لاگین باید منو بزنید، در env بگذارید:

```bash
TEJARAT_NAV_STEPS_JSON='[{"click":"text=گردش حساب","wait_ms":2000}]'
TEJARAT_STMT_WAIT_SELECTOR='table tbody tr'
```

4. دوباره probe:

```bash
sudo -u tejarat-browser bash /opt/tejarat-browser-test/services/tejarat-browser-worker/scripts/run-probe.sh
```

وقتی `row_count > 0` شد، `TEJARAT_STMT_SELECTORS_JSON` را از DevTools روی همان جدول تنظیم کنید.
