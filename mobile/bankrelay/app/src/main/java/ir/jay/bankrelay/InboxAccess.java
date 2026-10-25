package ir.jay.bankrelay;

import android.content.Context;
import android.database.Cursor;
import android.provider.Telephony;

/** Core columns work even when an OEM provider omits optional SIM/sent-time columns. */
public final class InboxAccess {
 public static Cursor query(Context c,String selection,String[] args){
  try {
   return c.getContentResolver().query(Telephony.Sms.Inbox.CONTENT_URI,
    new String[]{"_id","address","body","date","sub_id","date_sent"},selection,args,"date DESC");
  } catch(IllegalArgumentException | android.database.sqlite.SQLiteException e){
   return c.getContentResolver().query(Telephony.Sms.Inbox.CONTENT_URI,
    new String[]{"_id","address","body","date"},selection,args,"date DESC");
  }
 }
 public static int subscription(Cursor c){int i=c.getColumnIndex("sub_id");return i<0||c.isNull(i)?-1:c.getInt(i);}
 public static long sentAt(Cursor c){int i=c.getColumnIndex("date_sent");return i>=0&&!c.isNull(i)&&c.getLong(i)>0?c.getLong(i):c.getLong(c.getColumnIndexOrThrow("date"));}
 public static String failure(Exception e){
  if(e instanceof SecurityException)return "اندروید خواندن واقعی صندوق را رد کرد؛ مجوز SMS و محدودیت‌های برنامه در تنظیمات گوشی بررسی شود (SecurityException).";
  return "خطای صندوق ("+e.getClass().getSimpleName()+")؛ آزمون را تکرار و گزارش را بررسی کنید.";
 }
}
