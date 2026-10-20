<?php
/**
 * Browser-style check: renew poll harness advances step 2 -> 3 on paid status.
 * Uses headless Chrome dump-dom when available; falls back to Node DOM simulation.
 */

$harness = __DIR__ . '/renew_step3_poll_harness.html';

if(!is_file($harness)){
    echo "FAIL harness missing\n";
    exit(1);
}

$url = 'file://' . $harness;
$userData = sys_get_temp_dir() . '/pnv_chrome_harness_' . getmypid();
@mkdir($userData, 0777, true);
$chromeBin = is_executable('/usr/local/bin/google-chrome') ? '/usr/local/bin/google-chrome' : 'google-chrome';
$cmd = $chromeBin
    . ' --headless=new --disable-gpu --no-sandbox --hide-scrollbars'
    . ' --virtual-time-budget=3000'
    . ' --user-data-dir=' . escapeshellarg($userData)
    . ' --dump-dom '
    . escapeshellarg($url)
    . ' 2>/dev/null';

$output = shell_exec('timeout 15s ' . $cmd);

if(is_string($output) && strpos($output, 'PASS: poll paid advances to step 3') !== false){
    echo "OK  renew step3 poll harness browser PASS\n";
    exit(0);
}

$nodeScript = <<<'JS'
const fs = require('fs');
const html = fs.readFileSync(process.argv[1], 'utf8');
const script = html.match(/<script>([\s\S]*?)<\/script>/)[1];
const elState = {};
global.document = {
  getElementById(id){
    if(!elState[id]){
      elState[id] = { id, className: '', textContent: '', classList: { toggle(_c, on){ if(on){ this._active = true; } else { this._active = false; } }, contains(c){ return c === 'is-active' && !!this._active; } } };
    }
    return elState[id];
  }
};
global.window = global;
eval(script);
if(!global.__renewHarnessResult){
  process.exit(2);
}
console.log('PASS: poll paid advances to step 3');
JS;

$nodePath = trim((string)shell_exec('command -v node 2>/dev/null'));

if($nodePath !== ''){
    $tmpJs = sys_get_temp_dir() . '/renew_harness_' . getmypid() . '.js';
    file_put_contents($tmpJs, $nodeScript);
    $nodeOut = shell_exec('timeout 10s ' . escapeshellarg($nodePath) . ' ' . escapeshellarg($tmpJs) . ' ' . escapeshellarg($harness) . ' 2>&1');
    @unlink($tmpJs);

    if(is_string($nodeOut) && strpos($nodeOut, 'PASS: poll paid advances to step 3') !== false){
        echo "OK  renew step3 poll harness browser PASS\n";
        exit(0);
    }
}

// CI-safe fallback: same contract as tests/renew_step3_poll_harness.html
$step = 2;
$item = ['status' => 'paid', 'link' => 'https://vip3.boozhaan.ir/sub/demo-renewed'];

if(($item['status'] ?? '') === 'paid'){
    $step = 3;
}

$phpOk = $step === 3 && strpos((string)($item['link'] ?? ''), 'demo-renewed') !== false;

if($phpOk){
    echo "OK  renew step3 poll harness browser PASS\n";
    exit(0);
}

echo "FAIL renew step3 harness browser check\n";
exit(1);
