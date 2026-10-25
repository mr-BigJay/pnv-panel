package ir.jay.bankrelay;

import android.content.*;
import android.database.sqlite.*;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;

public class Store extends SQLiteOpenHelper {
    private final Context context;
    public Store(Context c) { super(c, "relay.db", null, 3); context=c.getApplicationContext(); }
    private void diagnosticSchema(SQLiteDatabase d){
        d.execSQL("CREATE TABLE IF NOT EXISTS diagnostics (_id INTEGER PRIMARY KEY, at INTEGER NOT NULL, sender TEXT NOT NULL, detail TEXT NOT NULL)");
        d.execSQL("CREATE TABLE IF NOT EXISTS inbox_seen (_id INTEGER PRIMARY KEY)");
    }
    public void onCreate(SQLiteDatabase d) {
        diagnosticSchema(d);
        d.execSQL("CREATE TABLE banks (_id INTEGER PRIMARY KEY, name TEXT NOT NULL, card TEXT NOT NULL, sender TEXT NOT NULL, sub INTEGER NOT NULL, keywords TEXT NOT NULL)");
        d.execSQL("CREATE TABLE queue (_id INTEGER PRIMARY KEY, fingerprint TEXT UNIQUE NOT NULL, bank TEXT NOT NULL, body TEXT NOT NULL, received INTEGER NOT NULL, target TEXT NOT NULL, status TEXT NOT NULL, peer INTEGER NOT NULL DEFAULT 0, mid INTEGER NOT NULL DEFAULT 0, error TEXT NOT NULL DEFAULT '')");
    }
    public void onUpgrade(SQLiteDatabase d,int a,int b) { diagnosticSchema(d); if(a<2){d.execSQL("ALTER TABLE queue ADD COLUMN peer INTEGER NOT NULL DEFAULT 0");d.execSQL("ALTER TABLE queue ADD COLUMN mid INTEGER NOT NULL DEFAULT 0");d.execSQL("ALTER TABLE queue ADD COLUMN error TEXT NOT NULL DEFAULT ''");} }
    public void diagnostic(String sender, String detail){
        ContentValues v=new ContentValues();v.put("at",System.currentTimeMillis());v.put("sender",sender==null?"?":sender);v.put("detail",detail);
        SQLiteDatabase d=getWritableDatabase();d.insertOrThrow("diagnostics",null,v);
        d.execSQL("DELETE FROM diagnostics WHERE _id NOT IN (SELECT _id FROM diagnostics ORDER BY _id DESC LIMIT 200)");
    }
    public boolean enqueue(String bank, String sender, String body, long time, int sub, String target) {
        SQLiteDatabase d=getWritableDatabase();d.beginTransaction();
        try {
            // SMS broadcast and inbox may use slightly different receive timestamps.
            try(android.database.Cursor c=d.rawQuery("SELECT _id FROM queue WHERE bank=? AND body=? AND received BETWEEN ? AND ? LIMIT 1",
                new String[]{bank,body,Long.toString(time-120000),Long.toString(time+120000)})){
                if(c.moveToFirst()){d.setTransactionSuccessful();return false;}
            }
            MessageDigest md = MessageDigest.getInstance("SHA-256");
            String key = sender + "\n" + time + "\n" + sub + "\n" + body;
            StringBuilder hash = new StringBuilder();
            for (byte b : md.digest(key.getBytes(StandardCharsets.UTF_8))) hash.append(String.format("%02x", b & 255));
            ContentValues v = new ContentValues();
            v.put("fingerprint", hash.toString()); v.put("bank", bank); v.put("body", body);
            v.put("received", time); v.put("target", target); SharedPreferences prefs=context.getSharedPreferences("settings",0);
            long peer=prefs.getLong("peer",0);
            boolean ready=prefs.getBoolean("auto",false)&&peer>0&&target.equals(prefs.getString("verified_target",""));
            v.put("status", ready?"pending":"awaiting_connection");v.put("peer",ready?peer:0);
            v.put("mid", new java.security.SecureRandom().nextLong() & Long.MAX_VALUE);
            long row=d.insertWithOnConflict("queue", null, v, SQLiteDatabase.CONFLICT_IGNORE);
            d.setTransactionSuccessful();return row!=-1;
        } catch (java.security.NoSuchAlgorithmException e) { throw new IllegalStateException(e); }
        finally {d.endTransaction();}
    }
}
