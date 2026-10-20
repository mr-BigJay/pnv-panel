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
