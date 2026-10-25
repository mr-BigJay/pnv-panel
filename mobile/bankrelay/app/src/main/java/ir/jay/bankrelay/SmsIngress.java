package ir.jay.bankrelay;
import android.content.*;
import android.database.Cursor;
public final class SmsIngress {
 public static String accept(Context c,String from,String body,long time,int sub,String source){
  SharedPreferences p=c.getSharedPreferences("settings",0);
  try(Store db=new Store(c)){
   String reason="هیچ بانکی ثبت نشده";boolean matchingSender=false;
   if(!p.getBoolean("enabled",false)){reason="دریافت پیامک خاموش است";}
   else try(Cursor rows=db.getReadableDatabase().rawQuery("SELECT name,sender,sub,keywords FROM banks",null)){
    while(rows.moveToNext()){
     String r=SmsRules.reason(from,rows.getString(1),sub,rows.getInt(2),body,rows.getString(3));
     if(r.isEmpty()){
      boolean added=db.enqueue(rows.getString(0),DepositFilter.sender(from),body,time,sub,p.getString("target","@Jay24x7Pusbank_bot"));
      reason=added?"واریز وارد صف شد":"تکراری؛ قبلاً ثبت شده";
      db.diagnostic(from,source+" • سیم "+sub+" • "+reason);
      if(added)SendJob.schedule(c);
      return reason;
     }
     if(DepositFilter.sender(from).equals(DepositFilter.sender(rows.getString(1)))){matchingSender=true;reason=r;}
     else if(!matchingSender)reason="فرستنده مطابق بانک‌های ثبت‌شده نیست";
    }
   }
   db.diagnostic(from,source+" • سیم "+sub+" • "+reason);return reason;
  }
 }
}
