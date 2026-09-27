package com.connectix.vpn

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import java.io.Serializable
import java.util.Locale

class V2rayNotificationReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != "V2RAY_CONNECTION_INFO") return

        try {
            val stateObj = intent.getSerializableExtra("STATE")
            val stateStr = stateObj?.toString() ?: ""
            val isConnected = stateStr.contains("CONNECTED") && !stateStr.contains("DISCONNECTED")

            val notificationManager = NotificationManagerCompat.from(context)

            if (!isConnected) {
                // When VPN disconnects, clean up notification 1
                try {
                    notificationManager.cancel(1)
                } catch (_: Exception) {}
                return
            }

            val duration = intent.getStringExtra("DURATION") ?: "00:00:00"
            val uploadSpeed = intent.getLongExtra("UPLOAD_SPEED", 0L)
            val downloadSpeed = intent.getLongExtra("DOWNLOAD_SPEED", 0L)

            val downStr = formatSpeed(downloadSpeed)
            val upStr = formatSpeed(uploadSpeed)

            val channelId = "A_FLUTTER_V2RAY_SERVICE_CH_ID"

            // Ensure channel exists with low importance so it updates silently every second
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                val sysMgr = context.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
                if (sysMgr != null && sysMgr.getNotificationChannel(channelId) == null) {
                    val channel = NotificationChannel(
                        channelId,
                        "Connectix VPN Service",
                        NotificationManager.IMPORTANCE_LOW
                    ).apply {
                        description = "نمایش سرعت و وضعیت اتصال وی‌پی‌ان"
                        setShowBadge(false)
                        setSound(null, null)
                        enableVibration(false)
                    }
                    sysMgr.createNotificationChannel(channel)
                }
            }

            val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
            } else {
                PendingIntent.FLAG_UPDATE_CURRENT
            }

            // Launch app intent when notification body is clicked
            val launchIntent = context.packageManager.getLaunchIntentForPackage(context.packageName)
            val contentPendingIntent = PendingIntent.getActivity(context, 200, launchIntent, flags)

            // Disconnect Action Intent (calls V2rayVPNService with STOP_SERVICE via reflection)
            var stopPendingIntent: PendingIntent? = null
            try {
                val serviceClass = Class.forName("com.github.blueboytm.flutter_v2ray.v2ray.services.V2rayVPNService")
                val cmdEnumClass = Class.forName("com.github.blueboytm.flutter_v2ray.v2ray.utils.AppConfigs\$V2RAY_SERVICE_COMMANDS")
                val stopCmd = cmdEnumClass.enumConstants?.firstOrNull { it.toString() == "STOP_SERVICE" }
                if (stopCmd != null) {
                    val stopIntent = Intent(context, serviceClass).apply {
                        putExtra("COMMAND", stopCmd as Serializable)
                    }
                    stopPendingIntent = PendingIntent.getService(context, 201, stopIntent, flags)
                }
            } catch (_: Exception) {}

            val builder = NotificationCompat.Builder(context, channelId)
                .setSmallIcon(R.mipmap.ic_launcher)
                .setContentTitle("Connectix VPN • متصل ($duration)")
                .setContentText("↓ $downStr   •   ↑ $upStr")
                .setStyle(NotificationCompat.BigTextStyle()
                    .bigText("سرعت دانلود: $downStr\nسرعت آپلود: $upStr\nمدت زمان اتصال: $duration"))
                .setPriority(NotificationCompat.PRIORITY_LOW)
                .setOngoing(true)
                .setOnlyAlertOnce(true)
                .setShowWhen(false)
                .setContentIntent(contentPendingIntent)

            if (stopPendingIntent != null) {
                builder.addAction(android.R.drawable.ic_menu_close_clear_cancel, "قطع اتصال", stopPendingIntent)
            }

            notificationManager.notify(1, builder.build())
        } catch (_: Exception) {}
    }

    private fun formatSpeed(bytesPerSec: Long): String {
        return when {
            bytesPerSec >= 1024 * 1024 -> String.format(Locale.US, "%.1f MB/s", bytesPerSec / (1024.0 * 1024.0))
            bytesPerSec >= 1024 -> String.format(Locale.US, "%d KB/s", bytesPerSec / 1024)
            else -> "$bytesPerSec B/s"
        }
    }
}
