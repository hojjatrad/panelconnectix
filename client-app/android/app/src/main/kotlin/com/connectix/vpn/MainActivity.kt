package com.connectix.vpn

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.usage.UsageStatsManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationManager
import android.location.provider.ProviderProperties
import android.net.Uri
import android.os.Build
import android.os.SystemClock
import android.provider.Settings
import androidx.core.app.NotificationCompat
import androidx.core.content.FileProvider
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File
import java.util.ArrayList
import java.util.HashMap
import java.util.HashSet
import java.util.Timer
import java.util.TimerTask

class MainActivity: FlutterActivity() {
    // v4.0.75 FULL-SCREEN: تمام صفحه در هر گوشی - Edge-to-Edge
    override fun onCreate(savedInstanceState: android.os.Bundle?) {
        super.onCreate(savedInstanceState)
        try {
            // Enable edge-to-edge for full-screen on all phones
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.R) {
                window.setDecorFitsSystemWindows(false)
                window.statusBarColor = android.graphics.Color.TRANSPARENT
                window.navigationBarColor = android.graphics.Color.TRANSPARENT
                // Allow content behind cutout
                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.P) {
                    window.attributes.layoutInDisplayCutoutMode = android.view.WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_SHORT_EDGES
                }
            } else {
                @Suppress("DEPRECATION")
                window.decorView.systemUiVisibility = (
                    android.view.View.SYSTEM_UI_FLAG_LAYOUT_STABLE
                    or android.view.View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
                    or android.view.View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
                )
                @Suppress("DEPRECATION")
                window.statusBarColor = android.graphics.Color.TRANSPARENT
                @Suppress("DEPRECATION")
                window.navigationBarColor = android.graphics.Color.TRANSPARENT
            }
            android.util.Log.i("ConnectixFullScreen", "v4.0.75 Edge-to-Edge enabled for full-screen")
        } catch (e: Exception) {
            android.util.Log.w("ConnectixFullScreen", "v4.0.75 Edge-to-Edge failed: ${e.message}")
        }
    }

    private val CHANNEL = "com.connectix.vpn/updater"
    private val NOTIF_CHANNEL_ID = "connectix_vpn_status_ch"
    private val V2RAY_CHANNEL_ID = "A_FLUTTER_V2RAY_SERVICE_CH_ID"
    private val NOTIF_ID = 1
    private var methodChannel: MethodChannel? = null
    // v4.0.28 GPS Spoof - Method 1 (Mock) + Method 3 (VPN Service) combined
    private var mockLocationTimer: Timer? = null
    private var currentMockLat: Double = 0.0
    private var currentMockLng: Double = 0.0
    private var isMocking: Boolean = false
    // v4.0.29 FIX: Screen off disconnect + slow download
    private var wakeLock: android.os.PowerManager.WakeLock? = null
    private var wifiLock: android.net.wifi.WifiManager.WifiLock? = null
    private var downloadId: Long = -1

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        if (intent.action == "DISCONNECT_VPN_ACTION") {
            try {
                val notifMgr = getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
                notifMgr?.cancel(NOTIF_ID)
                notifMgr?.cancel(9999)
            } catch (_: Exception) {}
            methodChannel?.invokeMethod("onNotificationDisconnect", null)
        }
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        val channel = MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CHANNEL)
        methodChannel = channel

        if (intent?.action == "DISCONNECT_VPN_ACTION") {
            try {
                val notifMgr = getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
                notifMgr?.cancel(NOTIF_ID)
                notifMgr?.cancel(9999)
            } catch (_: Exception) {}
            channel.invokeMethod("onNotificationDisconnect", null)
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            try {
                if (checkSelfPermission(android.Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                    requestPermissions(arrayOf(android.Manifest.permission.POST_NOTIFICATIONS), 101)
                }
            } catch (_: Exception) {}
        }

        channel.setMethodCallHandler { call, result ->
            when (call.method) {
                "updateNotification" -> {
                    val title = call.argument<String>("title") ?: "Connectix VPN"
                    val content = call.argument<String>("content") ?: ""
                    val isConnected = call.argument<Boolean>("isConnected") ?: false
                    try {
                        val notifMgr = getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
                        if (notifMgr != null) {
                            if (!isConnected) {
                                // v4.0.66 FIX: Don't cancel foreground service notification here - let XrayVPNService handle it
                                // Only cancel our custom status notification if we use different ID
                                // But we use same ID 1 as foreground, so don't cancel when disconnected - service already stopped
                                try {
                                    notifMgr.cancel(NOTIF_ID)
                                } catch (_: Exception) {}
                                try {
                                    notifMgr.cancel(9999)
                                } catch (_: Exception) {}
                                result.success(true)
                                return@setMethodCallHandler
                            }

                            // v4.0.66 FIX: Use vpn_service_channel (library's channel) as primary to update foreground notification with speed
                            // Library uses channel "vpn_service_channel" and ID 1 for foreground service
                            // We should use same channel to update its notification with speed + disconnect button
                            var channelId = "vpn_service_channel"
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                                // Check if library's channel exists, if not create it
                                if (notifMgr.getNotificationChannel("vpn_service_channel") == null) {
                                    val vpnCh = NotificationChannel(
                                        "vpn_service_channel",
                                        "VPN Service",
                                        NotificationManager.IMPORTANCE_LOW
                                    ).apply {
                                        description = "نمایش زنده سرعت و وضعیت اتصال وی‌پی‌ان"
                                        setShowBadge(false)
                                        setSound(null, null)
                                        enableVibration(false)
                                    }
                                    notifMgr.createNotificationChannel(vpnCh)
                                }
                                // Also ensure our custom channel exists as fallback
                                if (notifMgr.getNotificationChannel(V2RAY_CHANNEL_ID) != null) {
                                    // Library may have created with old ID in some versions, prefer vpn_service_channel but keep compat
                                }
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
                            } else {
                                // Pre-O, use NOTIF_CHANNEL_ID as fallback if vpn_service_channel not available
                                if (notifMgr.getNotificationChannel("vpn_service_channel") == null) {
                                    channelId = NOTIF_CHANNEL_ID
                                }
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

                            // v4.0.66 FIX: Build notification with speed + disconnect button, using same ID 1 as foreground service
                            // This updates the foreground notification to show speed
                            val notif = NotificationCompat.Builder(this, channelId)
                                .setSmallIcon(R.mipmap.ic_launcher)
                                .setContentTitle(title)
                                .setContentText(content)
                                .setOngoing(true)
                                .setOnlyAlertOnce(true)
                                .setContentIntent(contentPendingIntent)
                                .addAction(android.R.drawable.ic_menu_close_clear_cancel, "قطع اتصال", discPendingIntent)
                                .setPriority(NotificationCompat.PRIORITY_LOW)
                                .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
                                .setCategory(NotificationCompat.CATEGORY_SERVICE)
                                .build()

                            notifMgr.notify(NOTIF_ID, notif)
                            // Don't cancel 9999 here - it might be used by other
                        }
                        result.success(true)
                    } catch (e: Exception) {
                        android.util.Log.e("ConnectixNotif", "updateNotification failed: ${e.message}", e)
                        result.success(false)
                    }
                }
                "cancelNotification" -> {
                    try {
                        val notifMgr = getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
                        // v4.0.66 FIX: Only cancel our custom, don't interfere with foreground service if it's still running
                        try {
                            notifMgr?.cancel(NOTIF_ID)
                        } catch (_: Exception) {}
                        try {
                            notifMgr?.cancel(9999)
                        } catch (_: Exception) {}
                        result.success(true)
                    } catch (e: Exception) {
                        result.success(false)
                    }
                }
                "getCacheDir" -> {
                    try {
                        // v4.0.13 PURE INTENT FIX: Use external files dir like working v4.0.3 - best for FileProvider on MIUI/Samsung
                        val extDir = context.getExternalFilesDir(null)
                        val cacheDir = if (extDir != null && extDir.exists()) extDir else context.cacheDir
                        if (!cacheDir.exists()) {
                            cacheDir.mkdirs()
                        }
                        result.success(cacheDir.absolutePath)
                    } catch (e: Exception) {
                        try {
                            result.success(context.cacheDir.absolutePath)
                        } catch (e2: Exception) {
                            result.error("CACHE_DIR_ERROR", e.message, null)
                        }
                    }
                }
                "getExternalFilesDir" -> {
                    try {
                        // v4.0.41 FORENSIC FIX: Explicit externalFilesDir handler - best for MIUI/Samsung FileProvider
                        val extDir = context.getExternalFilesDir(null)
                        if (extDir != null) {
                            if (!extDir.exists()) extDir.mkdirs()
                            result.success(extDir.absolutePath)
                        } else {
                            result.success(context.cacheDir.absolutePath)
                        }
                    } catch (e: Exception) {
                        try {
                            result.success(context.cacheDir.absolutePath)
                        } catch (e2: Exception) {
                            result.error("EXT_DIR_ERROR", e.message, null)
                        }
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
                "openBrowser" -> {
                    // v4.0.66 FIX: Open browser for manual download fallback
                    val url = call.argument<String>("url") ?: ""
                    try {
                        if (url.isEmpty()) {
                            result.error("INVALID_URL", "URL empty", null)
                            return@setMethodCallHandler
                        }
                        val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url)).apply {
                            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                        }
                        context.startActivity(intent)
                        result.success(true)
                    } catch (e: Exception) {
                        result.error("BROWSER_ERROR", e.message, null)
                    }
                }
                "openFileManager" -> {
                    // v4.0.13: Open file manager at APK location - pure Intent
                    val filePath = call.argument<String>("filePath") ?: ""
                    try {
                        val file = if (filePath.isNotEmpty()) File(filePath) else null
                        val dir = file?.parentFile ?: context.getExternalFilesDir(null) ?: context.cacheDir
                        
                        val intent = Intent(Intent.ACTION_VIEW).apply {
                            val dirUri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                FileProvider.getUriForFile(context, context.packageName + ".fileprovider", dir)
                            } else {
                                Uri.fromFile(dir)
                            }
                            setDataAndType(dirUri, "resource/folder")
                            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                        }
                        try {
                            context.startActivity(intent)
                            result.success(true)
                            return@setMethodCallHandler
                        } catch (_: Exception) {}
                        
                        val fallbackIntent = Intent(Intent.ACTION_VIEW).apply {
                            type = "resource/folder"
                            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                        }
                        context.startActivity(fallbackIntent)
                        result.success(true)
                    } catch (e: Exception) {
                        result.error("FILE_MANAGER_ERROR", e.message, null)
                    }
                }
                "openApkFile" -> {
                    // v4.0.13 PURE INTENT: Directly open APK file via Intent (for retry button) - NO PackageInstaller
                    val filePath = call.argument<String>("filePath")
                    if (filePath == null) {
                        result.error("INVALID_ARGUMENT", "filePath is null", null)
                        return@setMethodCallHandler
                    }
                    val file = File(filePath)
                    if (!file.exists()) {
                        result.error("FILE_NOT_FOUND", "File does not exist: $filePath", null)
                        return@setMethodCallHandler
                    }
                    try {
                        file.setReadable(true, false)
                        val uri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                            FileProvider.getUriForFile(context, context.packageName + ".fileprovider", file)
                        } else {
                            Uri.fromFile(file)
                        }
                        
                        // v4.0.13: Grant to ALL known installer packages (critical for MIUI/Samsung)
                        val installers = listOf(
                            "com.android.packageinstaller",
                            "com.google.android.packageinstaller",
                            "com.miui.packageinstaller",
                            "com.miui.global.packageinstaller",
                            "com.miui.securitycenter",
                            "com.samsung.android.packageinstaller",
                            "com.sec.android.preloadinstaller",
                            "com.android.managedprovisioning",
                            "com.google.android.permissioncontroller"
                        )
                        for (pkg in installers) {
                            try { context.grantUriPermission(pkg, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION) } catch (_: Exception) {}
                        }
                        
                        val intent = Intent(Intent.ACTION_VIEW).apply {
                            setDataAndType(uri, "application/vnd.android.package-archive")
                            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                            addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP)
                            putExtra(Intent.EXTRA_ALLOW_REPLACE, true)
                            putExtra(Intent.EXTRA_NOT_UNKNOWN_SOURCE, true)
                            putExtra("android.intent.extra.ALLOW_REPLACE", true)
                        }
                        
                        // Grant to resolved
                        try {
                            val resList = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                packageManager.queryIntentActivities(intent, PackageManager.ResolveInfoFlags.of(PackageManager.MATCH_DEFAULT_ONLY.toLong()))
                            } else {
                                @Suppress("DEPRECATION")
                                packageManager.queryIntentActivities(intent, PackageManager.MATCH_DEFAULT_ONLY)
                            }
                            for (ri in resList) {
                                try { context.grantUriPermission(ri.activityInfo.packageName, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION) } catch (_: Exception) {}
                            }
                        } catch (_: Exception) {}
                        
                        context.startActivity(intent)
                        android.util.Log.i("ConnectixInstaller", "v4.0.13 openApkFile SUCCESS via VIEW: ${file.length()} bytes")
                        result.success(true)
                    } catch (e: Exception) {
                        result.error("OPEN_APK_ERROR", e.message, null)
                    }
                }
                "getApkFilePath" -> {
                    try {
                        val extDir = context.getExternalFilesDir(null)
                        val file = File(extDir, "Connectix-Update.apk")
                        if (file.exists()) {
                            result.success(file.absolutePath)
                        } else {
                            val cacheFile = File(context.cacheDir, "Connectix-Update.apk")
                            if (cacheFile.exists()) {
                                result.success(cacheFile.absolutePath)
                            } else {
                                result.success("")
                            }
                        }
                    } catch (e: Exception) {
                        result.success("")
                    }
                }
                                                "installApk" -> {
                    // v4.0.82 FIX: Install window closes - MIUI/Samsung/Android14 fix - Intent VIEW first
                    val filePath = call.argument<String>("filePath")
                    if (filePath == null) {
                        result.error("INVALID_ARGUMENT", "filePath is null", null)
                        return@setMethodCallHandler
                    }
                    val file = File(filePath)
                    if (!file.exists()) {
                        result.error("FILE_NOT_FOUND", "File does not exist: $filePath", null)
                        return@setMethodCallHandler
                    }
                    if (file.length() < 100000) {
                        result.error("FILE_TOO_SMALL", "File too small", null)
                        return@setMethodCallHandler
                    }
                    try {
                        file.setReadable(true, false)
                        android.util.Log.i("ConnectixInstaller", "v4.0.82 FIX: path=$filePath len=${file.length()} brand=${Build.BRAND} sdk=${Build.VERSION.SDK_INT}")
                        var workingFile = file
                        try {
                            val extDir = context.getExternalFilesDir(null)
                            if (extDir != null) {
                                if (!extDir.exists()) extDir.mkdirs()
                                val newFile = File(extDir, "Connectix-Update.apk")
                                try { if (newFile.exists()) newFile.delete() } catch (_: Exception) {}
                                file.copyTo(newFile, overwrite = true)
                                newFile.setReadable(true, false)
                                workingFile = newFile
                            }
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "Copy to external failed: ${e.message}")
                        }
                        var downloadsFile: File? = null
                        try {
                            val dlDir = File("/storage/emulated/0/Download")
                            if (dlDir.exists()) {
                                val dlFile = File(dlDir, "Connectix-Update.apk")
                                try { if (dlFile.exists()) dlFile.delete() } catch (_: Exception) {}
                                workingFile.copyTo(dlFile, overwrite = true)
                                dlFile.setReadable(true, false)
                                downloadsFile = dlFile
                            }
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "Downloads copy failed: ${e.message}")
                        }
                        try {
                            val uri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                FileProvider.getUriForFile(context, context.packageName + ".fileprovider", workingFile)
                            } else {
                                Uri.fromFile(workingFile)
                            }
                            val installers = listOf(
                                "com.android.packageinstaller",
                                "com.google.android.packageinstaller",
                                "com.miui.packageinstaller",
                                "com.miui.global.packageinstaller",
                                "com.miui.securitycenter",
                                "com.samsung.android.packageinstaller",
                                "com.sec.android.preloadinstaller",
                                "com.google.android.permissioncontroller"
                            )
                            for (pkg in installers) {
                                try { context.grantUriPermission(pkg, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION) } catch (_: Exception) {}
                            }
                            val viewIntent = Intent(Intent.ACTION_VIEW).apply {
                                setDataAndType(uri, "application/vnd.android.package-archive")
                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP)
                                putExtra(Intent.EXTRA_RETURN_RESULT, true)
                                putExtra(Intent.EXTRA_ALLOW_REPLACE, true)
                            }
                            try {
                                val resList = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                    packageManager.queryIntentActivities(viewIntent, PackageManager.ResolveInfoFlags.of(PackageManager.MATCH_DEFAULT_ONLY.toLong()))
                                } else {
                                    @Suppress("DEPRECATION")
                                    packageManager.queryIntentActivities(viewIntent, PackageManager.MATCH_DEFAULT_ONLY)
                                }
                                for (ri in resList) {
                                    try { context.grantUriPermission(ri.activityInfo.packageName, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION) } catch (_: Exception) {}
                                }
                            } catch (_: Exception) {}
                            context.startActivity(viewIntent)
                            result.success(true)
                            return@setMethodCallHandler
                        } catch (e: Exception) {
                            android.util.Log.e("ConnectixInstaller", "Intent VIEW failed: ${e.message}", e)
                        }
                        try {
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                                val packageInstaller = context.packageManager.packageInstaller
                                val params = android.content.pm.PackageInstaller.SessionParams(
                                    android.content.pm.PackageInstaller.SessionParams.MODE_FULL_INSTALL
                                )
                                params.setAppPackageName(context.packageName)
                                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                                    params.setRequireUserAction(android.content.pm.PackageInstaller.SessionParams.USER_ACTION_REQUIRED)
                                }
                                try {
                                    val m = params.javaClass.getMethod("setAllowDowngrade", Boolean::class.javaPrimitiveType)
                                    m.invoke(params, true)
                                } catch (_: Exception) {}
                                val sessionId = packageInstaller.createSession(params)
                                val session = packageInstaller.openSession(sessionId)
                                try {
                                    session.openWrite("base.apk", 0, workingFile.length()).use { out ->
                                        workingFile.inputStream().use { input ->
                                            val buf = ByteArray(131072)
                                            var c: Int
                                            while (input.read(buf).also { c = it } != -1) {
                                                out.write(buf, 0, c)
                                            }
                                        }
                                        session.fsync(out)
                                    }
                                    val callbackIntent = Intent(context, MainActivity::class.java).apply {
                                        action = "INSTALL_COMPLETE"
                                        putExtra("sessionId", sessionId)
                                    }
                                    val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                                        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                                    } else {
                                        PendingIntent.FLAG_UPDATE_CURRENT
                                    }
                                    val pi = PendingIntent.getActivity(context, sessionId, callbackIntent, flags)
                                    session.commit(pi.intentSender)
                                    result.success(true)
                                    return@setMethodCallHandler
                                } catch (e: Exception) {
                                    try { session.close() } catch (_: Exception) {}
                                    try { packageInstaller.abandonSession(sessionId) } catch (_: Exception) {}
                                } finally {
                                    try { session.close() } catch (_: Exception) {}
                                }
                            }
                        } catch (e: Exception) {}
                        result.success(true)
                    } catch (e: Exception) {
                        result.error("INSTALL_FAILED", e.message, null)
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
                "getInstalledBypassApps" -> {
                    try {
                        val rawList = call.argument<List<*>>("packages") ?: emptyList<Any>()
                        val candidateList = rawList.mapNotNull { it?.toString() }
                        val pm = context.packageManager
                        val installed = ArrayList<String>()
                        for (pkg in candidateList) {
                            try {
                                pm.getPackageInfo(pkg, 0)
                                installed.add(pkg)
                            } catch (_: Exception) {}
                        }
                        result.success(installed)
                    } catch (e: Exception) {
                        result.success(emptyList<String>())
                    }
                }
                "getApkVersionName" -> {
                    // v4.0.19 FOREVER LAW: Get versionName from downloaded APK to verify it's not stale
                    val filePath = call.argument<String>("filePath") ?: ""
                    try {
                        val file = File(filePath)
                        if (!file.exists()) {
                            result.success("")
                            return@setMethodCallHandler
                        }
                        val pm = context.packageManager
                        val info = if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
                            pm.getPackageArchiveInfo(filePath, android.content.pm.PackageManager.PackageInfoFlags.of(0))
                        } else {
                            @Suppress("DEPRECATION")
                            pm.getPackageArchiveInfo(filePath, 0)
                        }
                        val versionName = info?.versionName ?: ""
                        val versionCode = if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.P) {
                            info?.longVersionCode?.toString() ?: ""
                        } else {
                            @Suppress("DEPRECATION")
                            info?.versionCode?.toString() ?: ""
                        }
                        android.util.Log.i("ConnectixInstaller", "v4.0.19 getApkVersionName: $filePath -> $versionName ($versionCode) len=${file.length()}")
                        result.success(versionName)
                    } catch (e: Exception) {
                        android.util.Log.e("ConnectixInstaller", "v4.0.19 getApkVersionName failed: ${e.message}")
                        result.success("")
                    }
                }
                "getInstalledAppVersion" -> {
                    // v4.0.43 FOREVER LAW: Get ACTUAL installed app version from PackageManager, not hardcoded
                    // Fixes "still shows old version 4.0.39 after install" - must read real version
                    try {
                        val pm = context.packageManager
                        val packageName = context.packageName
                        val info = if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
                            pm.getPackageInfo(packageName, android.content.pm.PackageManager.PackageInfoFlags.of(0))
                        } else {
                            @Suppress("DEPRECATION")
                            pm.getPackageInfo(packageName, 0)
                        }
                        val versionName = info.versionName ?: ""
                        val versionCode = if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.P) {
                            info.longVersionCode.toString()
                        } else {
                            @Suppress("DEPRECATION")
                            info.versionCode.toString()
                        }
                        android.util.Log.i("ConnectixInstaller", "v4.0.43 getInstalledAppVersion: $versionName ($versionCode)")
                        result.success(mapOf("versionName" to versionName, "versionCode" to versionCode))
                    } catch (e: Exception) {
                        android.util.Log.e("ConnectixInstaller", "v4.0.43 getInstalledAppVersion failed: ${e.message}")
                        result.success(mapOf("versionName" to "", "versionCode" to ""))
                    }
                }
                "getAllInstalledApps" -> {
                    Thread {
                        try {
                            val pm = context.packageManager
                            val mainIntent = Intent(Intent.ACTION_MAIN, null).apply {
                                addCategory(Intent.CATEGORY_LAUNCHER)
                            }
                            val resolveInfos = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                pm.queryIntentActivities(mainIntent, PackageManager.ResolveInfoFlags.of(PackageManager.MATCH_ALL.toLong()))
                            } else {
                                @Suppress("DEPRECATION")
                                pm.queryIntentActivities(mainIntent, PackageManager.MATCH_ALL)
                            }
                            val appsList = ArrayList<Map<String, String>>()
                            val selfPkg = context.packageName
                            val seenPackages = HashSet<String>()

                            for (ri in resolveInfos) {
                                try {
                                    val pkg = ri.activityInfo?.packageName ?: continue
                                    if (pkg == selfPkg || seenPackages.contains(pkg)) continue
                                    seenPackages.add(pkg)

                                    val label = try {
                                        ri.loadLabel(pm)?.toString() ?: pkg
                                    } catch (_: Exception) {
                                        pkg
                                    }

                                    val map = HashMap<String, String>()
                                    map["packageName"] = pkg
                                    map["appName"] = label
                                    appsList.add(map)
                                } catch (_: Exception) {
                                    continue
                                }
                            }

                            appsList.sortBy { it["appName"]?.lowercase() ?: "" }

                            runOnUiThread {
                                try {
                                    result.success(appsList)
                                } catch (_: Exception) {}
                            }
                        } catch (e: Exception) {
                            runOnUiThread {
                                try {
                                    result.error("GET_APPS_ERROR", e.message ?: "Unknown error", null)
                                } catch (_: Exception) {}
                            }
                        }
                    }.start()
                }
                "checkUsageStatsPermission" -> {
                    try {
                        val appOps = getSystemService(Context.APP_OPS_SERVICE) as android.app.AppOpsManager
                        val mode = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                            appOps.unsafeCheckOpNoThrow("android:get_usage_stats", android.os.Process.myUid(), packageName)
                        } else {
                            @Suppress("DEPRECATION")
                            appOps.checkOpNoThrow("android:get_usage_stats", android.os.Process.myUid(), packageName)
                        }
                        result.success(mode == android.app.AppOpsManager.MODE_ALLOWED)
                    } catch (e: Exception) {
                        result.success(false)
                    }
                }
                "openUsageStatsSettings" -> {
                    try {
                        val intent = Intent(Settings.ACTION_USAGE_ACCESS_SETTINGS)
                        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK
                        startActivity(intent)
                        result.success(true)
                    } catch (e: Exception) {
                        try {
                            val intent = Intent(Settings.ACTION_USAGE_ACCESS_SETTINGS)
                            intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK
                            context.startActivity(intent)
                            result.success(true)
                        } catch (e2: Exception) {
                            result.error("SETTINGS_ERROR", e2.message, null)
                        }
                    }
                }
                "getForegroundApp" -> {
                    Thread {
                        try {
                            val usm = getSystemService(Context.USAGE_STATS_SERVICE) as UsageStatsManager
                            val end = System.currentTimeMillis()
                            val begin = end - 1000 * 10
                            val stats = usm.queryUsageStats(UsageStatsManager.INTERVAL_DAILY, begin, end)
                            var foregroundPkg: String? = null
                            var lastTime: Long = 0
                            if (stats != null) {
                                for (s in stats) {
                                    try {
                                        if (s.lastTimeUsed > lastTime) {
                                            lastTime = s.lastTimeUsed
                                            foregroundPkg = s.packageName
                                        }
                                    } catch (_: Exception) {}
                                }
                            }
                            if (foregroundPkg == null) {
                                try {
                                    val events = usm.queryEvents(begin, end)
                                    val event = android.app.usage.UsageEvents.Event()
                                    var lastEventPkg: String? = null
                                    while (events.hasNextEvent()) {
                                        events.getNextEvent(event)
                                        if (event.eventType == android.app.usage.UsageEvents.Event.MOVE_TO_FOREGROUND) {
                                            lastEventPkg = event.packageName
                                        }
                                    }
                                    if (lastEventPkg != null) {
                                        foregroundPkg = lastEventPkg
                                    }
                                } catch (_: Exception) {}
                            }

                            val pkgToReturn = foregroundPkg ?: ""
                            runOnUiThread {
                                try {
                                    result.success(pkgToReturn)
                                } catch (_: Exception) {}
                            }
                        } catch (e: Exception) {
                            runOnUiThread {
                                try {
                                    result.success("")
                                } catch (_: Exception) {}
                            }
                        }
                    }.start()
                }

                // v4.0.28 GPS Spoof + Device Info - Combined Method 1 + 3
                "getAbi" -> {
                    try {
                        val abis = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                            Build.SUPPORTED_ABIS
                        } else {
                            arrayOf(Build.CPU_ABI, Build.CPU_ABI2)
                        }
                        val primaryAbi = abis.firstOrNull() ?: "arm64-v8a"
                        android.util.Log.i("ConnectixGPS", "Device ABI: ${abis.joinToString()} primary: $primaryAbi")
                        result.success(primaryAbi)
                    } catch (e: Exception) {
                        result.success("arm64-v8a")
                    }
                }
                "isMockLocationEnabled" -> {
                    try {
                        val isEnabled = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                            try {
                                Settings.Secure.getInt(contentResolver, Settings.Secure.ALLOW_MOCK_LOCATION, 0) != 0 ||
                                !Settings.Secure.getString(contentResolver, Settings.Secure.ALLOW_MOCK_LOCATION).isNullOrEmpty() ||
                                packageManager.getApplicationInfo(packageName, 0).let {
                                    // Check if this app is selected as mock location app
                                    try {
                                        val mockApp = Settings.Secure.getString(contentResolver, "mock_location")
                                        mockApp == packageName || Settings.Secure.getString(contentResolver, Settings.Secure.ALLOW_MOCK_LOCATION) == "1"
                                    } catch (_: Exception) { false }
                                }
                            } catch (_: Exception) {
                                // For Android 6+, check if developer options enabled and app is mock location app
                                try {
                                    val devEnabled = Settings.Global.getInt(contentResolver, Settings.Global.DEVELOPMENT_SETTINGS_ENABLED, 0) == 1
                                    devEnabled
                                } catch (_: Exception) { false }
                            }
                        } else {
                            Settings.Secure.getInt(contentResolver, Settings.Secure.ALLOW_MOCK_LOCATION, 0) != 0
                        }
                        // Also check if we can set mock location (app is selected)
                        var canMock = false
                        try {
                            val lm = getSystemService(Context.LOCATION_SERVICE) as LocationManager
                            // Try to check if mock location app is this app
                            canMock = true // Assume true if we can get here, actual test will be in setMock
                        } catch (_: Exception) {}
                        result.success(mapOf("enabled" to isEnabled, "canMock" to canMock))
                    } catch (e: Exception) {
                        result.success(mapOf("enabled" to false, "canMock" to false, "error" to (e.message ?: "")))
                    }
                }
                "openMockLocationSettings" -> {
                    try {
                        val intent = Intent(Settings.ACTION_APPLICATION_DEVELOPMENT_SETTINGS)
                        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK
                        startActivity(intent)
                        result.success(true)
                    } catch (e: Exception) {
                        try {
                            val intent = Intent(Settings.ACTION_SETTINGS)
                            intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK
                            startActivity(intent)
                            result.success(true)
                        } catch (e2: Exception) {
                            result.error("SETTINGS_ERROR", e2.message, null)
                        }
                    }
                }
                "setMockLocation" -> {
                    // Method 1: Basic mock location via Test Provider
                    val lat = call.argument<Double>("lat") ?: 0.0
                    val lng = call.argument<Double>("lng") ?: 0.0
                    val alt = call.argument<Double>("alt") ?: 0.0
                    try {
                        currentMockLat = lat
                        currentMockLng = lng
                        val lm = getSystemService(Context.LOCATION_SERVICE) as LocationManager
                        
                        // Remove existing test providers
                        try { lm.removeTestProvider(LocationManager.GPS_PROVIDER) } catch (_: Exception) {}
                        try { lm.removeTestProvider(LocationManager.NETWORK_PROVIDER) } catch (_: Exception) {}
                        
                        // Add test provider for GPS
                        try {
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                                lm.addTestProvider(
                                    LocationManager.GPS_PROVIDER,
                                    false, false, false, false, false, true, true,
                                    ProviderProperties.POWER_USAGE_LOW, ProviderProperties.ACCURACY_FINE
                                )
                            } else {
                                @Suppress("DEPRECATION")
                                lm.addTestProvider(
                                    LocationManager.GPS_PROVIDER,
                                    false, false, false, false, true, true, true,
                                    0, 5
                                )
                            }
                            lm.setTestProviderEnabled(LocationManager.GPS_PROVIDER, true)
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixGPS", "Add GPS test provider failed: ${e.message}")
                        }
                        
                        // Add test provider for Network
                        try {
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                                lm.addTestProvider(
                                    LocationManager.NETWORK_PROVIDER,
                                    false, false, false, false, false, true, true,
                                    ProviderProperties.POWER_USAGE_LOW, ProviderProperties.ACCURACY_FINE
                                )
                            } else {
                                @Suppress("DEPRECATION")
                                lm.addTestProvider(
                                    LocationManager.NETWORK_PROVIDER,
                                    false, false, false, false, true, true, true,
                                    0, 5
                                )
                            }
                            lm.setTestProviderEnabled(LocationManager.NETWORK_PROVIDER, true)
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixGPS", "Add Network test provider failed: ${e.message}")
                        }
                        
                        // Set mock location
                        val mockLocation = Location(LocationManager.GPS_PROVIDER).apply {
                            latitude = lat
                            longitude = lng
                            altitude = alt
                            accuracy = 3.0f
                            time = System.currentTimeMillis()
                            elapsedRealtimeNanos = SystemClock.elapsedRealtimeNanos()
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                                bearingAccuracyDegrees = 0.1f
                                verticalAccuracyMeters = 0.1f
                                speedAccuracyMetersPerSecond = 0.1f
                            }
                        }
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN_MR1) {
                            mockLocation.isMock = true
                        }
                        
                        try { lm.setTestProviderLocation(LocationManager.GPS_PROVIDER, mockLocation) } catch (e: Exception) {
                            android.util.Log.w("ConnectixGPS", "Set GPS location failed: ${e.message}")
                        }
                        try { 
                            val netLoc = Location(LocationManager.NETWORK_PROVIDER).apply {
                                latitude = lat
                                longitude = lng
                                altitude = alt
                                accuracy = 5.0f
                                time = System.currentTimeMillis()
                                elapsedRealtimeNanos = SystemClock.elapsedRealtimeNanos()
                            }
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN_MR1) {
                                netLoc.isMock = true
                            }
                            lm.setTestProviderLocation(LocationManager.NETWORK_PROVIDER, netLoc) 
                        } catch (_: Exception) {}
                        
                        android.util.Log.i("ConnectixGPS", "Mock location set: $lat, $lng")
                        result.success(true)
                    } catch (e: Exception) {
                        android.util.Log.e("ConnectixGPS", "setMockLocation failed: ${e.message}", e)
                        result.error("MOCK_ERROR", e.message, null)
                    }
                }
                "startMockLocation" -> {
                    // Start continuous mocking (updates every 2s to keep location alive)
                    val lat = call.argument<Double>("lat") ?: currentMockLat
                    val lng = call.argument<Double>("lng") ?: currentMockLng
                    val interval = call.argument<Int>("interval") ?: 2000
                    try {
                        currentMockLat = lat
                        currentMockLng = lng
                        isMocking = true
                        
                        mockLocationTimer?.cancel()
                        mockLocationTimer = Timer()
                        mockLocationTimer?.scheduleAtFixedRate(object : TimerTask() {
                            override fun run() {
                                try {
                                    val lm = getSystemService(Context.LOCATION_SERVICE) as LocationManager
                                    val mockLocation = Location(LocationManager.GPS_PROVIDER).apply {
                                        latitude = currentMockLat
                                        longitude = currentMockLng
                                        accuracy = 3.0f + (Math.random() * 2).toFloat() // Slight random for realism
                                        time = System.currentTimeMillis()
                                        elapsedRealtimeNanos = SystemClock.elapsedRealtimeNanos()
                                        bearing = (Math.random() * 360).toFloat()
                                        speed = (Math.random() * 0.5).toFloat()
                                    }
                                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN_MR1) {
                                        mockLocation.isMock = true
                                    }
                                    try { lm.setTestProviderLocation(LocationManager.GPS_PROVIDER, mockLocation) } catch (_: Exception) {}
                                    try { 
                                        val netLoc = Location(LocationManager.NETWORK_PROVIDER).apply {
                                            latitude = currentMockLat
                                            longitude = currentMockLng
                                            accuracy = 5.0f
                                            time = System.currentTimeMillis()
                                            elapsedRealtimeNanos = SystemClock.elapsedRealtimeNanos()
                                        }
                                        lm.setTestProviderLocation(LocationManager.NETWORK_PROVIDER, netLoc) 
                                    } catch (_: Exception) {}
                                } catch (_: Exception) {}
                            }
                        }, 0, interval.toLong())
                        
                        android.util.Log.i("ConnectixGPS", "Started mocking at $lat, $lng every ${interval}ms")
                        result.success(true)
                    } catch (e: Exception) {
                        result.error("MOCK_START_ERROR", e.message, null)
                    }
                }
                "stopMockLocation" -> {
                    try {
                        isMocking = false
                        mockLocationTimer?.cancel()
                        mockLocationTimer = null
                        
                        val lm = getSystemService(Context.LOCATION_SERVICE) as LocationManager
                        try { lm.removeTestProvider(LocationManager.GPS_PROVIDER) } catch (_: Exception) {}
                        try { lm.removeTestProvider(LocationManager.NETWORK_PROVIDER) } catch (_: Exception) {}
                        
                        android.util.Log.i("ConnectixGPS", "Stopped mocking")
                        result.success(true)
                    } catch (e: Exception) {
                        result.error("MOCK_STOP_ERROR", e.message, null)
                    }
                }
                "getAbi" -> {
                    // For device_info channel compatibility
                    try {
                        val abis = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                            Build.SUPPORTED_ABIS
                        } else {
                            arrayOf(Build.CPU_ABI, Build.CPU_ABI2)
                        }
                        val primaryAbi = abis.firstOrNull() ?: "arm64-v8a"
                        result.success(primaryAbi)
                    } catch (e: Exception) {
                        result.success("arm64-v8a")
                    }
                }

                // v4.0.29 FIX: Screen off disconnect - Keep VPN alive
                "acquireWakeLock" -> {
                    try {
                        val powerManager = getSystemService(Context.POWER_SERVICE) as android.os.PowerManager
                        if (wakeLock == null) {
                            wakeLock = powerManager.newWakeLock(
                                android.os.PowerManager.PARTIAL_WAKE_LOCK,
                                "ConnectixVPN::KeepAlive"
                            )
                        }
                        if (wakeLock?.isHeld == false) {
                            wakeLock?.acquire(10*60*60*1000L) // 10 hours
                            android.util.Log.i("ConnectixKeepAlive", "WakeLock acquired")
                        }
                        val wifiManager = applicationContext.getSystemService(Context.WIFI_SERVICE) as android.net.wifi.WifiManager
                        if (wifiLock == null) {
                            wifiLock = wifiManager.createWifiLock(
                                android.net.wifi.WifiManager.WIFI_MODE_FULL_HIGH_PERF,
                                "ConnectixVPN::WifiLock"
                            )
                        }
                        if (wifiLock?.isHeld == false) {
                            wifiLock?.acquire()
                            android.util.Log.i("ConnectixKeepAlive", "WifiLock acquired")
                        }
                        result.success(true)
                    } catch (e: Exception) {
                        android.util.Log.e("ConnectixKeepAlive", "acquireWakeLock failed: ${e.message}", e)
                        result.success(false)
                    }
                }
                "releaseWakeLock" -> {
                    try {
                        // v4.0.41 FORENSIC FIX: Safe release - check isHeld safely, handle already released
                        try {
                            wakeLock?.let { 
                                try { if (it.isHeld) it.release() } catch (_: Exception) {}
                            }
                        } catch (_: Exception) {}
                        try {
                            wifiLock?.let { 
                                try { if (it.isHeld) it.release() } catch (_: Exception) {}
                            }
                        } catch (_: Exception) {}
                        try { wakeLock = null } catch (_: Exception) {}
                        try { wifiLock = null } catch (_: Exception) {}
                        android.util.Log.i("ConnectixKeepAlive", "v4.0.41 WakeLocks released safely")
                        result.success(true)
                    } catch (e: Exception) {
                        try { wakeLock = null } catch (_: Exception) {}
                        try { wifiLock = null } catch (_: Exception) {}
                        result.success(false)
                    }
                }
                // v4.0.42 ULTIMATE - Sound effects for connect button
                "playConnectSound" -> {
                    try {
                        // Play system notification sound with custom tone - whoosh effect
                        // Using ToneGenerator for lightweight sound without MediaPlayer
                        try {
                            val toneGen = android.media.ToneGenerator(android.media.AudioManager.STREAM_MUSIC, 80)
                            // Play two tones for whoosh + success
                            toneGen.startTone(android.media.ToneGenerator.TONE_PROP_BEEP, 120)
                            android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
                                try { toneGen.startTone(android.media.ToneGenerator.TONE_PROP_ACK, 180) } catch (_: Exception) {}
                                android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
                                    try { toneGen.release() } catch (_: Exception) {}
                                }, 250)
                            }, 150)
                        } catch (_: Exception) {
                            // Fallback: use SoundPool with system sound
                            try {
                                val audioManager = getSystemService(Context.AUDIO_SERVICE) as android.media.AudioManager
                                audioManager.playSoundEffect(android.media.AudioManager.FX_KEYPRESS_STANDARD, 0.7f)
                            } catch (_: Exception) {}
                        }
                        result.success(true)
                    } catch (e: Exception) {
                        result.success(false)
                    }
                }
                "playDisconnectSound" -> {
                    try {
                        try {
                            val toneGen = android.media.ToneGenerator(android.media.AudioManager.STREAM_MUSIC, 60)
                            toneGen.startTone(android.media.ToneGenerator.TONE_PROP_BEEP2, 150)
                            android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
                                try { toneGen.release() } catch (_: Exception) {}
                            }, 200)
                        } catch (_: Exception) {}
                        result.success(true)
                    } catch (e: Exception) {
                        result.success(false)
                    }
                }
                "playClickSound" -> {
                    try {
                        val audioManager = getSystemService(Context.AUDIO_SERVICE) as android.media.AudioManager
                        audioManager.playSoundEffect(android.media.AudioManager.FX_KEY_CLICK, 0.5f)
                        result.success(true)
                    } catch (e: Exception) {
                        result.success(false)
                    }
                }
                "requestBatteryOptimizationExemption" -> {
                    try {
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                            val pm = getSystemService(Context.POWER_SERVICE) as android.os.PowerManager
                            val isIgnoring = pm.isIgnoringBatteryOptimizations(packageName)
                            if (!isIgnoring) {
                                val intent = Intent(android.provider.Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS).apply {
                                    data = Uri.parse("package:$packageName")
                                    flags = Intent.FLAG_ACTIVITY_NEW_TASK
                                }
                                startActivity(intent)
                                result.success(false) // Not yet ignoring, requested
                            } else {
                                result.success(true) // Already ignoring
                            }
                        } else {
                            result.success(true)
                        }
                    } catch (e: Exception) {
                        result.error("BATTERY_OPT_ERROR", e.message, null)
                    }
                }
                "isIgnoringBatteryOptimizations" -> {
                    try {
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                            val pm = getSystemService(Context.POWER_SERVICE) as android.os.PowerManager
                            result.success(pm.isIgnoringBatteryOptimizations(packageName))
                        } else {
                            result.success(true)
                        }
                    } catch (e: Exception) {
                        result.success(false)
                    }
                }
                // v4.0.29 FIX: Slow download - Use DownloadManager for background download that continues when screen off
                "downloadWithDownloadManager" -> {
                    val url = call.argument<String>("url") ?: ""
                    val fileName = call.argument<String>("fileName") ?: "Connectix-Update.apk"
                    val expectedVer = call.argument<String>("expectedVersion") ?: ""
                    try {
                        if (url.isEmpty()) {
                            result.error("INVALID_URL", "URL empty", null)
                            return@setMethodCallHandler
                        }
                        // Use DownloadManager for reliable background download
                        val dm = getSystemService(Context.DOWNLOAD_SERVICE) as android.app.DownloadManager
                        val uri = Uri.parse(url)
                        val request = android.app.DownloadManager.Request(uri).apply {
                            setTitle("Connectix v$expectedVer - در حال دانلود")
                            setDescription("دانلود نسخه جدید - حتی با خاموش شدن صفحه ادامه دارد")
                            setNotificationVisibility(android.app.DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                            setDestinationInExternalFilesDir(context, null, fileName)
                            setAllowedOverMetered(true)
                            setAllowedOverRoaming(true)
                            setVisibleInDownloadsUi(true)
                            addRequestHeader("User-Agent", "Mozilla/5.0 (Linux; Android 10; Mobile) Connectix v4.0.29")
                            addRequestHeader("Cache-Control", "no-cache")
                        }
                        downloadId = dm.enqueue(request)
                        android.util.Log.i("ConnectixDownload", "DownloadManager enqueued id=$downloadId url=$url")
                        result.success(mapOf("downloadId" to downloadId, "fileName" to fileName))
                    } catch (e: Exception) {
                        android.util.Log.e("ConnectixDownload", "DownloadManager failed: ${e.message}", e)
                        result.error("DOWNLOAD_MANAGER_ERROR", e.message, null)
                    }
                }
                "getDownloadManagerStatus" -> {
                    val id = call.argument<Long>("downloadId") ?: downloadId
                    try {
                        val dm = getSystemService(Context.DOWNLOAD_SERVICE) as android.app.DownloadManager
                        val query = android.app.DownloadManager.Query().setFilterById(id)
                        val cursor = dm.query(query)
                        if (cursor.moveToFirst()) {
                            val statusIdx = cursor.getColumnIndex(android.app.DownloadManager.COLUMN_STATUS)
                            val bytesIdx = cursor.getColumnIndex(android.app.DownloadManager.COLUMN_BYTES_DOWNLOADED_SO_FAR)
                            val totalIdx = cursor.getColumnIndex(android.app.DownloadManager.COLUMN_TOTAL_SIZE_BYTES)
                            val reasonIdx = cursor.getColumnIndex(android.app.DownloadManager.COLUMN_REASON)
                            val status = cursor.getInt(statusIdx)
                            val bytes = cursor.getLong(bytesIdx)
                            val total = cursor.getLong(totalIdx)
                            val reason = cursor.getInt(reasonIdx)
                            val localUriIdx = cursor.getColumnIndex(android.app.DownloadManager.COLUMN_LOCAL_URI)
                            var localUri = cursor.getString(localUriIdx) ?: ""
                            // v4.0.31 FIX: Handle content:// URIs and provide direct file path
                            var filePath = ""
                            try {
                                if (localUri.startsWith("content://")) {
                                    // Try to get file path from DownloadManager's local file
                                    // For external files dir, we know the path
                                    val externalFile = getExternalFilesDir(null)?.let { 
                                        java.io.File(it, "Connectix-Update.apk").absolutePath 
                                    } ?: ""
                                    if (externalFile.isNotEmpty() && java.io.File(externalFile).exists()) {
                                        filePath = externalFile
                                        localUri = "file://$externalFile"
                                    } else {
                                        // Try to copy from content URI to cache
                                        try {
                                            val inputStream = contentResolver.openInputStream(Uri.parse(localUri))
                                            if (inputStream != null) {
                                                val cacheFile = java.io.File(cacheDir, "Connectix-Update.apk")
                                                val outputStream = java.io.FileOutputStream(cacheFile)
                                                inputStream.copyTo(outputStream)
                                                inputStream.close()
                                                outputStream.close()
                                                filePath = cacheFile.absolutePath
                                                localUri = "file://$filePath"
                                            }
                                        } catch (e: Exception) {
                                            android.util.Log.w("ConnectixDownload", "Failed to copy from content URI: ${e.message}")
                                        }
                                    }
                                } else if (localUri.startsWith("file://")) {
                                    filePath = Uri.parse(localUri).path ?: ""
                                }
                                // Fallback: check known locations
                                if (filePath.isEmpty()) {
                                    val possiblePaths = listOf(
                                        getExternalFilesDir(null)?.let { java.io.File(it, "Connectix-Update.apk").absolutePath } ?: "",
                                        java.io.File(cacheDir, "Connectix-Update.apk").absolutePath,
                                        "/storage/emulated/0/Android/data/$packageName/files/Connectix-Update.apk",
                                        "$cacheDir/Connectix-Update.apk"
                                    )
                                    for (p in possiblePaths) {
                                        if (p.isNotEmpty() && java.io.File(p).exists() && java.io.File(p).length() > 1000000) {
                                            filePath = p
                                            localUri = "file://$p"
                                            break
                                        }
                                    }
                                }
                            } catch (e: Exception) {
                                android.util.Log.w("ConnectixDownload", "File path handling failed: ${e.message}")
                            }
                            cursor.close()
                            
                            val statusStr = when(status) {
                                android.app.DownloadManager.STATUS_PENDING -> "pending"
                                android.app.DownloadManager.STATUS_RUNNING -> "running"
                                android.app.DownloadManager.STATUS_PAUSED -> "paused"
                                android.app.DownloadManager.STATUS_SUCCESSFUL -> "successful"
                                android.app.DownloadManager.STATUS_FAILED -> "failed"
                                else -> "unknown"
                            }
                            result.success(mapOf(
                                "status" to status,
                                "statusStr" to statusStr,
                                "bytes" to bytes,
                                "total" to total,
                                "reason" to reason,
                                "localUri" to (localUri ?: ""),
                                "filePath" to filePath,
                                "progress" to if (total > 0) (bytes.toDouble() / total.toDouble()) else if (bytes > 0) 0.5 else 0.0
                            ))
                        } else {
                            cursor.close()
                            result.success(mapOf("status" to -1, "statusStr" to "not_found"))
                        }
                    } catch (e: Exception) {
                        result.error("STATUS_ERROR", e.message, null)
                    }
                }
                "cancelDownloadManager" -> {
                    try {
                        val id = call.argument<Long>("downloadId") ?: downloadId
                        val dm = getSystemService(Context.DOWNLOAD_SERVICE) as android.app.DownloadManager
                        dm.remove(id)
                        result.success(true)
                    } catch (e: Exception) {
                        result.success(false)
                    }
                }
                else -> {
                    result.notImplemented()
                }
            }
        }
    }
}
