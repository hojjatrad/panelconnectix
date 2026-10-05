import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import '../services/api_service.dart';

class ProxyScreen extends StatefulWidget {
  const ProxyScreen({Key? key}) : super(key: key);

  @override
  State<ProxyScreen> createState() => _ProxyScreenState();
}

class _ProxyScreenState extends State<ProxyScreen> {
  bool _isLoading = true;
  String _error = '';
  Map<String, dynamic>? _proxies;

  @override
  void initState() {
    super.initState();
    _loadProxies();
  }

  Future<void> _loadProxies() async {
    setState(() {
      _isLoading = true;
      _error = '';
    });
    try {
      final data = await ApiService.getProxies();
      if (mounted) {
        setState(() {
          _isLoading = false;
          if (data != null) {
            _proxies = data;
          } else {
            _error = 'دریافت پروکسی‌ها ناموفق بود. اینترنت را چک کنید.';
          }
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _error = 'خطا: $e';
        });
      }
    }
  }

  void _copyToClipboard(String text, String label) {
    Clipboard.setData(ClipboardData(text: text));
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('✅ $label کپی شد'), backgroundColor: const Color(0xFF10B981), duration: const Duration(seconds: 2)),
    );
  }

  void _openUrl(String url) async {
    try {
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF090D16),
      appBar: AppBar(
        backgroundColor: const Color(0xFF0F172A),
        elevation: 0,
        title: const Text('پروکسی برای تلگرام و سایر برنامه‌ها', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh, size: 20), onPressed: _loadProxies, tooltip: 'بروزرسانی'),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF6366F1)))
          : _error.isNotEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.error_outline, color: Color(0xFFEF4444), size: 48),
                      const SizedBox(height: 12),
                      Text(_error, style: const TextStyle(color: Color(0xFFF87171), fontSize: 13), textAlign: TextAlign.center),
                      const SizedBox(height: 16),
                      ElevatedButton.icon(onPressed: _loadProxies, icon: const Icon(Icons.refresh, size: 16), label: const Text('تلاش مجدد'), style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF6366F1))),
                    ],
                  ),
                )
              : _proxies == null
                  ? const Center(child: Text('پروکسی‌ای یافت نشد', style: TextStyle(color: Color(0xFF64748B))))
                  : SingleChildScrollView(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Info banner
                          Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              gradient: const LinearGradient(colors: [Color(0xFF1E1B4B), Color(0xFF312E81)]),
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: const Color(0xFF6366F1).withOpacity(0.3)),
                            ),
                            child: Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(8),
                                  decoration: BoxDecoration(color: const Color(0xFF10B981).withOpacity(0.15), borderRadius: BorderRadius.circular(10)),
                                  child: const Icon(Icons.check_circle_rounded, color: Color(0xFF10B981), size: 20),
                                ),
                                const SizedBox(width: 12),
                                const Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text('رایگان برای شما + قابل فروش جدا', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12)),
                                      SizedBox(height: 2),
                                      Text('از سرورهای فعلی استفاده میشه - برای تلگرام و همه برنامه‌ها', style: TextStyle(color: Color(0xFFA5B4FC), fontSize: 10, height: 1.4)),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 16),

                          // Local proxies (when VPN connected)
                          _buildSectionTitle('پروکسی محلی (وقتی VPN وصله) - رایگان', Icons.phone_android_rounded, const Color(0xFF10B981)),
                          const SizedBox(height: 8),
                          _buildProxyCard(
                            title: 'SOCKS5 محلی',
                            url: _proxies!['local']?['socks']?['url'] ?? 'socks5://127.0.0.1:10808',
                            host: '127.0.0.1',
                            port: '10808',
                            type: 'SOCKS5',
                            note: 'برای همین گوشی - تلگرام → SOCKS5 → 127.0.0.1:10808',
                            isFree: true,
                            icon: Icons.phone_iphone_rounded,
                            color: const Color(0xFF10B981),
                          ),
                          const SizedBox(height: 10),
                          _buildProxyCard(
                            title: 'HTTP محلی',
                            url: _proxies!['local']?['http']?['url'] ?? 'http://127.0.0.1:10809',
                            host: '127.0.0.1',
                            port: '10809',
                            type: 'HTTP',
                            note: 'برای TV و کنسول: 192.168.43.1:10809 از طریق هات‌اسپات',
                            isFree: true,
                            icon: Icons.tv_rounded,
                            color: const Color(0xFF38BDF8),
                          ),
                          const SizedBox(height: 20),

                          // Dedicated proxies (without VPN)
                          _buildSectionTitle('پروکسی اختصاصی (بدون VPN) - رایگان برای VPN', Icons.cloud_rounded, const Color(0xFF6366F1)),
                          const SizedBox(height: 8),
                          _buildProxyCard(
                            title: 'SOCKS5 اختصاصی',
                            url: _proxies!['dedicated']?['socks']?['url'] ?? '',
                            host: _proxies!['dedicated']?['socks']?['host'] ?? '',
                            port: _proxies!['dedicated']?['socks']?['port']?.toString() ?? '1080',
                            username: _proxies!['dedicated']?['socks']?['username'] ?? '',
                            password: _proxies!['dedicated']?['socks']?['password'] ?? '',
                            type: 'SOCKS5',
                            note: 'بدون نیاز به VPN - برای تلگرام و سایر برنامه‌ها',
                            isFree: true,
                            icon: Icons.security_rounded,
                            color: const Color(0xFF9333EA),
                            showQr: true,
                          ),
                          const SizedBox(height: 10),
                          _buildProxyCard(
                            title: 'HTTP اختصاصی',
                            url: _proxies!['dedicated']?['http']?['url'] ?? '',
                            host: _proxies!['dedicated']?['http']?['host'] ?? '',
                            port: _proxies!['dedicated']?['http']?['port']?.toString() ?? '8080',
                            username: _proxies!['dedicated']?['http']?['username'] ?? '',
                            password: _proxies!['dedicated']?['http']?['password'] ?? '',
                            type: 'HTTP',
                            note: 'برای مرورگر کروم، فایرفاکس و سایر برنامه‌ها',
                            isFree: true,
                            icon: Icons.language_rounded,
                            color: const Color(0xFFF59E0B),
                            showQr: true,
                          ),
                          const SizedBox(height: 20),

                          // MTProto
                          _buildSectionTitle('MTProto تلگرام - مخصوص تلگرام', Icons.telegram_rounded, const Color(0xFF38BDF8)),
                          const SizedBox(height: 8),
                          _buildMtprotoCard(),
                          const SizedBox(height: 20),

                          // Tutorials
                          _buildSectionTitle('آموزش‌ها', Icons.menu_book_rounded, const Color(0xFF94A3B8)),
                          const SizedBox(height: 8),
                          _buildTutorialCard('تلگرام با SOCKS5', _proxies!['tutorials']?['telegram_socks']?['steps'] ?? [], Icons.telegram_rounded, const Color(0xFF38BDF8)),
                          const SizedBox(height: 10),
                          _buildTutorialCard('تلگرام با MTProto', _proxies!['tutorials']?['telegram_mtproto']?['steps'] ?? [], Icons.telegram_rounded, const Color(0xFF38BDF8)),
                          const SizedBox(height: 10),
                          _buildTutorialCard('مرورگر', _proxies!['tutorials']?['browser']?['steps'] ?? [], Icons.language_rounded, const Color(0xFFF59E0B)),
                          const SizedBox(height: 20),

                          // Sell info
                          Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: const Color(0xFF1E293B),
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: const Color(0xFF334155)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.all(6),
                                      decoration: BoxDecoration(color: const Color(0xFFF59E0B).withOpacity(0.15), borderRadius: BorderRadius.circular(8)),
                                      child: const Icon(Icons.sell_rounded, color: Color(0xFFF59E0B), size: 16),
                                    ),
                                    const SizedBox(width: 8),
                                    const Text('قابل فروش جدا به عنوان پلن پروکسی', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12)),
                                  ],
                                ),
                                const SizedBox(height: 10),
                                const Text(
                                  '• میتوانید پلن \"فقط پروکسی\" با قیمت ارزان‌تر (مثلا 30% VPN) بسازید\n'
                                  '• مشتری فقط پروکسی میگیره، نه VPN کامل\n'
                                  '• از همین سرورهای فعلی استفاده میشه (Xray inbound 1080/8080)\n'
                                  '• ترافیک پروکسی جدا یا مشترک قابل تنظیم\n'
                                  '• در ربات تلگرام دکمه \"خرید پروکسی\" اضافه کنید\n'
                                  '• برای تلگرام و همه برنامه‌ها کار میکنه',
                                  style: TextStyle(color: Color(0xFF94A3B8), fontSize: 11, height: 1.6),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 20),
                        ],
                      ),
                    ),
    );
  }

  Widget _buildSectionTitle(String title, IconData icon, Color color) {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(6),
          decoration: BoxDecoration(color: color.withOpacity(0.15), borderRadius: BorderRadius.circular(8)),
          child: Icon(icon, color: color, size: 14),
        ),
        const SizedBox(width: 8),
        Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
      ],
    );
  }

  Widget _buildProxyCard({
    required String title,
    required String url,
    required String host,
    required String port,
    String? username,
    String? password,
    required String type,
    required String note,
    required bool isFree,
    required IconData icon,
    required Color color,
    bool showQr = false,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFF0F172A),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFF1E293B)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(color: color.withOpacity(0.15), borderRadius: BorderRadius.circular(10)),
                child: Icon(icon, color: color, size: 18),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12)),
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(color: color.withOpacity(0.15), borderRadius: BorderRadius.circular(6), border: Border.all(color: color.withOpacity(0.3))),
                          child: Text(type, style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.bold)),
                        ),
                        if (isFree) ...[
                          const SizedBox(width: 4),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                            decoration: BoxDecoration(color: const Color(0xFF10B981).withOpacity(0.15), borderRadius: BorderRadius.circular(6)),
                            child: const Text('رایگان', style: TextStyle(color: Color(0xFF10B981), fontSize: 8, fontWeight: FontWeight.bold)),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(note, style: const TextStyle(color: Color(0xFF64748B), fontSize: 10)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: const Color(0xFF1E293B), borderRadius: BorderRadius.circular(10)),
            child: Column(
              children: [
                _buildInfoRow('هاست:', host, host),
                _buildInfoRow('پورت:', port, port),
                if (username != null && username.isNotEmpty) _buildInfoRow('یوزر:', username, username),
                if (password != null && password.isNotEmpty) _buildInfoRow('پسورد:', '••••••••', password),
                const SizedBox(height: 6),
                Row(
                  children: [
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: () => _copyToClipboard(url, title),
                        icon: const Icon(Icons.copy_rounded, size: 14),
                        label: const Text('کپی لینک', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                        style: ElevatedButton.styleFrom(backgroundColor: color, padding: const EdgeInsets.symmetric(vertical: 8), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
                      ),
                    ),
                    if (showQr) ...[
                      const SizedBox(width: 8),
                      OutlinedButton.icon(
                        onPressed: () => _copyToClipboard(url, 'QR $title'),
                        icon: const Icon(Icons.qr_code_rounded, size: 14),
                        label: const Text('QR', style: TextStyle(fontSize: 10)),
                        style: OutlinedButton.styleFrom(foregroundColor: color, side: BorderSide(color: color), padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String display, String copyValue) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11)),
          InkWell(
            onTap: () => _copyToClipboard(copyValue, label),
            child: Row(
              children: [
                Text(display, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
                const SizedBox(width: 4),
                const Icon(Icons.copy_rounded, color: Color(0xFF64748B), size: 12),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMtprotoCard() {
    final mtproto = _proxies!['mtproto'] ?? {};
    final url = mtproto['url'] ?? '';
    final host = mtproto['host'] ?? '';
    final port = mtproto['port']?.toString() ?? '443';
    final secret = mtproto['secret_short'] ?? '';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF0F172A), Color(0xFF1E293B)]),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFF38BDF8).withOpacity(0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(color: const Color(0xFF38BDF8).withOpacity(0.15), borderRadius: BorderRadius.circular(10)),
                child: const Icon(Icons.telegram_rounded, color: Color(0xFF38BDF8), size: 18),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('MTProto تلگرام', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12)),
                    Text('مخصوص تلگرام - بدون نیاز به فیلترشکن', style: TextStyle(color: Color(0xFF64748B), fontSize: 10)),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(color: const Color(0xFF10B981).withOpacity(0.15), borderRadius: BorderRadius.circular(6)),
                child: const Text('رایگان', style: TextStyle(color: Color(0xFF10B981), fontSize: 8, fontWeight: FontWeight.bold)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: const Color(0xFF0F172A), borderRadius: BorderRadius.circular(10)),
            child: Column(
              children: [
                _buildInfoRow('سرور:', host, host),
                _buildInfoRow('پورت:', port, port),
                _buildInfoRow('Secret:', '${secret.substring(0, 8)}...', secret),
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: () => _openUrl(url),
                    icon: const Icon(Icons.telegram_rounded, size: 16),
                    label: const Text('باز کردن در تلگرام', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF38BDF8), padding: const EdgeInsets.symmetric(vertical: 10), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
                  ),
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    Expanded(child: OutlinedButton.icon(onPressed: () => _copyToClipboard(url, 'MTProto'), icon: const Icon(Icons.copy_rounded, size: 12), label: const Text('کپی لینک', style: TextStyle(fontSize: 10)), style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF38BDF8), side: const BorderSide(color: Color(0xFF38BDF8)), padding: const EdgeInsets.symmetric(vertical: 8), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))))),
                    const SizedBox(width: 8),
                    Expanded(child: OutlinedButton.icon(onPressed: () => _copyToClipboard(mtproto['tg_url'] ?? '', 'tg://'), icon: const Icon(Icons.link_rounded, size: 12), label: const Text('tg://', style: TextStyle(fontSize: 10)), style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF94A3B8), side: const BorderSide(color: Color(0xFF334155)), padding: const EdgeInsets.symmetric(vertical: 8), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))))),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTutorialCard(String title, List<dynamic> steps, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: const Color(0xFF0F172A), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFF1E293B))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: color, size: 14),
              const SizedBox(width: 6),
              Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 11)),
            ],
          ),
          const SizedBox(height: 8),
          ...steps.map((step) => Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 16,
                      height: 16,
                      decoration: BoxDecoration(color: color.withOpacity(0.15), shape: BoxShape.circle),
                      child: Center(child: Text('${steps.indexOf(step) + 1}', style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.bold))),
                    ),
                    const SizedBox(width: 8),
                    Expanded(child: Text(step.toString(), style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 10, height: 1.4))),
                  ],
                ),
              )),
        ],
      ),
    );
  }
}
