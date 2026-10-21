#!/usr/bin/env python3
"""Production-compatible bank panel deploy preflight: no writes, no SSH/bank calls."""
from pathlib import Path
import os
import subprocess
import sys
import tempfile

repo = Path(__file__).resolve().parent.parent
script = repo / "scripts/deploy-bank-connection-safe.py"
menu = '<a href="<?php echo htmlspecialchars(pnvAdminUrl(\'bale.php\'), ENT_QUOTES, \'UTF-8\'); ?>">'
cards = "<?php if($page=='cards'){ ?>"

with tempfile.TemporaryDirectory(prefix="pnv-bank-deploy-test-") as td:
    root = Path(td)
    (root / "admin").mkdir()
    target = root / "admin/index.php"
    original = ("<?php // CUSTOM LIVE SENTINEL ?>\n" + menu + "\n" + cards + "\n<?php } ?>\n").encode()
    target.write_bytes(original)
    cmd = [sys.executable,str(script),"--source",str(repo),"--root",str(root),"--no-reload"]
    good = subprocess.run(cmd, text=True, capture_output=True)
    assert good.returncode == 0, good.stdout + good.stderr
    assert "SAFE DRY RUN PASS" in good.stdout, good.stdout
    assert target.read_bytes() == original
    assert not (root/"bank_connection_lib.php").exists()
    print("PASS deploy dry-run does not touch customized production")
    target.write_text("<?php // custom page without expected navigation ?>")
    bad = subprocess.run(cmd,text=True,capture_output=True)
    assert bad.returncode != 0
    assert "STOP custom navigation anchor differs" in bad.stderr
    assert not (root/"bank_connection_lib.php").exists()
    print("PASS unknown production structure rejected without mutations")
print("ALL BANK DEPLOY SAFETY TESTS PASSED")
