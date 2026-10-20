import 'dart:convert';

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
  final bool isInfo; // v4.0.76 NEW: from panel flag
  final bool isReal; // v4.0.76 NEW: from panel flag
  int? pingMs;
  bool isPinging;

  ServerModel({
    required this.id,
    required this.name,
    required this.countryName,
    required this.countryCode,
    required this.flag,
    required this.protocol,
    required this.protocolLabel,
    required this.protocolIcon,
    required this.protocolColor,
    required this.protocolDesc,
    required this.bestFor,
    required this.operatorTag,
    required this.operatorName,
    required this.pingUrl,
    required this.configUri,
    required this.isRecommended,
    required this.isOnline,
    this.isInfo = false,
    this.isReal = true,
    this.pingMs,
    this.isPinging = false,
  });

  factory ServerModel.fromJson(Map<String, dynamic> json) {
    // v4.0.76: Read is_info flag from panel if present
    bool panelIsInfo = false;
    if (json.containsKey('is_info')) {
      panelIsInfo = json['is_info'] == true || json['is_info'] == 1 || json['is_info'] == '1';
    } else if (json.containsKey('is_traffic_info')) {
      panelIsInfo = json['is_traffic_info'] == true || json['is_traffic_info'] == 1;
    }
    bool panelIsReal = true;
    if (json.containsKey('is_real')) {
      panelIsReal = json['is_real'] == true || json['is_real'] == 1;
    } else {
      panelIsReal = !panelIsInfo;
    }

    return ServerModel(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? 'Unknown',
      countryName: json['country_name']?.toString() ?? 'Unknown',
      countryCode: json['country_code']?.toString() ?? 'XX',
      flag: json['flag']?.toString() ?? '🌐',
      protocol: json['protocol']?.toString() ?? 'vless',
      protocolLabel: json['protocol_label']?.toString() ?? 'VLESS',
      protocolIcon: json['protocol_icon']?.toString() ?? '🔒',
      protocolColor: json['protocol_color']?.toString() ?? '#6366F1',
      protocolDesc: json['protocol_desc']?.toString() ?? '',
      bestFor: json['best_for']?.toString() ?? 'عمومی',
      operatorTag: json['operator_tag']?.toString() ?? '',
      operatorName: json['operator_name']?.toString() ?? '',
      pingUrl: json['ping_url']?.toString() ?? 'https://www.google.com/generate_204',
      configUri: json['config_uri']?.toString() ?? '',
      isRecommended: json['is_recommended'] == true,
      isOnline: json['is_online'] == true || json['is_online'] == null,
      isInfo: panelIsInfo,
      isReal: panelIsReal,
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
      'is_info': isInfo,
      'is_real': isReal,
    };
  }

  // v4.0.76 ULTRA STRICT FINAL - هرگز حجم/زمان را به عنوان کانکشن انتخاب نکند
  // 4-layer filter: panel flag + name keywords + URI + size heuristics
  bool get isInfoBanner {
    // Layer 1: Panel flag (100% guarantee)
    if (isInfo) return true;
    if (!isReal) return true;

    final lower = name.toLowerCase();
    final lowerUri = configUri.toLowerCase();

    // Layer 2: Emoji check - including 👤 for account info
    if (name.contains('📊') || name.contains('⏳') || name.contains('📉') || name.contains('⌛') || name.contains('💾') || name.contains('📈') || name.contains('⏰') || name.contains('⌚') || name.contains('💿') || name.contains('📅') || name.contains('👤') || name.contains('👥') || name.contains('🙍') || name.contains('🙎')) {
      return true;
    }

    // Layer 3: Persian + English keywords with numbers + account info
    final volumeKeywords = ['روز', 'ساعت', 'دقیقه', 'ثانیه', 'انقضا', 'اعتبار', 'حجم', 'مصرف', 'ترافیک', 'باقی', 'مانده', 'کل', 'زمان', 'مدت', 'شده', 'تاریخ', 'مهلت', 'پایان', 'شروع', 'کاربر', 'اکانت', 'یوزر', 'پروفایل'];
    final hasVolumeKeyword = volumeKeywords.any((k) => lower.contains(k));
    final hasNumber = RegExp(r'\d').hasMatch(name);
    final hasGBMB = RegExp(r'(gb|mb)', caseSensitive: false).hasMatch(name);
    final hasPercent = name.contains('%');

    if (hasVolumeKeyword && (hasNumber || hasGBMB || hasPercent)) {
      return true;
    }

    // Layer 3b: Direct phrases
    if (lower.contains('روز اعتبار') ||
        lower.contains('اعتبار باقی') ||
        lower.contains('حجم باقی') ||
        lower.contains('حجم مصرف') ||
        lower.contains('مصرف حجم') ||
        lower.contains('مصرف شده') ||
        lower.contains('حجم کل') ||
        lower.contains('ترافیک') ||
        lower.contains('انقضا') ||
        lower.contains('باقی مانده') ||
        lower.contains('زمان باقی') ||
        lower.contains('مدت باقی') ||
        lower.contains('traffic remaining') ||
        lower.contains('traffic used') ||
        lower.contains('expire') ||
        lower.contains('remaining') ||
        lower.contains('used traffic') ||
        lower.contains('total traffic') ||
        lower.contains('time left') ||
        lower.contains('days left') ||
        lower.contains('volume') ||
        lower.contains('quota') ||
        lower.contains('usage') ||
        lower.contains('subscription info') ||
        lower.contains('account info') ||
        lower.contains('account status') ||
        lower.contains('traffic info')) {
      return true;
    }

    // Layer 3c: GB/MB + volume context
    if (RegExp(r'\d+(\.\d+)?\s*(gb|mb)', caseSensitive: false).hasMatch(name) &&
        (lower.contains('روز') || lower.contains('day') || lower.contains('expire') || lower.contains('اعتبار') || lower.contains('حجم') || lower.contains('مصرف') || lower.contains('ترافیک') || lower.contains('زمان') || lower.contains('ساعت'))) {
      return true;
    }

    // Layer 4: URI encoded emoji or keywords
    if (lowerUri.contains('%f0%9f%93%8a') || lowerUri.contains('traffic') || lowerUri.contains('expire') || lowerUri.contains('info') || lowerUri.contains('remaining') || lowerUri.contains('quota') || lowerUri.contains('account')) {
      return true;
    }

    // Layer 5: Invalid URI
    if (configUri.isEmpty || configUri.length < 30) {
      return true;
    }

    // Layer 6: Short name + GB/MB heuristic
    if (name.length < 20 && hasNumber && hasGBMB) {
      final hasCountry = RegExp(r'(germany|france|usa|netherlands|finland|turkey|iran|uk|gb|de|nl|fi|tr|ir|us|singapore|japan|canada)', caseSensitive: false).hasMatch(lower);
      final hasVolumeWord = lower.contains('حجم') || lower.contains('مصرف') || lower.contains('ترافیک') || lower.contains('باقی') || lower.contains('روز') || lower.contains('traffic') || lower.contains('expire') || lower.contains('quota') || lower.contains('account');
      if (hasVolumeWord) return true;
      if (name.length < 12 && !hasCountry) return true;
    }

    // Layer 7: Pure percent or pure GB/MB
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
    final forbidden = ['حجم', 'مصرف', 'ترافیک', 'روز', 'ساعت', 'انقضا', 'اعتبار', 'باقی', 'مانده', 'traffic', 'expire', 'remaining', 'volume', 'quota', 'account', 'subscription', 'info', 'status', '📊', '⏳', '👤', 'کاربر', 'اکانت', 'یوزر'];
    if (forbidden.any((k) => lower.contains(k))) return false;
    if (RegExp(r'^\s*\d+\s*%\s*$').hasMatch(name) || RegExp(r'^\s*\d+(\.\d+)?\s*(gb|mb)\s*$', caseSensitive: false).hasMatch(name)) return false;
    return true;
  }

  bool get isSelectableForSmartConnect {
    if (isInfoBanner) return false;
    if (!isRealVpnServer) return false;
    if (configUri.isEmpty || configUri.length < 30) return false;
    final lower = name.toLowerCase();
    if (lower.contains('📊') || lower.contains('⏳') || lower.contains('👤') || lower.contains('حجم') || lower.contains('روز') || lower.contains('ساعت') || lower.contains('انقضا') || lower.contains('باقی') || lower.contains('traffic') || lower.contains('expire') || lower.contains('remaining') || lower.contains('quota') || lower.contains('account') || lower.contains('subscription') || lower.contains('info') || lower.contains('کاربر') || lower.contains('اکانت')) return false;
    // v4.0.78 NEW: Block servers that contain 👤 or look like account info
    if (name.contains('👤') || name.contains('👥')) return false;
    return true;
  }

  String get rejectReason {
    if (isInfo) return 'panelFlag_is_info';
    if (!isReal) return 'panelFlag_not_real';
    if (isInfoBanner) return 'isInfoBanner';
    if (!isRealVpnServer) return 'notReal';
    if (configUri.isEmpty || configUri.length < 30) return 'invalidUri';
    return 'ok';
  }

  ServerModel copyWith({
    String? id,
    String? name,
    String? countryName,
    String? countryCode,
    String? flag,
    String? protocol,
    String? protocolLabel,
    String? protocolIcon,
    String? protocolColor,
    String? protocolDesc,
    String? bestFor,
    String? operatorTag,
    String? operatorName,
    String? pingUrl,
    String? configUri,
    bool? isRecommended,
    bool? isOnline,
    bool? isInfo,
    bool? isReal,
    int? pingMs,
    bool? isPinging,
  }) {
    return ServerModel(
      id: id ?? this.id,
      name: name ?? this.name,
      countryName: countryName ?? this.countryName,
      countryCode: countryCode ?? this.countryCode,
      flag: flag ?? this.flag,
      protocol: protocol ?? this.protocol,
      protocolLabel: protocolLabel ?? this.protocolLabel,
      protocolIcon: protocolIcon ?? this.protocolIcon,
      protocolColor: protocolColor ?? this.protocolColor,
      protocolDesc: protocolDesc ?? this.protocolDesc,
      bestFor: bestFor ?? this.bestFor,
      operatorTag: operatorTag ?? this.operatorTag,
      operatorName: operatorName ?? this.operatorName,
      pingUrl: pingUrl ?? this.pingUrl,
      configUri: configUri ?? this.configUri,
      isRecommended: isRecommended ?? this.isRecommended,
      isOnline: isOnline ?? this.isOnline,
      isInfo: isInfo ?? this.isInfo,
      isReal: isReal ?? this.isReal,
      pingMs: pingMs ?? this.pingMs,
      isPinging: isPinging ?? this.isPinging,
    );
  }
}
