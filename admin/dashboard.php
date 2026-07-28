<div class="statsGrid">

    <div class="statBox">
        <div class="statTitle">تعداد کل کاربران</div>
        <div class="statValue"><?php echo number_format($totalUsers); ?></div>
    </div>

    <div class="statBox">
        <div class="statTitle">ثبت نام های امروز</div>
        <div class="statValue"><?php echo number_format($todayUsers); ?></div>
    </div>

    <div class="statBox">
        <div class="statTitle">تعداد کل خریدهای اشتراک</div>
        <div class="statValue"><?php echo number_format($totalPayments); ?></div>
    </div>

    <div class="statBox">
        <div class="statTitle">تعداد خریدهای اشتراک امروز</div>
        <div class="statValue"><?php echo number_format($todayPayments); ?></div>
    </div>

    <div class="statBox">
        <div class="statTitle">تعداد کل تمدیدهای اشتراک</div>
        <div class="statValue"><?php echo number_format($totalRenews); ?></div>
    </div>

    <div class="statBox">
        <div class="statTitle">تعداد تمدیدهای اشتراک امروز</div>
        <div class="statValue"><?php echo number_format($todayRenews); ?></div>
    </div>

</div>

<div class="statsGrid">
    <div class="statBox">
        <div class="statTitle">وضعیت بات تلگرام</div>
        <div class="statValue" style="font-size:22px;color:<?php echo !empty($telegramEnabled) ? '#22c55e' : '#ef4444'; ?>">
            <?php echo !empty($telegramEnabled) ? 'فعال' : (!empty($telegramConfigured) ? 'خاموش' : 'تنظیم‌نشده'); ?>
        </div>
        <div style="margin-top:10px">
            <a href="<?php echo htmlspecialchars(pnvAdminUrl('telegram.php'), ENT_QUOTES, 'UTF-8'); ?>" style="color:#93c5fd;text-decoration:none;font-size:13px">تنظیمات تلگرام</a>
        </div>
    </div>

    <div class="statBox">
        <div class="statTitle">اتوماسیون 3x-ui</div>
        <div class="statValue" style="font-size:22px;color:<?php echo !empty($xuiEnabled) ? '#22c55e' : '#ef4444'; ?>">
            <?php echo !empty($xuiEnabled) ? 'فعال' : (!empty($xuiConfigured) ? 'خاموش' : 'تنظیم‌نشده'); ?>
        </div>
        <div style="margin-top:10px">
            <a href="<?php echo htmlspecialchars(pnvAdminUrl('xui-servers.php'), ENT_QUOTES, 'UTF-8'); ?>" style="color:#93c5fd;text-decoration:none;font-size:13px">سرورهای 3x-ui</a>
        </div>
    </div>

    <div class="statBox">
        <div class="statTitle">امروز (جلالی)</div>
        <div class="statValue" style="font-size:20px"><?php echo htmlspecialchars($todayShamsi ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
</div>

<div class="box">
    <h2>داشبورد مدیریت</h2>
    <p style="line-height:2;color:#cbd5e1">
        به پنل مدیریت خوش آمدید.
        <?php if(!empty($hasNewPayments) || !empty($hasNewRenews)){ ?>
            موارد در انتظار بررسی دارید:
            <?php if(!empty($hasNewPayments)){ ?>خرید جدید<?php } ?>
            <?php if(!empty($hasNewPayments) && !empty($hasNewRenews)){ ?> و <?php } ?>
            <?php if(!empty($hasNewRenews)){ ?>تمدید جدید<?php } ?>.
        <?php } else { ?>
            خرید یا تمدید در انتظار بررسی نیست.
        <?php } ?>
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:14px">
        <a href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?page=payments'), ENT_QUOTES, 'UTF-8'); ?>" style="background:#166534;color:#fff;text-decoration:none;padding:10px 14px;border-radius:10px">خریدها</a>
        <a href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?page=renews'), ENT_QUOTES, 'UTF-8'); ?>" style="background:#1d4ed8;color:#fff;text-decoration:none;padding:10px 14px;border-radius:10px">تمدیدها</a>
        <a href="<?php echo htmlspecialchars(pnvAdminUrl('users.php'), ENT_QUOTES, 'UTF-8'); ?>" style="background:#334155;color:#fff;text-decoration:none;padding:10px 14px;border-radius:10px">کاربران</a>
    </div>
</div>
