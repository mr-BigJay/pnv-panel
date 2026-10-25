package ir.jay.bankrelay;
import android.Manifest;
import android.content.*;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.provider.Telephony;
import java.util.concurrent.atomic.AtomicBoolean;
public final class InboxRecovery {
 private static final AtomicBoolean running=new AtomicBoolean();
 public static String scan(Context context){
  SharedPreferences p=context.getSharedPreferences("settings",0);
  if(!p.getBoolean("recovery",false)||!p.getBoolean("enabled",false))return "بازیابی یا دریافت پیامک خاموش است";
  if(context.checkSelfPermission(Manifest.permission.READ_SMS)!=PackageManager.PERMISSION_GRANTED)return "مجوز خواندن پیامک داده نشده";
  if(!running.compareAndSet(false,true))return "بررسی در حال اجراست";
  long since=Math.max(p.getLong("recovery_since",System.currentTimeMillis()),System.currentTimeMillis()-86400000L);
  int checked=0;
  try(Store db=new Store(context);Cursor rows=InboxAccess.query(context,"date>=?",new String[]{Long.toString(since)})){
   if(rows==null)throw new IllegalStateException("Null cursor");
   while(checked<500&&rows.moveToNext()){
    if(!p.getBoolean("enabled",false)||!p.getBoolean("recovery",false))break;
    checked++;long id=rows.getLong(0);
    try(Cursor seen=db.getReadableDatabase().rawQuery("SELECT _id FROM inbox_seen WHERE _id=?",new String[]{Long.toString(id)})){if(seen.moveToFirst())continue;}
    String from=rows.getString(1),body=rows.getString(2);if(from==null||body==null)continue;
    SmsIngress.accept(context,from,body,InboxAccess.sentAt(rows),InboxAccess.subscription(rows),"بازیابی صندوق");
    ContentValues v=new ContentValues();v.put("_id",id);db.getWritableDatabase().insertWithOnConflict("inbox_seen",null,v,android.database.sqlite.SQLiteDatabase.CONFLICT_IGNORE);
   }
   String summary="دسترسی صندوق موفق؛ "+checked+" پیام در بازهٔ بازیابی. "+(checked==0?"پیامی پس از مبدأ پیدا نشد؛ این خطای مجوز نیست.":"نتیجهٔ فیلترها در گزارش دریافت است.");
   p.edit().putLong("last_recovery",System.currentTimeMillis()).putString("last_recovery_result",summary).apply();
   return "بررسی صندوق انجام شد؛ "+checked+" پیام بررسی شد. نتیجه در گزارش دریافت است.";
  }catch(Exception e){
   try(Store db=new Store(context)){db.diagnostic("—",InboxAccess.failure(e));}
   p.edit().putLong("last_recovery",System.currentTimeMillis()).putString("last_recovery_result",InboxAccess.failure(e)).apply();
   return InboxAccess.failure(e);
  }finally{running.set(false);}
 }
}
