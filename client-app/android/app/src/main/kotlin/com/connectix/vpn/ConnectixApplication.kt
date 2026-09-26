package com.connectix.vpn

import android.app.Application
import android.os.Build
import java.io.File
import java.io.PrintWriter
import java.io.StringWriter
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * 3.3.5: catches Java/Kotlin exceptions that happen BEFORE Flutter's first
 * frame (Application/Activity creation, plugin & provider init). The 3.3.3
 * Dart-level crash capture can never see those. The report is written to a
 * plain text file that MainActivity exposes to the Dart side, which then
 * shows it on the crash screen and auto-sends it to support on next launch.
 */
class ConnectixApplication : Application() {

    override fun onCreate() {
        super.onCreate()
        installCrashCapture()
    }

    private fun installCrashCapture() {
        val previous = Thread.getDefaultUncaughtExceptionHandler()
        Thread.setDefaultUncaughtExceptionHandler { thread, throwable ->
            try {
                val sb = StringBuilder()
                val time = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(Date())
                sb.append("Native/Java crash (captured before Flutter start)\n")
                sb.append("Time: $time\n")
                sb.append("Thread: ${thread.name}\n")
                sb.append("Exception: ${throwable.javaClass.name}: ${throwable.message}\n")
                sb.append("Device: ${Build.MANUFACTURER} ${Build.MODEL} / Android ${Build.VERSION.RELEASE} (API ${Build.VERSION.SDK_INT})\n")
                try {
                    val pkg = packageManager.getPackageInfo(packageName, 0)
                    val vc = if (android.os.Build.VERSION.SDK_INT >= 28) pkg.longVersionCode else pkg.versionCode.toLong()
                    sb.append("App: v${pkg.versionName} (code $vc)\n")
                } catch (_: Exception) {}
                // Causal chain (Caused by: ...)
                var cause = throwable
                var depth = 0
                while (cause != null && depth < 4) {
                    sb.append("\n--- chain[$depth]: ${cause.javaClass.name}: ${cause.message}\n")
                    val sw = StringWriter()
                    cause.printStackTrace(PrintWriter(sw))
                    sb.append(sw.toString().split("\n").take(15).joinToString("\n"))
                    cause = cause.cause
                    depth++
                }
                val dir = externalFilesDir ?: filesDir
                File(dir, "connectix_last_crash.txt").writeText(sb.toString())
            } catch (_: Exception) {
                // Never let the handler itself crash the process.
            }
            // Delegate to the previous handler (default = process death).
            if (previous != null) {
                previous.uncaughtException(thread, throwable)
            } else {
                android.os.Process.killProcess(android.os.Process.myPid())
            }
        }
    }
}
