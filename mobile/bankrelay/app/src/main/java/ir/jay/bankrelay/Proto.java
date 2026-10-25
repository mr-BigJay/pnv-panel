package ir.jay.bankrelay;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.util.*;
/** Minimal bounded protobuf codec: no reflection, no schema guessing. */
public final class Proto {
 private final ByteArrayOutputStream out=new ByteArrayOutputStream();
 private static void var(ByteArrayOutputStream o,long n){while((n & ~127L)!=0){o.write((int)(n&127)|128);n>>>=7;}o.write((int)n);}
 public Proto num(int f,long n){var(out,(long)f<<3);var(out,n);return this;}
 public Proto bytes(int f,byte[] b){var(out,((long)f<<3)|2);var(out,b.length);out.write(b,0,b.length);return this;}
 public Proto str(int f,String s){return bytes(f,s.getBytes(StandardCharsets.UTF_8));}
 public Proto msg(int f,Proto p){return bytes(f,p.data());}
 public byte[] data(){return out.toByteArray();}
 public static class Fields {
  final Map<Integer,List<Object>> map=new HashMap<>();
  public long num(int f){Object o=first(f);return o instanceof Long?(Long)o:0;}
  public Object first(int f){List<Object> l=map.get(f);return l==null?null:l.get(0);}
  public byte[] bytes(int f){Object o=first(f);return o instanceof byte[]?(byte[])o:new byte[0];}
  public String str(int f){return new String(bytes(f),StandardCharsets.UTF_8);}
  public Fields msg(int f)throws IOException{return parse(bytes(f));}
 }
 private static long read(byte[] b,int[] p)throws IOException{long n=0;for(int s=0;s<64;s+=7){if(p[0]>=b.length)throw new IOException("Truncated protobuf");int v=b[p[0]++]&255;if(s==63 && (v&254)!=0)throw new IOException("Overflow");n|=(long)(v&127)<<s;if((v&128)==0)return n;}throw new IOException("Invalid varint");}
 public static Fields parse(byte[] b)throws IOException{
  Fields r=new Fields();int[] p={0};while(p[0]<b.length){long t=read(b,p);int f=(int)(t>>>3), w=(int)(t&7);if(f<=0)throw new IOException("Invalid field");Object v;
   if(w==0)v=read(b,p);else{long length=w==1?8:w==5?4:w==2?read(b,p):-1;if(length<0||length>b.length-p[0])throw new IOException("Invalid field length");int n=(int)length;v=Arrays.copyOfRange(b,p[0],p[0]+n);p[0]+=n;}
   r.map.computeIfAbsent(f,k->new ArrayList<>()).add(v);
  }return r;
 }
}
