package com.connectix.vpn

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.core.app.NotificationCompat
import androidx.core.content.FileProvider
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File

class MainActivity: FlutterActivity() {
    private val CHANNEL = "com.connectix.vpn/updater"
    private val NOTIF_CHANNEL_ID = "connectix_vpn_status_ch"
    private val NOTIF_ID = 9999
    private var methodChannel: MethodChannel? = null

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        if (intent.action == "DISCONNECT_VPN_ACTION") {
            methodChannel?.invokeMethod("onNotificationDisconnect", null)
        }
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        val channel = MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CHANNEL)
        methodChannel = channel

        channel.setMethodCallHandler { call, result ->
            when (call.method) {
                "updateNotification" -> {
                    val title = call.argument<String>("title") ?: "Connectix VPN"
                    val content = call.argument<String>("content") ?: ""
                    val isConnected = call.argument<Boolean>("isConnected") ?: false
                    try {
                        val notifMgr = getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
                        if (notifMgr != null) {
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                                if (notifMgr.getNotificationChannel(NOTIF_CHANNEL_ID) == null) {
                                    val ch = NotificationChannel(
                                        NOTIF_CHANNEL_ID,
                                        "Connectix VPN Status",
                                        NotificationManager.IMPORTANCE_LOW
                                    ).apply {
                                        description = "نمایش زنده سرعت و وضعیت اتصال وی‌پی‌ان"
                                        setShowBadge(false)
                                        setSound(null, null)
                                        enableVibration(false)
                                    }
                                    notifMgr.createNotificationChannel(ch)
                                }
                            }

                            if (!isConnected) {
                                notifMgr.cancel(NOTIF_ID)
                                result.success(true)
                                return@setMethodCallHandler
                            }

                            val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                                PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
                            } else {
                                PendingIntent.FLAG_UPDATE_CURRENT
                            }

                            val launchIntent = packageManager.getLaunchIntentForPackage(packageName)
                            val contentPendingIntent = PendingIntent.getActivity(this, 100, launchIntent, flags)

                            val discIntent = Intent(this, MainActivity::class.java).apply {
                                action = "DISCONNECT_VPN_ACTION"
                                this.flags = Intent.FLAG_ACTIVITY_SINGLE_TOP
                            }
                            val discPendingIntent = PendingIntent.getActivity(this, 101, discIntent, flags)

                            val notif = NotificationCompat.Builder(this, NOTIF_CHANNEL_ID)
                                .setSmallIcon(R.mipmap.ic_launcher)
                                .setContentTitle(title)
                                .setContentText(content)
                                .setOngoing(true)
                                .setOnlyAlertOnce(true)
                                .setContentIntent(contentPendingIntent)
                                .addAction(android.R.drawable.ic_menu_close_clear_cancel, "قطع اتصال", discPendingIntent)
                                .setPriority(NotificationCompat.PRIORITY_LOW)
                                .build()

                            notifMgr.notify(NOTIF_ID, notif)
                        }
                        result.success(true)
                    } catch (e: Exception) {
                        result.success(false)
                    }
                }
                "cancelNotification" -> {
                    try {
                        val notifMgr = getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
                        notifMgr?.cancel(NOTIF_ID)
                        result.success(true)
                    } catch (e: Exception) {
                        result.success(false)
                    }
                }
                "getCacheDir" -> {
                    try {
                        val cacheDir = context.externalCacheDir ?: context.cacheDir
                        result.success(cacheDir.absolutePath)
                    } catch (e: Exception) {
                        result.error("CACHE_DIR_ERROR", e.message, null)
                    }
                }
                "canInstallPackages" -> {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                        result.success(context.packageManager.canRequestPackageInstalls())
                    } else {
                        result.success(true)
                    }
                }
                "openInstallPermissionSettings" -> {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                        try {
                            val intent = Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES)
                            intent.data = Uri.parse("package:" + context.packageName)
                            intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK
                            context.startActivity(intent)
                            result.success(true)
                        } catch (e: Exception) {
                            result.error("SETTINGS_ERROR", e.message, null)
                        }
                    } else {
                        result.success(true)
                    }
                }
                "openHotspotSettings" -> {
                    try {
                        val intent = Intent(Intent.ACTION_MAIN)
                        intent.setClassName("com.android.settings", "com.android.settings.TetherSettings")
                        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK
                        context.startActivity(intent)
                        result.success(true)
                    } catch (e: Exception) {
                        try {
                            val fallbackIntent = Intent(Settings.ACTION_WIRELESS_SETTINGS)
                            fallbackIntent.flags = Intent.FLAG_ACTIVITY_NEW_TASK
                            context.startActivity(fallbackIntent)
                            result.success(true)
                        } catch (e2: Exception) {
                            result.error("HOTSPOT_SETTINGS_ERROR", e2.message, null)
                        }
                    }
                }
                "installApk" -> {
                    val filePath = call.argument<String>("filePath")
                    if (filePath != null) {
                        val file = File(filePath)
                        if (file.exists()) {
                            try {
                                val intent = Intent(Intent.ACTION_VIEW)
                                val uri: Uri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                    FileProvider.getUriForFile(context, context.packageName + ".fileprovider", file)
                                } else {
                                    Uri.fromFile(file)
                                }
                                intent.setDataAndType(uri, "application/vnd.android.package-archive")
                                intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_GRANT_READ_URI_PERMISSION
                                context.startActivity(intent)
                                result.success(true)
                            } catch (e: Exception) {
                                result.error("INSTALL_ERROR", e.message, null)
                            }
                        } else {
                            result.error("FILE_NOT_FOUND", "File does not exist: $filePath", null)
                        }
                    } else {
                        result.error("INVALID_ARGUMENT", "filePath is null", null)
                    }
                }
                "getLastCrashReport" -> {
                    try {
                        val dir = context.getExternalFilesDir(null) ?: context.filesDir
                        val file = File(dir, "connectix_last_crash.txt")
                        result.success(if (file.exists()) file.readText() else "")
                    } catch (e: Exception) {
                        result.success("")
                    }
                }
                else -> {
                    result.notImplemented()
                }
            }
        }
    }
}
