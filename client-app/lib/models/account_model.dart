import 'dart:convert';

class VpnAccount {
  final String id;
  final String username;
  final String panelUrl;
  final String displayName;
  final String colorHex;
  final String avatarEmoji;
  final int lastUsed;
  final int serverCount;
  final String planTitle;
  final String apiKey; // v4.0.49 API KEY support

  VpnAccount({
    required this.id,
    required this.username,
    required this.panelUrl,
    this.displayName = '',
    this.colorHex = '#8B5CF6',
    this.avatarEmoji = '👤',
    this.lastUsed = 0,
    this.serverCount = 0,
    this.planTitle = '',
    this.apiKey = '',
  });

  String get effectiveName => displayName.isNotEmpty ? displayName : username;
  String get shortPanel {
    try {
      final uri = Uri.parse(panelUrl);
      return uri.host;
    } catch (_) {
      return panelUrl;
    }
  }

  bool get hasApiKey => apiKey.isNotEmpty;

  Map<String, dynamic> toJson() => {
    'id': id,
    'username': username,
    'panelUrl': panelUrl,
    'displayName': displayName,
    'colorHex': colorHex,
    'avatarEmoji': avatarEmoji,
    'lastUsed': lastUsed,
    'serverCount': serverCount,
    'planTitle': planTitle,
    'apiKey': apiKey,
  };

  factory VpnAccount.fromJson(Map<String, dynamic> j) => VpnAccount(
    id: j['id'] ?? '',
    username: j['username'] ?? '',
    panelUrl: j['panelUrl'] ?? j['panel_url'] ?? 'https://vpbotn.ir',
    displayName: j['displayName'] ?? j['display_name'] ?? '',
    colorHex: j['colorHex'] ?? j['color_hex'] ?? '#8B5CF6',
    avatarEmoji: j['avatarEmoji'] ?? j['avatar_emoji'] ?? '👤',
    lastUsed: j['lastUsed'] ?? j['last_used'] ?? 0,
    serverCount: j['serverCount'] ?? j['server_count'] ?? 0,
    planTitle: j['planTitle'] ?? j['plan_title'] ?? '',
    apiKey: j['apiKey'] ?? j['api_key'] ?? '',
  );

  VpnAccount copyWith({
    String? displayName,
    String? colorHex,
    String? avatarEmoji,
    int? lastUsed,
    int? serverCount,
    String? planTitle,
    String? panelUrl,
    String? apiKey,
  }) => VpnAccount(
    id: id,
    username: username,
    panelUrl: panelUrl ?? this.panelUrl,
    displayName: displayName ?? this.displayName,
    colorHex: colorHex ?? this.colorHex,
    avatarEmoji: avatarEmoji ?? this.avatarEmoji,
    lastUsed: lastUsed ?? this.lastUsed,
    serverCount: serverCount ?? this.serverCount,
    planTitle: planTitle ?? this.planTitle,
    apiKey: apiKey ?? this.apiKey,
  );
}

class AccountServerEntry {
  final String accountId;
  final String accountName;
  final String accountColor;
  final String accountAvatar;
  final dynamic server; // ServerModel
  AccountServerEntry({
    required this.accountId,
    required this.accountName,
    required this.accountColor,
    required this.accountAvatar,
    required this.server,
  });
}
