#!/usr/bin/env python3
"""No writes to live data; simulate selective buy expiry rollout on mock text."""
import importlib.util
from pathlib import Path
import subprocess
import tempfile

root = Path(__file__).resolve().parents[1]
target = root / "scripts/deploy-buy-expiry-safe.py"
spec = importlib.util.spec_from_file_location("deploy_buy_expiry", target)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
base = module.checked_source(root)
source = (root / "xui_lib.php").read_text(encoding="utf-8")
assert "expiryTime' => 0" in module.function_block(base, "xuiCreateClient")
result = module.patch(base, base, source)
assert result != base
assert module.patch(result, base, source) == result
assert module.function_block(result, "xuiProvisionBuy") == module.function_block(source, "xuiProvisionBuy")
assert module.function_block(result, "xuiCreateClient") == module.function_block(source, "xuiCreateClient")
assert module.function_block(result, "xuiPlanDurationMeta") == module.function_block(source, "xuiPlanDurationMeta")
assert module.function_block(result, "xuiParsePlanGb") == module.function_block(source, "xuiParsePlanGb")
assert "function xuiProvisionRenew(" in result
assert result.count("function xuiNormalizePlanDigits(") == 1
assert result.count("function xuiResolveNewClientPlanDuration(") == 1
assert "xuiCreateClient($server, $email, $gb, '', intval($duration['days']))" in result
with tempfile.TemporaryDirectory() as td:
    generated = Path(td) / "xui_lib.php"
    generated.write_text(result, encoding="utf-8")
    subprocess.run(["php", "-l", str(generated)], check=True, capture_output=True)
    custom_header = "// custom production extension not overwritten\n"
    target_live = custom_header + base
    modified = module.patch(target_live, base, source)
    assert modified.startswith(custom_header)
    assert custom_header in modified
    broken = base.replace("'expiryTime' => 0,", "'expiryTime' => 123,")
    try:
        module.patch(broken, base, source)
    except RuntimeError as e:
        assert "xuiCreateClient" in str(e)
    else:
        raise AssertionError("unsafe customized client constructor was overwritten")
print("PASS selective deployment preserves unrelated production customizations")
print("PASS idempotent re-run and PHP lint")
print("PASS conflicting customized creator fails closed")
