package ir.jay.bankrelay;
import android.content.*;
import android.os.Bundle;
import android.provider.Telephony;
import android.telephony.*;
public class SmsReceiver extends BroadcastReceiver {
 @Override public void onReceive(Context c,Intent intent){
  if(!Telephony.Sms.Intents.SMS_RECEIVED_ACTION.equals(intent.getAction()))return;
  c.getSharedPreferences("settings",0).edit().putLong("last_sms_broadcast",System.currentTimeMillis()).apply();
  try{
   SmsMessage[] parts=Telephony.Sms.Intents.getMessagesFromIntent(intent);
   if(parts==null||parts.length==0||parts[0]==null)throw new IllegalArgumentException();
   String from=parts[0].getOriginatingAddress();if(from==null)throw new IllegalArgumentException();
   StringBuilder body=new StringBuilder();
   for(SmsMessage part:parts){
    if(part==null||!DepositFilter.sender(from).equals(DepositFilter.sender(part.getOriginatingAddress())))throw new IllegalArgumentException();
    if(part.getMessageBody()!=null)body.append(part.getMessageBody());
   }
   Bundle b=intent.getExtras();
   int sub=SmsRules.subscription(b==null?null:b.get(SubscriptionManager.EXTRA_SUBSCRIPTION_INDEX),b==null?null:b.get("subscription"),b==null?null:b.get("subscription_id"));
   SmsIngress.accept(c,from,body.toString(),parts[0].getTimestampMillis()>0?parts[0].getTimestampMillis():System.currentTimeMillis(),sub,"دریافت مستقیم");
  }catch(Exception e){try(Store db=new Store(c)){db.diagnostic("?","خطای دریافت پیامک: "+e.getClass().getSimpleName());}}
 }
}
