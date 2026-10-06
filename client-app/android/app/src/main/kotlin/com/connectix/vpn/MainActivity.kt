package com.connectix.vpn

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.usage.UsageStatsManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
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

class MainActivity: FlutterActivity() {
    private val CHANNEL = "com.connectix.vpn/updater"
    private val NOTIF_CHANNEL_ID = "connectix_vpn_status_ch"
    private val V2RAY_CHANNEL_ID = "A_FLUTTER_V2RAY_SERVICE_CH_ID"
    private val NOTIF_ID = 1
    private var methodChannel: MethodChannel? = null

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
                                notifMgr.cancel(NOTIF_ID)
                                notifMgr.cancel(9999)
                                result.success(true)
                                return@setMethodCallHandler
                            }

                            var channelId = NOTIF_CHANNEL_ID
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                                if (notifMgr.getNotificationChannel(V2RAY_CHANNEL_ID) != null) {
                                    channelId = V2RAY_CHANNEL_ID
                                } else {
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

                            val notif = NotificationCompat.Builder(this, channelId)
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
                            notifMgr.cancel(9999)
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
                        notifMgr?.cancel(9999)
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
                    // v4.0.13 PURE INTENT FIX - FOREVER FIX LIKE v4.0.3 WORKING VERSION
                    // User said: "یک روش دیگه ای برای نصب اعمال کن مثل آپ گذشته که مشکلی نداشت و پنجره نصب گوشی میامد و نصب انجام میشد بدون اسکن فایل"
                    // Root cause: PackageInstaller API is blocked on MIUI/Samsung Android 14+ - shows success but no UI appears
                    // Solution: Pure Intent method like v4.0.3 (ACTION_INSTALL_PACKAGE + ACTION_VIEW) - NO PackageInstaller at all
                    // This directly opens system installer window without scanning
                    val filePath = call.argument<String>("filePath")
                    val allowSameVersion = call.argument<Boolean>("allowSameVersion") ?: true
                    if (filePath == null) {
                        result.error("INVALID_ARGUMENT", "filePath is null", null)
                        return@setMethodCallHandler
                    }
                    val file = File(filePath)
                    if (!file.exists()) {
                        result.error("FILE_NOT_FOUND", "File does not exist: $filePath", null)
                        return@setMethodCallHandler
                    }
                    if (file.length() < 500000) {
                        result.error("FILE_TOO_SMALL", "File too small (${file.length()} bytes), likely HTML error page: $filePath", null)
                        return@setMethodCallHandler
                    }
                    try {
                        file.setReadable(true, false)
                        android.util.Log.i("ConnectixInstaller", "v4.0.13 PURE INTENT start: path=$filePath len=${file.length()} brand=${Build.BRAND} manufacturer=${Build.MANUFACTURER} model=${Build.MODEL} sdk=${Build.VERSION.SDK_INT}")

                        // v4.0.13: Ensure file is in external files dir (best FileProvider compatibility on MIUI/Samsung Android 10+)
                        var workingFile = file
                        try {
                            val extDir = context.getExternalFilesDir(null)
                            if (extDir != null && extDir.exists()) {
                                if (!file.absolutePath.startsWith(extDir.absolutePath)) {
                                    val newFile = File(extDir, "Connectix-Update.apk")
                                    if (newFile.exists()) newFile.delete()
                                    file.copyTo(newFile, overwrite = true)
                                    newFile.setReadable(true, false)
                                    workingFile = newFile
                                    android.util.Log.i("ConnectixInstaller", "v4.0.13 Copied APK to external dir: ${newFile.absolutePath} len=${newFile.length()}")
                                } else {
                                    file.setReadable(true, false)
                                }
                            }
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "v4.0.13 Failed to copy to external dir: ${e.message}")
                            workingFile = file
                        }

                        // v4.0.13: Prepare URI with FileProvider - try multiple fallbacks like v4.0.3
                        var uri: Uri? = null
                        var uriError: String? = null
                        try {
                            uri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                FileProvider.getUriForFile(context, context.packageName + ".fileprovider", workingFile)
                            } else {
                                Uri.fromFile(workingFile)
                            }
                        } catch (e: Exception) {
                            uriError = e.message
                            android.util.Log.e("ConnectixInstaller", "v4.0.13 FileProvider primary failed: ${e.message}")
                            // Fallback 1: try cache dir file
                            try {
                                val cacheFile = File(context.cacheDir, "Connectix-Update.apk")
                                if (cacheFile.exists() && cacheFile != workingFile) {
                                    cacheFile.setReadable(true, false)
                                    uri = FileProvider.getUriForFile(context, context.packageName + ".fileprovider", cacheFile)
                                    workingFile = cacheFile
                                    android.util.Log.i("ConnectixInstaller", "v4.0.13 FileProvider fallback to cache success")
                                }
                            } catch (e2: Exception) {
                                android.util.Log.e("ConnectixInstaller", "v4.0.13 FileProvider cache fallback failed: ${e2.message}")
                            }
                            // Fallback 2: try files dir
                            if (uri == null) {
                                try {
                                    val filesFile = File(context.filesDir, "Connectix-Update.apk")
                                    if (filesFile.exists()) {
                                        filesFile.setReadable(true, false)
                                        uri = FileProvider.getUriForFile(context, context.packageName + ".fileprovider", filesFile)
                                        workingFile = filesFile
                                        android.util.Log.i("ConnectixInstaller", "v4.0.13 FileProvider fallback to files success")
                                    }
                                } catch (e3: Exception) {
                                    android.util.Log.e("ConnectixInstaller", "v4.0.13 FileProvider files fallback failed: ${e3.message}")
                                }
                            }
                        }

                        if (uri == null) {
                            throw Exception("FileProvider failed for ${workingFile.absolutePath} len=${workingFile.length()} error=$uriError - check file_paths.xml")
                        }

                        // v4.0.13: Grant URI permission to ALL known installer packages (critical for MIUI/Samsung)
                        val installerPackages = listOf(
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
                        try {
                            for (pkg in installerPackages) {
                                try {
                                    context.grantUriPermission(pkg, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                    android.util.Log.i("ConnectixInstaller", "v4.0.13 Granted to $pkg")
                                } catch (_: Exception) {}
                            }
                        } catch (_: Exception) {}

                        // Also grant to all resolved activities for VIEW intent
                        val viewIntentForQuery = Intent(Intent.ACTION_VIEW).apply {
                            setDataAndType(uri, "application/vnd.android.package-archive")
                            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                        }
                        try {
                            val resInfoList = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                packageManager.queryIntentActivities(viewIntentForQuery, PackageManager.ResolveInfoFlags.of(PackageManager.MATCH_DEFAULT_ONLY.toLong()))
                            } else {
                                @Suppress("DEPRECATION")
                                packageManager.queryIntentActivities(viewIntentForQuery, PackageManager.MATCH_DEFAULT_ONLY)
                            }
                            android.util.Log.i("ConnectixInstaller", "v4.0.13 Found ${resInfoList.size} handlers for APK VIEW")
                            for (resolveInfo in resInfoList) {
                                try {
                                    val pkgName = resolveInfo.activityInfo.packageName
                                    context.grantUriPermission(pkgName, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                    android.util.Log.i("ConnectixInstaller", "v4.0.13 Granted URI to resolved $pkgName")
                                } catch (_: Exception) {}
                            }
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "v4.0.13 queryIntentActivities failed: ${e.message}")
                        }

                        android.util.Log.i("ConnectixInstaller", "v4.0.13 Device: manufacturer=${Build.MANUFACTURER} brand=${Build.BRAND} model=${Build.MODEL} sdk=${Build.VERSION.SDK_INT} file=${workingFile.absolutePath} len=${workingFile.length()}")

                        // v4.0.13 PURE INTENT LOGIC LIKE v4.0.3 - NO PackageInstaller
                        // This is the working method from old version that user requested
                        val intents = mutableListOf<Intent>()

                        // Intent 1: ACTION_INSTALL_PACKAGE (most reliable for updates, directly opens system installer)
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN) {
                            val installPkg = Intent(Intent.ACTION_INSTALL_PACKAGE).apply {
                                setDataAndType(uri, "application/vnd.android.package-archive")
                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                putExtra(Intent.EXTRA_RETURN_RESULT, true)
                                putExtra(Intent.EXTRA_NOT_UNKNOWN_SOURCE, true)
                                putExtra("android.intent.extra.ALLOW_REPLACE", true)
                                if (allowSameVersion) {
                                    putExtra(Intent.EXTRA_ALLOW_REPLACE, true)
                                }
                            }
                            intents.add(installPkg)
                        }

                        // Intent 2: ACTION_VIEW (fallback, also opens system installer)
                        val viewIntent = Intent(Intent.ACTION_VIEW).apply {
                            setDataAndType(uri, "application/vnd.android.package-archive")
                            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                            addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP)
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                                addFlags(Intent.FLAG_ACTIVITY_CLEAR_TASK)
                            }
                            putExtra(Intent.EXTRA_RETURN_RESULT, true)
                            putExtra("android.intent.extra.ALLOW_REPLACE", true)
                            if (allowSameVersion) {
                                putExtra(Intent.EXTRA_ALLOW_REPLACE, true)
                            }
                        }
                        intents.add(viewIntent)

                        // Try each intent - first one that works wins (like v4.0.3)
                        var lastError: Exception? = null
                        for (intent in intents) {
                            try {
                                try {
                                    val resInfoList = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                        packageManager.queryIntentActivities(intent, PackageManager.ResolveInfoFlags.of(PackageManager.MATCH_DEFAULT_ONLY.toLong()))
                                    } else {
                                        @Suppress("DEPRECATION")
                                        packageManager.queryIntentActivities(intent, PackageManager.MATCH_DEFAULT_ONLY)
                                    }
                                    if (resInfoList.isEmpty() && intent === viewIntent) {
                                        // No handler found for VIEW, try chooser (forces UI)
                                        val chooser = Intent.createChooser(intent, "نصب بروزرسانی Connectix")
                                        chooser.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                        chooser.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                        context.startActivity(chooser)
                                        android.util.Log.i("ConnectixInstaller", "v4.0.13 PURE INTENT chooser SUCCESS len=${workingFile.length()}")
                                        result.success(true)
                                        return@setMethodCallHandler
                                    }
                                    for (resolveInfo in resInfoList) {
                                        try {
                                            val pkgName = resolveInfo.activityInfo.packageName
                                            context.grantUriPermission(pkgName, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                        } catch (_: Exception) {}
                                    }
                                } catch (_: Exception) {}

                                context.startActivity(intent)
                                android.util.Log.i("ConnectixInstaller", "v4.0.13 PURE INTENT ${intent.action} SUCCESS len=${workingFile.length()} bytes - system installer window should appear NOW")
                                result.success(true)
                                return@setMethodCallHandler
                            } catch (e: Exception) {
                                lastError = e
                                android.util.Log.e("ConnectixInstaller", "v4.0.13 PURE INTENT ${intent.action} FAIL: ${e.message}", e)
                                continue
                            }
                        }

                        // Last resort: chooser
                        try {
                            val chooserIntent = Intent.createChooser(viewIntent, "نصب بروزرسانی Connectix").apply {
                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                            }
                            context.startActivity(chooserIntent)
                            android.util.Log.i("ConnectixInstaller", "v4.0.13 PURE INTENT chooser fallback SUCCESS")
                            result.success(true)
                            return@setMethodCallHandler
                        } catch (e: Exception) {
                            lastError = e
                            android.util.Log.e("ConnectixInstaller", "v4.0.13 chooser FAIL: ${e.message}", e)
                        }

                        throw lastError ?: Exception("No installer activity found - v4.0.13 pure Intent failed")

                    } catch (e: Exception) {
                        val msg = "INSTALL_ERROR v4.0.13 PURE INTENT: ${e.message} path=$filePath len=${file.length()} canRequest=${if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) packageManager.canRequestPackageInstalls() else true} sameVer=$allowSameVersion manufacturer=${Build.MANUFACTURER} brand=${Build.BRAND} model=${Build.MODEL} sdk=${Build.VERSION.SDK_INT}"
                        android.util.Log.e("ConnectixInstaller", msg, e)
                        result.error("INSTALL_ERROR", msg, null)
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
                else -> {
                    result.notImplemented()
                }
            }
        }
    }
}
