package com.connectix.vpn

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.usage.UsageStatsManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageInstaller
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
import java.io.FileInputStream
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
        // Handle install complete from PackageInstaller
        if (intent.action == "INSTALL_COMPLETE") {
            try {
                val status = intent.getIntExtra(PackageInstaller.EXTRA_STATUS, -999)
                val msg = intent.getStringExtra(PackageInstaller.EXTRA_STATUS_MESSAGE) ?: ""
                android.util.Log.i("ConnectixInstaller", "Install complete status=$status msg=$msg")
            } catch (_: Exception) {}
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
                        // v4.0.8 FIX: Use external files dir like working v4.0.3 for better FileProvider compatibility
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
                "installApk" -> {
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

                        // v4.0.10 PRO MAX FIX: Ensure file is in external files dir (not cache) for MIUI/Samsung compatibility
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
                                    android.util.Log.i("ConnectixInstaller", "Copied APK to external dir: ${newFile.absolutePath} len=${newFile.length()}")
                                }
                            }
                        } catch (e: Exception) {
                            android.util.Log.w("ConnectixInstaller", "Failed to copy to external dir: ${e.message}")
                            workingFile = file
                        }

                        // Prepare URI with FileProvider
                        val uri: Uri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                            try {
                                FileProvider.getUriForFile(context, context.packageName + ".fileprovider", workingFile)
                            } catch (e: Exception) {
                                try {
                                    val extDir = context.getExternalFilesDir(null)
                                    if (extDir != null) {
                                        val altFile = File(extDir, workingFile.name)
                                        if (altFile.exists() && altFile != workingFile) {
                                            try {
                                                FileProvider.getUriForFile(context, context.packageName + ".fileprovider", altFile)
                                            } catch (_: Exception) {
                                                throw e
                                            }
                                        } else {
                                            throw e
                                        }
                                    } else {
                                        throw e
                                    }
                                } catch (e2: Exception) {
                                    try {
                                        val cacheAlt = File(context.cacheDir, workingFile.name)
                                        if (cacheAlt.exists()) {
                                            FileProvider.getUriForFile(context, context.packageName + ".fileprovider", cacheAlt)
                                        } else {
                                            throw e2
                                        }
                                    } catch (_: Exception) {
                                        throw Exception("FileProvider failed for ${workingFile.absolutePath} len=${workingFile.length()}: ${e.message} | ${e2.message}")
                                    }
                                }
                            }
                        } else {
                            Uri.fromFile(workingFile)
                        }

                        // v4.0.10: Grant URI permission to ALL known installer packages (MIUI, Samsung, etc)
                        val installerPackages = listOf(
                            "com.android.packageinstaller",
                            "com.google.android.packageinstaller",
                            "com.miui.packageinstaller",
                            "com.miui.global.packageinstaller",
                            "com.samsung.android.packageinstaller",
                            "com.sec.android.preloadinstaller",
                            "com.android.managedprovisioning"
                        )
                        try {
                            for (pkg in installerPackages) {
                                try {
                                    context.grantUriPermission(pkg, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                } catch (_: Exception) {}
                            }
                            // Also grant to all resolved activities
                            val tempIntent = Intent(Intent.ACTION_VIEW).apply {
                                setDataAndType(uri, "application/vnd.android.package-archive")
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                            }
                            val resInfoList = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                packageManager.queryIntentActivities(tempIntent, PackageManager.ResolveInfoFlags.of(PackageManager.MATCH_DEFAULT_ONLY.toLong()))
                            } else {
                                @Suppress("DEPRECATION")
                                packageManager.queryIntentActivities(tempIntent, PackageManager.MATCH_DEFAULT_ONLY)
                            }
                            for (resolveInfo in resInfoList) {
                                try {
                                    val pkgName = resolveInfo.activityInfo.packageName
                                    context.grantUriPermission(pkgName, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                    android.util.Log.i("ConnectixInstaller", "Granted URI permission to $pkgName")
                                } catch (_: Exception) {}
                            }
                        } catch (_: Exception) {}

                        // v4.0.10 PRO MAX: Try PackageInstaller BUT also prepare Intent fallback - DON'T return immediately
                        var packageInstallerSuccess = false
                        var packageInstallerError: String? = null
                        try {
                            val packageInstaller = context.packageManager.packageInstaller
                            val params = PackageInstaller.SessionParams(PackageInstaller.SessionParams.MODE_FULL_INSTALL)
                            val sessionId = packageInstaller.createSession(params)
                            val session = packageInstaller.openSession(sessionId)
                            
                            FileInputStream(workingFile).use { input ->
                                session.openWrite("package", 0, -1).use { output ->
                                    val buffer = ByteArray(65536)
                                    var bytesRead: Int
                                    while (input.read(buffer).also { bytesRead = it } != -1) {
                                        output.write(buffer, 0, bytesRead)
                                    }
                                    session.fsync(output)
                                }
                            }
                            
                            val intent = Intent(context, MainActivity::class.java).apply {
                                action = "INSTALL_COMPLETE"
                            }
                            val pendingFlags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                            } else {
                                PendingIntent.FLAG_UPDATE_CURRENT
                            }
                            val pendingIntent = PendingIntent.getActivity(context, sessionId, intent, pendingFlags)
                            
                            session.commit(pendingIntent.intentSender)
                            session.close()
                            
                            android.util.Log.i("ConnectixInstaller", "v4.0.10 PackageInstaller session $sessionId committed for ${workingFile.length()} bytes")
                            packageInstallerSuccess = true
                        } catch (e: Exception) {
                            packageInstallerError = e.message
                            android.util.Log.e("ConnectixInstaller", "v4.0.10 PackageInstaller failed: ${e.message}", e)
                        }

                        // v4.0.10: ALWAYS also try Intent method (even if PackageInstaller succeeded) - dual approach ensures window appears on MIUI/Samsung
                        // This is the key fix: PackageInstaller alone often doesn't show UI on Xiaomi/Samsung, but Intent does
                        val intents = ArrayList<Intent>()

                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.ICE_CREAM_SANDWICH) {
                            val installPkg = Intent(Intent.ACTION_INSTALL_PACKAGE).apply {
                                setDataAndType(uri, "application/vnd.android.package-archive")
                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP)
                                putExtra(Intent.EXTRA_RETURN_RESULT, true)
                                putExtra(Intent.EXTRA_NOT_UNKNOWN_SOURCE, true)
                                putExtra("android.intent.extra.NOT_UNKNOWN_SOURCE", true)
                                putExtra(Intent.EXTRA_ALLOW_REPLACE, true)
                                putExtra("android.intent.extra.ALLOW_REPLACE", true)
                                if (allowSameVersion) {
                                    putExtra(Intent.EXTRA_ALLOW_REPLACE, true)
                                }
                            }
                            intents.add(installPkg)
                        }

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
                            putExtra(Intent.EXTRA_NOT_UNKNOWN_SOURCE, true)
                            if (allowSameVersion) {
                                putExtra(Intent.EXTRA_ALLOW_REPLACE, true)
                            }
                        }
                        intents.add(viewIntent)

                        var intentSuccess = false
                        var lastError: Exception? = null
                        for (intent in intents) {
                            try {
                                // For MIUI, we need to add extra flags
                                if (Build.MANUFACTURER.equals("Xiaomi", ignoreCase = true) || Build.MANUFACTURER.equals("Redmi", ignoreCase = true)) {
                                    intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                    intent.putExtra("isUpdate", true)
                                }

                                context.startActivity(intent)
                                intentSuccess = true
                                android.util.Log.i("ConnectixInstaller", "v4.0.10 Intent ${intent.action} launched successfully for ${workingFile.length()} bytes")
                                break // Success, don't try next
                            } catch (e: Exception) {
                                lastError = e
                                android.util.Log.e("ConnectixInstaller", "v4.0.10 Intent ${intent.action} failed: ${e.message}", e)
                                continue
                            }
                        }

                        // v4.0.10: If either PackageInstaller or Intent succeeded, return success
                        // PackageInstaller may show UI with delay, Intent shows immediately - at least one will work
                        if (packageInstallerSuccess || intentSuccess) {
                            // Small delay to allow installer UI to appear before Flutter closes modal
                            try {
                                Thread.sleep(500)
                            } catch (_: Exception) {}
                            result.success(true)
                            return@setMethodCallHandler
                        }

                        // Both failed
                        val errorDetail = "PackageInstaller: ${if (packageInstallerSuccess) "OK" else "FAIL:$packageInstallerError"} | Intent: ${if (intentSuccess) "OK" else "FAIL:${lastError?.message}"}"
                        throw lastError ?: Exception("All install methods failed - $errorDetail for ${workingFile.absolutePath}")

                    } catch (e: Exception) {
                        val msg = "INSTALL_ERROR v4.0.10: ${e.message} path=$filePath len=${file.length()} canRequest=${if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) packageManager.canRequestPackageInstalls() else true} sameVer=$allowSameVersion manufacturer=${Build.MANUFACTURER} sdk=${Build.VERSION.SDK_INT}"
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
