package ir.jay.bankrelay;

import android.Manifest;
import android.app.*;
import android.content.*;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.os.Bundle;
import android.telephony.*;
import android.view.*;
import android.widget.*;
import java.util.*;

public class MainActivity extends Activity {
    private LinearLayout page;
    private SharedPreferences prefs;
    @Override public void onCreate(Bundle b) {
        super.onCreate(b); prefs = getSharedPreferences("settings", 0); home();
        if(prefs.getBoolean("enabled",false))SendJob.schedule(this);
    }
    private void screen(String title) {
        ScrollView scroll = new ScrollView(this);
        page = new LinearLayout(this); page.setOrientation(LinearLayout.VERTICAL);
        page.setPadding(28, 48, 28, 32); page.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        scroll.addView(page); setContentView(scroll);
        label(title, 25);
    }
    private void label(String s, int size) { TextView t = new TextView(this); t.setText(s); t.setTextSize(size); t.setPadding(0,16,0,16); page.addView(t); }
    private void button(String title, Runnable run) { Button b = new Button(this); b.setText(title); b.setOnClickListener(v -> run.run()); page.addView(b); }
    private EditText field(String title, String value) {
        label(title, 14); EditText e = new EditText(this); e.setText(value); e.setSingleLine(true); page.addView(e); return e;
    }
    private void toast(String s) { Toast.makeText(this, s, Toast.LENGTH_LONG).show(); }
    private void home() {
        screen("پیام‌رسان واریز");
        label("نسخه آزمایشی ۰٫۴ — پیامک به بله\nابتدا ورود و پیام آزمایشی را انجام دهید.", 16);
        Switch active = new Switch(this); active.setText("دریافت پیامک بانک‌های ثبت‌شده");
        active.setChecked(prefs.getBoolean("enabled",false)); page.addView(active);
        active.setOnCheckedChangeListener((v,on) -> {
            if (on && checkSelfPermission(Manifest.permission.RECEIVE_SMS) != PackageManager.PERMISSION_GRANTED) {
                active.setChecked(false);
                requestPermissions(new String[]{Manifest.permission.RECEIVE_SMS, Manifest.permission.READ_PHONE_STATE}, 10);
                toast("پس از دادن مجوز، دریافت را روشن کنید."); return;
            }
            prefs.edit().putBoolean("enabled",on).putLong("recovery_since",System.currentTimeMillis()).commit();
            if(on)SendJob.schedule(this);
        });
        button("افزودن بانک", () -> bank(-1));
        button("بانک‌های من", this::banks);
        button("مقصد بله", this::connection);
        button("صف پیام‌ها", this::queue);
        button("عیب‌یابی کامل (Diagnose)", this::diagnose);
        button("گزارش دریافت و تنظیمات پیامک", this::diagnostics);
        label("فقط پیامک‌های جدید با فرستندهٔ مطابق و عبارت واریز وارد صف می‌شوند. رمز ورود و برداشت کنار گذاشته می‌شوند. شماره کارت صرفاً یادداشت شماست و به متن پیام اضافه نمی‌شود.",14);
    }
    private void banks() {
        screen("بانک‌های من"); button("بازگشت",this::home);
        try (Store db = new Store(this); Cursor c = db.getReadableDatabase().rawQuery("SELECT _id,name,card,sender FROM banks ORDER BY _id DESC", null)) {
            while(c.moveToNext()) {
                long id = c.getLong(0); String card = c.getString(2);
                String last = card.length()>4 ? card.substring(card.length()-4) : card;
                button(c.getString(1) + " • " + last + " • " + c.getString(3), () -> bank(id));
            }
        }
    }
    private void bank(long id) {
        String name="", card="", sender="", keywords="واریز,بستانکار"; int selected=-1;
        if (id>=0) try (Store db=new Store(this); Cursor c=db.getReadableDatabase().rawQuery("SELECT name,card,sender,keywords,sub FROM banks WHERE _id=?",new String[]{Long.toString(id)})) {
            if(c.moveToFirst()){name=c.getString(0);card=c.getString(1);sender=c.getString(2);keywords=c.getString(3);selected=c.getInt(4);}
        }
        screen(id<0?"افزودن بانک":"ویرایش بانک");
        label("برای پست‌بانک POSTBANK را وارد کنید؛ اسم مخاطبی که در دفترچه تلفن ساخته‌اید ملاک نیست. اگر انتخاب سیم مشخص مشکل دارد، هر دو سیم‌کارت را انتخاب کنید.",14);
        EditText n=field("نام بانک",name), ca=field("شماره کارت یا چهار رقم آخر (اختیاری)",card), se=field("فرستنده دقیق پیامک",sender), kw=field("عبارت‌های واریز، جداشده با ویرگول",keywords);
        label("سیم‌کارت دریافت پیامک",14);
        ArrayList<String> labels=new ArrayList<>(); ArrayList<Integer> ids=new ArrayList<>();
        labels.add("هر دو سیم‌کارت");ids.add(-1);
        if(checkSelfPermission(Manifest.permission.READ_PHONE_STATE)==PackageManager.PERMISSION_GRANTED) {
            List<SubscriptionInfo> sims=getSystemService(SubscriptionManager.class).getActiveSubscriptionInfoList();
            if(sims!=null) for(SubscriptionInfo s:sims){ids.add(s.getSubscriptionId());labels.add("سیم " +(s.getSimSlotIndex()+1)+" — "+s.getDisplayName());}
        } else {
            label("برای انتخاب سیم مشخص، مجوز تلفن را بدهید و دوباره این صفحه را باز کنید.",14);
            button("مجوز انتخاب سیم‌کارت",()->requestPermissions(new String[]{Manifest.permission.READ_PHONE_STATE},11));
        }
        if(!ids.contains(selected)){ids.add(selected);labels.add("سیم ذخیره‌شده (اکنون در دسترس نیست)");}
        Spinner sim=new Spinner(this);sim.setAdapter(new ArrayAdapter<>(this,android.R.layout.simple_spinner_dropdown_item,labels)); sim.setSelection(ids.indexOf(selected));page.addView(sim);
        button("ذخیره",()->{
            if(n.getText().toString().trim().isEmpty()||se.getText().toString().trim().isEmpty()||kw.getText().toString().trim().isEmpty()){toast("نام بانک، فرستنده و عبارت واریز را کامل کنید.");return;}
            ContentValues v=new ContentValues();v.put("name",n.getText().toString().trim());v.put("card",ca.getText().toString().trim());v.put("sender",DepositFilter.sender(se.getText().toString()));v.put("keywords",kw.getText().toString());v.put("sub",ids.get(sim.getSelectedItemPosition()));
            try(Store db=new Store(this)){if(id<0)db.getWritableDatabase().insertOrThrow("banks",null,v);else db.getWritableDatabase().update("banks",v,"_id=?",new String[]{Long.toString(id)});}
            banks();
        });
        if(id>=0)button("حذف بانک",()->new AlertDialog.Builder(this).setMessage("بانک حذف شود؟ پیام‌های قبلی صف باقی می‌مانند.").setPositiveButton("حذف",(d,w)->{try(Store db=new Store(this)){db.getWritableDatabase().delete("banks","_id=?",new String[]{Long.toString(id)});}banks();}).setNegativeButton("انصراف",null).show());
        button("بازگشت",this::banks);
    }
    private void diagnose(){
        screen("عیب‌یابی دریافت و ارسال — ۰٫۴");
        button("بازگشت",this::home);
        label("صندوق و فیلترها بدون وارد کردن پیام به صف بررسی می‌شوند. اتصال و مقصد بله با یک درخواست بررسی می‌شود؛ پیام ارسال نمی‌شود. گزارش شامل متن پیامک، شماره کارت یا توکن نیست.",14);
        TextView result=new TextView(this);result.setTextSize(15);result.setTextIsSelectable(true);
        Button run=new Button(this);run.setText("شروع عیب‌یابی");page.addView(run);
        Button copy=new Button(this);copy.setText("کپی گزارش عیب‌یابی");copy.setEnabled(false);page.addView(copy);page.addView(result);
        copy.setOnClickListener(v->{((android.content.ClipboardManager)getSystemService(CLIPBOARD_SERVICE)).setPrimaryClip(ClipData.newPlainText("BankRelay Diagnose",result.getText()));toast("گزارش کپی شد");});
        run.setOnClickListener(v->{run.setEnabled(false);copy.setEnabled(false);result.setText("در حال بررسی صندوق، فیلترها، صف و بله؛ ارتباط ممکن است تا حدود ۴۰ ثانیه طول بکشد…");
            new Thread(()->{
                String report;
                try{report=RelayDiagnostics.run(getApplicationContext());}catch(Exception e){report="عیب‌یابی کامل نشد: "+e.getClass().getSimpleName();}
                final String text=report;
                runOnUiThread(()->{if(!isFinishing()&&!isDestroyed()){result.setText(text);run.setEnabled(true);copy.setEnabled(true);}});
            },"relay-diagnose").start();
        });
    }
    private void diagnostics(){
        screen("گزارش دریافت پیامک — ۰٫۴");button("بازگشت",this::home);button("تازه‌سازی",this::diagnostics);
        label("مجوز دریافت: "+(checkSelfPermission(Manifest.permission.RECEIVE_SMS)==PackageManager.PERMISSION_GRANTED?"دارد":"ندارد")+
          "\nمجوز خواندن صندوق: "+(checkSelfPermission(Manifest.permission.READ_SMS)==PackageManager.PERMISSION_GRANTED?"دارد":"ندارد")+
          "\nدریافت: "+(prefs.getBoolean("enabled",false)?"روشن":"خاموش")+
          " | ارسال خودکار: "+(prefs.getBoolean("auto",false)?"روشن":"خاموش"),16);
        button("عیب‌یابی کامل (Diagnose)",this::diagnose);
        label("آخرین بررسی صندوق: "+prefs.getString("last_recovery_result","هنوز نتیجه‌ای ذخیره نشده")+"\nمبدأ بازیابی: "+android.text.format.DateFormat.format("yyyy/MM/dd HH:mm:ss",prefs.getLong("recovery_since",System.currentTimeMillis())),14);
        button("دادن مجوز پیامک",()->requestPermissions(new String[]{Manifest.permission.RECEIVE_SMS,Manifest.permission.READ_SMS},12));
        button("تنظیمات برنامه و باتری",()->{try{startActivity(new Intent(android.provider.Settings.ACTION_APPLICATION_DETAILS_SETTINGS,android.net.Uri.parse("package:"+getPackageName())));}catch(Exception e){toast("تنظیمات برنامه را از تنظیمات گوشی باز کنید");}});
        label("در تنظیمات Poco، شروع خودکار پس‌زمینه را روشن و محدودیت باتری این برنامه را بردارید. اگر برنامه را توقف اجباری کنید، دوباره بازش کنید.",14);
        Switch recovery=new Switch(this);recovery.setText("بازیابی پیامک‌های جدید از صندوق");recovery.setChecked(prefs.getBoolean("recovery",false));page.addView(recovery);
        label("بازیابی فقط از زمان روشن کردن این گزینه، حداکثر ۲۴ ساعت اخیر است. پیام‌های قدیمی‌تر ارسال نمی‌شوند. فقط پیامک بانک مطابق فیلتر وارد صف می‌شود. بررسی دوره‌ای تابع محدودیت‌های اندروید است.",14);
        recovery.setOnCheckedChangeListener((v,on)->{
            if(on&&checkSelfPermission(Manifest.permission.READ_SMS)!=PackageManager.PERMISSION_GRANTED){recovery.setChecked(false);requestPermissions(new String[]{Manifest.permission.READ_SMS},12);toast("پس از دادن مجوز، بازیابی را روشن کنید");return;}
            prefs.edit().putBoolean("recovery",on).putLong("recovery_since",System.currentTimeMillis()).commit();if(on)SendJob.schedule(this);
        });
        button("بررسی صندوق از زمان فعال‌سازی",()->{new Thread(()->{String result=InboxRecovery.scan(this);SendJob.schedule(this);runOnUiThread(()->{if(!isDestroyed()){toast(result);diagnostics();}});},"sms-recovery").start();});
        label("گزارش فقط فرستنده، سیم و علت پذیرش یا رد را نگه می‌دارد؛ متن پیامک ردشده ذخیره نمی‌شود.",14);
        try(Store db=new Store(this);Cursor c=db.getReadableDatabase().rawQuery("SELECT at,sender,detail FROM diagnostics ORDER BY _id DESC LIMIT 100",null)){
            if(c.getCount()==0)label("هنوز رویدادی ثبت نشده؛ بعد از نصب یک پیامک جدید دریافت کنید.",16);
            while(c.moveToNext())label(android.text.format.DateFormat.format("MM/dd HH:mm:ss",c.getLong(0))+" • "+c.getString(1)+"\n"+c.getString(2),14);
        }
    }
    private String transaction="";
    private boolean busy=false;
    interface Task { String run() throws Exception; }
    private void network(Task task,Runnable success) {
        if(busy){toast("در حال ارتباط با بله…");return;}busy=true;
        new Thread(()->{String result;boolean ok;
            try{result=task.run();ok=true;}catch(Exception e){result=e instanceof BaleApi.RpcError?e.getMessage():"ارتباط یا پاسخ بله تأیید نشد. اینترنت و وضعیت حساب را بررسی کنید.";ok=false;}
            final String message=result;final boolean done=ok;
            runOnUiThread(()->{busy=false;if(isFinishing()||isDestroyed())return;new AlertDialog.Builder(this).setMessage(message).setPositiveButton("باشه",null).show();if(done)success.run();});
        },"bale-connect").start();
    }
    private void connection() {
        screen("اتصال حساب شخصی بله");
        label("نسخه آزمایشی غیررسمی؛ کد فقط به سرور بله فرستاده می‌شود. ابتدا ربات را در بله خودت شروع کرده باش.",14);
        label(Vault.has(this)?"نشست ورود روی این گوشی ذخیره شده":"وارد نشده‌اید",16);
        if(!Vault.has(this)) {
            EditText phone=field("شماره موبایل بله","");phone.setInputType(android.text.InputType.TYPE_CLASS_PHONE);
            button("دریافت کد ورود",()->network(()->{transaction=new BaleApi(this).start(phone.getText().toString());return "کد درخواست شد؛ پیامک یا پیام ورود در بله را بررسی کن.";},()->{}));
            EditText code=field("کد ورود","");code.setInputType(android.text.InputType.TYPE_CLASS_NUMBER|android.text.InputType.TYPE_NUMBER_VARIATION_PASSWORD);code.setSaveEnabled(false);
            button("تأیید کد",()->{if(transaction.isEmpty()){toast("ابتدا کد را درخواست کنید");return;}String value=DepositFilter.normalize(code.getText().toString().trim());code.setText("");network(()->{new BaleApi(this).verify(transaction,value,false);return "ورود انجام شد. حالا مقصد را بررسی و پیام آزمایشی بفرست.";},this::connection);});
            EditText password=field("رمز دومرحله‌ای (فقط اگر بله درخواست کرد)","");password.setInputType(129);password.setSaveEnabled(false);
            button("تأیید رمز دومرحله‌ای",()->{if(transaction.isEmpty()){toast("ابتدا کد را درخواست کنید");return;}String value=password.getText().toString();password.setText("");network(()->{new BaleApi(this).verify(transaction,value,true);return "ورود انجام شد";},this::connection);});
        }
        EditText target=field("نام کاربری ربات",prefs.getString("target","@Jay24x7Pusbank_bot"));
        button("بررسی و ذخیره مقصد",()->{
            String s=target.getText().toString().trim();if(!s.matches("@[A-Za-z0-9_]{3,64}")){toast("نام کاربری را با @ وارد کنید");return;}
            network(()->{long id=new BaleApi(this).resolve(s);prefs.edit().putString("target",s).putString("verified_target",s).putLong("peer",id).putBoolean("tested",false).putBoolean("auto",false).commit();return "مقصد پیدا شد: "+s+"\nشناسه: "+id;},this::connection);
        });
        button("ارسال پیام آزمایشی به ربات ذخیره‌شده",()->{
            long peer=prefs.getLong("peer",0);if(peer<=0){toast("ابتدا مقصد را بررسی کنید");return;}
            new AlertDialog.Builder(this).setMessage("پیام «آزمایش اتصال پیام‌رسان واریز» به "+prefs.getString("verified_target","")+" فرستاده شود؟").setNegativeButton("انصراف",null).setPositiveButton("ارسال",(d,w)->network(()->{new BaleApi(this).send(peer,new java.security.SecureRandom().nextLong()&Long.MAX_VALUE,"آزمایش اتصال پیام‌رسان واریز");prefs.edit().putBoolean("tested",true).commit();return "بله ارسال پیام آزمایشی را تأیید کرد.";},this::connection)).show();
        });
        Switch auto=new Switch(this);auto.setText("ارسال خودکار واریزهای جدید");auto.setChecked(prefs.getBoolean("auto",false));page.addView(auto);
        auto.setOnCheckedChangeListener((v,on)->{if(on&&(!Vault.has(this)||!prefs.getBoolean("tested",false))){auto.setChecked(false);toast("ابتدا ورود، بررسی مقصد و ارسال آزمایشی را کامل کنید");return;}prefs.edit().putBoolean("auto",on).commit();if(on)SendJob.schedule(this);});
        label("پیام‌های جمع‌آوری‌شده پیش از فعال‌سازی خودکار ارسال نمی‌شوند. با صفحه خاموش، اندروید ممکن است ارسال را به تأخیر بیندازد. نتیجه نامشخص خودکار تکرار نمی‌شود.",14);
        if(Vault.has(this))button("حذف نشست از این گوشی",()->{prefs.edit().putBoolean("auto",false).putBoolean("tested",false).remove("peer").remove("verified_target").commit();Vault.clear(this);try(Store db=new Store(this)){db.getWritableDatabase().execSQL("UPDATE queue SET status='awaiting_connection' WHERE status='pending'");}transaction="";toast("نشست محلی حذف شد؛ برای لغو نشست از تنظیمات دستگاه‌های بله استفاده کنید.");connection();});
        button("بازگشت",this::home);
    }
    private void queue() {
        screen("صف پیام‌ها");button("بازگشت",this::home);button("تازه‌سازی",this::queue);
        try(Store db=new Store(this);Cursor c=db.getReadableDatabase().rawQuery("SELECT bank,body,received,target,status,error FROM queue ORDER BY _id DESC LIMIT 100",null)){
            if(c.getCount()==0)label("هنوز پیام واریزی در صف نیست.",16);
            while(c.moveToNext()) {
                label(c.getString(0)+" • "+android.text.format.DateFormat.format("yyyy/MM/dd HH:mm",c.getLong(2)),17);
                String state=c.getString(4);String title=state.equals("sent")?"ارسال به بله تأیید شد":state.equals("pending")?"در صف ارسال":state.equals("sending")?"در حال ارسال":state.equals("uncertain")?"نتیجه نامشخص — بله را بررسی کنید":"ارسال خودکار برای این پیام فعال نبوده";
                label(title+" • "+c.getString(3),14);label(c.getString(1),16);if(!c.getString(5).isEmpty())label(c.getString(5),14);
            }
        }
        button("حذف همه پیام‌های صف",()->new AlertDialog.Builder(this).setMessage("متن پیام‌های ذخیره‌شده پاک شود؟").setPositiveButton("پاک کردن",(d,w)->{try(Store db=new Store(this)){db.getWritableDatabase().delete("queue",null,null);}queue();}).setNegativeButton("انصراف",null).show());
    }
}
