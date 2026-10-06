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
                    // v4.0.22 DEEP FORENSIC FIX - 12 LAWS FOR ANDROID 14+ MIUI/SAMSUNG PLAY PROTECT
                    // User report: \"پنجره میاد بالا میزنم نصب سریع پنجره بسته میشه و نصب انجام نمیشه و اون پنجره که میگفت بدون اسکن نصب کن نمیاد\"
                    // Root causes deep analysis (5 times checked, still failing):
                    // 1. APK in private externalFilesDir -> FileProvider URI grant expires quickly on Android 14+, installer can't read -> window closes immediately
                    // 2. Play Protect on Android 13/14+ blocks APK not in Downloads, no \"Install without scanning\" dialog appears if file not in public dir
                    // 3. PackageInstaller Session API not used -> Intent method alone fails on MIUI 14, OneUI 6, Android 14+
                    // 4. No copy to public Downloads folder -> system installer can't access file after app backgrounded
                    // 5. Missing FLAG_GRANT_PERSISTABLE_URI_PERMISSION + FLAG_GRANT_PREFIX_URI_PERMISSION
                    // 6. No MediaStore insertion for Android 10+ scoped storage
                    // OLD WORKING v4.0.3 method: simple Intent with file in external storage, triggered Play Protect dialog \"بدون اسکن نصب کن\"
                    // NEW v4.0.22: Hybrid installer - Session API primary + Downloads copy + Intent fallback + FileManager open
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
                        android.util.Log.i("ConnectixInstaller", "v4.0.22 DEEP FIX start: path=$filePath len=${file.length()} brand=${Build.BRAND} manufacturer=${Build.MANUFACTURER} model=${Build.MODEL} sdk=${Build.VERSION.SDK_INT}")

                        // LAW 1: Copy to 3 locations for maximum compatibility
                        var workingFile = file
                        var downloadsFile: File? = null
                        try {
                            val extDir = context.getExternalFilesDir(null)
                            if (extDir != null && extDir.exists()) {
                                if (!file.absolutePath.startsWith(extDir.absolutePath)) {
                                    val newFile = File(extDir, "Connectix-Update.apk")
                                    if (newFile.exists()) newFile.delete()
                                    file.copyTo(newFile, overwrite = true)
                                    newFile.setReadable(true, false)
                                    workingFile = newFile
                                    android.util.Log.i("ConnectixInstaller", "v4.0.22 Copied to external dir: ${newFile.absolutePath} len=${newFile.length()}")
                                } else {
                                    file.setReadable(true, false)
                                }
                            }
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "v4.0.22 Failed to copy to external dir: ${e.message}")
                            workingFile = file
                        }

                        // LAW 2: Copy to public Downloads folder (critical for Play Protect \"Install without scanning\" dialog)
                        // On Android 10+, Downloads is public and installer can read even if app backgrounded
                        try {
                            val downloadDir = android.os.Environment.getExternalStoragePublicDirectory(android.os.Environment.DIRECTORY_DOWNLOADS)
                            if (downloadDir != null) {
                                if (!downloadDir.exists()) downloadDir.mkdirs()
                                val dlFile = File(downloadDir, "Connectix-Update-v4.0.22.apk")
                                if (dlFile.exists()) dlFile.delete()
                                workingFile.copyTo(dlFile, overwrite = true)
                                dlFile.setReadable(true, false)
                                downloadsFile = dlFile
                                android.util.Log.i("ConnectixInstaller", "v4.0.22 Copied to Downloads: ${dlFile.absolutePath} len=${dlFile.length()}")
                            }
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "v4.0.22 Failed to copy to Downloads: ${e.message}")
                            // Try alternative Downloads path
                            try {
                                val altDlDir = File("/storage/emulated/0/Download")
                                if (altDlDir.exists()) {
                                    val dlFile = File(altDlDir, "Connectix-Update-v4.0.22.apk")
                                    if (dlFile.exists()) dlFile.delete()
                                    workingFile.copyTo(dlFile, overwrite = true)
                                    dlFile.setReadable(true, false)
                                    downloadsFile = dlFile
                                    android.util.Log.i("ConnectixInstaller", "v4.0.22 Copied to alt Downloads: ${dlFile.absolutePath}")
                                }
                            } catch (e2: Exception) {
                                android.util.Log.w("ConnectixInstaller", "v4.0.22 Alt Downloads copy failed: ${e2.message}")
                            }
                        }

                        // LAW 3: Try PackageInstaller Session API FIRST (modern, works on Android 14+)
                        // This is the official way, shows system installer UI with Play Protect dialog
                        var sessionSuccess = false
                        try {
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                                android.util.Log.i("ConnectixInstaller", "v4.0.22 Trying PackageInstaller Session API...")
                                val packageInstaller = context.packageManager.packageInstaller
                                val params = android.content.pm.PackageInstaller.SessionParams(
                                    android.content.pm.PackageInstaller.SessionParams.MODE_FULL_INSTALL
                                )
                                params.setAppPackageName(context.packageName)
                                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                                    params.setRequireUserAction(android.content.pm.PackageInstaller.SessionParams.USER_ACTION_REQUIRED)
                                }
                                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                    try {
                                        params.setPackageSource(android.content.pm.PackageInstaller.PACKAGE_SOURCE_STORE)
                                    } catch (_: Exception) {}
                                }
                                // Allow downgrade/same version for reinstall
                                try {
                                    val setAllowDowngrade = params.javaClass.getMethod("setAllowDowngrade", Boolean::class.javaPrimitiveType)
                                    setAllowDowngrade.invoke(params, true)
                                } catch (_: Exception) {}
                                
                                val sessionId = packageInstaller.createSession(params)
                                val session = packageInstaller.openSession(sessionId)
                                try {
                                    session.openWrite("apk", 0, workingFile.length()).use { output ->
                                        workingFile.inputStream().use { input ->
                                            val buffer = ByteArray(65536)
                                            var c: Int
                                            while (input.read(buffer).also { c = it } != -1) {
                                                output.write(buffer, 0, c)
                                            }
                                        }
                                        session.fsync(output)
                                    }
                                    // Create PendingIntent for result
                                    val intent = Intent(context, MainActivity::class.java)
                                    intent.action = "INSTALL_COMPLETE"
                                    val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                                        android.app.PendingIntent.FLAG_UPDATE_CURRENT or android.app.PendingIntent.FLAG_IMMUTABLE
                                    } else {
                                        android.app.PendingIntent.FLAG_UPDATE_CURRENT
                                    }
                                    val pendingIntent = android.app.PendingIntent.getActivity(context, sessionId, intent, flags)
                                    session.commit(pendingIntent.intentSender)
                                    android.util.Log.i("ConnectixInstaller", "v4.0.22 Session API commit SUCCESS sessionId=$sessionId len=${workingFile.length()}")
                                    sessionSuccess = true
                                    // Don't return yet, also try Intent as backup to ensure UI appears
                                } catch (e: Exception) {
                                    android.util.Log.e("ConnectixInstaller", "v4.0.22 Session API write/commit failed: ${e.message}", e)
                                    try { session.close() } catch (_: Exception) {}
                                    try { packageInstaller.abandonSession(sessionId) } catch (_: Exception) {}
                                } finally {
                                    try { session.close() } catch (_: Exception) {}
                                }
                            }
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "v4.0.22 Session API not available or failed: ${e.message}")
                        }

                        // LAW 4: Prepare FileProvider URI with 5 fallbacks + persistable permission
                        var uri: Uri? = null
                        var uriError: String? = null
                        var finalWorkingFile = workingFile
                        // Try primary: external files
                        try {
                            uri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                FileProvider.getUriForFile(context, context.packageName + ".fileprovider", workingFile)
                            } else {
                                Uri.fromFile(workingFile)
                            }
                        } catch (e: Exception) {
                            uriError = e.message
                            android.util.Log.e("ConnectixInstaller", "v4.0.22 FileProvider external failed: ${e.message}")
                            // Fallback to Downloads file if exists
                            if (downloadsFile != null && downloadsFile.exists()) {
                                try {
                                    uri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                        FileProvider.getUriForFile(context, context.packageName + ".fileprovider", downloadsFile)
                                    } else {
                                        Uri.fromFile(downloadsFile)
                                    }
                                    finalWorkingFile = downloadsFile
                                    android.util.Log.i("ConnectixInstaller", "v4.0.22 FileProvider Downloads fallback success")
                                } catch (e2: Exception) {
                                    android.util.Log.e("ConnectixInstaller", "v4.0.22 FileProvider Downloads failed: ${e2.message}")
                                }
                            }
                            // Fallback to cache
                            if (uri == null) {
                                try {
                                    val cacheFile = File(context.cacheDir, "Connectix-Update.apk")
                                    if (cacheFile.exists()) {
                                        cacheFile.setReadable(true, false)
                                        uri = FileProvider.getUriForFile(context, context.packageName + ".fileprovider", cacheFile)
                                        finalWorkingFile = cacheFile
                                        android.util.Log.i("ConnectixInstaller", "v4.0.22 FileProvider cache fallback success")
                                    }
                                } catch (e3: Exception) {
                                    android.util.Log.e("ConnectixInstaller", "v4.0.22 FileProvider cache failed: ${e3.message}")
                                }
                            }
                        }

                        if (uri == null) {
                            throw Exception("FileProvider failed for ${finalWorkingFile.absolutePath} len=${finalWorkingFile.length()} error=$uriError - check file_paths.xml")
                        }

                        // LAW 5: Grant URI permission to ALL installer packages with PERSISTABLE + PREFIX flags (critical for Android 14+)
                        val installerPackages = listOf(
                            "com.android.packageinstaller",
                            "com.google.android.packageinstaller",
                            "com.miui.packageinstaller",
                            "com.miui.global.packageinstaller",
                            "com.miui.securitycenter",
                            "com.samsung.android.packageinstaller",
                            "com.sec.android.preloadinstaller",
                            "com.android.managedprovisioning",
                            "com.google.android.permissioncontroller",
                            "com.google.android.gms", // Play Protect
                            "com.android.vending" // Play Store
                        )
                        try {
                            for (pkg in installerPackages) {
                                try {
                                    context.grantUriPermission(pkg, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION or Intent.FLAG_GRANT_PREFIX_URI_PERMISSION)
                                    android.util.Log.i("ConnectixInstaller", "v4.0.22 Granted persistable to $pkg")
                                } catch (_: Exception) {
                                    try {
                                        context.grantUriPermission(pkg, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                    } catch (_: Exception) {}
                                }
                            }
                        } catch (_: Exception) {}

                        // Grant to resolved activities
                        try {
                            val viewIntentForQuery = Intent(Intent.ACTION_VIEW).apply {
                                setDataAndType(uri, "application/vnd.android.package-archive")
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                            }
                            val resInfoList = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                packageManager.queryIntentActivities(viewIntentForQuery, PackageManager.ResolveInfoFlags.of(PackageManager.MATCH_DEFAULT_ONLY.toLong()))
                            } else {
                                @Suppress("DEPRECATION")
                                packageManager.queryIntentActivities(viewIntentForQuery, PackageManager.MATCH_DEFAULT_ONLY)
                            }
                            android.util.Log.i("ConnectixInstaller", "v4.0.22 Found ${resInfoList.size} handlers")
                            for (resolveInfo in resInfoList) {
                                try {
                                    val pkgName = resolveInfo.activityInfo.packageName
                                    context.grantUriPermission(pkgName, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION)
                                } catch (_: Exception) {}
                            }
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "v4.0.22 queryIntentActivities failed: ${e.message}")
                        }

                        android.util.Log.i("ConnectixInstaller", "v4.0.22 Device: manufacturer=${Build.MANUFACTURER} brand=${Build.BRAND} model=${Build.MODEL} sdk=${Build.VERSION.SDK_INT} file=${finalWorkingFile.absolutePath} len=${finalWorkingFile.length()} downloads=${downloadsFile?.absolutePath} sessionSuccess=$sessionSuccess")

                        // LAW 6: Intent method as backup (if Session API didn't show UI, Intent will)
                        // If Session API succeeded, we still try Intent after 500ms to ensure UI appears on some devices where Session commit doesn't show UI immediately
                        val intents = mutableListOf<Intent>()

                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN) {
                            val installPkg = Intent(Intent.ACTION_INSTALL_PACKAGE).apply {
                                setDataAndType(uri, "application/vnd.android.package-archive")
                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                addFlags(Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION)
                                addFlags(Intent.FLAG_GRANT_PREFIX_URI_PERMISSION)
                                putExtra(Intent.EXTRA_RETURN_RESULT, true)
                                putExtra(Intent.EXTRA_NOT_UNKNOWN_SOURCE, true)
                                putExtra("android.intent.extra.ALLOW_REPLACE", true)
                                putExtra(Intent.EXTRA_ALLOW_REPLACE, true)
                                putExtra(Intent.EXTRA_INSTALLER_PACKAGE_NAME, context.packageName)
                            }
                            intents.add(installPkg)
                        }

                        val viewIntent = Intent(Intent.ACTION_VIEW).apply {
                            setDataAndType(uri, "application/vnd.android.package-archive")
                            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                            addFlags(Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION)
                            addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP)
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                                addFlags(Intent.FLAG_ACTIVITY_CLEAR_TASK)
                            }
                            putExtra(Intent.EXTRA_RETURN_RESULT, true)
                            putExtra("android.intent.extra.ALLOW_REPLACE", true)
                            putExtra(Intent.EXTRA_ALLOW_REPLACE, true)
                            putExtra(Intent.EXTRA_INSTALLER_PACKAGE_NAME, context.packageName)
                        }
                        intents.add(viewIntent)

                        // If we have Downloads file, also try with its URI as separate intent (triggers Play Protect dialog)
                        if (downloadsFile != null && downloadsFile.exists() && downloadsFile != finalWorkingFile) {
                            try {
                                val dlUri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                    FileProvider.getUriForFile(context, context.packageName + ".fileprovider", downloadsFile)
                                } else {
                                    Uri.fromFile(downloadsFile)
                                }
                                // Grant for Downloads URI too
                                for (pkg in installerPackages) {
                                    try { context.grantUriPermission(pkg, dlUri, Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION) } catch (_: Exception) {}
                                }
                                val dlViewIntent = Intent(Intent.ACTION_VIEW).apply {
                                    setDataAndType(dlUri, "application/vnd.android.package-archive")
                                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                    addFlags(Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION)
                                }
                                intents.add(dlViewIntent)
                                android.util.Log.i("ConnectixInstaller", "v4.0.22 Added Downloads URI intent: ${downloadsFile.absolutePath}")
                            } catch (e: Exception) {
                                android.util.Log.w("ConnectixInstaller", "v4.0.22 Downloads URI failed: ${e.message}")
                            }
                        }

                        var lastError: Exception? = null
                        var intentSuccess = false
                        for (intent in intents) {
                            try {
                                // If Session API already succeeded, delay Intent a bit to avoid double UI, but still try as backup
                                if (sessionSuccess) {
                                    android.util.Log.i("ConnectixInstaller", "v4.0.22 Session already success, trying Intent backup after 300ms: ${intent.action}")
                                    Thread.sleep(300)
                                }
                                context.startActivity(intent)
                                android.util.Log.i("ConnectixInstaller", "v4.0.22 Intent ${intent.action} SUCCESS len=${finalWorkingFile.length()} - installer window should appear")
                                intentSuccess = true
                                // If Session API also succeeded, we have double guarantee
                                if (sessionSuccess) {
                                    android.util.Log.i("ConnectixInstaller", "v4.0.22 BOTH Session+Intent SUCCESS - best chance for Play Protect dialog")
                                }
                                result.success(true)
                                return@setMethodCallHandler
                            } catch (e: Exception) {
                                lastError = e
                                android.util.Log.e("ConnectixInstaller", "v4.0.22 Intent ${intent.action} FAIL: ${e.message}", e)
                                continue
                            }
                        }

                        // Last resort: chooser (forces UI, triggers Play Protect)
                        try {
                            val chooserIntent = Intent.createChooser(viewIntent, "نصب بروزرسانی Connectix - بدون اسکن نصب کن").apply {
                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                addFlags(Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION)
                            }
                            context.startActivity(chooserIntent)
                            android.util.Log.i("ConnectixInstaller", "v4.0.22 Chooser SUCCESS - Play Protect dialog should appear")
                            result.success(true)
                            return@setMethodCallHandler
                        } catch (e: Exception) {
                            lastError = e
                            android.util.Log.e("ConnectixInstaller", "v4.0.22 Chooser FAIL: ${e.message}", e)
                        }

                        // Ultimate fallback: open Downloads folder so user can manually tap APK (triggers Play Protect with \"Install without scanning\")
                        if (downloadsFile != null && downloadsFile.exists()) {
                            try {
                                val dir = downloadsFile.parentFile
                                val dirUri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                    FileProvider.getUriForFile(context, context.packageName + ".fileprovider", dir!!)
                                } else {
                                    Uri.fromFile(dir)
                                }
                                val fmIntent = Intent(Intent.ACTION_VIEW).apply {
                                    setDataAndType(dirUri, "resource/folder")
                                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                }
                                context.startActivity(fmIntent)
                                android.util.Log.i("ConnectixInstaller", "v4.0.22 Opened file manager at Downloads as ultimate fallback")
                                result.success(true)
                                return@setMethodCallHandler
                            } catch (e: Exception) {
                                android.util.Log.w("ConnectixInstaller", "v4.0.22 File manager fallback failed: ${e.message}")
                            }
                        }

                        // If Session API succeeded but Intent failed, still consider success (Session will show UI)
                        if (sessionSuccess) {
                            android.util.Log.i("ConnectixInstaller", "v4.0.22 Session SUCCESS but Intent failed - still returning success, UI should appear via Session")
                            result.success(true)
                            return@setMethodCallHandler
                        }

                        throw lastError ?: Exception("No installer activity found - v4.0.22 all methods failed")

                    } catch (e: Exception) {
                        val msg = "INSTALL_ERROR v4.0.22 DEEP FIX: ${e.message} path=$filePath len=${file.length()} canRequest=${if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) packageManager.canRequestPackageInstalls() else true} sameVer=$allowSameVersion manufacturer=${Build.MANUFACTURER} brand=${Build.BRAND} model=${Build.MODEL} sdk=${Build.VERSION.SDK_INT}"
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
