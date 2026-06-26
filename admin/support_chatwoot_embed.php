<?php

if(!chatwootEnabled()){
    echo '<div class="box"><p>Chatwoot فعال نیست. از تنظیمات Chatwoot، آدرس و Token را وارد کنید.</p></div>';
    return;
}

$embedUrl = chatwootAdminEmbedUrl();

?>

<div class="chatwootAdminEmbed">

<p class="chatwootAdminNote">
پیام‌های کاربران با نام کاربری پنل در Chatwoot نمایش داده می‌شوند.
اگر بار اول است، در کادر زیر با حساب ادمین Chatwoot یک‌بار وارد شوید.
</p>

<iframe
title="Chatwoot"
src="<?php echo htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8'); ?>"
class="chatwootAdminFrame"
allow="clipboard-read; clipboard-write; microphone; camera"
></iframe>

</div>

<style>
.chatwootAdminEmbed{
height:100%;
min-height:calc(100vh - 48px);
display:flex;
flex-direction:column;
}
.chatwootAdminNote{
margin:0 0 12px;
padding:12px 14px;
background:#1e293b;
border-radius:12px;
color:#cbd5e1;
font-size:13px;
line-height:26px;
}
.chatwootAdminFrame{
flex:1;
width:100%;
min-height:720px;
border:none;
border-radius:14px;
background:#0f172a;
}
</style>
