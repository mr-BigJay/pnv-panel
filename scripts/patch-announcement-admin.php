<?php
if(PHP_SAPI !== 'cli' || $argc !== 3){ exit(2); }
$s = file_get_contents($argv[1]);
if(strpos($s, 'name="display_limit"') !== false){
    if(strpos($s, "'display_limit' => \$displayLimit") === false){ fwrite(STDERR,"STOP: partial admin display-limit implementation\n"); exit(1); }
    file_put_contents($argv[2],$s); exit(0);
}
$replacements = [
    "    \$priority = max(0, intval(\$_POST['priority'] ?? 100));" => "    \$priority = max(0, intval(\$_POST['priority'] ?? 100));\n    \$displayLimit = max(0, min(1000, intval(\$_POST['display_limit'] ?? 1)));",
    "            'priority' => \$priority," => "            'priority' => \$priority,\n            'display_limit' => \$displayLimit,",
    '<div class="campaignToggleRow">' => <<<'HTML'
<div class="campaignField">
<label class="campaignLabel" for="annDisplayLimit">تعداد نمایش خودکار برای هر کاربر</label>
<input class="campaignInput" id="annDisplayLimit" name="display_limit" type="number" min="0" max="1000" step="1" value="<?php echo max(0, min(1000, (int)($editRow['display_limit'] ?? 1))); ?>" required>
<p class="campaignHint">مثلاً ۳ یعنی حداکثر سه بار در بازدیدهای جداگانه. صفر یعنی فقط بازکردن دستی. در هر بار بازشدن داشبورد فقط یک اطلاعیه خودکار نمایش داده می‌شود. افزایش عدد، دفعات باقی‌مانده را بیشتر می‌کند و ویرایش متن شمارنده را صفر نمی‌کند.</p>
</div>
<div class="campaignToggleRow">
HTML
];
foreach($replacements as $before=>$after){
    if(substr_count($s,$before)!==1){fwrite(STDERR,"STOP: admin template differs; send admin/campaign-announcements.php\n");exit(1);}
    $s=str_replace($before,$after,$s);
}
if(file_put_contents($argv[2],$s)===false){exit(1);}
