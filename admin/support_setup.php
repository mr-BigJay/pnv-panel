<?php

$config = chatwootConfig();
$settingsUrl = function_exists('pnvAdminUrl') ? pnvAdminUrl('chatwoot-settings.php') : 'chatwoot-settings.php';
$legacyUrl = function_exists('pnvAdminUrl') ? pnvAdminUrl('index.php?page=support&legacy=1') : 'index.php?page=support&legacy=1';
$chatwootAppUrl = chatwootAdminUrl() ?: 'https://panel.ticketin.ir/app';

?>

<div class="box chatwootSetupBox">

<h2>فعال‌سازی Chatwoot (جایگزین مسنجر)</h2>

<p class="chatwootSetupLead">
مسنجر قدیمی هنوز فعال است چون Chatwoot در پنل تنظیم نشده. برای نمایش پیام کاربران در Chatwoot این مراحل را انجام دهید:
</p>

<ol class="chatwootSetupSteps">
<li>در سرور مطمئن شوید Chatwoot روی پورت ۳۰۰۰ بالا است (<code>docker compose ps</code> در پوشه chatwoot)</li>
<li>یک‌بار در <a href="<?php echo htmlspecialchars($chatwootAppUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">پنل Chatwoot</a> حساب ادمین بسازید</li>
<li>یک Inbox از نوع <strong>Website</strong> بسازید (دامنه: panel.ticketin.ir)</li>
<li>از تنظیمات Inbox، <strong>Website Token</strong> و <strong>Identity Validation Key</strong> را کپی کنید</li>
<li>در پنل Ticketin → تنظیمات Chatwoot → مقادیر را وارد و تیک فعال‌سازی را بزنید</li>
</ol>

<p class="chatwootSetupDefaults">
مقادیر پیشنهادی:<br>
Base URL: <code>https://panel.ticketin.ir</code><br>
Admin URL: <code>https://panel.ticketin.ir/app</code>
</p>

<div class="chatwootSetupActions">
<a class="setupBtn primary" href="<?php echo htmlspecialchars($settingsUrl, ENT_QUOTES, 'UTF-8'); ?>">رفتن به تنظیمات Chatwoot</a>
<a class="setupBtn" href="<?php echo htmlspecialchars($chatwootAppUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">باز کردن پنل Chatwoot</a>
<a class="setupBtn muted" href="<?php echo htmlspecialchars($legacyUrl, ENT_QUOTES, 'UTF-8'); ?>">مسنجر قدیمی (موقت)</a>
</div>

<?php if(trim($config['base_url'] ?? '') !== '' && empty($config['enabled'])){ ?>
<p class="chatwootSetupWarn">تنظیمات ذخیره شده ولی فعال‌سازی خاموش است — تیک «فعال‌سازی» را بزنید.</p>
<?php } ?>

</div>

<style>
.chatwootSetupBox{
max-width:760px;
line-height:30px;
}
.chatwootSetupLead{
color:#cbd5e1;
margin-top:0;
}
.chatwootSetupSteps{
padding-right:20px;
color:#e2e8f0;
}
.chatwootSetupSteps li{
margin-bottom:10px;
}
.chatwootSetupSteps a{
color:#38bdf8;
}
.chatwootSetupDefaults{
background:#0f172a;
padding:14px;
border-radius:12px;
font-size:14px;
color:#94a3b8;
}
.chatwootSetupActions{
display:flex;
flex-wrap:wrap;
gap:10px;
margin-top:18px;
}
.setupBtn{
display:inline-block;
padding:12px 18px;
border-radius:12px;
text-decoration:none;
color:white;
background:#334155;
font-size:14px;
}
.setupBtn.primary{
background:#22c55e;
}
.setupBtn.muted{
background:#475569;
}
.chatwootSetupWarn{
color:#fbbf24;
font-size:14px;
}
</style>
