import 'package:flutter/material.dart';
import '../services/gps_spoof_service.dart';

class GpsSpoofScreen extends StatefulWidget {
  const GpsSpoofScreen({super.key});

  @override
  State<GpsSpoofScreen> createState() => _GpsSpoofScreenState();
}

class _GpsSpoofScreenState extends State<GpsSpoofScreen> {
  bool _isMockEnabled = false;
  bool _canMock = false;
  bool _isSpoofing = false;
  Map<String, dynamic>? _currentMock;
  List<Map<String, dynamic>> _presets = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    final mockStatus = await GpsSpoofService.isMockLocationEnabled();
    final current = await GpsSpoofService.getCurrentMock();
    final presets = await GpsSpoofService.getPresetsList();
    
    setState(() {
      _isMockEnabled = mockStatus['enabled'] == true;
      _canMock = mockStatus['canMock'] == true;
      _currentMock = current;
      _isSpoofing = current != null;
      _presets = presets;
      _isLoading = false;
    });
  }

  Future<void> _setPreset(String key) async {
    final preset = GpsSpoofService.presets[key];
    if (preset == null) return;
    
    // Show confirmation for Meta mode
    if (key == 'meta_hq') {
      final confirm = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: const Text('🥷 حالت متا'),
          content: const Text(
            'برای ساخت توکن متا بهترین تنظیم:\n\n'
            '• GPS: منلو پارک، کالیفرنیا (دفتر متا)\n'
            '• IP: باید آمریکا باشه (VPN آمریکا وصل کن)\n'
            '• Timezone: America/Los_Angeles\n'
            '• زبان: انگلیسی آمریکا پیشنهاد میشه\n\n'
            'متا فکر میکنه از دفتر خودش داری توکن میسازی!\n\n'
            'ادامه بدم؟'
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('لغو')),
            ElevatedButton(onPressed: () => Navigator.pop(c, true), child: const Text('فعال کن')),
          ],
        ),
      );
      if (confirm != true) return;
    }

    setState(() => _isLoading = true);
    final ok = await GpsSpoofService.setPreset(key);
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('✅ GPS به ${preset['name_fa']} تغییر کرد')),
      );
      await _loadData();
    } else {
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('❌ خطا - آیا Mock Location App را انتخاب کردی؟')),
      );
      _showMockSetupDialog();
    }
  }

  void _showMockSetupDialog() {
    showDialog(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('🔓 فعال‌سازی جعل GPS'),
        content: const Text(
          'برای جعل GPS بدون روت (روش 1):\n\n'
          '1. برو Settings → About Phone\n'
          '2. روی Build Number 7 بار بزن → Developer Options فعال میشه\n'
          '3. برو Settings → Developer Options\n'
          '4. گزینه Mock Location App یا Select mock location app را پیدا کن\n'
          '5. Connectix را انتخاب کن\n\n'
          'برای اندروید 12+ (روش 3):\n'
          'نیازی به تنظیم بالا نیست - خودکار از طریق VPN Service کار میکنه!\n\n'
          'بعد از تنظیم، دوباره امتحان کن.'
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c), child: const Text('فهمیدم')),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(c);
              GpsSpoofService.openMockLocationSettings();
            },
            child: const Text('باز کردن تنظیمات'),
          ),
        ],
      ),
    );
  }

  Future<void> _stopSpoofing() async {
    setState(() => _isLoading = true);
    await GpsSpoofService.stopMockLocation();
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('✅ جعل GPS خاموش شد - GPS واقعی')),
    );
    await _loadData();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('📍 جعل GPS - مخفی کردن کشور'),
        actions: [
          IconButton(onPressed: _loadData, icon: const Icon(Icons.refresh)),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Status card
                  Card(
                    color: _isSpoofing ? Colors.green.withOpacity(0.1) : Colors.orange.withOpacity(0.1),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Icon(
                                _isSpoofing ? Icons.location_on : Icons.location_off,
                                color: _isSpoofing ? Colors.green : Colors.orange,
                              ),
                              const SizedBox(width: 8),
                              Text(
                                _isSpoofing ? '✅ GPS فیک فعال' : '📍 GPS واقعی',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          if (_currentMock != null) ...[
                            Text('📍 ${ _currentMock!['lat']}, ${_currentMock!['lng']}'),
                            Text('🌍 کشور: ${_currentMock!['country']} - Preset: ${_currentMock!['preset']}'),
                          ] else
                            const Text('GPS واقعی گوشی - کشور ایران'),
                          const SizedBox(height: 8),
                          Text(
                            'Mock Location: ${_isMockEnabled ? 'فعال' : 'غیرفعال'} | Can Mock: ${_canMock ? 'بله' : 'خیر'}',
                            style: const TextStyle(fontSize: 12, color: Colors.grey),
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 16),

                  // Meta mode - big button
                  Card(
                    color: Colors.purple.withOpacity(0.1),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Row(
                            children: [
                              Icon(Icons.rocket_launch, color: Colors.purple),
                              SizedBox(width: 8),
                              Text('🥷 حالت متا - برای توکن', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                            ],
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'یک کلیک برای ساخت توکن متا:\n'
                            '• GPS: منلو پارک (دفتر متا)\n'
                            '• IP: باید VPN آمریکا باشه\n'
                            '• Timezone: America/Los_Angeles\n'
                            '• متا فکر میکنه از دفتر خودش هستی!',
                            style: TextStyle(fontSize: 12),
                          ),
                          const SizedBox(height: 12),
                          SizedBox(
                            width: double.infinity,
                            child: ElevatedButton.icon(
                              onPressed: () => _setPreset('meta_hq'),
                              icon: const Icon(Icons.rocket),
                              label: const Text('🚀 فعال‌سازی حالت متا - یک کلیک'),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: Colors.purple,
                                foregroundColor: Colors.white,
                                padding: const EdgeInsets.symmetric(vertical: 14),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 16),

                  // Presets
                  const Text('🌍 مکان‌های آماده - یک کلیک:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  const SizedBox(height: 8),
                  ..._presets.map((p) => Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: ListTile(
                          leading: Text(
                            p['country'] == 'US' ? '🇺🇸' : p['country'] == 'DE' ? '🇩🇪' : p['country'] == 'GB' ? '🇬🇧' : '🌍',
                            style: const TextStyle(fontSize: 24),
                          ),
                          title: Text(p['name_fa']),
                          subtitle: Text('${p['city']} - ${p['desc']}\n${p['lat']}, ${p['lng']}'),
                          isThreeLine: true,
                          trailing: _currentMock != null && _currentMock!['preset'] == p['key']
                              ? const Icon(Icons.check_circle, color: Colors.green)
                              : const Icon(Icons.location_on_outlined),
                          onTap: () => _setPreset(p['key']),
                        ),
                      )),

                  const SizedBox(height: 16),

                  // Controls
                  if (_isSpoofing)
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: _stopSpoofing,
                        icon: const Icon(Icons.location_off),
                        label: const Text('خاموش کردن جعل - بازگشت به واقعی'),
                        style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
                      ),
                    ),

                  const SizedBox(height: 16),

                  // Info
                  Card(
                    color: Colors.blue.withOpacity(0.1),
                    child: const Padding(
                      padding: EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('💡 راهنما:', style: TextStyle(fontWeight: FontWeight.bold)),
                          SizedBox(height: 8),
                          Text(
                            '• بدون روت: Settings → Developer Options → Mock Location App → Connectix\n'
                            '• اندروید 12+: بدون تنظیم، از طریق VPN Service خودکار (غیرقابل تشخیص)\n'
                            '• با روت: خودکار تشخیص + 100% مخفی\n'
                            '• برای متا: حتما VPN آمریکا + GPS آمریکا + Timezone آمریکا همه یکی باشه\n'
                            '• ترکیب: IP آمریکا + GPS منلو پارک = متا فکر میکنه از دفتر خودش هستی!',
                            style: TextStyle(fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
