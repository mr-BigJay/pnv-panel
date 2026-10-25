import ir.jay.bankrelay.Proto;
public class ProtoTest {
 static void yes(boolean b){if(!b)throw new AssertionError();}
 public static void main(String[] args)throws Exception{
  Proto.Fields r=Proto.parse(new Proto().num(1,Long.MAX_VALUE).str(2,"واریز").msg(3,new Proto().num(1,42)).data());
  yes(r.num(1)==Long.MAX_VALUE);yes(r.str(2).equals("واریز"));yes(r.msg(3).num(1)==42);
  yes(Proto.parse(new Proto().num(1,-1).data()).num(1)==-1);
  for(byte[] invalid:new byte[][]{{10,100},{0},{8,(byte)128},{15}}){boolean rejected=false;try{Proto.parse(invalid);}catch(java.io.IOException e){rejected=true;}yes(rejected);}
  System.out.println("8 protobuf checks passed");
 }
}
