#!/usr/bin/env python3
"""Fail-closed 3-way merge adapter for the known server3 PR28 conflicts."""
import argparse, re
from pathlib import Path

FILES = ['instant_pay_lib.php','instant-pay-api.php','buy.php','renew.php',
         'admin/payments.php','admin/renews.php','xui_lib.php']
RX = re.compile(r'^<<<<<<< [^\n]*\n(.*?)^=======\n(.*?)^>>>>>>> [^\n]*\n', re.M|re.S)

def one(s,a,b,what):
    if s.count(a)!=1: raise RuntimeError(f'{what}: expected once, found {s.count(a)}')
    return s.replace(a,b)

def merge(s,file):
    choices = {'instant_pay_lib.php':['pr','production','production','production'],
        'admin/payments.php':['production','production'],
        'admin/renews.php':['production','production']}.get(file,[])
    found=list(RX.finditer(s))
    if len(found)!=len(choices): raise RuntimeError(f'{file}: unexpected conflicts {len(found)}')
    if file=='instant_pay_lib.php':
        a,b=found[:2]
        if not ('instantPayPurgeStaleAdminRows' in a.group(1)
            and 'instantPayAdminCancelledHistory' in a.group(2)
            and 'instantPayAdminRowListBucket' in b.group(1)
            and 'return is_array($row);' in b.group(2)):
            raise RuntimeError('Unrecognized library conflict')
    elif file.startswith('admin/'):
        a=found[0]
        if not ('$statusFilter' in a.group(1) and 'CancelledPayments' in a.group(2)):
            raise RuntimeError(file+': unrecognized tabs conflict')
    picks=iter(choices)
    return RX.sub(lambda m:m.group(1 if next(picks)=='production' else 2),s)

def method_region(s, start, end, label):
    if s.count(start)!=1 or s.count(end)!=1:
        raise RuntimeError(label+': function bounds changed, manual review required')
    a=s.index(start)
    b=s.index(end,a+len(start))
    if b<=a:
        raise RuntimeError(label+': invalid function bounds')
    return a,b,s[a:b]

def library(s, pr_source):
    # Production's older supersede implementation may merge cleanly by Git,
    # yet keep the legacy type filter / skip the batch CSV cancellation.
    # Copy only this transaction-critical function from the audited PR,
    # preserving Production's separate four-state admin filter below.
    start='    function instantPaySupersedeUserWaiting('
    end='    function instantPayCloseUserMatchable('
    _,_,good=method_region(pr_source,start,end,'audited PR supersede')
    a,b,old=method_region(s,start,end,'merged supersede')
    if 'instantPayMarkManyCsvCancelled' not in good or 'if($type !== null &&' not in good:
        raise RuntimeError('Audited supersede function no longer has required guards')
    if 'foreach($items as $i => $item)' not in old:
        raise RuntimeError('Production supersede changed unexpectedly')
    s=s[:a]+good+s[b:]
    s=one(s,"    function instantPayPurgeStaleAdminRows($items = null){\n        return instantPayExpireDue($items);\n    }",
        "    function instantPayPurgeStaleAdminRows($items = null){\n        $items = instantPayExpireDue($items);\n        return instantPayRebuildCsvIndexes($items);\n    }",'Production CSV index refresh')
    s=one(s,"            $item = instantPayFindJsonByTracking($row[0] ?? '', $tracking);",
        "            $item = instantPayFindJsonForCsvRow($row);",'timestamp-aware identity')
    s=one(s,"        if($status === 'رد شد'){\n            return 'rejected';\n        }",
        "        $reason = trim((string)($row[7] ?? ''));\n"
        "        if($jsonStatus === 'cancelled' || ($status === 'رد شد' && strpos($reason, 'لغو') === 0)){\n"
        "            return 'cancelled';\n        }\n"
        "        if($status === 'رد شد'){\n            return 'rejected';\n        }",'cancelled category before rejected')
    for x in ("instantPayRollbackFailedCreate(", "flock($lockHandle, LOCK_EX)",
              "instantPayMarkManyCsvCancelled("):
        if x not in s:
            raise RuntimeError("Critical create/rollback/lock guard missing: "+x)
    for x in ('function instantPayAdminRowListBucket(', 'function instantPayAdminCancelledHistory(',
              'function instantPayFindJsonForCsvRow('):
        if x not in s: raise RuntimeError('Missing archive/identity function: '+x)
    return s

def admin(s,file):
    buy=file=='admin/payments.php'
    prefix='payments' if buy else 'renews'
    label='خرید' if buy else 'تمدید'
    records='$buyPayments' if buy else '$renews'
    counts='$paymentsStatusCounts' if buy else '$renewsStatusCounts'
    nav='<nav class="pnvPaymentTabs"'
    if s.count(nav)!=1: raise RuntimeError(file+': unexpected PR nav')
    a=s.index(nav); b=s.index('</nav>',a)+len('</nav>')
    s=s[:a]+s[b:]
    tabs="['approved', 'pending', 'expired', 'rejected']"
    if s.count(tabs)!=2: raise RuntimeError(file+': Production tab options differ')
    s=s.replace(tabs,"['approved', 'pending', 'expired', 'rejected', 'cancelled']")
    s=one(s,"    'rejected' => 'رد شده',",
        "    'rejected' => 'رد شده',\n    'cancelled' => 'کنسل شده',",'cancelled label')
    s=s.replace('$paymentTab' if buy else '$renewTab','$statusFilter').replace('tab=cancelled','status=cancelled')
    if "if($statusFilter !== 'cancelled')" not in s or s.count("instantPayAdminRowBucket($payments[$index])")!=3:
        raise RuntimeError(file+': legacy action handlers not guarded')
    # A few legacy admin tests render a partial admin tree without instant_pay_lib.php.
    # Keep the page usable when the optional archive provider is absent;
    # in real Production the library is loaded, so the archive is populated.
    archive=("$"+prefix+f"CancelledArchive = function_exists('instantPayAdminCancelledHistory')\n"
             +f"    ? instantPayAdminCancelledHistory($payments, '{label}') : [];\n"
             +counts+"['cancelled'] = count($" + prefix +"CancelledArchive);\n"
             +"if($statusFilter === 'cancelled'){\n    "+records+" = $"+prefix+"CancelledArchive;\n}\n\n")
    anchor="$currentPage = intval($_GET['p'] ?? 1);" if buy else '$perPage = 20;'
    s=one(s,anchor,archive+anchor,file+' virtual archive')
    for x in ('role="tablist"','$statusFilter',counts+"['cancelled']"):
        if x not in s: raise RuntimeError(file+': lost custom Production UI '+x)
    return s

def main():
    p=argparse.ArgumentParser()
    p.add_argument('--merged',type=Path,required=True)
    p.add_argument('--output',type=Path,required=True)
    x=p.parse_args()
    if x.output.exists() and any(x.output.iterdir()): raise RuntimeError('Output directory is nonempty')
    pr_file=x.merged.parent/'pr'/'instant_pay_lib.php'
    if not pr_file.is_file():
        raise RuntimeError('Expected pinned PR worktree beside merged output: '+str(pr_file))
    pr_source=pr_file.read_text(encoding='utf-8')
    for name in FILES:
        f=x.merged/name
        if not f.is_file(): raise RuntimeError('Missing '+str(f))
        s=merge(f.read_text(encoding='utf-8'),name)
        if name=='instant_pay_lib.php': s=library(s,pr_source)
        elif name.startswith('admin/'): s=admin(s,name)
        if re.search(r'(?m)^(?:<<<<<<<|=======|>>>>>>>)',s): raise RuntimeError(name+': conflict markers')
        dest=x.output/name
        dest.parent.mkdir(parents=True,exist_ok=True)
        dest.write_text(s,encoding='utf-8')
        print('RESOLVED',name,flush=True)
    print('Prepared 7 PHP files, Production untouched.',flush=True)

if __name__=='__main__': main()
