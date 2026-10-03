package com.connectix.bankforwarder;

import android.Manifest;
import android.content.pm.PackageManager;
import android.os.Bundle;
import android.widget.EditText;
import android.widget.Button;
import android.widget.TextView;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;

/**
 * Connectix Bank SMS Forwarder - Simple version
 * Listens to bank SMS and forwards to panel webhook
 */
public class MainActivity extends AppCompatActivity {
    EditText webhookUrlInput, secretInput, senderFilterInput;
    Button saveBtn, testBtn;
    TextView logView;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webhookUrlInput = findViewById(R.id.webhookUrl);
        secretInput = findViewById(R.id.secret);
        senderFilterInput = findViewById(R.id.senderFilter);
        saveBtn = findViewById(R.id.saveBtn);
        testBtn = findViewById(R.id.testBtn);
        logView = findViewById(R.id.logView);

        // Request SMS permission
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.RECEIVE_SMS) != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(this, new String[]{Manifest.permission.RECEIVE_SMS, Manifest.permission.READ_SMS, Manifest.permission.INTERNET}, 1);
        }

        // Load saved settings
        webhookUrlInput.setText(getSharedPreferences("cfg", MODE_PRIVATE).getString("webhook", "https://vpbotn.ir/api/bank-webhook"));
        secretInput.setText(getSharedPreferences("cfg", MODE_PRIVATE).getString("secret", ""));
        senderFilterInput.setText(getSharedPreferences("cfg", MODE_PRIVATE).getString("senders", "200033,200044,3000,2000111"));

        saveBtn.setOnClickListener(v -> {
            getSharedPreferences("cfg", MODE_PRIVATE).edit()
                .putString("webhook", webhookUrlInput.getText().toString())
                .putString("secret", secretInput.getText().toString())
                .putString("senders", senderFilterInput.getText().toString())
                .apply();
            logView.setText("✅ تنظیمات ذخیره شد - منتظر پیامک بانک...");
        });

        testBtn.setOnClickListener(v -> {
            // Test webhook
            String url = webhookUrlInput.getText().toString() + "?secret=" + secretInput.getText().toString() + "&amount=290147&tracking=TEST123&sms=Test";
            new Thread(() -> {
                try {
                    java.net.URL u = new java.net.URL(url);
                    java.net.HttpURLConnection c = (java.net.HttpURLConnection) u.openConnection();
                    c.setRequestMethod("GET");
                    int code = c.getResponseCode();
                    runOnUiThread(() -> logView.setText("تست وب‌هوک: HTTP " + code));
                } catch (Exception e) {
                    runOnUiThread(() -> logView.setText("خطا: " + e.getMessage()));
                }
            }).start();
        });
    }
}
