package ir.jay.bankrelay;
import android.content.*;
import android.security.keystore.*;
import android.util.Base64;
import java.security.KeyStore;
import javax.crypto.*;
import javax.crypto.spec.GCMParameterSpec;
import java.nio.charset.StandardCharsets;
/** Session remains in app-private storage encrypted with Android Keystore. */
public final class Vault {
 private static javax.crypto.SecretKey key()throws Exception{
  KeyStore k=KeyStore.getInstance("AndroidKeyStore");k.load(null);
  if(!k.containsAlias("bale-session")){KeyGenerator g=KeyGenerator.getInstance("AES","AndroidKeyStore");g.init(new KeyGenParameterSpec.Builder("bale-session",KeyProperties.PURPOSE_ENCRYPT|KeyProperties.PURPOSE_DECRYPT).setBlockModes(KeyProperties.BLOCK_MODE_GCM).setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE).build());g.generateKey();}
  return (javax.crypto.SecretKey)k.getKey("bale-session",null);
 }
 public static void save(Context c,String token)throws Exception{
  Cipher x=Cipher.getInstance("AES/GCM/NoPadding");x.init(Cipher.ENCRYPT_MODE,key());
  if(!c.getSharedPreferences("vault",0).edit().putString("iv",Base64.encodeToString(x.getIV(),Base64.NO_WRAP)).putString("token",Base64.encodeToString(x.doFinal(token.getBytes(StandardCharsets.UTF_8)),Base64.NO_WRAP)).commit())throw new Exception("ذخیره نشست ناموفق بود");
 }
 public static String token(Context c)throws Exception{
  SharedPreferences p=c.getSharedPreferences("vault",0);String s=p.getString("token","");if(s.isEmpty())return "";
  Cipher x=Cipher.getInstance("AES/GCM/NoPadding");x.init(Cipher.DECRYPT_MODE,key(),new GCMParameterSpec(128,Base64.decode(p.getString("iv",""),0)));return new String(x.doFinal(Base64.decode(s,0)),StandardCharsets.UTF_8);
 }
 public static boolean has(Context c){return c.getSharedPreferences("vault",0).contains("token");}
 public static void clear(Context c){c.getSharedPreferences("vault",0).edit().clear().commit();}
}
