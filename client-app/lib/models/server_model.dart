class ServerModel {
  final String id;
  final String name;
  final String countryName;
  final String countryCode;
  final String flag;
  final String protocol;
  final String protocolLabel;
  final String protocolIcon;
  final String protocolColor;
  final String protocolDesc;
  final String bestFor;
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
    this.protocolLabel = '',
    this.protocolIcon = '🌐',
    this.protocolColor = '#6B7280',
    this.protocolDesc = '',
    this.bestFor = 'عمومی',
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
      protocolLabel: json['protocol_label'] ?? (json['protocol'] ?? 'vless').toString().toUpperCase(),
      protocolIcon: json['protocol_icon'] ?? _getProtocolIcon(json['protocol'] ?? 'vless'),
      protocolColor: json['protocol_color'] ?? _getProtocolColor(json['protocol'] ?? 'vless'),
      protocolDesc: json['protocol_desc'] ?? _getProtocolDesc(json['protocol'] ?? 'vless'),
      bestFor: json['best_for'] ?? _getBestFor(json['protocol'] ?? 'vless'),
      operatorTag: json['operator_tag'] ?? 'all',
      operatorName: json['operator_name'] ?? 'تمام اپراتورها',
      pingUrl: json['ping_url'] ?? 'https://www.google.com/generate_204',
      configUri: json['config_uri'] ?? '',
      isRecommended: json['is_recommended'] ?? false,
      isOnline: json['is_online'] ?? true,
      pingMs: json['ping_ms'] is int ? json['ping_ms'] : null,
    );
  }
  
  // v4.0.73 NEW: Multi-protocol metadata helpers
  static String _getProtocolIcon(String proto) {
    switch (proto.toLowerCase()) {
      case 'vless': return '🛡️';
      case 'vmess': return '🔒';
      case 'trojan': return '🐴';
      case 'ss': case 'shadowsocks': return '👻';
      case 'hysteria2': case 'hy2': case 'hysteria': return '⚡';
      case 'tuic': return '🚀';
      case 'wireguard': return '🔐';
      default: return '🌐';
    }
  }
  static String _getProtocolColor(String proto) {
    switch (proto.toLowerCase()) {
      case 'vless': return '#10B981';
      case 'vmess': return '#3B82F6';
      case 'trojan': return '#EF4444';
      case 'ss': case 'shadowsocks': return '#8B5CF6';
      case 'hysteria2': case 'hy2': case 'hysteria': return '#F59E0B';
      case 'tuic': return '#8B5CF6';
      case 'wireguard': return '#06B6D4';
      default: return '#6B7280';
    }
  }
  static String _getProtocolDesc(String proto) {
    switch (proto.toLowerCase()) {
      case 'vless': return 'پایدار + مقاوم در برابر فیلتر - 9.5/10';
      case 'hysteria2': case 'hy2': return 'سریع برای اینترنت ضعیف - استریم + دانلود';
      case 'tuic': return 'کمترین تاخیر 110-140ms - بازی + تماس صوتی';
      case 'vmess': return 'کلاسیک + سازگار';
      case 'trojan': return 'ساده + سریع';
      default: return '';
    }
  }
  static String _getBestFor(String proto) {
    switch (proto.toLowerCase()) {
      case 'vless': return 'روزمره + استقامت';
      case 'hysteria2': case 'hy2': return 'استریم + دانلود حجیم';
      case 'tuic': return 'بازی آنلاین + تماس صوتی + ترید';
      case 'vmess': return 'سازگاری عمومی';
      case 'trojan': return 'سرعت بالا';
      default: return 'عمومی';
    }
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'country_name': countryName,
      'country_code': countryCode,
      'flag': flag,
      'protocol': protocol,
      'protocol_label': protocolLabel,
      'protocol_icon': protocolIcon,
      'protocol_color': protocolColor,
      'protocol_desc': protocolDesc,
      'best_for': bestFor,
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
    if (pingMs == 0) return 'آماده'; // v4.0.14: server exists but ping not measurable, show as ready
    return '$pingMs ms';
  }

  bool get isInfoBanner {
    final lower = name.toLowerCase();
    final lowerUri = configUri.toLowerCase();
    if (name.contains('📊') || name.contains('⏳') || name.contains('📉') || name.contains('⌛') || name.contains('💾') || name.contains('📈') || name.contains('⏰') || name.contains('⌚') || name.contains('💿') || name.contains('📅')) {
      return true;
    }
    final volumeKeywords = ['روز', 'ساعت', 'دقیقه', 'ثانیه', 'انقضا', 'اعتبار', 'حجم', 'مصرف', 'ترافیک', 'باقی', 'مانده', 'کل', 'زمان', 'مدت', 'شده', 'تاریخ', 'مهلت', 'پایان'];
    final hasVolumeKeyword = volumeKeywords.any((k) => lower.contains(k));
    final hasNumber = RegExp(r'\d').hasMatch(name);
    final hasGBMB = RegExp(r'(gb|mb)', caseSensitive: false).hasMatch(name);
    final hasPercent = name.contains('%');
    if (hasVolumeKeyword && (hasNumber || hasGBMB || hasPercent)) {
      return true;
    }
    if (lower.contains('روز اعتبار') || lower.contains('اعتبار باقی') || lower.contains('حجم باقی') || lower.contains('حجم مصرف') || lower.contains('مصرف حجم') || lower.contains('مصرف شده') || lower.contains('حجم کل') || lower.contains('ترافیک') || lower.contains('انقضا') || lower.contains('باقی مانده') || lower.contains('زمان باقی') || lower.contains('مدت باقی') || lower.contains('traffic remaining') || lower.contains('traffic used') || lower.contains('expire') || lower.contains('remaining') || lower.contains('volume') || lower.contains('quota') || lower.contains('usage') || lower.contains('subscription info') || lower.contains('account info')) {
      return true;
    }
    if (RegExp(r'\d+(\.\d+)?\s*(gb|mb)', caseSensitive: false).hasMatch(name) && (lower.contains('روز') || lower.contains('day') || lower.contains('expire') || lower.contains('اعتبار') || lower.contains('حجم') || lower.contains('مصرف') || lower.contains('ترافیک') || lower.contains('زمان') || lower.contains('ساعت'))) {
      return true;
    }
    if (lowerUri.contains('%f0%9f%93%8a') || lowerUri.contains('traffic') || lowerUri.contains('expire') || lowerUri.contains('info') || lowerUri.contains('remaining') || lowerUri.contains('quota')) {
      return true;
    }
    if (configUri.isEmpty || configUri.length < 30) {
      return true;
    }
    if (name.length < 20 && hasNumber && hasGBMB) {
      final hasCountry = RegExp(r'(germany|france|usa|netherlands|finland|turkey|iran|uk|gb|de|nl|fi|tr|ir|us)', caseSensitive: false).hasMatch(lower);
      final hasVolumeWord = lower.contains('حجم') || lower.contains('مصرف') || lower.contains('ترافیک') || lower.contains('باقی') || lower.contains('روز') || lower.contains('traffic') || lower.contains('expire');
      if (hasVolumeWord) return true;
      if (name.length < 12 && !hasCountry) return true;
    }
    if (RegExp(r'^\s*\d+\s*%\s*$').hasMatch(name) || RegExp(r'^\s*\d+(\.\d+)?\s*(gb|mb)\s*$', caseSensitive: false).hasMatch(name)) {
      return true;
    }
    return false;
  }
  bool get isRealVpnServer {
    if (isInfoBanner) return false;
    if (configUri.isEmpty) return false;
    if (configUri.length < 30) return false;
    final validProtocols = ['vless://', 'vmess://', 'trojan://', 'ss://', 'socks://', 'http://', 'wireguard://', 'hysteria://', 'hysteria2://', 'tuic://', 'juicity://'];
    final hasValidProto = validProtocols.any((p) => configUri.toLowerCase().startsWith(p));
    if (!hasValidProto) return false;
    final lower = name.toLowerCase();
    final forbidden = ['حجم', 'مصرف', 'ترافیک', 'روز', 'ساعت', 'انقضا', 'اعتبار', 'باقی', 'مانده', 'traffic', 'expire', 'remaining', 'volume', 'quota', 'account', 'subscription', 'info'];
    if (forbidden.any((k) => lower.contains(k))) return false;
    if (RegExp(r'^\s*\d+\s*%\s*$').hasMatch(name) || RegExp(r'^\s*\d+(\.\d+)?\s*(gb|mb)\s*$', caseSensitive: false).hasMatch(name)) return false;
    return true;
  }
  bool get isSelectableForSmartConnect {
    if (isInfoBanner) return false;
    if (!isRealVpnServer) return false;
    if (configUri.isEmpty || configUri.length < 30) return false;
    final lower = name.toLowerCase();
    if (lower.contains('📊') || lower.contains('⏳') || lower.contains('حجم') || lower.contains('روز') || lower.contains('ساعت') || lower.contains('انقضا') || lower.contains('باقی') || lower.contains('traffic') || lower.contains('expire') || lower.contains('remaining') || lower.contains('quota')) return false;
    return true;
  }
  String get rejectReason {
    if (isInfoBanner) return 'isInfoBanner';
    if (!isRealVpnServer) return 'notReal';
    if (configUri.isEmpty || configUri.length < 30) return 'invalidUri';
    return 'ok';
  }

  // v4.0.73 NEW: Protocol display helpers
  String get displayProtocolLabel => protocolLabel.isNotEmpty ? protocolLabel : protocol.toUpperCase();
  String get displayIcon => protocolIcon.isNotEmpty ? protocolIcon : _getProtocolIcon(protocol);
  String get displayDesc => protocolDesc.isNotEmpty ? protocolDesc : _getProtocolDesc(protocol);
  
  bool get isTuic => protocol.toLowerCase() == 'tuic';
  bool get isHysteria2 => protocol.toLowerCase() == 'hysteria2' || protocol.toLowerCase() == 'hy2' || protocol.toLowerCase() == 'hysteria';
  bool get isVless => protocol.toLowerCase() == 'vless';
  
  // Color as int for Flutter
  int get protocolColorInt {
    try {
      var hex = protocolColor.replaceAll('#', '');
      if (hex.length == 6) hex = 'FF$hex';
      return int.parse(hex, radix: 16);
    } catch (_) {
      return 0xFF6B7280;
    }
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
