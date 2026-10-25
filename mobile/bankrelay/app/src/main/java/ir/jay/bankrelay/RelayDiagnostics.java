package ir.jay.bankrelay;

import android.Manifest;
import android.app.ActivityManager;
import android.app.job.JobScheduler;
import android.content.*;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.net.*;
import android.os.PowerManager;
import java.util.*;

/** Read-only probe: never calls SmsIngress, enqueue, send, or changes the recovery watermark. */
public final class RelayDiagnostics {
 private static void line(StringBuilder b,String title,String detail){b.append(title).append("\n").append(detail).append("\n\n");}
 private static String time(long value){return value<=0?"ثبت نشده":android.text.format.DateFormat.format("yyyy/MM/dd HH:mm:ss",value).toString();}
 private static boolean granted(Context c,String permission){return c.checkSelfPermission(permission)==PackageManager.PERMISSION_GRANTED;}
 public static String run(Context c){
  StringBuilder b=new StringBuilder("BankRelay 0.4 — Diagnose\n"+time(System.currentTimeMillis())+"\n\n");
  SharedPreferences p=c.getSharedPreferences("settings",0);
  line(b,"۱. مجوزها","دریافت SMS: "+granted(c,Manifest.permission.RECEIVE_SMS)+" | خواندن Inbox: "+granted(c,Manifest.permission.READ_SMS));
  line(b,"۲. وضعیت دریافت","دریافت: "+p.getBoolean("enabled",false)+" | بازیابی: "+p.getBoolean("recovery",false)+
   "\nمبدأ بازیابی: "+time(p.getLong("recovery_since",0))+"\nآخرین ورود رویداد مستقیم SMS: "+time(p.getLong("last_sms_broadcast",0))+
   "\nنبود رویداد، به‌تنهایی مسدود بودن دریافت را ثابت نمی‌کند. برای تأیید، یک SMS جدید لازم است.");
  try(Store db=new Store(c)){
   ArrayList<String[]> banks=new ArrayList<>();
   try(Cursor r=db.getReadableDatabase().rawQuery("SELECT sender,sub,keywords FROM banks",null)){
    while(r.moveToNext())banks.add(new String[]{r.getString(0),r.getString(1),r.getString(2)});
   }
   line(b,"۳. فیلتر بانک",banks.isEmpty()?"خطا: هیچ بانکی تعریف نشده.":banks.size()+" بانک تعریف شده؛ آزمون فیلتر روی پیام‌های موجود انجام می‌شود.");
   if(!granted(c,Manifest.permission.READ_SMS))line(b,"۴. آزمون واقعی Inbox","اجرا نشد: ابتدا مجوز خواندن SMS را بدهید.");
   else try(Cursor r=InboxAccess.query(c,null,null)){
    if(r==null)throw new IllegalStateException("Null cursor");
    int total=0, eligible=0, older=0, matching=0, seen=0;
    long latest=0, since=Math.max(p.getLong("recovery_since",System.currentTimeMillis()),System.currentTimeMillis()-86400000L);
    Map<String,Integer> rejected=new LinkedHashMap<>();
    boolean limited=false, hasSim=r.getColumnIndex("sub_id")>=0;
    while(r.moveToNext()){
     if(total==500){limited=true;break;}total++;
     long date=r.getLong(3);latest=Math.max(latest,date);
     if(date<since)older++;else eligible++;
     String from=r.getString(1),body=r.getString(2),reason="فرستنده در بانک‌ها تعریف نشده";
     if(from==null||body==null){rejected.merge("فرستنده یا متن خالی",1,Integer::sum);continue;}
     boolean matched=false;
     for(String[] bank:banks){
      if(!DepositFilter.sender(from).equals(DepositFilter.sender(bank[0])))continue;
      reason=SmsRules.reason(from,bank[0],InboxAccess.subscription(r),Integer.parseInt(bank[1]),body,bank[2]);
      if(reason.isEmpty()){matched=true;break;}
     }
     if(matched)matching++;else rejected.merge(reason,1,Integer::sum);
     try(Cursor s=db.getReadableDatabase().rawQuery("SELECT _id FROM inbox_seen WHERE _id=?",new String[]{r.getString(0)})){if(s.moveToFirst())seen++;}
    }
    String detail="موفق: درخواست واقعی خواندن صندوق پاسخ داد.\nپیام‌های بررسی‌شده: "+total+(limited?" (محدود به ۵۰۰ پیام اخیر)":"")+
     "\nتازه‌ترین پیام: "+time(latest)+"\nدر بازه بازیابی: "+eligible+" | قبل از مبدأ/بیش از ۲۴ ساعت: "+older+
     "\nمطابق فیلتر بانک در نمونه: "+matching+" | قبلاً بررسی‌شده: "+seen+
     "\nاین شمارش‌ها مستقل‌اند؛ پیام مطابق فیلتر ممکن است قدیمی یا تکراری باشد.";
    if(total==0)detail+="\nصندوق برای این برنامه خالی برگردانده شد؛ اگر در برنامه SMS پیام دارید، پروفایل کاری/فضای دوم و محدودیت‌های گوشی را بررسی کنید.";
    else if(eligible==0)detail+="\nعلت خالی بودن بازیابی: همه پیام‌های نمونه قبل از مبدأ هستند؛ مجوز خواندن برقرار است.";
    if(!hasSim)detail+="\nستون سیم در دسترس نیست؛ فیلتر سیم خاص ممکن است رد شود. برای بررسی، هر دو سیم را انتخاب کنید.";
    for(Map.Entry<String,Integer> e:rejected.entrySet())detail+="\nرد فیلتر: "+e.getKey()+" = "+e.getValue();
    line(b,"۴. آزمون واقعی Inbox و فیلتر",detail);
   }catch(Exception e){line(b,"۴. آزمون واقعی Inbox",InboxAccess.failure(e));}
   StringBuilder queue=new StringBuilder();
   try(Cursor r=db.getReadableDatabase().rawQuery("SELECT status,COUNT(*) FROM queue GROUP BY status",null)){
    while(r.moveToNext())queue.append(r.getString(0)).append(": ").append(r.getInt(1)).append("\n");
   }
   line(b,"۵. صف ارسال",queue.length()==0?"صف خالی است؛ هنوز پیام پذیرفته‌شده‌ای ذخیره نشده.":queue+"pending: منتظر ارسال؛ sent: تأیید بله؛ uncertain: نامشخص، خودکار تکرار نمی‌شود؛ awaiting_connection: هنگام دریافت، ارسال آماده نبوده.");
  }catch(Exception e){line(b,"پایگاه داده محلی","خطا: "+e.getClass().getSimpleName());}
  line(b,"آخرین بررسی بازیابی",time(p.getLong("last_recovery",0))+"\n"+p.getString("last_recovery_result","گزارشی ذخیره نشده؛ بررسی دستی صندوق را اجرا کنید."));
  try{
   PowerManager power=c.getSystemService(PowerManager.class);
   ActivityManager activity=c.getSystemService(ActivityManager.class);
   JobScheduler jobs=c.getSystemService(JobScheduler.class);
   line(b,"۶. پس‌زمینه و زمان‌بندی","معاف از بهینه‌سازی باتری: "+power.isIgnoringBatteryOptimizations(c.getPackageName())+
    "\nمحدودیت پس‌زمینه: "+(android.os.Build.VERSION.SDK_INT>=28?activity.isBackgroundRestricted():"نامشخص")+
    "\nکار دوره‌ای ثبت‌شده: "+(jobs.getPendingJob(12)!=null)+"\nآخرین شروع کار: "+time(p.getLong("last_job_start",0))+
    "\nکار دوره‌ای فعلی به شبکه نیاز دارد. وضعیت Autostart در Poco خودکار قابل تأیید نیست؛ تنظیمات گوشی را بررسی کنید.");
  }catch(Exception e){line(b,"۶. پس‌زمینه","قابل بررسی نیست: "+e.getClass().getSimpleName());}
  try{
   ConnectivityManager cm=c.getSystemService(ConnectivityManager.class);
   NetworkCapabilities n=cm.getNetworkCapabilities(cm.getActiveNetwork());
   line(b,"۷. شبکه","شبکه فعال: "+(n!=null)+" | اینترنت تأییدشده اندروید: "+(n!=null&&n.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED))+
    " | VPN: "+(n!=null&&n.hasTransport(NetworkCapabilities.TRANSPORT_VPN)));
  }catch(Exception e){line(b,"۷. شبکه","قابل بررسی نیست: "+e.getClass().getSimpleName());}
  line(b,"۸. آمادگی ارسال","ارسال خودکار: "+p.getBoolean("auto",false)+" | تست ارسال قبلی: "+p.getBoolean("tested",false)+
   "\nپیام آزمایشی، تحویل پیامک بانکی یا تأیید پرداخت را ثابت نمی‌کند.");
  try{
   if(Vault.token(c).isEmpty())line(b,"۹. نشست و مقصد بله","وارد حساب نشده‌اید؛ از مقصد بله ورود را انجام دهید.");
   else{
    String target=p.getString("target","");
    if(!target.matches("@[A-Za-z0-9_]{3,64}"))line(b,"۹. نشست و مقصد بله","مقصد ذخیره‌شده معتبر نیست.");
    else{
     long peer=new BaleApi(c).resolve(target);
     line(b,"۹. نشست و مقصد بله",peer==p.getLong("peer",0)&&target.equals(p.getString("verified_target",""))?
      "موفق: بله درخواست احراز هویت‌شده را پاسخ داد و شناسه مقصد با تنظیمات یکسان است.":"خطا: مقصد پیدا شد ولی با مقصد تأییدشده برابر نیست؛ بررسی و ذخیره مقصد را انجام دهید.");
    }
   }
  }catch(Exception e){line(b,"۹. نشست و مقصد بله",networkFailure(e));}
  line(b,"۱۰. تحویل نهایی","این آزمون پیام ارسال نمی‌کند. ارسال واقعی از بخش مقصد بله آزمایش می‌شود. رسیدن به بله، خوانده‌شدن توسط ربات و تأیید پرداخت در پنل مراحل جدا هستند؛ این اپ پاسخ پنل را دریافت نمی‌کند، پس آن مرحله تأیید نشده است.");
  return b.toString();
 }
 public static String networkFailure(Exception e){
  if(e instanceof BaleApi.RpcError && ((BaleApi.RpcError)e).httpStatus>0){
   int status=((BaleApi.RpcError)e).httpStatus;
   return "پاسخ HTTP "+status+((status==401||status==403)?"؛ دسترسی رد شد. نشست یا محدودیت شبکه/سرور را بررسی کنید.":status==429?"؛ محدودیت درخواست؛ کمی بعد تکرار کنید.":"؛ سرور پاسخ موفق نداد.");
  }
  if(e instanceof java.net.UnknownHostException)return "خطای DNS: نام سرور بله پیدا نشد؛ اینترنت یا VPN را بررسی کنید.";
  if(e instanceof javax.net.ssl.SSLException)return "خطای TLS: ساعت گوشی و اتصال امن/VPN را بررسی کنید.";
  if(e instanceof java.net.SocketTimeoutException)return "مهلت ارتباط با بله تمام شد؛ شبکه یا VPN را بررسی کنید.";
  if(e instanceof java.net.ConnectException)return "اتصال به سرور بله برقرار نشد.";
  return "درخواست بله تأیید نشد ("+e.getClass().getSimpleName()+"). نشست، مقصد و اتصال را در بخش مقصد بله بررسی کنید.";
 }
}
