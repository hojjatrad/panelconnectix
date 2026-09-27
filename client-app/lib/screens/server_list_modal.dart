import 'dart:math' as math;
import 'package:flutter/material.dart';
import '../models/server_model.dart';
import '../services/api_service.dart';

class ServerListModal extends StatefulWidget {
  final List<ServerModel> servers;
  final ServerModel? selectedServer;
  final VoidCallback? onRefresh;
  final Future<int?> Function(String uri)? pingFunction;

  const ServerListModal({
    Key? key,
    required this.servers,
    required this.selectedServer,
    this.onRefresh,
    this.pingFunction,
  }) : super(key: key);

  @override
  State<ServerListModal> createState() => _ServerListModalState();
}

class _ServerListModalState extends State<ServerListModal> {
  late List<ServerModel> _list;
  bool _isPingingAll = false;
  int _pingProgress = 0;
  bool _sortByPing = false;
  ServerModel? _bestServer;

  @override
  void initState() {
    super.initState();
    _list = widget.servers.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
    _calculateBestServer();
    if (_list.isEmpty) {
      _loadFromCacheIfEmpty();
    }
  }

  void _loadFromCacheIfEmpty() async {
    var cached = await ApiService.getCachedServers();
    if (cached.isEmpty) {
      cached = await ApiService.getServers();
    }
    cached = cached.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
    if (mounted && cached.isNotEmpty && _list.isEmpty) {
      setState(() {
        _list = List<ServerModel>.from(cached);
        _calculateBestServer();
      });
    }
  }

  @override
  void didUpdateWidget(ServerListModal oldWidget) {
    super.didUpdateWidget(oldWidget);
    final clean = widget.servers.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
    if (clean.length != _list.length) {
      setState(() {
        _list = List<ServerModel>.from(clean);
        if (_sortByPing) {
          _applySort();
        } else {
          _calculateBestServer();
        }
      });
    }
  }

  void _calculateBestServer() {
    final valid = _list.where((s) => s.pingMs != null && s.pingMs! > 0).toList();
    if (valid.isNotEmpty) {
      valid.sort((a, b) => a.pingMs!.compareTo(b.pingMs!));
      _bestServer = valid.first;
    } else {
      _bestServer = null;
    }
  }

  void _applySort() {
    if (_sortByPing) {
      _list.sort((a, b) {
        final aVal = (a.pingMs != null && a.pingMs! > 0) ? a.pingMs! : 999999;
        final bVal = (b.pingMs != null && b.pingMs! > 0) ? b.pingMs! : 999999;
        return aVal.compareTo(bVal);
      });
    } else {
      _list = widget.servers.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
    }
    _calculateBestServer();
  }

  Future<void> _pingAll() async {
    if (_isPingingAll || _list.isEmpty) return;
    setState(() {
      _isPingingAll = true;
      _pingProgress = 0;
    });

    await ApiService.pingAllServers(
      _list,
      pingFn: widget.pingFunction,
      onProgress: (current, total) {
        if (mounted) {
          setState(() {
            _pingProgress = current;
          });
        }
      },
    );

    if (mounted) {
      setState(() {
        _isPingingAll = false;
        _calculateBestServer();
        if (_sortByPing) {
          _applySort();
        }
      });

      if (_bestServer != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'سنجش پینگ به پایان رسید. بهترین سرور: ${_bestServer!.name} (${_bestServer!.pingMs} میلی‌ثانیه)',
            ),
            backgroundColor: const Color(0xFF10B981),
            duration: const Duration(seconds: 3),
          ),
        );
      }
    }
  }

  Widget _buildPingBadge(ServerModel s) {
    if (s.pingMs == null) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
          color: const Color(0xFF1E293B),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: const Color(0xFF334155)),
        ),
        child: const Text(
          'آماده اتصال',
          style: TextStyle(color: Color(0xFF94A3B8), fontSize: 10, fontWeight: FontWeight.bold),
        ),
      );
    }

    if (s.pingMs! < 0) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
          color: const Color(0xFFEF4444).withOpacity(0.15),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: const Color(0xFFEF4444).withOpacity(0.3)),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.close_rounded, size: 10, color: Color(0xFFF87171)),
            SizedBox(width: 3),
            Text(
              'بدون پاسخ',
              style: TextStyle(color: Color(0xFFF87171), fontSize: 10, fontWeight: FontWeight.bold),
            ),
          ],
        ),
      );
    }

    final ms = s.pingMs!;
    final Color badgeColor = ms < 180
        ? const Color(0xFF10B981)
        : (ms < 350 ? const Color(0xFFF59E0B) : const Color(0xFFF97316));

    final bool isBest = (_bestServer != null && _bestServer!.id == s.id);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: badgeColor.withOpacity(0.15),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: badgeColor.withOpacity(0.4)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.bolt_rounded, size: 12, color: badgeColor),
          const SizedBox(width: 2),
          Text(
            '$ms ms',
            style: TextStyle(color: badgeColor, fontSize: 10, fontWeight: FontWeight.bold),
          ),
          if (isBest) ...[
            const SizedBox(width: 4),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
              decoration: BoxDecoration(
                color: const Color(0xFF10B981),
                borderRadius: BorderRadius.circular(4),
              ),
              child: const Text(
                'بهترین',
                style: TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold),
              ),
            ),
          ],
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Color(0xFF090D16),
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      padding: const EdgeInsets.all(20),
      child: SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.max,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFF334155),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'انتخاب سرور و پروتکل اتصال',
                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                ),
                Row(
                  children: [
                    if (widget.onRefresh != null)
                      InkWell(
                        onTap: () {
                          widget.onRefresh!();
                          Navigator.pop(context);
                        },
                        borderRadius: BorderRadius.circular(10),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFF1E293B),
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: const Color(0xFF334155)),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.refresh, size: 13, color: Color(0xFF38BDF8)),
                              SizedBox(width: 4),
                              Text('بروزرسانی', style: TextStyle(color: Color(0xFF38BDF8), fontSize: 10, fontWeight: FontWeight.bold)),
                            ],
                          ),
                        ),
                      ),
                    const SizedBox(width: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFF6366F1).withOpacity(0.15),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFF6366F1).withOpacity(0.3)),
                      ),
                      child: Text(
                        '${_list.length} سرور',
                        style: const TextStyle(color: Color(0xFFA5B4FC), fontSize: 11, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 6),
            const Text(
              'کلیه کانکشن‌های اختصاصی سرور در این بخش نمایش داده می‌شوند.',
              style: TextStyle(color: Color(0xFF64748B), fontSize: 11),
            ),
            const SizedBox(height: 12),

            // Action Bar: Ping All Servers + Sort by Ping
            if (_list.isNotEmpty) ...[
              Row(
                children: [
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: _isPingingAll ? null : _pingAll,
                      icon: _isPingingAll
                          ? const SizedBox(
                              width: 14,
                              height: 14,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Icon(Icons.speed_rounded, size: 16),
                      label: Text(
                        _isPingingAll
                            ? 'در حال سنجش پینگ ($_pingProgress/${_list.length})...'
                            : 'تست پینگ همه سرورها',
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF6366F1),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  InkWell(
                    onTap: () {
                      setState(() {
                        _sortByPing = !_sortByPing;
                        _applySort();
                      });
                    },
                    borderRadius: BorderRadius.circular(12),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
                      decoration: BoxDecoration(
                        color: _sortByPing ? const Color(0xFF10B981).withOpacity(0.2) : const Color(0xFF1E293B),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: _sortByPing ? const Color(0xFF10B981) : const Color(0xFF334155),
                        ),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            Icons.sort_rounded,
                            size: 15,
                            color: _sortByPing ? const Color(0xFF34D399) : const Color(0xFF94A3B8),
                          ),
                          const SizedBox(width: 4),
                          Text(
                            'کمترین پینگ',
                            style: TextStyle(
                              color: _sortByPing ? const Color(0xFF34D399) : const Color(0xFF94A3B8),
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),

              // Smart Connect Card (اتصال هوشمند - کمترین پینگ)
              InkWell(
                onTap: () {
                  final target = _bestServer ?? _list.first;
                  Navigator.pop(context, target);
                },
                borderRadius: BorderRadius.circular(16),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF4F46E5), Color(0xFF9333EA)],
                      begin: Alignment.centerLeft,
                      end: Alignment.centerRight,
                    ),
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF6366F1).withOpacity(0.3),
                        blurRadius: 16,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.2),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Icon(Icons.bolt_rounded, color: Colors.amberAccent, size: 22),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _bestServer != null
                                  ? '⚡️ اتصال به بهترین سرور (${_bestServer!.name} - ${_bestServer!.pingMs}ms)'
                                  : '⚡️ اتصال هوشمند (کمترین پینگ)',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 13),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              _bestServer != null
                                  ? 'سریع‌ترین سرور بر اساس تست پینگ لحظه‌ای'
                                  : 'انتخاب خودکار پایدارترین سرور سازگار با اینترنت شما',
                              style: const TextStyle(color: Color(0xFFE0E7FF), fontSize: 10),
                            ),
                          ],
                        ),
                      ),
                      const Icon(Icons.arrow_forward_ios_rounded, color: Colors.white70, size: 14),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 10),
            ],

            Expanded(
              child: _list.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: const [
                          Icon(Icons.cloud_off, color: Color(0xFF64748B), size: 48),
                          SizedBox(height: 12),
                          Text('هیچ کانکشنی یافت نشد.', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 13)),
                          SizedBox(height: 6),
                          Text(
                            'اگر ادامه یافت: دکمهٔ بروزرسانی را بزنید،\nیا یک‌بار از حساب خارج و دوباره وارد شوید.',
                            style: TextStyle(color: Color(0xFF64748B), fontSize: 11),
                            textAlign: TextAlign.center,
                          ),
                        ],
                      ),
                    )
                  : ListView.separated(
                      itemCount: _list.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (context, index) {
                        final s = _list[index];
                        final isSelected = (s.id == widget.selectedServer?.id);
                        final hasConfig = s.configUri.isNotEmpty;

                        return Container(
                          decoration: BoxDecoration(
                            color: isSelected ? const Color(0xFF1E1B4B) : const Color(0xFF0F172A),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: isSelected
                                  ? const Color(0xFF6366F1)
                                  : (hasConfig ? const Color(0xFF1E293B) : const Color(0xFF450A0A)),
                            ),
                          ),
                          child: ListTile(
                            onTap: () => Navigator.pop(context, s),
                            leading: Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                color: isSelected
                                    ? const Color(0xFF6366F1).withOpacity(0.2)
                                    : const Color(0xFF1E293B),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Center(
                                child: Text(s.flag, style: const TextStyle(fontSize: 22)),
                              ),
                            ),
                            title: Text(
                              s.name,
                              style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
                            ),
                            subtitle: Padding(
                              padding: const EdgeInsets.only(top: 4.0),
                              child: Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFF334155),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      s.protocol.toUpperCase(),
                                      style: const TextStyle(color: Color(0xFFE2E8F0), fontSize: 9, fontWeight: FontWeight.bold),
                                    ),
                                  ),
                                  const SizedBox(width: 6),
                                  Text(
                                    s.operatorName,
                                    style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
                                  ),
                                ],
                              ),
                            ),
                            trailing: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                _buildPingBadge(s),
                                const SizedBox(width: 8),
                                if (isSelected)
                                  const Icon(Icons.check_circle, color: Color(0xFF6366F1), size: 20),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
