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
      pingMs: json['ping_ms'] is int ? json['ping_ms'] : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'country_name': countryName,
      'country_code': countryCode,
      'flag': flag,
      'protocol': protocol,
      'operator_tag': operatorTag,
      'operator_name': operatorName,
      'ping_url': pingUrl,
      'config_uri': configUri,
      'is_recommended': isRecommended,
      'is_online': isOnline,
      'ping_ms': pingMs,
    };
  }

  String get pingDisplay {
    if (pingMs == null) return '--';
    if (pingMs! < 0) return 'خطا';
    return '$pingMs ms';
  }

  /// True if this node is an upstream quota/status pseudo-config rather than a functional proxy server
  bool get isInfoBanner {
    final lower = name.toLowerCase();
    final lowerUri = configUri.toLowerCase();
    if (name.contains('📊') || name.contains('⏳') || name.contains('📉') || name.contains('⌛')) {
      return true;
    }
    if (lower.contains('روز اعتبار') ||
        lower.contains('اعتبار باقی') ||
        lower.contains('حجم باقی') ||
        lower.contains('انقضا') ||
        lower.contains('traffic remaining')) {
      return true;
    }
    if (RegExp(r'\d+(\.\d+)?\s*(gb|mb)', caseSensitive: false).hasMatch(name) &&
        (lower.contains('روز') || lower.contains('day') || lower.contains('expire') || lower.contains('اعتبار'))) {
      return true;
    }
    if (lowerUri.contains('%f0%9f%93%8a') || lowerUri.contains('%e2%8f%b3')) {
      return true;
    }
    return false;
  }

  factory ServerModel.fromUri(String rawUri, int index) {
    String uri = rawUri.trim();
    String proto = 'vless';
    if (uri.contains('://')) {
      proto = uri.split('://').first.toLowerCase();
    } else if (uri.trim().startsWith('{')) {
      proto = 'raw';
    } else if (uri.contains('proxies:') || uri.contains('proxy-groups:')) {
      proto = 'clash';
    }

    String name = 'سرور ابری #$index';
    if (uri.contains('#')) {
      try {
        final frag = uri.split('#').last;
        if (frag.length < 200) {
          name = Uri.decodeComponent(frag);
        }
      } catch (_) {
        try {
          final frag = uri.split('#').last;
          if (frag.length < 200) name = frag;
        } catch (_) {}
      }
    }
    // If raw JSON, try to extract remark/ps
    if (name.startsWith('سرور ابری') && uri.trim().startsWith('{')) {
      try {
        // Attempt to parse remark from JSON comment or ps field
        if (uri.contains('"ps"')) {
          final m = RegExp(r'"ps"\s*:\s*"([^"]+)"').firstMatch(uri);
          if (m != null) name = m.group(1)!;
        }
      } catch (_) {}
    }

    String flag = '🌐';
    String countryName = 'بین‌الملل';
    String countryCode = 'INT';
    final lower = name.toLowerCase();

    if (lower.contains('irancell') || lower.contains('ایرانسل') || lower.contains('germany') || lower.contains('آلمان') || lower.contains('🇩🇪')) {
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
