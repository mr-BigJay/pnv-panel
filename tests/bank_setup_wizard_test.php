<?php
/** Real PHP render contract for the Tejarat three-step admin setup page. */
$base = sys_get_temp_dir() . '/pnv-bank-wizard-' . bin2hex(random_bytes(5));
mkdir($base, 0700, true);
putenv('PNV_BANK_CONNECTION_CONFIG_PATH=' . $base . '/config.json');
putenv('PNV_BANK_CREDENTIAL_KEY_PATH=' . $base . '/key');
putenv('PNV_BANK_CREDENTIAL_STORE_PATH=' . $base . '/credentials.json');
putenv('PNV_BANK_CONNECTION_LEDGER_PATH=' . $base . '/ledger.json');

function pnvAdminIsLoggedIn(){ return true; }
function pnvAdminUrl($path = 'index.php'){ return '/admin/' . ltrim($path,'/'); }
require_once __DIR__ . '/../bank_connection_lib.php';
function testBankWizard($name, $yes){
    global $failed;
    echo ($yes ? 'PASS ' : 'FAIL ') . $name . "\n";
    if(!$yes) $failed++;
}
function bankWizardRender($step = null, $method = 'GET', array $post = []){
    $_GET = ['page' => 'banks'];
    if($step !== null) $_GET['step'] = (string)$step;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = $post;
    ob_start();
    include __DIR__ . '/../admin/bank-connection.php';
    return ob_get_clean();
}
$failed = 0;
$page = bankWizardRender(1);
testBankWizard('step one displays encrypted credentials form', 
    strpos($page,'مرحله ۱ از ۳') !== false && strpos($page,'name="bank_password"') !== false);
testBankWizard('three navigation steps exist', 
    substr_count($page,'class="bankStep ') === 3 && strpos($page,'step=3') !== false);
testBankWizard('step one no SMS/OTP input', strpos($page,'name="otp"') === false && strpos($page,'name="sms_code"') === false);
$csrf = $_SESSION['pnv_bank_csrf'];
$invalid = bankWizardRender(1,'POST',[
    'csrf'=>'bad', 'action'=>'save_credentials',
    'bank_username'=>'should_not_save','bank_password'=>'should_not_save',
]);
testBankWizard('invalid CSRF rejects credential write', strpos($invalid,'نشست امنیتی معتبر نیست') !== false &&
    pnvBankTejaratCredentialsStatus() === 'not_configured');
file_put_contents($base . '/key', bin2hex(random_bytes(32)) . "\n");
chmod($base . '/key',0600);
testBankWizard('credential save works', pnvBankSaveTejaratCredentials('demo-only','never-print-me'));
$default = bankWizardRender();
testBankWizard('configured bank lands on SMS onboarding step two',
    strpos($default,'مرحله ۲ از ۳') !== false);
testBankWizard('SMS step uses official bank, not password reflection',
    strpos($default,'https://hb.tejaratbank.ir/') !== false &&
    strpos($default,'never-print-me') === false);
testBankWizard('step two never asks for OTP on panel',
    strpos($default,'name="otp"') === false && strpos($default,'name="bank_password"') === false &&
    strpos($default,'فقط در صفحه رسمی بانک') !== false);
$step3 = bankWizardRender(3);
testBankWizard('step three reports not-connected truthfully',
    strpos($step3,'نشست معتبر بانک از این صفحه هنوز قابل تأیید نیست') !== false &&
    strpos($step3,'بررسی آمادگی تنظیمات') !== false);
$readiness = bankWizardRender(3,'POST',['csrf'=>$csrf,'action'=>'check_readiness']);
testBankWizard('readiness is a read-only local check',
    strpos($readiness,'بررسی محلی تنظیمات انجام شد') !== false &&
    strpos($readiness,'بدون اتصال به بانک') !== false);
testBankWizard('no bank transactions fabricated', !file_exists($base . '/ledger.json'));
testBankWizard('password stays out of rendered stage three',strpos($step3,'never-print-me')===false);
@unlink($base . '/credentials.json');
@unlink($base . '/key');
@unlink($base . '/config.json');
@rmdir($base);
if($failed){ fwrite(STDERR, "FAILURES: $failed\n");exit(1); }
echo "ALL BANK SETUP WIZARD TESTS PASSED\n";
