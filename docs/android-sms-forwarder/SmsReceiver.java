package com.connectix.bankforwarder;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.telephony.SmsMessage;
import android.util.Log;
import java.util.regex.*;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;

/**
 * Receives bank SMS and forwards to panel
 */
public class SmsReceiver extends BroadcastReceiver {
    
    @Override
    public void onReceive(Context context, Intent intent) {
        if (!intent.getAction().equals("android.provider.Telephony.SMS_RECEIVED")) return;
        
        Bundle bundle = intent.getExtras();
        if (bundle == null) return;
        
        Object[] pdus = (Object[]) bundle.get("pdus");
        if (pdus == null) return;
        
        String webhook = context.getSharedPreferences("cfg", Context.MODE_PRIVATE).getString("webhook", "");
        String secret = context.getSharedPreferences("cfg", Context.MODE_PRIVATE).getString("secret", "");
        String senderFilter = context.getSharedPreferences("cfg", Context.MODE_PRIVATE).getString("senders", "200033,200044");
        
        if (webhook.isEmpty() || secret.isEmpty()) return;
        
        for (Object pdu : pdus) {
            SmsMessage sms = SmsMessage.createFromPdu((byte[]) pdu);
            String sender = sms.getOriginatingAddress();
            String body = sms.getMessageBody();
            
            Log.d("BankForwarder", "SMS from " + sender + ": " + body);
            
            // Check if sender is bank (filter)
            boolean isBank = false;
            for (String allowed : senderFilter.split(",")) {
                if (sender != null && sender.contains(allowed.trim())) {
                    isBank = true;
                    break;
                }
            }
            // Also check if body contains bank keywords
            if (!isBank) {
                if (body.contains("واریز") || body.contains("ریال") || body.contains("مبلغ")) {
                    // Might be bank, still process but log
                    isBank = true;
                }
            }
            
            if (!isBank) continue;
            
            // Extract amount
            int amount = extractAmount(body);
            if (amount < 1000) continue;
            
            // Extract tracking
            String tracking = extractTracking(body);
            
            // Forward to server in background
            forwardToServer(context, webhook, secret, amount, body, tracking, sender);
        }
    }
    
    private int extractAmount(String sms) {
        // Patterns
        String[] patterns = {
            "واریز\\s*([\\d,]+)\\s*ریال",
            "مبلغ\\s*([\\d,]+)\\s*ریال",
            "([\\d,]+)\\s*ریال\\s*واریز",
            "واریز\\s*([\\d,]+)\\s*تومان",
        };
        for (String pat : patterns) {
            Pattern p = Pattern.compile(pat);
            Matcher m = p.matcher(sms);
            if (m.find()) {
                String num = m.group(1).replaceAll("[,،]", "");
                try {
                    int amount = Integer.parseInt(num);
                    if (amount > 100000) amount = amount / 10; // Rial to Toman
                    return amount;
                } catch (Exception e) {}
            }
        }
        return 0;
    }
    
    private String extractTracking(String sms) {
        Pattern p = Pattern.compile("پیگیری\\s*[:]?\\s*(\\d{6,20})");
        Matcher m = p.matcher(sms);
        if (m.find()) return m.group(1);
        return "";
    }
    
    private void forwardToServer(Context ctx, String webhook, String secret, int amount, String sms, String tracking, String sender) {
        new Thread(() -> {
            try {
                String urlStr = webhook + "?secret=" + URLEncoder.encode(secret, "UTF-8") 
                    + "&amount=" + amount 
                    + "&tracking=" + URLEncoder.encode(tracking, "UTF-8")
                    + "&sender=" + URLEncoder.encode(sender, "UTF-8")
                    + "&sms=" + URLEncoder.encode(sms, "UTF-8");
                
                URL url = new URL(urlStr);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("GET");
                conn.setConnectTimeout(10000);
                conn.setReadTimeout(10000);
                
                int code = conn.getResponseCode();
                BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                StringBuilder resp = new StringBuilder();
                String line;
                while ((line = reader.readLine()) != null) resp.append(line);
                
                Log.d("BankForwarder", "Forwarded amount " + amount + " => HTTP " + code + " resp: " + resp.toString());
            } catch (Exception e) {
                Log.e("BankForwarder", "Forward failed: " + e.getMessage());
            }
        }).start();
    }
}
