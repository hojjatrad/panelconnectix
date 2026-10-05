import 'package:flutter/material.dart';

class ClientModel {
  final int id;
  final String username;
  final String status;
  final String planTitle;
  final String serverName;
  final double trafficTotalGb;
  final double trafficUsedGb;
  final double trafficRemainingGb;
  final double usagePercent;
  final String expireAt;
  final String daysRemaining;
  final String subUrl;

  ClientModel({
    required this.id,
    required this.username,
    required this.status,
    required this.planTitle,
    required this.serverName,
    required this.trafficTotalGb,
    required this.trafficUsedGb,
    required this.trafficRemainingGb,
    required this.usagePercent,
    required this.expireAt,
    required this.daysRemaining,
    required this.subUrl,
  });

  factory ClientModel.fromJson(Map<String, dynamic> json) {
    return ClientModel(
      id: json['id'] ?? 0,
      username: json['username'] ?? '',
      status: json['status'] ?? 'active',
      planTitle: json['plan_title'] ?? 'اشتراک استاندارد',
      serverName: json['server_name'] ?? 'سرور ابری پرسرعت',
      trafficTotalGb: (json['traffic_total_gb'] as num?)?.toDouble() ?? 0.0,
      trafficUsedGb: (json['traffic_used_gb'] as num?)?.toDouble() ?? 0.0,
      trafficRemainingGb: (json['traffic_remaining_gb'] as num?)?.toDouble() ?? 0.0,
      usagePercent: (json['usage_percent'] as num?)?.toDouble() ?? 0.0,
      expireAt: json['expire_at'] ?? '',
      daysRemaining: json['days_remaining'] ?? '',
      subUrl: json['sub_url'] ?? '',
    );
  }
}

class BrandingModel {
  final String appName;
  final String logoUrl;
  final String themeColor;
  final String telegramSupport;
  final String whatsappSupport;
  final String renewalUrl;
  final String supportLink;
  final String announcement;

  BrandingModel({
    required this.appName,
    required this.logoUrl,
    required this.themeColor,
    required this.telegramSupport,
    required this.whatsappSupport,
    required this.renewalUrl,
    this.supportLink = '',
    this.announcement = '',
  });

  factory BrandingModel.fromJson(Map<String, dynamic> json) {
    return BrandingModel(
      appName: json['app_name'] ?? 'Connectix VPN',
      logoUrl: json['logo_url'] ?? '',
      themeColor: json['theme_color'] ?? 'violet',
      telegramSupport: json['telegram_support'] ?? '@Support',
      whatsappSupport: json['whatsapp_support'] ?? '',
      renewalUrl: json['renewal_url'] ?? '',
      supportLink: (json['support_link'] ?? '').toString(),
      announcement: (json['announcement'] ?? '').toString(),
    );
  }

  /// Reseller white-label accent color (the 5 panel themes)
  Color get accentColor {
    switch (themeColor) {
      case 'cyan':
        return const Color(0xFF22D3EE);
      case 'emerald':
        return const Color(0xFF10B981);
      case 'amber':
        return const Color(0xFFF59E0B);
      case 'rose':
        return const Color(0xFFF43F5E);
      case 'blue':
        return const Color(0xFF3B82F6);
      case 'green':
        return const Color(0xFF34D399);
      case 'orange':
        return const Color(0xFFFBBF24);
      case 'black':
        return const Color(0xFF94A3B8);
      case 'violet':
      default:
        return const Color(0xFFA855F7);
    }
  }
}
