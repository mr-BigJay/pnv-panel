#!/usr/bin/env python3
"""Selective, fail-closed merge of PR #59 coupon fix into customized live PNV files.

This is deliberately NOT a git patch and never overwrites a whole file from GitHub.
It changes only: (1) coupon plan lookup + duration-aware discounted invoice labels,
(2) read-only coupon preview endpoint/helper, (3) coupon UI step/plan prices, and
(4) tiny associated CSS. All other live code, especially capped discounts,
payments/renewals/provisioning, remains verbatim.

Dry run (default):
  sudo python3 scripts/deploy-coupon-step1-custom-safe.py --root=/var/www/html
Apply after a successful dry run:
  sudo python3 scripts/deploy-coupon-step1-custom-safe.py --root=/var/www/html --apply
"""
from __future__ import annotations

import argparse
import datetime as dt
import difflib
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys
import tempfile

FILES = ("buy.php", "renew.php", "coupon-api.php", "coupon_lib.php", "plan_step_ui.css")
SOURCE = Path(__file__).resolve().parents[1]


class Stop(RuntimeError):
    pass


def once(s: str, old: str, new: str, label: str) -> str:
    n = s.count(old)
    if n != 1:
        raise Stop(f"{label}: expected exactly one unchanged anchor, found {n}")
    return s.replace(old, new, 1)


def extract(s: str, begin: str, end: str, label: str) -> str:
    if s.count(begin) != 1 or s.count(end) != 1:
        raise Stop(f"{label}: could not uniquely locate block")
    i, j = s.index(begin), s.index(end)
    if j <= i:
        raise Stop(f"{label}: block boundaries reversed")
    return s[i:j]


def move_coupon_section(s: str, template: str, filename: str) -> str:
    begin = '<div class="couponSection">'
    end = '\n\n<div class="destCardSection">'
    heading = '<div class="sectionTitle">نوع پلن را انتخاب کنید</div>'
    if s.count(begin) != 1 or s.count(end) != 1 or s.count(heading) != 1:
        raise Stop(f"{filename}: coupon HTML step anchors changed")
    i, j = s.index(begin), s.index(end)
    step2 = s.index('id="step2"')
    if i <= step2 or j <= i:
        raise Stop(f"{filename}: coupon was not in step 2")
    coupon_html = s[i:j]
    template_html = extract(template, begin, heading, f"{filename} source coupon section").strip()
    if coupon_html.strip() != template_html:
        raise Stop(f"{filename}: coupon markup differs; refusing to move")
    s = s[:i] + s[j+2:]
    return once(s, heading, coupon_html + "\n\n" + heading, f"{filename}: insert coupon before plan")


def rewrite_form(s: str, template: str, filename: str) -> str:
    s = move_coupon_section(s, template, filename)
    s = once(s, "plan_step_ui.css?v=23", "plan_step_ui.css?v=24", f"{filename}: stylesheet version")
    s = once(s, "let couponTimer = null;",
             "let couponTimer = null;\nlet couponRequestSeq = 0;\nlet couponDiscounts = null;\nlet couponPreviewError = '';",
             f"{filename}: coupon state")

    if filename == "buy.php":
        old = "    toStep2Btn.disabled = !(selectedCategory && selectedPlan && planSelect.value);"
        new = "    const eligible = !hasCouponCheck.checked || !!(couponDiscounts && selectedPlan && couponDiscounts[planSelect.value]);\n    toStep2Btn.disabled = !(selectedCategory && selectedPlan && planSelect.value && eligible);"
    else:
        old = "    toStep2Btn.disabled = !!(locked || !hasPlan);"
        new = "    const eligible = !hasCouponCheck.checked || !!(couponDiscounts && selectedPlan && couponDiscounts[planSelect.value]);\n    toStep2Btn.disabled = !!(locked || !hasPlan || !eligible);"
    s = once(s, old, new, f"{filename}: eligibility guard")

    price_old = "        btn.querySelector('.planPrice').textContent = plan.price_text;"
    price_new = """        const priceNode = btn.querySelector('.planPrice');
        const discount = couponForPlan(plan);
        if(discount){
            priceNode.innerHTML = '<s class="couponOldPrice">' + escapeHtml(plan.price_text) + '</s>' +
                '<strong class="couponNewPrice">' + escapeHtml(discount.final_text) + '</strong>';
        }else{
            priceNode.textContent = plan.price_text;
        }"""
    s = once(s, price_old, price_new, f"{filename}: discounted plan chips")

    summary_old = "        planSummary.innerHTML = renderPlanSummaryHtml(selectedPlan, selectedCategory, extraHtml);"
    summary_new = """        const discount = couponForPlan(selectedPlan);
        const summaryPlan = discount
            ? Object.assign({}, selectedPlan, { price_text: discount.final_text })
            : selectedPlan;
        if(discount){
            extraHtml += '<div class="planSummaryExtra">قیمت قبل از تخفیف: <s>' +
                escapeHtml(selectedPlan.price_text) + '</s></div>';
        }
        planSummary.innerHTML = renderPlanSummaryHtml(summaryPlan, selectedCategory, extraHtml);"""
    s = once(s, summary_old, summary_new, f"{filename}: summary price")
    s = once(s, "        validateCoupon();\n        ensureInstantPay();",
             "        updateCouponFeedback();\n        ensureInstantPay();",
             f"{filename}: payment step avoids restarting validation")

    oldblock = extract(s, "function resetCouponResult(){", "function formatRemain(", f"{filename}: old coupon JS")
    if "planSelect.addEventListener('change', validateCoupon);" not in oldblock:
        raise Stop(f"{filename}: unexpected coupon JS block; refusing overwrite")
    # No unrelated action/event is allowed in the block to be replaced.
    if oldblock.count("function validateCoupon(") != 1 or oldblock.count("function resetCouponResult(") != 1:
        raise Stop(f"{filename}: malformed original coupon JS")
    newblock = extract(template, "function resetCouponResult(){", "function formatRemain(", f"{filename}: new coupon JS")
    if "mode=preview&code=" not in newblock or "couponDiscounts" not in newblock:
        raise Stop(f"{filename}: PR#59 coupon JS not found")
    s = once(s, oldblock, newblock, f"{filename}: coupon-only JS handler")

    if s.find('id="hasCouponCheck"') > s.find('id="step2"'):
        raise Stop(f"{filename}: coupon not moved before payment step")
    if s.count("body.set('coupon_code', couponCodeInput.value.trim())") != template.count("body.set('coupon_code', couponCodeInput.value.trim())"):
        raise Stop(f"{filename}: payment coupon submission differs from upstream; manual review needed")
    return s


def rewrite_coupon_lib(s: str, template: str) -> str:
    fn = "    function couponFindPlanByValue($planValue, $plans){"
    if s.count(fn) != 1:
        raise Stop("coupon_lib: plan resolver function not unique")
    after = """        $planValue = trim($planValue);

        foreach($plans as $plan){"""
    patch = """        $planValue = trim($planValue);

        // The UI's option value includes duration; match that unchanged value
        // before falling back to any legacy coupon plan representation.
        if(!function_exists('pnvFindPlanByValue')){
            require_once __DIR__ . '/plan_ui_lib.php';
        }
        $matched = pnvFindPlanByValue($planValue, $plans);
        if(is_array($matched)){
            return $matched;
        }

        foreach($plans as $plan){"""
    if s.index(after) < s.index(fn):
        raise Stop("coupon_lib: original plan matching structure changed")
    s = once(s, after, patch, "coupon_lib: duration-aware resolver")

    helper = extract(template, "    /**\n     * Read-only preview for step one:",
                     "    function couponApplyDiscountThousands(", "coupon_lib: preview helper")
    if s.count("function couponPreviewForPlans("):
        raise Stop("coupon_lib: preview helper already exists; inspect manually")
    s = once(s, fn, helper + fn, "coupon_lib: insert read-only preview")

    # Keep the *existing* capped-discount calculation exactly as-is.
    label_start = "    function couponBuildPlanLabel($plan, $percent, $maxDiscountThousands = 0){"
    if s.count(label_start) != 1:
        raise Stop("coupon_lib: live capped-discount label function changed")
    next_function = re.search(r'\n    function [A-Za-z_]\w*\(', s[s.index(label_start)+len(label_start):])
    label_end = s.index(label_start) + len(label_start) + next_function.start() if next_function else len(s)
    orig_label = s[s.index(label_start):label_end]
    oldhead = """        $finalText = couponFormatPriceThousands($calc['final_amount']);

        if($percent <= 0){
            return ($plan['name'] ?? '') . ' - ' . $original;
        }

        $label = ($plan['name'] ?? '')
            . ' - '
            . $original
            . ' | تخفیف '"""
    newhead = """        $finalText = couponFormatPriceThousands($calc['final_amount']);
        // Preserve 30/90-day duration for downstream subscription provisioning.
        if(!function_exists('pnvPlanOptionValue')){
            require_once __DIR__ . '/plan_ui_lib.php';
        }
        $baseLabel = pnvPlanOptionValue($plan);

        if($percent <= 0){
            return $baseLabel;
        }

        $label = $baseLabel
            . ' | تخفیف '"""
    revised_label = once(orig_label, oldhead, newhead,
                         "coupon_lib: preserve capped discount and append duration")
    s = once(s, orig_label, revised_label, "coupon_lib: replace only invoice label")

    if "couponCalculateDiscountThousands($originalAmount, $percent, $maxDiscountThousands)" not in revised_label:
        raise Stop("coupon_lib: cap calculation missing, refusing modification")
    return s


def rewrite_coupon_api(s: str, template: str) -> str:
    if "mode'] ?? $_POST['mode']" in s or "couponPreviewForPlans(" in s:
        raise Stop("coupon-api: preview already installed; manual review needed")
    block = extract(template, "// First-step preview:", "if($plan === ''){", "coupon-api: preview endpoint")
    return once(s, "if($plan === ''){", block + "if($plan === ''){",
                "coupon-api: add read-only first-step preview")


def rewrite_css(s: str, template: str) -> str:
    if ".couponNewPrice" in s or ".couponOldPrice" in s:
        raise Stop("plan_step_ui.css: modified pricing styles already exist")
    new_css = extract(template, ".planChip .couponOldPrice{", ".planChip .planDays{",
                      "stylesheet: coupon price classes")
    s = once(s, ".planChip .planDays{", new_css + ".planChip .planDays{",
             "stylesheet: coupon discounted prices")
    pending = ".couponResult.is-pending{display:block;background:#1e293b;color:#cbd5e1}"
    s = once(s, ".couponResult.is-error{display:block;background:#7f1d1d}",
             ".couponResult.is-error{display:block;background:#7f1d1d}\n" + pending,
             "stylesheet: preview pending feedback")
    return s


def php_lint(path: Path):
    run = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
    if run.returncode:
        raise Stop(f"PHP syntax invalid in {path.name}: {(run.stdout + run.stderr).strip()}")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default="/var/www/html")
    ap.add_argument("--apply", action="store_true")
    args = ap.parse_args()
    root = Path(args.root).resolve()
    if str(root) != "/var/www/html":
        raise Stop("this tool is locked to the expected PNV production root")
    if not all((root / f).is_file() and (SOURCE / f).is_file() for f in FILES):
        raise Stop("missing live or reference checkout files")

    original = {f: (root / f).read_bytes() for f in FILES}
    for f in FILES:
        if b"\r\n" in original[f]:
            raise Stop(f"{f}: unexpected CRLF; requires manual review")

    text_source = {f: (SOURCE / f).read_text(encoding="utf-8") for f in FILES}
    local = {f: original[f].decode("utf-8") for f in FILES}
    revised = dict(local)

    for f in ("buy.php", "renew.php"):
        revised[f] = rewrite_form(local[f], text_source[f], f)
    revised["coupon_lib.php"] = rewrite_coupon_lib(local["coupon_lib.php"], text_source["coupon_lib.php"])
    revised["coupon-api.php"] = rewrite_coupon_api(local["coupon-api.php"], text_source["coupon-api.php"])
    revised["plan_step_ui.css"] = rewrite_css(local["plan_step_ui.css"], text_source["plan_step_ui.css"])

    for f in FILES:
        if revised[f] == local[f]:
            raise Stop(f"{f}: no modification detected")
        print(f"{f}: tailored patch ready (+{len(revised[f])-len(local[f])} UTF-8 chars)")
    # No alteration to payment or renewal endpoints; those files are not inputs.

    with tempfile.TemporaryDirectory(prefix="pnv-coupon-preflight-") as temp:
        tmp = Path(temp)
        for f in FILES:
            (tmp / f).write_text(revised[f], encoding="utf-8")
        for f in FILES:
            if f.endswith(".php"):
                php_lint(tmp / f)
        if shutil.which("node"):
            for f in ("buy.php", "renew.php"):
                blocks = re.findall(r"<script(?:\s[^>]*)?>(.*?)</script\s*>",
                                    revised[f], re.DOTALL | re.IGNORECASE)
                script = "\n".join(blocks)
                # Strip PHP echo fragments from bootstrapped constants.
                script = re.sub(r"<\?php.*?\?>", "null", script, flags=re.DOTALL)
                js = tmp / (f + ".js")
                js.write_text(script, encoding="utf-8")
                run = subprocess.run(["node", "--check", str(js)],
                                     capture_output=True, text=True)
                if run.returncode:
                    raise Stop(f"{f}: JavaScript syntax check failed: {run.stderr[:220]}")
        print("PREFLIGHT: PHP lint OK; template and live anchor checks passed")

    if not args.apply:
        print("DRY_RUN_PASS: no production files modified; execute again with --apply to install")
        return

    if os.geteuid() != 0:
        raise Stop("--apply must run as root")
    backup_dir = Path(tempfile.mkdtemp(prefix="pnv-coupon-backup-",
                                       dir="/root"))
    backup_dir.chmod(0o700)
    for f in FILES:
        if (root / f).read_bytes() != original[f]:
            raise Stop(f"{f}: file changed after preflight; abort")
        shutil.copy2(root / f, backup_dir / f)
    changed = []
    try:
        for f in FILES:
            target = root / f
            if target.read_bytes() != original[f]:
                raise Stop(f"{f}: changed concurrently before install")
            fd, staged_name = tempfile.mkstemp(prefix=f".{f}.", dir=str(root))
            try:
                with os.fdopen(fd, "wb") as out:
                    out.write(revised[f].encode("utf-8"))
                    out.flush()
                    os.fsync(out.fileno())
                meta = target.stat()
                os.chmod(staged_name, stat.S_IMODE(meta.st_mode))
                os.chown(staged_name, meta.st_uid, meta.st_gid)
                os.replace(staged_name, target)
                changed.append(f)
            finally:
                if os.path.exists(staged_name):
                    os.unlink(staged_name)
        for f in FILES:
            if f.endswith(".php"):
                php_lint(root / f)
    except Exception:
        for f in changed:
            shutil.copy2(backup_dir / f, root / f)
        print("ROLLBACK: restored every file changed by this installer",
              file=sys.stderr)
        raise

    print("APPLIED_OK: only coupon/checkout preview paths were updated")
    print("BACKUP:", backup_dir)
    print("NOTE: verify live checkout using a non-paying test coupon/order before production use")


if __name__ == "__main__":
    try:
        main()
    except (Stop, OSError, UnicodeError) as exc:
        print("STOP:", exc, file=sys.stderr)
        sys.exit(2)
