package ir.jay.bankrelay;
/** Shared, testable rules for broadcasts and inbox recovery. */
public final class SmsRules {
 private SmsRules(){}
 public static int subscription(Object... values){
  for(Object v:values) if(v instanceof Number){long n=((Number)v).longValue();if(n>=0&&n<=Integer.MAX_VALUE)return (int)n;}
  return -1;
 }
 public static String reason(String actual,String configured,int actualSub,int selectedSub,String text,String keywords){
  if(!DepositFilter.sender(actual).equals(DepositFilter.sender(configured)))return "فرستنده مطابق نیست";
  if(selectedSub!=-1&&actualSub!=selectedSub)return actualSub<0?"شناسه سیم‌کارت در پیام موجود نیست؛ گزینه هر دو سیم را بررسی کنید":"سیم‌کارت مطابق نیست";
  if(!DepositFilter.isDeposit(text,keywords))return "عبارت واریز پیدا نشد یا پیام شامل رمز/برداشت است";
  return "";
 }
}
