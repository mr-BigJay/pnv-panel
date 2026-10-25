import ir.jay.bankrelay.*;
public class SmsRulesTest {
 static int n=0;static void check(boolean b,String s){n++;if(!b)throw new AssertionError(s);}
 public static void main(String[]args){
  String sms="پست بانک\nواريز به کارت: 6156\n+1,495,160\n1405/07/29\n12:56\nمانده: 44,557,909 ريال";
  check(SmsRules.subscription(null,Long.valueOf(7))==7,"long subscription");
  check(SmsRules.subscription(Integer.valueOf(3),Long.valueOf(7))==3,"canonical int");
  check(SmsRules.subscription(-1,7L)==7,"invalid canonical fallback");
  check(SmsRules.subscription(null,"7",Long.MAX_VALUE)==-1,"unknown not coerced into a SIM");
  check(SmsRules.reason("POSTBANK","postbank",7,7,sms,"واریز,بستانکار").isEmpty(),"Postbank real format");
  check(SmsRules.reason("\u200fPOSTBANK\u00a0","postbank",7,7,sms,"واریز").isEmpty(),"invisible sender formatting");
  check(SmsRules.reason("POSTBANK","POSTBANK",-1,-1,sms,"واریز").isEmpty(),"all SIM with absent extra");
  check(!SmsRules.reason("POSTBANK","POSTBANK",-1,7,sms,"واریز").isEmpty(),"specific SIM fails closed");
  check(!SmsRules.reason("POSTBANK","POSTBANK",8,7,sms,"واریز").isEmpty(),"wrong SIM");
  check(!SmsRules.reason("OTHERBANK","POSTBANK",7,7,sms,"واریز").isEmpty(),"wrong sender");
  check(!SmsRules.reason("POSTBANK","بانک من",7,7,sms,"واریز").isEmpty(),"contact alias not sender");
  check(!SmsRules.reason("POSTBANK","POSTBANK",7,7,"رمز واریز 123456","واریز").isEmpty(),"OTP rejected");
  check(!SmsRules.reason("POSTBANK","POSTBANK",7,7,"برداشت برای واریز","واریز").isEmpty(),"debit rejected");
  System.out.println(n+" SMS regression checks passed");
 }
}
