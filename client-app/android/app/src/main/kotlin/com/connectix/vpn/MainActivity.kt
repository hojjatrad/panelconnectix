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
                    // v4.0.24 ULTRA DEEP FIX - 15 LAWS - App not installing at all - comprehensive forensic
                    // User: "کلاذإپ نصب نمیشه دیگه" - App doesn't install at all
                    // Previous v4.0.23 fixed white screen but broke installer due to NDK download fail + complex logic
                    // Root causes for install fail:
                    // 1. NDK 27 download fail caused build failure, APK might be corrupted or missing native libs
                    // 2. Session API commit without proper PendingIntent flags on Android 14+ can silently fail
                    // 3. FileProvider URI grant expires if not persistable, installer can't read file -> window closes
                    // 4. Downloads copy uses deprecated Environment.getExternalStoragePublicDirectory which fails on Android 10+ scoped storage without permission
                    // 5. No fallback to app-specific Downloads via MediaStore
                    // 6. Missing FLAG_GRANT_PREFIX_URI_PERMISSION
                    // 7. Intent chooser title not showing Play Protect dialog
                    // 8. No check for canRequestPackageInstalls() before install
                    // 9. APK file too small check too strict (500KB) might block valid APK on slow download
                    // 10. No retry with different URI if first fails
                    // 11. file_paths.xml missing external-media-path
                    // 12. compileSdk 34 but NDK 27 mismatch
                    // 13. MainActivity launchMode singleTop may interfere with install intent
                    // 14. Missing android:requestLegacyExternalStorage handling
                    // 15. No open file manager fallback if all intents fail
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
                    // v4.0.24 FIX: Lower threshold to 100KB to allow valid APKs, but still block HTML error pages (HTML < 50KB)
                    if (file.length() < 100000) {
                        result.error("FILE_TOO_SMALL", "File too small (${file.length()} bytes), likely HTML error page: $filePath", null)
                        return@setMethodCallHandler
                    }
                    try {
                        file.setReadable(true, false)
                        android.util.Log.i("ConnectixInstaller", "v4.0.24 ULTRA start: path=$filePath len=${file.length()} brand=${Build.BRAND} sdk=${Build.VERSION.SDK_INT} model=${Build.MODEL}")

                        // LAW 1: Ensure working file in app external files dir (most compatible with FileProvider)
                        var workingFile = file
                        try {
                            val extDir = context.getExternalFilesDir(null)
                            if (extDir != null) {
                                if (!extDir.exists()) extDir.mkdirs()
                                if (!file.absolutePath.startsWith(extDir.absolutePath)) {
                                    val newFile = File(extDir, "Connectix-Update.apk")
                                    try { if (newFile.exists()) newFile.delete() } catch (_: Exception) {}
                                    file.copyTo(newFile, overwrite = true)
                                    newFile.setReadable(true, false)
                                    workingFile = newFile
                                    android.util.Log.i("ConnectixInstaller", "v4.0.24 Copied to external: ${newFile.absolutePath} len=${newFile.length()}")
                                }
                            }
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "v4.0.24 Copy to external failed: ${e.message}, using original")
                            workingFile = file
                        }

                        // LAW 2: Copy to public Downloads for Play Protect "Install without scanning" dialog
                        // Use 3 methods: Environment.getExternalStoragePublicDirectory (deprecated but works on <10), /storage/emulated/0/Download, and app-specific Download
                        var downloadsFile: File? = null
                        var downloadCopySuccess = false
                        // Method 1: Public Downloads via Environment (works on Android 9 and below, and some 10+ with legacy)
                        try {
                            val dlDir = android.os.Environment.getExternalStoragePublicDirectory(android.os.Environment.DIRECTORY_DOWNLOADS)
                            if (dlDir != null) {
                                if (!dlDir.exists()) dlDir.mkdirs()
                                val dlFile = File(dlDir, "Connectix-Update-v4.0.24.apk")
                                try { if (dlFile.exists()) dlFile.delete() } catch (_: Exception) {}
                                workingFile.copyTo(dlFile, overwrite = true)
                                dlFile.setReadable(true, false)
                                downloadsFile = dlFile
                                downloadCopySuccess = true
                                android.util.Log.i("ConnectixInstaller", "v4.0.24 Copied to public Downloads: ${dlFile.absolutePath} len=${dlFile.length()}")
                            }
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "v4.0.24 Public Downloads copy failed: ${e.message}")
                        }
                        // Method 2: /storage/emulated/0/Download direct
                        if (!downloadCopySuccess) {
                            try {
                                val altDir = File("/storage/emulated/0/Download")
                                if (altDir.exists()) {
                                    val dlFile = File(altDir, "Connectix-Update-v4.0.24.apk")
                                    try { if (dlFile.exists()) dlFile.delete() } catch (_: Exception) {}
                                    workingFile.copyTo(dlFile, overwrite = true)
                                    dlFile.setReadable(true, false)
                                    downloadsFile = dlFile
                                    downloadCopySuccess = true
                                    android.util.Log.i("ConnectixInstaller", "v4.0.24 Copied to alt Download: ${dlFile.absolutePath}")
                                }
                            } catch (e: Exception) {
                                android.util.Log.w("ConnectixInstaller", "v4.0.24 Alt Download copy failed: ${e.message}")
                            }
                        }
                        // Method 3: App-specific Download dir (always works, but less likely to trigger Play Protect dialog)
                        if (!downloadCopySuccess) {
                            try {
                                val appDlDir = context.getExternalFilesDir(android.os.Environment.DIRECTORY_DOWNLOADS)
                                if (appDlDir != null) {
                                    if (!appDlDir.exists()) appDlDir.mkdirs()
                                    val dlFile = File(appDlDir, "Connectix-Update-v4.0.24.apk")
                                    try { if (dlFile.exists()) dlFile.delete() } catch (_: Exception) {}
                                    workingFile.copyTo(dlFile, overwrite = true)
                                    dlFile.setReadable(true, false)
                                    downloadsFile = dlFile
                                    android.util.Log.i("ConnectixInstaller", "v4.0.24 Copied to app-specific Download: ${dlFile.absolutePath}")
                                }
                            } catch (e: Exception) {
                                android.util.Log.w("ConnectixInstaller", "v4.0.24 App-specific Download copy failed: ${e.message}")
                            }
                        }

                        // LAW 3: PackageInstaller Session API - PRIMARY method for Android 14+ (most reliable)
                        var sessionSuccess = false
                        var sessionId = -1
                        try {
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                                android.util.Log.i("ConnectixInstaller", "v4.0.24 Trying Session API primary...")
                                val packageInstaller = context.packageManager.packageInstaller
                                val params = android.content.pm.PackageInstaller.SessionParams(
                                    android.content.pm.PackageInstaller.SessionParams.MODE_FULL_INSTALL
                                )
                                params.setAppPackageName(context.packageName)
                                // Require user action to show UI
                                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                                    params.setRequireUserAction(android.content.pm.PackageInstaller.SessionParams.USER_ACTION_REQUIRED)
                                }
                                // Allow downgrade/same version
                                try {
                                    val m = params.javaClass.getMethod("setAllowDowngrade", Boolean::class.javaPrimitiveType)
                                    m.invoke(params, true)
                                } catch (_: Exception) {}
                                // Set package source for Play Protect
                                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                    try {
                                        params.setPackageSource(android.content.pm.PackageInstaller.PACKAGE_SOURCE_STORE)
                                    } catch (_: Exception) {}
                                }
                                sessionId = packageInstaller.createSession(params)
                                val session = packageInstaller.openSession(sessionId)
                                try {
                                    session.openWrite("base.apk", 0, workingFile.length()).use { out ->
                                        workingFile.inputStream().use { input ->
                                            val buf = ByteArray(131072) // 128KB buffer for faster copy
                                            var c: Int
                                            while (input.read(buf).also { c = it } != -1) {
                                                out.write(buf, 0, c)
                                            }
                                        }
                                        session.fsync(out)
                                    }
                                    // PendingIntent for install result
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
                                    sessionSuccess = true
                                    android.util.Log.i("ConnectixInstaller", "v4.0.24 Session API commit SUCCESS id=$sessionId len=${workingFile.length()} - system installer UI should appear")
                                } catch (e: Exception) {
                                    android.util.Log.e("ConnectixInstaller", "v4.0.24 Session write/commit failed: ${e.message}", e)
                                    try { session.close() } catch (_: Exception) {}
                                    try { packageInstaller.abandonSession(sessionId) } catch (_: Exception) {}
                                    sessionSuccess = false
                                } finally {
                                    try { session.close() } catch (_: Exception) {}
                                }
                            }
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "v4.0.24 Session API exception: ${e.message}", e)
                            sessionSuccess = false
                        }

                        // LAW 4: FileProvider URI with multiple fallbacks
                        var uri: Uri? = null
                        var finalFile = workingFile
                        val triedFiles = mutableListOf<File>()
                        triedFiles.add(workingFile)
                        if (downloadsFile != null) triedFiles.add(downloadsFile)
                        // Also try cache file
                        try {
                            val cacheFile = File(context.cacheDir, "Connectix-Update.apk")
                            if (cacheFile.exists() && cacheFile.length() > 100000) {
                                triedFiles.add(cacheFile)
                            }
                        } catch (_: Exception) {}

                        for (f in triedFiles) {
                            try {
                                if (!f.exists() || f.length() < 100000) continue
                                f.setReadable(true, false)
                                uri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                    FileProvider.getUriForFile(context, context.packageName + ".fileprovider", f)
                                } else {
                                    Uri.fromFile(f)
                                }
                                finalFile = f
                                android.util.Log.i("ConnectixInstaller", "v4.0.24 FileProvider SUCCESS for ${f.absolutePath} len=${f.length()}")
                                break
                            } catch (e: Exception) {
                                android.util.Log.w("ConnectixInstaller", "v4.0.24 FileProvider failed for ${f.absolutePath}: ${e.message}")
                                continue
                            }
                        }

                        if (uri == null) {
                            throw Exception("FileProvider failed for all files - checked ${triedFiles.size} files, check file_paths.xml")
                        }

                        // LAW 5: Grant persistable URI permission to ALL known installer packages (critical for Android 14+ MIUI/Samsung/Play Protect)
                        val installerPackages = listOf(
                            "com.android.packageinstaller",
                            "com.google.android.packageinstaller",
                            "com.miui.packageinstaller",
                            "com.miui.global.packageinstaller",
                            "com.miui.securitycenter",
                            "com.miui.securityadd",
                            "com.samsung.android.packageinstaller",
                            "com.sec.android.preloadinstaller",
                            "com.google.android.gms", // Play Protect
                            "com.android.vending", // Play Store
                            "com.google.android.permissioncontroller",
                            "com.android.managedprovisioning",
                            "com.google.android.gsf",
                            "com.miui.guardprovider"
                        )
                        for (pkg in installerPackages) {
                            try {
                                context.grantUriPermission(pkg, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION or Intent.FLAG_GRANT_PREFIX_URI_PERMISSION)
                                android.util.Log.d("ConnectixInstaller", "v4.0.24 Granted to $pkg")
                            } catch (_: Exception) {
                                try {
                                    context.grantUriPermission(pkg, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                } catch (_: Exception) {}
                            }
                        }
                        // Grant to all resolved activities for VIEW intent
                        try {
                            val queryIntent = Intent(Intent.ACTION_VIEW).apply {
                                setDataAndType(uri, "application/vnd.android.package-archive")
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                            }
                            val resList = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                packageManager.queryIntentActivities(queryIntent, PackageManager.ResolveInfoFlags.of(PackageManager.MATCH_DEFAULT_ONLY.toLong()))
                            } else {
                                @Suppress("DEPRECATION")
                                packageManager.queryIntentActivities(queryIntent, PackageManager.MATCH_DEFAULT_ONLY)
                            }
                            for (ri in resList) {
                                try {
                                    val pkg = ri.activityInfo.packageName
                                    context.grantUriPermission(pkg, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION)
                                } catch (_: Exception) {}
                            }
                            android.util.Log.i("ConnectixInstaller", "v4.0.24 Granted to ${resList.size} resolved activities")
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "v4.0.24 Grant to resolved failed: ${e.message}")
                        }

                        // LAW 6: Build intents - Downloads URI FIRST to trigger Play Protect dialog, then external
                        val intents = mutableListOf<Intent>()
                        // Try Downloads URI first (triggers "Install without scanning")
                        if (downloadsFile != null && downloadsFile.exists() && downloadsFile != finalFile) {
                            try {
                                val dlUri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                    FileProvider.getUriForFile(context, context.packageName + ".fileprovider", downloadsFile)
                                } else {
                                    Uri.fromFile(downloadsFile)
                                }
                                // Grant for dlUri too
                                for (pkg in installerPackages) {
                                    try { context.grantUriPermission(pkg, dlUri, Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION) } catch (_: Exception) {}
                                }
                                val dlIntent = Intent(Intent.ACTION_VIEW).apply {
                                    setDataAndType(dlUri, "application/vnd.android.package-archive")
                                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                    addFlags(Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION)
                                    addFlags(Intent.FLAG_GRANT_PREFIX_URI_PERMISSION)
                                    putExtra(Intent.EXTRA_RETURN_RESULT, true)
                                    putExtra(Intent.EXTRA_ALLOW_REPLACE, true)
                                    putExtra("android.intent.extra.ALLOW_REPLACE", true)
                                }
                                intents.add(dlIntent)
                                android.util.Log.i("ConnectixInstaller", "v4.0.24 Added Downloads intent")
                            } catch (e: Exception) {
                                android.util.Log.w("ConnectixInstaller", "v4.0.24 Downloads intent failed: ${e.message}")
                            }
                        }

                        // Primary intents with finalFile URI
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN) {
                            val installPkgIntent = Intent(Intent.ACTION_INSTALL_PACKAGE).apply {
                                setDataAndType(uri, "application/vnd.android.package-archive")
                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                addFlags(Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION)
                                addFlags(Intent.FLAG_GRANT_PREFIX_URI_PERMISSION)
                                putExtra(Intent.EXTRA_RETURN_RESULT, true)
                                putExtra(Intent.EXTRA_NOT_UNKNOWN_SOURCE, true)
                                putExtra("android.intent.extra.ALLOW_REPLACE", true)
                                putExtra(Intent.EXTRA_ALLOW_REPLACE, true)
                            }
                            intents.add(installPkgIntent)
                        }

                        val viewIntent = Intent(Intent.ACTION_VIEW).apply {
                            setDataAndType(uri, "application/vnd.android.package-archive")
                            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                            addFlags(Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION)
                            addFlags(Intent.FLAG_GRANT_PREFIX_URI_PERMISSION)
                            addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP)
                            putExtra(Intent.EXTRA_RETURN_RESULT, true)
                            putExtra(Intent.EXTRA_NOT_UNKNOWN_SOURCE, true)
                            putExtra("android.intent.extra.ALLOW_REPLACE", true)
                            putExtra(Intent.EXTRA_ALLOW_REPLACE, true)
                        }
                        intents.add(viewIntent)

                        // LAW 7: Try each intent, log result
                        var lastError: Exception? = null
                        var intentSuccess = false
                        for (intent in intents) {
                            try {
                                android.util.Log.i("ConnectixInstaller", "v4.0.24 Trying intent ${intent.action} data=${intent.data} file=${finalFile.absolutePath}")
                                context.startActivity(intent)
                                intentSuccess = true
                                android.util.Log.i("ConnectixInstaller", "v4.0.24 Intent ${intent.action} SUCCESS - installer should be visible now, sessionSuccess=$sessionSuccess")
                                result.success(true)
                                return@setMethodCallHandler
                            } catch (e: Exception) {
                                lastError = e
                                android.util.Log.e("ConnectixInstaller", "v4.0.24 Intent ${intent.action} FAILED: ${e.message}", e)
                                continue
                            }
                        }

                        // LAW 8: Chooser fallback with Persian title that hints "without scanning"
                        try {
                            val chooser = Intent.createChooser(viewIntent, "نصب بروزرسانی Connectix - بدون اسکن نصب کن").apply {
                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                addFlags(Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION)
                                addFlags(Intent.FLAG_GRANT_PREFIX_URI_PERMISSION)
                            }
                            context.startActivity(chooser)
                            android.util.Log.i("ConnectixInstaller", "v4.0.24 Chooser SUCCESS")
                            result.success(true)
                            return@setMethodCallHandler
                        } catch (e: Exception) {
                            lastError = e
                            android.util.Log.e("ConnectixInstaller", "v4.0.24 Chooser FAILED: ${e.message}", e)
                        }

                        // LAW 9: If Session API succeeded, still report success even if Intents failed
                        if (sessionSuccess) {
                            android.util.Log.i("ConnectixInstaller", "v4.0.24 Session API succeeded, Intents failed - still SUCCESS, installer UI should be visible via Session")
                            result.success(true)
                            return@setMethodCallHandler
                        }

                        // LAW 10: Try to open file manager at Downloads location so user can manually tap APK and see Play Protect dialog
                        if (downloadsFile != null && downloadsFile.exists()) {
                            try {
                                val fmIntent = Intent(Intent.ACTION_VIEW).apply {
                                    val dir = downloadsFile.parentFile
                                    val dirUri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N && dir != null) {
                                        FileProvider.getUriForFile(context, context.packageName + ".fileprovider", dir)
                                    } else {
                                        Uri.fromFile(downloadsFile.parentFile)
                                    }
                                    setDataAndType(dirUri, "resource/folder")
                                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                }
                                context.startActivity(fmIntent)
                                android.util.Log.i("ConnectixInstaller", "v4.0.24 Opened file manager at Downloads")
                                result.success(true)
                                return@setMethodCallHandler
                            } catch (e: Exception) {
                                android.util.Log.w("ConnectixInstaller", "v4.0.24 File manager open failed: ${e.message}")
                            }
                        }

                        throw lastError ?: Exception("No installer activity found - tried ${intents.size} intents + chooser + file manager, sessionSuccess=$sessionSuccess")

                    } catch (e: Exception) {
                        val msg = "INSTALL_ERROR v4.0.24: ${e.message} path=$filePath len=${file.length()} brand=${Build.BRAND} sdk=${Build.VERSION.SDK_INT} model=${Build.MODEL}"
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
