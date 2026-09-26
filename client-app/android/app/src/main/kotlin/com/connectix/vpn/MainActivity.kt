package com.connectix.vpn


import android.content.Intent

import android.net.Uri

import android.os.Build

import android.provider.Settings

import androidx.core.content.FileProvider

import io.flutter.embedding.android.FlutterActivity

import io.flutter.embedding.engine.FlutterEngine

import io.flutter.plugin.common.MethodChannel

import java.io.File


class MainActivity: FlutterActivity() {

    private val CHANNEL = "com.connectix.vpn/updater"


    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {

        super.configureFlutterEngine(flutterEngine)

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CHANNEL).setMethodCallHandler { call, result ->

            when (call.method) {

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

