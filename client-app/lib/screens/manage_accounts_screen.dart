import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/account_model.dart';
import '../services/account_manager.dart';
import '../services/api_service.dart';

class ManageAccountsScreen extends StatefulWidget {
  final bool isAdding;
  const ManageAccountsScreen({Key? key, this.isAdding = false}) : super(key: key);

  @override
  State<ManageAccountsScreen> createState() => _ManageAccountsScreenState();
}

class _ManageAccountsScreenState extends State<ManageAccountsScreen> {
  List<VpnAccount> _accounts = [];
  VpnAccount? _active;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
    if (widget.isAdding) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _showAddDialog());
    }
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final accs = await AccountManager.getAccounts();
    final active = await AccountManager.getActiveAccount();
    if (mounted) {
      setState(() {
        _accounts = accs;
        _active = active;
        _loading = false;
      });
    }
  }

  Color _hexToColor(String hex) {
    try {
      var h = hex.replaceAll('#', '');
      if (h.length == 6) h = 'FF$h';
      return Color(int.parse(h, radix: 16));
    } catch (_) {
      return const Color(0xFF8B5CF6);
    }
  }

  void _showAddDialog({VpnAccount? editAccount}) {
    final usernameCtrl = TextEditingController(text: editAccount?.username ?? '');
    final passwordCtrl = TextEditingController();
    final panelCtrl = TextEditingController(text: editAccount?.panelUrl ?? ApiService.baseUrl);
    final displayNameCtrl = TextEditingController(text: editAccount?.displayName ?? '');
    String selectedColor = editAccount?.colorHex ?? AccountManager.randomColor();
    String selectedAvatar = editAccount?.avatarEmoji ?? AccountManager.randomAvatar(editAccount?.username ?? '');

    // Load existing password if editing
    if (editAccount != null) {
      AccountManager.getPassword(editAccount.id).then((p) {
        if (p.isNotEmpty) passwordCtrl.text = p;
      });
    }

    final colors = ['#8B5CF6','#06B6D4','#10B981','#F59E0B','#EF4444','#EC4899','#6366F1','#14B8A6','#F97316','#0EA5E9'];
    final avatars = ['🚀','🛡️','⚡','🌐','🔒','💎','🎯','🔥','✨','🌟','🎨','🦊','🐯','🦁','🦄','👑','💼','🎮','🎧','📱'];

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF0F172A),
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModal) => Padding(
          padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(ctx).viewInsets.bottom + 20),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: const Color(0xFF334155), borderRadius: BorderRadius.circular(4)))),
                const SizedBox(height: 16),
                Text(editAccount == null ? 'افزودن حساب جدید (پنل متفاوت پشتیبانی می‌شود)' : 'ویرایش حساب', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
                const SizedBox(height: 8),
                const Text('می‌توانید حساب‌های مختلف از پنل‌های مختلف (multi-service) اضافه کنید - نامحدود', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 11, height: 1.5)),
                const SizedBox(height: 16),
                // Avatar picker
                const Text('آواتار:', style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 12, fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: avatars.map((av) => GestureDetector(
                    onTap: () => setModal(() => selectedAvatar = av),
                    child: Container(
                      width: 44, height: 44,
                      decoration: BoxDecoration(
                        color: selectedAvatar == av ? _hexToColor(selectedColor).withOpacity(0.3) : const Color(0xFF1E293B),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: selectedAvatar == av ? _hexToColor(selectedColor) : const Color(0xFF334155), width: selectedAvatar == av ? 2 : 1),
                      ),
                      child: Center(child: Text(av, style: const TextStyle(fontSize: 20))),
                    ),
                  )).toList(),
                ),
                const SizedBox(height: 12),
                const Text('رنگ:', style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 12, fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: colors.map((c) => GestureDetector(
                    onTap: () => setModal(() => selectedColor = c),
                    child: Container(
                      width: 36, height: 36,
                      decoration: BoxDecoration(
                        color: _hexToColor(c),
                        shape: BoxShape.circle,
                        border: Border.all(color: selectedColor == c ? Colors.white : Colors.transparent, width: 3),
                        boxShadow: selectedColor == c ? [BoxShadow(color: _hexToColor(c).withOpacity(0.5), blurRadius: 8)] : [],
                      ),
                      child: selectedColor == c ? const Icon(Icons.check, color: Colors.white, size: 18) : null,
                    ),
                  )).toList(),
                ),
                const SizedBox(height: 16),
                _buildField(controller: displayNameCtrl, label: 'نام نمایشی (اختیاری) - مثلا پنل اصلی', hint: 'پنل اصلی', icon: Icons.badge_rounded),
                const SizedBox(height: 12),
                _buildField(controller: usernameCtrl, label: 'نام کاربری *', hint: 'username', icon: Icons.person_rounded),
                const SizedBox(height: 12),
                _buildField(controller: passwordCtrl, label: 'رمز عبور *', hint: 'password', icon: Icons.lock_rounded, obscure: true),
                const SizedBox(height: 12),
                _buildField(controller: panelCtrl, label: 'آدرس پنل * (multi-service)', hint: 'https://vpbotn.ir', icon: Icons.link_rounded),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(color: const Color(0xFF1E1B4B).withOpacity(0.5), borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFF4338CA).withOpacity(0.3))),
                  child: const Text('💡 می‌توانید از پنل‌های مختلف (مثلا vpbotn.ir و پنل دیگر) حساب اضافه کنید. هر حساب سرورهای خودش را دارد. در حالت یکپارچه همه سرورها با برچسب نمایش داده می‌شوند.', style: TextStyle(color: Color(0xFFA5B4FC), fontSize: 10, height: 1.5)),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: () async {
                      final u = usernameCtrl.text.trim();
                      final p = passwordCtrl.text.trim();
                      final panel = panelCtrl.text.trim();
                      if (u.isEmpty || p.isEmpty || panel.isEmpty) {
                        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('نام کاربری، رمز و آدرس پنل الزامی است')));
                        return;
                      }
                      Navigator.pop(ctx);
                      // Try login to validate
                      final prevBase = ApiService.baseUrl;
                      try {
                        ApiService.baseUrl = panel;
                        final res = await ApiService.login(u, p);
                        if (res['success'] == true) {
                          await AccountManager.addOrUpdateAccount(
                            username: u,
                            password: p,
                            panelUrl: panel,
                            displayName: displayNameCtrl.text.trim(),
                            colorHex: selectedColor,
                            avatarEmoji: selectedAvatar,
                            serverCount: (res['servers'] as List?)?.length ?? 0,
                          );
                          await _load();
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(editAccount == null ? '✅ حساب $u اضافه شد' : '✅ حساب $u ویرایش شد'), backgroundColor: const Color(0xFF10B981)));
                          }
                        } else {
                          ApiService.baseUrl = prevBase;
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('❌ لاگین ناموفق: ${res['error'] ?? 'نام کاربری/رمز اشتباه'}'), backgroundColor: const Color(0xFFEF4444)));
                          }
                        }
                      } catch (e) {
                        ApiService.baseUrl = prevBase;
                        if (mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('❌ خطا: $e'), backgroundColor: const Color(0xFFEF4444)));
                        }
                      }
                    },
                    icon: Icon(editAccount == null ? Icons.person_add_rounded : Icons.save_rounded, size: 18),
                    label: Text(editAccount == null ? 'افزودن و ورود' : 'ذخیره تغییرات', style: const TextStyle(fontWeight: FontWeight.bold)),
                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF6366F1), foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildField({required TextEditingController controller, required String label, required String hint, required IconData icon, bool obscure = false}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(color: Color(0xFFCBD5E1), fontSize: 12, fontWeight: FontWeight.bold)),
        const SizedBox(height: 6),
        TextField(
          controller: controller,
          obscureText: obscure,
          style: const TextStyle(color: Colors.white, fontSize: 13),
          decoration: InputDecoration(
            filled: true,
            fillColor: const Color(0xFF1E293B),
            hintText: hint,
            hintStyle: const TextStyle(color: Color(0xFF475569), fontSize: 12),
            prefixIcon: Icon(icon, color: const Color(0xFF64748B), size: 18),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF334155))),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF6366F1))),
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: const Color(0xFF090D16),
        appBar: AppBar(
          backgroundColor: const Color(0xFF0F172A),
          title: const Text('مدیریت حساب‌ها - نامحدود', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white)),
          actions: [
            IconButton(onPressed: _showAddDialog, icon: const Icon(Icons.person_add_rounded, color: Color(0xFF10B981)), tooltip: 'افزودن حساب'),
          ],
        ),
        body: _loading
            ? const Center(child: CircularProgressIndicator(color: Color(0xFF6366F1)))
            : _accounts.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(padding: const EdgeInsets.all(20), decoration: BoxDecoration(color: const Color(0xFF1E293B), borderRadius: BorderRadius.circular(20)), child: const Icon(Icons.switch_account_rounded, size: 48, color: Color(0xFF6366F1))),
                          const SizedBox(height: 16),
                          const Text('هیچ حساب دیگری وجود ندارد', style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 8),
                          const Text('می‌توانید حساب‌های مختلف از پنل‌های مختلف اضافه کنید\nنامحدود - multi-service', textAlign: TextAlign.center, style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12, height: 1.6)),
                          const SizedBox(height: 20),
                          ElevatedButton.icon(onPressed: _showAddDialog, icon: const Icon(Icons.add_rounded), label: const Text('افزودن حساب جدید'), style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF6366F1), padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12))),
                        ],
                      ),
                    ),
                  )
                : ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFF1E1B4B), Color(0xFF0F172A)], begin: Alignment.topLeft, end: Alignment.bottomRight), borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFF4338CA).withOpacity(0.3))),
                        child: Row(children: [
                          Container(padding: const EdgeInsets.all(8), decoration: BoxDecoration(color: const Color(0xFF6366F1).withOpacity(0.2), borderRadius: BorderRadius.circular(10)), child: const Icon(Icons.info_rounded, color: Color(0xFF818CF8), size: 20)),
                          const SizedBox(width: 10),
                          const Expanded(child: Text('هر حساب می‌تواند از پنل متفاوت باشد (multi-service). یک VPN در یک زمان فعال است. رمزها با امنیت ذخیره می‌شوند.', style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 11, height: 1.5))),
                        ]),
                      ),
                      const SizedBox(height: 16),
                      ..._accounts.map((acc) {
                        final isActive = _active?.id == acc.id;
                        return Container(
                          margin: const EdgeInsets.only(bottom: 10),
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: isActive ? _hexToColor(acc.colorHex).withOpacity(0.12) : const Color(0xFF0F172A),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: isActive ? _hexToColor(acc.colorHex) : const Color(0xFF1E293B), width: isActive ? 1.5 : 1),
                          ),
                          child: Row(
                            children: [
                              Container(width: 50, height: 50, decoration: BoxDecoration(color: _hexToColor(acc.colorHex).withOpacity(0.25), borderRadius: BorderRadius.circular(14), border: Border.all(color: _hexToColor(acc.colorHex).withOpacity(0.4))), child: Center(child: Text(acc.avatarEmoji, style: const TextStyle(fontSize: 24)))),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                  Row(children: [Expanded(child: Text(acc.effectiveName, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13))), if (isActive) Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2), decoration: BoxDecoration(color: const Color(0xFF10B981), borderRadius: BorderRadius.circular(6)), child: const Text('فعال', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold)))]),
                                  const SizedBox(height: 2),
                                  Text('${acc.username} • ${acc.shortPanel}', style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11)),
                                  const SizedBox(height: 4),
                                  Row(children: [Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2), decoration: BoxDecoration(color: const Color(0xFF1E293B), borderRadius: BorderRadius.circular(6)), child: Text('${acc.serverCount} سرور', style: const TextStyle(color: Color(0xFFCBD5E1), fontSize: 9))), const SizedBox(width: 6), Flexible(child: Text(acc.panelUrl, style: const TextStyle(color: Color(0xFF475569), fontSize: 9), overflow: TextOverflow.ellipsis))]),
                                ]),
                              ),
                              Column(
                                children: [
                                  InkWell(onTap: () => _showAddDialog(editAccount: acc), borderRadius: BorderRadius.circular(8), child: Container(padding: const EdgeInsets.all(6), decoration: BoxDecoration(color: const Color(0xFF1E293B), borderRadius: BorderRadius.circular(8)), child: const Icon(Icons.edit_rounded, color: Color(0xFF94A3B8), size: 16))),
                                  const SizedBox(height: 6),
                                  if (!isActive || _accounts.length > 1)
                                    InkWell(
                                      onTap: () async {
                                        final confirm = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(backgroundColor: const Color(0xFF1E293B), title: const Text('حذف حساب؟', style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold)), content: Text('آیا از حذف حساب ${acc.effectiveName} اطمینان دارید؟', style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12)), actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('لغو', style: TextStyle(color: Color(0xFF94A3B8)))), ElevatedButton(onPressed: () => Navigator.pop(ctx, true), style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFEF4444)), child: const Text('حذف', style: TextStyle(color: Colors.white)))]));
                                        if (confirm == true) {
                                          await AccountManager.removeAccount(acc.id);
                                          await _load();
                                          if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('حساب ${acc.effectiveName} حذف شد')));
                                        }
                                      },
                                      borderRadius: BorderRadius.circular(8),
                                      child: Container(padding: const EdgeInsets.all(6), decoration: BoxDecoration(color: const Color(0xFF7F1D1D).withOpacity(0.3), borderRadius: BorderRadius.circular(8)), child: const Icon(Icons.delete_rounded, color: Color(0xFFF87171), size: 16)),
                                    ),
                                ],
                              ),
                            ],
                          ),
                        );
                      }).toList(),
                      const SizedBox(height: 20),
                      SizedBox(width: double.infinity, child: ElevatedButton.icon(onPressed: _showAddDialog, icon: const Icon(Icons.person_add_rounded), label: const Text('افزودن حساب جدید از پنل دیگر (نامحدود)', style: TextStyle(fontWeight: FontWeight.bold)), style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF10B981), foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))))),
                    ],
                  ),
        floatingActionButton: _accounts.isNotEmpty
            ? FloatingActionButton.extended(onPressed: _showAddDialog, backgroundColor: const Color(0xFF6366F1), icon: const Icon(Icons.add_rounded, color: Colors.white), label: const Text('افزودن', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)))
            : null,
      ),
    );
  }
}
