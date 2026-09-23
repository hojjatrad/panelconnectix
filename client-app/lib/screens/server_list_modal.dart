import 'package:flutter/material.dart';
import '../models/server_model.dart';

class ServerListModal extends StatelessWidget {
  final List<ServerModel> servers;
  final ServerModel? selectedServer;

  const ServerListModal({
    Key? key,
    required this.servers,
    required this.selectedServer,
  }) : super(key: key);

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
          mainAxisSize: MainAxisSize.min,
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
            const Text(
              'انتخاب سرور و پروتکل اتصال',
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
            ),
            const SizedBox(height: 14),
            Expanded(
              child: ListView.separated(
                itemCount: servers.length,
                separatorBuilder: (_, __) => const SizedBox(height: 8),
                itemBuilder: (context, index) {
                  final s = servers[index];
                  final isSelected = (s.id == selectedServer?.id);

                  return Container(
                    decoration: BoxDecoration(
                      color: isSelected ? const Color(0xFF1E1B4B) : const Color(0xFF0F172A),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(
                        color: isSelected ? const Color(0xFF6366F1) : const Color(0xFF1E293B),
                      ),
                    ),
                    child: ListTile(
                      onTap: () => Navigator.pop(context, s),
                      leading: Text(s.flag, style: const TextStyle(fontSize: 24)),
                      title: Text(
                        s.name,
                        style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
                      ),
                      subtitle: Text(
                        '${s.protocol.toUpperCase()} • ${s.operatorName}',
                        style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
                      ),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Text('38 ms', style: TextStyle(color: Color(0xFF10B981), fontSize: 11, fontFamily: 'monospace')),
                          const SizedBox(width: 8),
                          if (isSelected)
                            const Icon(Icons.check_circle, color: Color(0xFF6366F1), size: 18),
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
