package ir.jay.bankrelay;
import android.content.Context;
import java.io.*;
import java.net.*;
import java.nio.ByteBuffer;
import java.nio.charset.StandardCharsets;
/** Protocol mapping adapted from MIT Aiobale, commit recorded in THIRD-PARTY.md. */
public final class BaleApi {
 private final Context context;
 public BaleApi(Context c){context=c.getApplicationContext();}
 public static class RpcError extends IOException {
  public final int httpStatus;
  public RpcError(String s){this(s,0);}
  public RpcError(String s,int httpStatus){super(s);this.httpStatus=httpStatus;}
 }
 public Proto.Fields call(String service,String method,Proto data,boolean auth)throws Exception{
  HttpURLConnection c=(HttpURLConnection)new URL("https://next-ws.bale.ai/"+service+"/"+method).openConnection();
  c.setInstanceFollowRedirects(false);c.setConnectTimeout(15000);c.setReadTimeout(25000);c.setRequestMethod("POST");c.setDoOutput(true);
  c.setRequestProperty("Content-Type","application/grpc-web+proto");c.setRequestProperty("Origin","https://web.bale.ai");c.setRequestProperty("X-Grpc-Web","1");
  c.setRequestProperty("User-Agent","Mozilla/5.0 Chrome/138.0.0.0");
  String[][] meta={{"app_version","113466"},{"browser_type","1"},{"browser_version","138.0.0.0"},{"os_type","3"},{"session_id",Long.toString(System.currentTimeMillis())}};
  for(String[] m:meta){c.setRequestProperty(m[0],m[1]);c.setRequestProperty("mt_"+m[0],m[1]);}
  if(auth){String token=Vault.token(context);if(token.isEmpty())throw new RpcError("ابتدا وارد بله شوید");c.setRequestProperty("Cookie","access_token="+token);}
  byte[] raw=data.data();byte[] frame=ByteBuffer.allocate(5+raw.length).put((byte)0).putInt(raw.length).put(raw).array();c.setFixedLengthStreamingMode(frame.length);
  try{
   try(OutputStream o=c.getOutputStream()){o.write(frame);}
   int http=c.getResponseCode();if(http!=200)throw new RpcError("HTTP "+http,http);
   String status=c.getHeaderField("grpc-status"), message=c.getHeaderField("grpc-message");
   if(status!=null&&!status.equals("0"))throw new RpcError(clean(message,status));
   if(message!=null&&!message.isEmpty()&&status==null)throw new RpcError(clean(message,"خطای بله"));
   ByteArrayOutputStream b=new ByteArrayOutputStream();try(InputStream in=c.getInputStream()){byte[] buf=new byte[4096];int n;while((n=in.read(buf))!=-1){if(b.size()+n>2097152)throw new IOException("Response too large");b.write(buf,0,n);}}
   byte[] all=b.toByteArray(), payload=null;int pos=0;boolean success="0".equals(status);
   while(pos<all.length){if(all.length-pos<5)throw new IOException("Invalid gRPC frame");int flag=all[pos]&255;int len=ByteBuffer.wrap(all,pos+1,4).getInt();pos+=5;if(len<0||len>all.length-pos)throw new IOException("Invalid gRPC length");
    if(flag==128){String tr=new String(all,pos,len,StandardCharsets.UTF_8);String st=null, msg=null;for(String line:tr.split("\r?\n")){int i=line.indexOf(':');if(i<0)continue;String k=line.substring(0,i).trim();String v=line.substring(i+1).trim();if(k.equalsIgnoreCase("grpc-status"))st=v;if(k.equalsIgnoreCase("grpc-message"))msg=v;}
     if(st!=null&&!st.equals("0"))throw new RpcError(clean(msg,st));success|="0".equals(st);
    }else if(flag==0){if(payload!=null)throw new IOException("Multiple data frames");payload=java.util.Arrays.copyOfRange(all,pos,pos+len);}else throw new IOException("Unsupported gRPC compression");pos+=len;
   }
   if(payload==null||!success)throw new IOException("پاسخ بله قابل تأیید نیست");return Proto.parse(payload);
  }finally{c.disconnect();}
 }
 private static String clean(String text,String fallback){if(text==null||text.isEmpty())return fallback;try{return URLDecoder.decode(text,"UTF-8");}catch(Exception e){return fallback;}}
 public String start(String phone)throws Exception{
  String s=DepositFilter.normalize(phone).replaceAll("[ +()-]","");if(s.startsWith("09"))s="98"+s.substring(1);if(!s.matches("989[0-9]{9}"))throw new RpcError("شماره موبایل معتبر وارد کنید");
  android.content.SharedPreferences p=context.getSharedPreferences("settings",0);String device=p.getString("device","");if(device.isEmpty()){device=java.util.UUID.randomUUID().toString();p.edit().putString("device",device).commit();}
  Proto.Fields r=call("bale.auth.v1.Auth","StartPhoneAuth",new Proto().num(1,Long.parseLong(s)).num(2,4).str(3,"C28D46DC4C3A7A26564BFCC48B929086A95C93C98E789A19847BEE8627DE4E7D").str(4,device).str(5,"BankRelay Android").num(9,1),false);
  String hash=r.str(1);if(hash.isEmpty())throw new IOException("پاسخ ورود ناقص است");return hash;
 }
 public void verify(String hash,String value,boolean password)throws Exception{
  Proto.Fields r=call("bale.auth.v1.Auth",password?"ValidatePassword":"ValidateCode",new Proto().str(1,hash).str(2,value).msg(3,new Proto().num(1,1)),false);
  String token=r.msg(4).str(1);if(token.isEmpty())throw new IOException("نشست در پاسخ ورود وجود ندارد");Vault.save(context,token);
 }
 public long resolve(String name)throws Exception{
  Proto.Fields r=call("bale.users.v1.Users","SearchContacts",new Proto().str(1,name),true);long id=r.msg(2).num(1);
  if(id<=0)throw new RpcError("ربات مقصد پیدا نشد");return id;
 }
 public void send(long id,long messageId,String body)throws Exception{
  Proto.Fields r=call("bale.messaging.v2.Messaging","SendMessage",new Proto().msg(1,new Proto().num(1,1).num(2,id)).num(2,messageId).msg(3,new Proto().msg(15,new Proto().str(1,body))).msg(6,new Proto().num(1,4).num(2,id)),true);
  if(r.num(2)<=0)throw new IOException("ارسال تأیید نشد؛ نتیجه نامشخص است");
 }
}
