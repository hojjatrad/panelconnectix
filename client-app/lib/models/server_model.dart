class ServerModel {
  final String id;
  final String name;
  final String countryName;
  final String countryCode;
  final String flag;
  final String protocol;
  final String operatorTag;
  final String operatorName;
  final String pingUrl;
  final String configUri;
  final bool isRecommended;
  final bool isOnline;
  int? pingMs;

  ServerModel({
    required this.id,
    required this.name,
    required this.countryName,
    required this.countryCode,
    required this.flag,
    required this.protocol,
    required this.operatorTag,
    required this.operatorName,
    required this.pingUrl,
    required this.configUri,
    required this.isRecommended,
    this.isOnline = true,
    this.pingMs,
  });

  factory ServerModel.fromJson(Map<String, dynamic> json) {
    return ServerModel(
      id: json['id'] ?? '',
      name: json['name'] ?? 'سرور ابری',
      countryName: json['country_name'] ?? 'بین‌الملل',
      countryCode: json['country_code'] ?? 'INT',
      flag: json['flag'] ?? '🌐',
      protocol: json['protocol'] ?? 'vless',
      operatorTag: json['operator_tag'] ?? 'all',
      operatorName: json['operator_name'] ?? 'تمام اپراتورها',
      pingUrl: json['ping_url'] ?? 'https://www.google.com/generate_204',
      configUri: json['config_uri'] ?? '',
      isRecommended: json['is_recommended'] ?? false,
      isOnline: json['is_online'] ?? true,
      pingMs: null,
    );
  }

  factory ServerModel.fromUri(String rawUri, int index) {
    String uri = rawUri.trim();
    String proto = 'vless';
    if (uri.contains('://')) {
      proto = uri.split('://').first.toLowerCase();
    }

    String name = 'سرور ابری #$index';
    if (uri.contains('#')) {
      try {
        final frag = uri.split('#').last;
        name = Uri.decodeComponent(frag);
      } catch (_) {
        name = uri.split('#').last;
      }
    }

    String flag = '🌐';
    String countryName = 'بین‌الملل';
    String countryCode = 'INT';
    final lower = name.toLowerCase();

    if (lower.contains('irancell') || lower.contains('ایرانسل') || lower.contains('germany') || lower.contains('آلمان') || lower.contains('🇩🇪') || lower.contains('de')) {
      flag = '🇩🇪'; countryName = 'آلمان'; countryCode = 'DE';
    } else if (lower.contains('netherlands') || lower.contains('هلند') || lower.contains('🇳🇱') || lower.contains('nl')) {
      flag = '🇳🇱'; countryName = 'هلند'; countryCode = 'NL';
    } else if (lower.contains('fi') || lower.contains('فنلاند') || lower.contains('finland') || lower.contains('🇫🇮')) {
      flag = '🇫🇮'; countryName = 'فنلاند'; countryCode = 'FI';
    } else if (lower.contains('turkey') || lower.contains('ترکیه') || lower.contains('🇹🇷') || lower.contains('tr')) {
      flag = '🇹🇷'; countryName = 'ترکیه'; countryCode = 'TR';
    } else if (lower.contains('usa') || lower.contains('آمریکا') || lower.contains('🇺🇸') || lower.contains('us')) {
      flag = '🇺🇸'; countryName = 'آمریکا'; countryCode = 'US';
    } else if (lower.contains('united kingdom') || lower.contains('انگلستان') || lower.contains('uk') || lower.contains('🇬🇧') || lower.contains('gb')) {
      flag = '🇬🇧'; countryName = 'انگلستان'; countryCode = 'GB';
    } else if (lower.contains('france') || lower.contains('فرانسه') || lower.contains('🇫🇷') || lower.contains('fr')) {
      flag = '🇫🇷'; countryName = 'فرانسه'; countryCode = 'FR';
    } else if (lower.contains('dubai') || lower.contains('امارات') || lower.contains('دبی') || lower.contains('🇦🇪')) {
      flag = '🇦🇪'; countryName = 'امارات'; countryCode = 'AE';
    } else if (lower.contains('it') || lower.contains('ایتالیا') || lower.contains('italy') || lower.contains('🇮🇹')) {
      flag = '🇮🇹'; countryName = 'ایتالیا'; countryCode = 'IT';
    }

    String operatorTag = 'all';
    String operatorName = 'تمام اپراتورها';
    if (lower.contains('irancell') || lower.contains('ایرانسل') || lower.contains('mtn')) {
      operatorTag = 'irancell'; operatorName = 'ایرانسل';
    } else if (lower.contains('mci') || lower.contains('همراه اول')) {
      operatorTag = 'mci'; operatorName = 'همراه اول';
    } else if (lower.contains('rightel') || lower.contains('رایتل')) {
      operatorTag = 'rightel'; operatorName = 'رایتل';
    } else if (lower.contains('wifi') || lower.contains('مخابرات') || lower.contains('شاتل') || lower.contains('وای‌فای')) {
      operatorTag = 'wifi'; operatorName = 'اینترنت خانگی / Wi-Fi';
    }

    return ServerModel(
      id: 'conn_$index',
      name: name,
      countryName: countryName,
      countryCode: countryCode,
      flag: flag,
      protocol: proto,
      operatorTag: operatorTag,
      operatorName: operatorName,
      pingUrl: 'https://www.google.com/generate_204',
      configUri: uri,
      isRecommended: (index == 2 || index == 3 || index == 1),
      isOnline: true,
      pingMs: null,
    );
  }
}
