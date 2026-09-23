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
    this.pingMs,
  });

  factory ServerModel.fromJson(Map<String, dynamic> json) {
    return ServerModel(
      id: json['id'] ?? '',
      name: json['name'] ?? 'سرور ابری',
      countryName: json['country_name'] ?? 'آلمان',
      countryCode: json['country_code'] ?? 'DE',
      flag: json['flag'] ?? '🌐',
      protocol: json['protocol'] ?? 'vless',
      operatorTag: json['operator_tag'] ?? 'all',
      operatorName: json['operator_name'] ?? 'تمام اپراتورها',
      pingUrl: json['ping_url'] ?? 'https://www.google.com/generate_204',
      configUri: json['config_uri'] ?? '',
      isRecommended: json['is_recommended'] ?? false,
      pingMs: null,
    );
  }
}
