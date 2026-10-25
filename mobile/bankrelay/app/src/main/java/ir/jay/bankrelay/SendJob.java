package ir.jay.bankrelay;
import android.app.job.*;
import android.content.*;
import android.database.Cursor;
import java.util.concurrent.atomic.AtomicBoolean;
public class SendJob extends JobService {
 private static final AtomicBoolean running=new AtomicBoolean(false);
 private volatile boolean stopped;
 public static void schedule(Context c){
  JobScheduler j=c.getSystemService(JobScheduler.class);
  ComponentName n=new ComponentName(c,SendJob.class);
  if(j.getPendingJob(11)==null)j.schedule(new JobInfo.Builder(11,n).setRequiredNetworkType(JobInfo.NETWORK_TYPE_ANY).setMinimumLatency(1000).setPersisted(true).build());
  if(j.getPendingJob(12)==null)j.schedule(new JobInfo.Builder(12,n).setRequiredNetworkType(JobInfo.NETWORK_TYPE_ANY).setPeriodic(15*60*1000).setPersisted(true).build());
 }
 public boolean onStartJob(JobParameters p){
  if(!running.compareAndSet(false,true))return false;stopped=false;
  getSharedPreferences("settings",0).edit().putLong("last_job_start",System.currentTimeMillis()).apply();
  new Thread(()->{try{drain();}finally{running.set(false);jobFinished(p,false);}},"bale-queue").start();return true;
 }
 public boolean onStopJob(JobParameters p){stopped=true;return true;}
 private void drain(){
  InboxRecovery.scan(this);
  if(!Vault.has(this))return;
  try(Store db=new Store(this)){
   // A prior process may have died after sending. Never retry those blindly.
   db.getWritableDatabase().execSQL("UPDATE queue SET status='uncertain' WHERE status='sending'");
   for(int i=0;i<20&&!stopped;i++){
    if(!getSharedPreferences("settings",0).getBoolean("auto",false))return;
    long row,peer,mid;String body;
    try(Cursor c=db.getReadableDatabase().rawQuery("SELECT _id,peer,mid,body FROM queue WHERE status='pending' ORDER BY _id LIMIT 1",null)){
     if(!c.moveToFirst())return;row=c.getLong(0);peer=c.getLong(1);mid=c.getLong(2);body=c.getString(3);
    }
    if(peer<=0||mid<=0)return;
    ContentValues v=new ContentValues();v.put("status","sending");
    if(db.getWritableDatabase().update("queue",v,"_id=? AND status='pending'",new String[]{""+row})!=1)continue;
    String status="uncertain", error="";
    try{new BaleApi(this).send(peer,mid,body);status="sent";}
    catch(Exception e){error=e instanceof BaleApi.RpcError?e.getMessage():"پاسخ ارسال دریافت نشد؛ قبل از تکرار، بله را بررسی کنید";}
    v=new ContentValues();v.put("status",status);v.put("error",error);db.getWritableDatabase().update("queue",v,"_id=?",new String[]{""+row});
    if(!status.equals("sent"))return;
   }
  }
 }
}
