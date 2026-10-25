import ir.jay.bankrelay.*;
public class DiagnosticsTest {
 static int n;
 static void check(boolean b){n++;if(!b)throw new AssertionError("check "+n);}
 public static void main(String[] args){
  check(RelayDiagnostics.networkFailure(new java.net.UnknownHostException("private-host")).contains("DNS"));
  check(RelayDiagnostics.networkFailure(new javax.net.ssl.SSLException("secret")).contains("TLS"));
  check(RelayDiagnostics.networkFailure(new java.net.SocketTimeoutException()).contains("مهلت"));
  check(RelayDiagnostics.networkFailure(new java.net.ConnectException()).contains("برقرار نشد"));
  check(RelayDiagnostics.networkFailure(new BaleApi.RpcError("secret-token",401)).contains("401"));
  check(RelayDiagnostics.networkFailure(new BaleApi.RpcError("secret-token",429)).contains("محدودیت"));
  check(!RelayDiagnostics.networkFailure(new BaleApi.RpcError("secret-token")).contains("secret-token"));
  check(!RelayDiagnostics.networkFailure(new Exception("private-sms-body")).contains("private-sms-body"));
  check(InboxAccess.failure(new SecurityException("private-sms-body")).contains("SecurityException"));
  check(!InboxAccess.failure(new IllegalArgumentException("private-sms-body")).contains("private-sms-body"));
  System.out.println(n+" diagnostics checks passed");
 }
}
