import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

class BypassAppsScreen extends StatefulWidget {
  final List<String> defaultBypassList;

  const BypassAppsScreen({
    Key? key,
    required this.defaultBypassList,
  }) : super(key: key);

  @override
  State<BypassAppsScreen> createState() => _BypassAppsScreenState();
}

class _BypassAppsScreenState extends State<BypassAppsScreen> {
  static const MethodChannel _channel = MethodChannel('com.connectix.vpn/updater');

  bool _isLoading = true;
  String _errorMessage = '';
  List<Map<String, dynamic>> _allApps = [];
  Set<String> _selectedPackages = {};
  String _searchQuery = '';
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadInstalledAppsAndSettings();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadInstalledAppsAndSettings() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    try {
      final prefs = await SharedPreferences.getInstance();
      final List<String>? savedList = prefs.getStringList('custom_bypass_apps');

      // Fetch all installed launcher apps from native Android
      final List<dynamic>? rawApps = await _channel.invokeMethod<List<dynamic>>('getAllInstalledApps');
      
      final List<Map<String, dynamic>> parsedApps = [];
      if (rawApps != null) {
        for (final item in rawApps) {
          if (item is Map) {
            parsedApps.add({
              'packageName': item['packageName']?.toString() ?? '',
              'appName': item['appName']?.toString() ?? '',
              'icon': item['icon'] as Uint8List?,
            });
          }
        }
      }

      final Set<String> initialSelected = {};
      if (savedList != null && savedList.isNotEmpty) {
        initialSelected.addAll(savedList);
      } else {
        // First run: pre-select all apps that match our default domestic & banking list
        for (final app in parsedApps) {
          final pkg = app['packageName'] as String;
          if (widget.defaultBypassList.contains(pkg)) {
            initialSelected.add(pkg);
          }
        }
      }

      if (mounted) {
        setState(() {
          _allApps = parsedApps;
          _selectedPackages = initialSelected;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'خطا در بارگذاری برنامه‌ها: $e';
          _isLoading = false;
        });
      }
    }
  }

  void _resetToRecommendedDefaults() {
    final Set<String> recommended = {};
    for (final app in _allApps) {
      final pkg = app['packageName'] as String;
      if (widget.defaultBypassList.contains(pkg)) {
        recommended.add(pkg);
      }
    }
    setState(() {
      _selectedPackages = recommended;
    });
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('لیست برنامه‌ها به حالت پیش‌فرض هوشمند (بانک‌ها و سامانه‌های ایرانی) بازنشانی شد (${recommended.length} برنامه).'),
        backgroundColor: const Color(0xFF10B981),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  void _selectAll(bool select) {
    setState(() {
      if (select) {
        _selectedPackages = _allApps.map((e) => e['packageName'] as String).toSet();
      } else {
        _selectedPackages.clear();
      }
    });
  }

  Future<void> _saveAndExit() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setStringList('custom_bypass_apps', _selectedPackages.toList());
    await prefs.setBool('bypass_apps_customized', true);

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تنظیمات با موفقیت ذخیره شد. برای اعمال، یک‌بار فیلترشکن را قطع و وصل کنید.'),
          backgroundColor: Color(0xFF10B981),
          behavior: SnackBarBehavior.floating,
        ),
      );
      Navigator.pop(context, true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final filteredApps = _allApps.where((app) {
      final name = (app['appName'] as String).toLowerCase();
      final pkg = (app['packageName'] as String).toLowerCase();
      final q = _searchQuery.toLowerCase().trim();
      if (q.isEmpty) return true;
      return name.contains(q) || pkg.contains(q);
    }).toList();

    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: const Color(0xFF0F172A),
        appBar: AppBar(
          backgroundColor: const Color(0xFF1E293B),
          elevation: 0,
          title: const Text(
            'مدیریت برنامه‌های عبور مستقیم',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white),
          ),
          actions: [
            TextButton.icon(
              onPressed: _saveAndExit,
              icon: const Icon(Icons.check_rounded, color: Color(0xFF10B981), size: 20),
              label: const Text(
                'ذخیره',
                style: TextStyle(color: Color(0xFF10B981), fontWeight: FontWeight.bold),
              ),
            ),
          ],
        ),
        body: _isLoading
            ? const Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CircularProgressIndicator(color: Color(0xFF10B981)),
                    SizedBox(height: 16),
                    Text(
                      'در حال اسکن برنامه‌های نصب‌شده روی دستگاه...',
                      style: TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
                    ),
                  ],
                ),
              )
            : _errorMessage.isNotEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24.0),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.error_outline_rounded, color: Colors.amber, size: 48),
                          const SizedBox(height: 12),
                          Text(_errorMessage, style: const TextStyle(color: Colors.white, fontSize: 13), textAlign: TextAlign.center),
                          const SizedBox(height: 16),
                          ElevatedButton(
                            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF10B981)),
                            onPressed: _loadInstalledAppsAndSettings,
                            child: const Text('تلاش مجدد'),
                          ),
                        ],
                      ),
                    ),
                  )
                : Column(
                    children: [
                      // Search and toolbar header
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        color: const Color(0xFF1E293B).withOpacity(0.5),
                        child: Column(
                          children: [
                            // Search TextField
                            TextField(
                              controller: _searchController,
                              style: const TextStyle(color: Colors.white, fontSize: 13),
                              decoration: InputDecoration(
                                hintText: 'جستجوی نام یا شناسه برنامه...',
                                hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                                prefixIcon: const Icon(Icons.search_rounded, color: Color(0xFF94A3B8), size: 20),
                                suffixIcon: _searchQuery.isNotEmpty
                                    ? IconButton(
                                        icon: const Icon(Icons.close_rounded, color: Color(0xFF94A3B8), size: 18),
                                        onPressed: () {
                                          _searchController.clear();
                                          setState(() => _searchQuery = '');
                                        },
                                      )
                                    : null,
                                filled: true,
                                fillColor: const Color(0xFF0F172A),
                                contentPadding: const EdgeInsets.symmetric(vertical: 10, horizontal: 16),
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(color: Color(0xFF334155)),
                                ),
                                enabledBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(color: Color(0xFF334155)),
                                ),
                                focusedBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(color: Color(0xFF10B981)),
                                ),
                              ),
                              onChanged: (val) {
                                setState(() {
                                  _searchQuery = val;
                                });
                              },
                            ),
                            const SizedBox(height: 10),

                            // Toolbar chips
                            Row(
                              children: [
                                Expanded(
                                  child: OutlinedButton.icon(
                                    style: OutlinedButton.styleFrom(
                                      foregroundColor: const Color(0xFF10B981),
                                      side: const BorderSide(color: Color(0xFF10B981)),
                                      padding: const EdgeInsets.symmetric(vertical: 8),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                    ),
                                    onPressed: _resetToRecommendedDefaults,
                                    icon: const Icon(Icons.verified_user_outlined, size: 16),
                                    label: const Text('پیش‌فرض هوشمند بانک‌ها', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                InkWell(
                                  onTap: () => _selectAll(_selectedPackages.length != _allApps.length),
                                  borderRadius: BorderRadius.circular(10),
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFF334155),
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    child: Text(
                                      _selectedPackages.length == _allApps.length ? 'لغو همه' : 'انتخاب همه',
                                      style: const TextStyle(color: Colors.white, fontSize: 11),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),

                            // Counter and Info
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  '${_selectedPackages.length} از ${_allApps.length} برنامه در حالت عبور مستقیم',
                                  style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFF10B981).withOpacity(0.15),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: const Text(
                                    'مستقیم بدون فیلترشکن',
                                    style: TextStyle(color: Color(0xFF10B981), fontSize: 10, fontWeight: FontWeight.bold),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),

                      // Apps list
                      Expanded(
                        child: filteredApps.isEmpty
                            ? Center(
                                child: Text(
                                  _searchQuery.isEmpty ? 'هیچ برنامه‌ای یافت نشد' : 'برنامه‌ای با این مشخصات یافت نشد',
                                  style: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
                                ),
                              )
                            : ListView.separated(
                                padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 12),
                                itemCount: filteredApps.length,
                                separatorBuilder: (ctx, i) => const Divider(color: Color(0xFF1E293B), height: 1),
                                itemBuilder: (ctx, i) {
                                  final app = filteredApps[i];
                                  final pkg = app['packageName'] as String;
                                  final name = app['appName'] as String;
                                  final Uint8List? iconBytes = app['icon'] as Uint8List?;
                                  final isSelected = _selectedPackages.contains(pkg);
                                  final isRecommended = widget.defaultBypassList.contains(pkg);

                                  return Material(
                                    color: Colors.transparent,
                                    child: InkWell(
                                      borderRadius: BorderRadius.circular(12),
                                      onTap: () {
                                        setState(() {
                                          if (isSelected) {
                                            _selectedPackages.remove(pkg);
                                          } else {
                                            _selectedPackages.add(pkg);
                                          }
                                        });
                                      },
                                      child: Padding(
                                        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 8),
                                        child: Row(
                                          children: [
                                            // App Icon
                                            Container(
                                              width: 44,
                                              height: 44,
                                              decoration: BoxDecoration(
                                                color: const Color(0xFF1E293B),
                                                borderRadius: BorderRadius.circular(10),
                                              ),
                                              clipBehavior: Clip.antiAlias,
                                              child: iconBytes != null && iconBytes.isNotEmpty
                                                  ? Image.memory(
                                                      iconBytes,
                                                      width: 44,
                                                      height: 44,
                                                      fit: BoxFit.cover,
                                                      errorBuilder: (_, __, ___) => const Icon(
                                                        Icons.android_rounded,
                                                        color: Color(0xFF94A3B8),
                                                        size: 26,
                                                      ),
                                                    )
                                                  : const Icon(
                                                      Icons.android_rounded,
                                                      color: Color(0xFF94A3B8),
                                                      size: 26,
                                                    ),
                                            ),
                                            const SizedBox(width: 12),

                                            // Name and package
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment: CrossAxisAlignment.start,
                                                children: [
                                                  Row(
                                                    children: [
                                                      Flexible(
                                                        child: Text(
                                                          name,
                                                          maxLines: 1,
                                                          overflow: TextOverflow.ellipsis,
                                                          style: const TextStyle(
                                                            color: Colors.white,
                                                            fontSize: 13,
                                                            fontWeight: FontWeight.w600,
                                                          ),
                                                        ),
                                                      ),
                                                      if (isRecommended) ...[
                                                        const SizedBox(width: 6),
                                                        Container(
                                                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                                                          decoration: BoxDecoration(
                                                            color: const Color(0xFF10B981).withOpacity(0.2),
                                                            borderRadius: BorderRadius.circular(4),
                                                            border: Border.all(color: const Color(0xFF10B981).withOpacity(0.4)),
                                                          ),
                                                          child: const Text(
                                                            'بانکی / داخلی',
                                                            style: TextStyle(color: Color(0xFF10B981), fontSize: 9, fontWeight: FontWeight.bold),
                                                          ),
                                                        ),
                                                      ],
                                                    ],
                                                  ),
                                                  const SizedBox(height: 3),
                                                  Text(
                                                    pkg,
                                                    maxLines: 1,
                                                    overflow: TextOverflow.ellipsis,
                                                    style: const TextStyle(
                                                      color: Color(0xFF64748B),
                                                      fontSize: 10,
                                                      fontFamily: 'monospace',
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ),

                                            // Checkbox / Switch
                                            Switch(
                                              value: isSelected,
                                              activeColor: const Color(0xFF10B981),
                                              activeTrackColor: const Color(0xFF10B981).withOpacity(0.3),
                                              inactiveThumbColor: const Color(0xFF64748B),
                                              inactiveTrackColor: const Color(0xFF1E293B),
                                              onChanged: (val) {
                                                setState(() {
                                                  if (val) {
                                                    _selectedPackages.add(pkg);
                                                  } else {
                                                    _selectedPackages.remove(pkg);
                                                  }
                                                });
                                              },
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  );
                                },
                              ),
                      ),

                      // Bottom Save Button bar
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: const BoxDecoration(
                          color: Color(0xFF1E293B),
                          border: Border(top: BorderSide(color: Color(0xFF334155), width: 0.5)),
                        ),
                        child: SafeArea(
                          top: false,
                          child: SizedBox(
                            width: double.infinity,
                            height: 48,
                            child: ElevatedButton(
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF10B981),
                                foregroundColor: Colors.white,
                                elevation: 0,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              onPressed: _saveAndExit,
                              child: Text(
                                'ذخیره و اعمال (${_selectedPackages.length} برنامه معاف از فیلترشکن)',
                                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
      ),
    );
  }
}
