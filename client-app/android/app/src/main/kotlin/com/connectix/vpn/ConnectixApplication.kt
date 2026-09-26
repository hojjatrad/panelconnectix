package com.connectix.vpn


import android.app.Application

import java.io.File

import java.text.SimpleDateFormat

import java.util.Date

import java.util.Locale


class ConnectixApplication : Application() {

    override fun onCreate() {

        super.onCreate()

        val defaultHandler = Thread.getDefaultUncaughtExceptionHandler()

        Thread.setDefaultUncaughtExceptionHandler { thread, throwable ->

            try {

                writeCrashReport(thread, throwable)

            } catch (_: Exception) {

            }

            if (defaultHandler != null) {

                defaultHandler.uncaughtException(thread, throwable)

            } else {

                android.os.Process.killProcess(android.os.Process.myPid())

                kotlin.system.exitProcess(10)

            }

        }

    }


    private fun writeCrashReport(thread: Thread, throwable: Throwable) {

        try {

            val dir = getExternalFilesDir(null) ?: filesDir

            val file = File(dir, "connectix_last_crash.txt")

            val time = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(Date())

            val sb = StringBuilder()

            sb.append("Time: ").append(time).append("\n")

            sb.append("Thread: ").append(thread.name).append("\n")

            sb.append("Device: ").append(android.os.Build.MANUFACTURER).append(" ")

                .append(android.os.Build.MODEL).append(" (Android ")

                .append(android.os.Build.VERSION.RELEASE).append(", API ")

                .append(android.os.Build.VERSION.SDK_INT).append(")\n")

            try {

                val info = packageManager.getPackageInfo(packageName, 0)

                sb.append("App: Connectix ").append(info.versionName ?: "unknown").append("\n")

            } catch (_: Exception) {

            }

            sb.append("NativeCrash: unhandled exception (may be before Flutter first frame)\n\n")

            var ex: Throwable? = throwable

            var depth = 0

            while (ex != null && depth < 4) {

                sb.append("Exception").append(if (depth > 0) " (cause $depth)" else "")

                    .append(": ").append(ex.javaClass.name).append(": ").append(ex.message ?: "null").append("\n")

                for (line in android.util.Log.getStackTraceString(ex).trim().lines().take(15)) {

                    sb.append(line).append("\n")

                }

                sb.append("\n")

                ex = ex.cause

                depth++

            }

            file.writeText(sb.toString())

        } catch (_: Exception) {

        }

    }

}

