#!/usr/bin/env python3
"""Synthetic regression fixture for nonstandard/capped production coupon files."""
import importlib.util
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "coupon_custom_deploy", ROOT / "scripts" / "deploy-coupon-step1-custom-safe.py")
mod = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(mod)

BASE = "ef1f791f2115d6aa961747928f9a268d514a327f"


def oldfile(name):
    return subprocess.check_output(
        ["git", "show", f"{BASE}:{name}"], cwd=ROOT).decode("utf-8")


def current(name):
    return (ROOT / name).read_text(encoding="utf-8")


def assert_true(v, label):
    if not v:
        raise AssertionError(label)
    print("PASS", label)


# The production output in the bug report shows a different capped-discount
# label; keep that exact shape while applying a minimal duration fix.
base_coupon = oldfile("coupon_lib.php")
start = base_coupon.index("    function couponBuildPlanLabel($plan, $percent){")
end = base_coupon.index("    function couponCalculateForPlan(", start)
custom_label = """    function couponBuildPlanLabel($plan, $percent, $maxDiscountThousands = 0){

        $originalAmount = intval($plan['price'] ?? 0);
        $original = couponFormatPriceThousands($originalAmount);
        $calc = couponCalculateDiscountThousands($originalAmount, $percent, $maxDiscountThousands);
        $finalText = couponFormatPriceThousands($calc['final_amount']);

        if($percent <= 0){
            return ($plan['name'] ?? '') . ' - ' . $original;
        }

        $label = ($plan['name'] ?? '')
            . ' - '
            . $original
            . ' | تخفیف '
            . $percent
            . '% → '
            . $finalText;

        if(intval($maxDiscountThousands) > 0){
            $label .= ' (سقف ' . couponFormatPriceThousands($maxDiscountThousands) . ')';
        }

        return $label;

    }

"""
production_coupon = base_coupon[:start] + custom_label + base_coupon[end:]

new_coupon = mod.rewrite_coupon_lib(production_coupon, current("coupon_lib.php"))
assert_true("function couponPreviewForPlans(" in new_coupon, "preview helper injected")
assert_true("pnvFindPlanByValue($planValue, $plans)" in new_coupon,
            "duration-bearing checkout values accepted")
assert_true("$calc = couponCalculateDiscountThousands($originalAmount, $percent, $maxDiscountThousands);" in new_coupon,
            "production capped-discount math not overwritten")
assert_true("$label .= ' (سقف '" in new_coupon,
            "production capped-discount badge not overwritten")
assert_true("$baseLabel = pnvPlanOptionValue($plan);" in new_coupon,
            "discount labels preserve 30/90-day duration")

for name in ("buy.php", "renew.php"):
    old = oldfile(name)
    # Observed live buy.js uses no-store for the old single-plan request.
    if name == "buy.php":
        old = old.replace("{ credentials: 'same-origin' })",
                          "{ credentials: 'same-origin', cache: 'no-store' })", 1)
    changed = mod.rewrite_form(old, current(name), name)
    assert_true(changed.index('id="hasCouponCheck"') < changed.index('id="step2"'),
                name + " coupon moved to plan step")
    assert_true("mode=preview&code=" in changed, name + " all-plan preview requests")
    assert_true("couponNewPrice" in changed, name + " discount chips displayed")
    assert_true(changed.count("body.set('coupon_code', couponCodeInput.value.trim())")
                == old.count("body.set('coupon_code', couponCodeInput.value.trim())"),
                name + " payment coupon submission unchanged")
    assert_true(changed.count("function ensureInstantPay(") == old.count("function ensureInstantPay("),
                name + " payment state function kept unchanged")
    broken = old.replace("btn.querySelector('.planPrice').textContent = plan.price_text;",
                         "/* unknown production price code */")
    try:
        mod.rewrite_form(broken, current(name), name)
    except mod.Stop:
        print("PASS", name + " drift causes safe refusal")
    else:
        raise AssertionError(name + " drift should fail closed")

api = mod.rewrite_coupon_api(oldfile("coupon-api.php"), current("coupon-api.php"))
assert_true(api.index("couponPreviewForPlans(") < api.index("if($plan === '')"),
            "preview does not require selected plan")
css = mod.rewrite_css(oldfile("plan_step_ui.css"), current("plan_step_ui.css"))
assert_true("couponNewPrice" in css and "couponResult.is-pending" in css,
            "preview prices and pending styles")
print("All customized coupon patch preflight tests passed")
