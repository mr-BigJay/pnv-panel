package ir.jay.bankrelay;

import java.util.Locale;
import java.util.regex.Pattern;

public final class DepositFilter {
    private DepositFilter() {}
    public static String normalize(String s) {
        StringBuilder b = new StringBuilder();
        for (char c : (s == null ? "" : s).toCharArray()) {
            if (c >= '۰' && c <= '۹') c = (char)('0' + c - '۰');
            if (c >= '٠' && c <= '٩') c = (char)('0' + c - '٠');
            b.append(c == 'ي' ? 'ی' : c == 'ك' ? 'ک' : c);
        }
        return b.toString().toLowerCase(Locale.ROOT).replace('\u200c', ' ');
    }
    public static String sender(String s) {
        s = normalize(s).replaceAll("[\\p{Cf}\\p{Z}]", "").replaceAll("[\\s()\\-]", "");
        if (s.matches("(?:\\+98|0098)9[0-9]{9}")) s = "0" + s.substring(s.length()-10);
        return s;
    }
    public static boolean isDeposit(String text, String keywords) {
        String s = normalize(text);
        if (Pattern.compile("رمز|کد ورود|کد تایید|کد تأیید|otp|verification|برداشت|خرید از|پرداخت به").matcher(s).find()) return false;
        for (String k : normalize(keywords).split("[,،\\n]")) {
            if (!k.trim().isEmpty() && s.contains(k.trim())) return true;
        }
        return false;
    }
}
