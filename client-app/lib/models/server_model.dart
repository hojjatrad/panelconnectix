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
  final bool isInfo;
  final bool isReal;
  int? pingMs;
  bool isPinging;

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
    this.isInfo = false,
    this.isReal = true,
    this.pingMs,
    this.isPinging = false,
  });

  factory ServerModel.fromJson(Map<String, dynamic> json) {
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
      protocolLabel: json['protocol_label']?.toString() ?? (json['protocol'] ?? 'vless').toString().toUpperCase(),
      protocolIcon: json['protocol_icon']?.toString() ?? _getProtocolIcon(json['protocol'] ?? 'vless'),
      protocolColor: json['protocol_color']?.toString() ?? _getProtocolColor(json['protocol'] ?? 'vless'),
      protocolDesc: json['protocol_desc']?.toString() ?? _getProtocolDesc(json['protocol'] ?? 'vless'),
      bestFor: json['best_for']?.toString() ?? _getBestFor(json['protocol'] ?? 'vless'),
      operatorTag: json['operator_tag']?.toString() ?? '',
      operatorName: json['operator_name']?.toString() ?? '',
      pingUrl: json['ping_url']?.toString() ?? 'https://www.google.com/generate_204',
      configUri: json['config_uri']?.toString() ?? '',
      isRecommended: json['is_recommended'] == true,
      isOnline: json['is_online'] == true || json['is_online'] == null,
      isInfo: panelIsInfo,
      isReal: panelIsReal,
      pingMs: json['ping_ms'] is int ? json['ping_ms'] : null,
    );
  }

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

  Map<String, dynamic> toJson() => {
    'id': id, 'name': name, 'country_name': countryName, 'country_code': countryCode, 'flag': flag, 'protocol': protocol,
    'protocol_label': protocolLabel, 'protocol_icon': protocolIcon, 'protocol_color': protocolColor, 'protocol_desc': protocolDesc,
    'best_for': bestFor, 'operator_tag': operatorTag, 'operator_name': operatorName, 'ping_url': pingUrl, 'config_uri': configUri,
    'is_recommended': isRecommended, 'is_online': isOnline, 'is_info': isInfo, 'is_real': isReal, 'ping_ms': pingMs,
  };

  String get pingDisplay {
    if (pingMs == null) return '--';
    if (pingMs! < 0) return 'خطا';
    if (pingMs == 0) return 'آماده';
    return '$pingMs ms';
  }
  String get displayProtocolLabel => protocolLabel.isNotEmpty ? protocolLabel : protocol.toUpperCase();
  String get displayIcon => protocolIcon.isNotEmpty ? protocolIcon : _getProtocolIcon(protocol);
  String get displayDesc => protocolDesc.isNotEmpty ? protocolDesc : _getProtocolDesc(protocol);
  String get displayProtocolDesc => displayDesc;
  bool get isTuic => protocol.toLowerCase() == 'tuic';
  bool get isHysteria2 => protocol.toLowerCase() == 'hysteria2' || protocol.toLowerCase() == 'hy2' || protocol.toLowerCase() == 'hysteria';
  bool get isVless => protocol.toLowerCase() == 'vless';
  int get protocolColorInt {
    try {
      var hex = protocolColor.replaceAll('#', '');
      if (hex.length == 6) hex = 'FF$hex';
      return int.parse(hex, radix: 16);
    } catch (_) {
      return 0xFF6B7280;
    }
  }

  bool get isInfoBanner {
    if (isInfo) return true;
    if (!isReal) return true;
    final lower = name.toLowerCase();
    final lowerUri = configUri.toLowerCase();
    if (name.contains('📊') || name.contains('⏳') || name.contains('📉') || name.contains('⌛') || name.contains('💾') || name.contains('📈') || name.contains('⏰') || name.contains('⌚') || name.contains('💿') || name.contains('📅') || name.contains('👤') || name.contains('👥')) return true;
    final volumeKeywords = ['روز', 'ساعت', 'دقیقه', 'ثانیه', 'انقضا', 'اعتبار', 'حجم', 'مصرف', 'ترافیک', 'باقی', 'مانده', 'کل', 'زمان', 'مدت', 'شده', 'تاریخ', 'مهلت', 'پایان', 'شروع', 'کاربر', 'اکانت', 'یوزر', 'پروفایل'];
    if (volumeKeywords.any((k) => lower.contains(k)) && (RegExp(r'\d').hasMatch(name) || RegExp(r'(gb|mb)', caseSensitive: false).hasMatch(name) || name.contains('%'))) return true;
    if (lower.contains('روز اعتبار') || lower.contains('اعتبار باقی') || lower.contains('حجم باقی') || lower.contains('ترافیک') || lower.contains('انقضا') || lower.contains('traffic remaining') || lower.contains('expire') || lower.contains('quota') || lower.contains('account')) return true;
    if (lowerUri.contains('traffic') || lowerUri.contains('expire') || lowerUri.contains('account')) return true;
    if (configUri.isEmpty || configUri.length < 30) return true;
    if (RegExp(r'^\s*\d+\s*%\s*$').hasMatch(name) || RegExp(r'^\s*\d+(\.\d+)?\s*(gb|mb)\s*$', caseSensitive: false).hasMatch(name)) return true;
    return false;
  }

  bool get isRealVpnServer {
    if (isInfoBanner) return false;
    if (configUri.isEmpty || configUri.length < 30) return false;
    final validProtocols = ['vless://', 'vmess://', 'trojan://', 'ss://', 'socks://', 'http://', 'wireguard://', 'hysteria://', 'hysteria2://', 'tuic://', 'juicity://'];
    if (!validProtocols.any((p) => configUri.toLowerCase().startsWith(p))) return false;
    final lower = name.toLowerCase();
    final forbidden = ['حجم', 'مصرف', 'ترافیک', 'روز', 'ساعت', 'انقضا', 'اعتبار', 'باقی', 'مانده', 'traffic', 'expire', 'remaining', 'volume', 'quota', 'account', 'subscription', 'info', 'status', '📊', '⏳', '👤', 'کاربر', 'اکانت', 'یوزر'];
    if (forbidden.any((k) => lower.contains(k))) return false;
    return true;
  }

  bool get isSelectableForSmartConnect {
    if (isInfoBanner) return false;
    if (!isRealVpnServer) return false;
    if (configUri.isEmpty || configUri.length < 30) return false;
    final lower = name.toLowerCase();
    if (lower.contains('📊') || lower.contains('⏳') || lower.contains('👤') || lower.contains('حجم') || lower.contains('روز') || lower.contains('traffic') || lower.contains('account')) return false;
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
    String? id, String? name, String? countryName, String? countryCode, String? flag, String? protocol,
    String? protocolLabel, String? protocolIcon, String? protocolColor, String? protocolDesc, String? bestFor,
    String? operatorTag, String? operatorName, String? pingUrl, String? configUri, bool? isRecommended,
    bool? isOnline, bool? isInfo, bool? isReal, int? pingMs, bool? isPinging,
  }) {
    return ServerModel(
      id: id ?? this.id, name: name ?? this.name, countryName: countryName ?? this.countryName,
      countryCode: countryCode ?? this.countryCode, flag: flag ?? this.flag, protocol: protocol ?? this.protocol,
      protocolLabel: protocolLabel ?? this.protocolLabel, protocolIcon: protocolIcon ?? this.protocolIcon,
      protocolColor: protocolColor ?? this.protocolColor, protocolDesc: protocolDesc ?? this.protocolDesc,
      bestFor: bestFor ?? this.bestFor, operatorTag: operatorTag ?? this.operatorTag,
      operatorName: operatorName ?? this.operatorName, pingUrl: pingUrl ?? this.pingUrl,
      configUri: configUri ?? this.configUri, isRecommended: isRecommended ?? this.isRecommended,
      isOnline: isOnline ?? this.isOnline, isInfo: isInfo ?? this.isInfo, isReal: isReal ?? this.isReal,
      pingMs: pingMs ?? this.pingMs, isPinging: isPinging ?? this.isPinging,
    );
  }

  factory ServerModel.fromUri(String rawUri, int index) {
    String uri = rawUri.trim();
    String proto = 'vless';
    if (uri.contains('://')) proto = uri.split('://').first.toLowerCase();
    else if (uri.trim().startsWith('{')) proto = 'raw';
    else if (uri.contains('proxies:') || uri.contains('proxy-groups:')) proto = 'clash';
    String name = 'سرور ابری #$index';
    if (uri.contains('#')) {
      try {
        final frag = uri.split('#').last;
        if (frag.length < 200) name = Uri.decodeComponent(frag);
      } catch (_) {}
    }
    String flag = '🌐'; String countryName = 'بین‌الملل'; String countryCode = 'INT';
    final lower = name.toLowerCase();
    if (lower.contains('germany') || lower.contains('آلمان') || lower.contains('🇩🇪')) { flag = '🇩🇪'; countryName = 'آلمان'; countryCode = 'DE'; }
    else if (lower.contains('netherlands') || lower.contains('هلند') || lower.contains('🇳🇱')) { flag = '🇳🇱'; countryName = 'هلند'; countryCode = 'NL'; }
    else if (lower.contains('finland') || lower.contains('🇫🇮')) { flag = '🇫🇮'; countryName = 'فنلاند'; countryCode = 'FI'; }
    String operatorTag = 'all'; String operatorName = 'تمام اپراتورها';
    if (lower.contains('irancell') || lower.contains('ایرانسل')) { operatorTag = 'irancell'; operatorName = 'ایرانسل'; }
    else if (lower.contains('mci') || lower.contains('همراه اول')) { operatorTag = 'mci'; operatorName = 'همراه اول'; }
    return ServerModel(
      id: 'conn_$index', name: name, countryName: countryName, countryCode: countryCode, flag: flag, protocol: proto,
      protocolLabel: proto.toUpperCase(), protocolIcon: _getProtocolIcon(proto), protocolColor: _getProtocolColor(proto),
      protocolDesc: _getProtocolDesc(proto), bestFor: _getBestFor(proto), operatorTag: operatorTag, operatorName: operatorName,
      pingUrl: 'https://www.google.com/generate_204', configUri: uri, isRecommended: (index == 2 || index == 3 || index == 1), isOnline: true,
    );
  }
}
