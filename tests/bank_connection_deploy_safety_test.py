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
# Existing deployments with the old Bank Connection link must migrate in place.
import runpy
patch = runpy.run_path(str(script))["patch_index"]
old_menu = """<a href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?page=bank-connection'), ENT_QUOTES, 'UTF-8'); ?>">
اتصال بانک
</a>

"""
old_page = """<?php if($page=='bank-connection'){ ?>
<?php pnvAdminInclude('bank-connection.php'); ?>
<?php } ?>

"""
legacy = "<?php // CUSTOM LIVE ?>\\n" + old_menu + old_page
patched = patch(legacy)
assert "index.php?page=banks" in patched
assert "اتصال بانک" in patched
assert "|| $page=='bank-connection'" in patched
assert patched.count("اتصال بانک") == 1
assert patch(patched) == patched
assert "CUSTOM LIVE" in patched
print("PASS legacy Bank Connection menu upgrades without losing custom code")

# PR43 already deployed: rename "بانک‌ها" without adding a duplicate.
banks_menu = """<a href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?page=banks'), ENT_QUOTES, 'UTF-8'); ?>">
بانک‌ها
</a>

"""
renamed = patch("<?php // CUSTOM PROD ?>\\n" + banks_menu + old_page)
assert "اتصال بانک" in renamed and "بانک‌ها" not in renamed
assert renamed.count("اتصال بانک") == 1
assert patch(renamed) == renamed
assert "CUSTOM PROD" in renamed
print("PASS existing Banks menu safely renamed in place")

print("ALL BANK DEPLOY SAFETY TESTS PASSED")
