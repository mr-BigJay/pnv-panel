import ir.jay.bankrelay.DepositFilter;
public class DepositFilterTest {
    static void check(boolean b, String name) { if (!b) throw new AssertionError(name); }
    public static void main(String[] args) {
        check(DepositFilter.isDeposit("پست بانک\nواريز به کارت: 6156\n+1,495,160", "واریز,بستانکار"), "Arabic yeh deposit");
        check(!DepositFilter.isDeposit("رمز یکبار مصرف واریز: 123456", "واریز"), "OTP excluded");
        check(!DepositFilter.isDeposit("برداشت از حساب؛ واریز به مقصد", "واریز"), "debit excluded");
        check(!DepositFilter.isDeposit("مانده 120000", "واریز"), "balance is not deposit");
        check(DepositFilter.sender("+98 912 123 4567").equals("09121234567"), "mobile normalization");
        check(!DepositFilter.sender("1000123").equals(DepositFilter.sender("123")), "shortcode exact match");
        check(DepositFilter.isDeposit("حساب شما بستانکار شد", "واریز،بستانکار"), "custom Persian comma");
        System.out.println("7 filter checks passed");
    }
}
