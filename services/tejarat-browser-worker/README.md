# Tejarat browser worker (read-only)

Collects **incoming** statement rows and pushes them to PNV ingest. See `/docs/tejarat/` for feasibility, security, and Ubuntu setup.

Quick test:

```bash
export PNV_TEJARAT_INGEST_URL=https://panel.example/tejarat-worker-ingest.php
export PNV_TEJARAT_INGEST_SECRET=...
export TEJARAT_COLLECT_MODE=fixture
export TEJARAT_FIXTURE_JSON=../../tests/fixtures/tejarat_worker_fixture.json
export TEJARAT_RUN_ONCE=1
python -m worker.main
```

Parsing tests (no Playwright):

```bash
cd services/tejarat-browser-worker
PYTHONPATH=. python -m unittest discover -s tests -p 'test_*.py'
```

Manual bank login (save session, no OTP automation):

```bash
TEJARAT_HEADLESS=false DISPLAY=:99 python -m worker.manual_login
```

Isolated VPS dry run: `/docs/tejarat/ISOLATED_VPS_TEST.md`
