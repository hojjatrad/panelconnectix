import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:connectix_vpn/design/design.dart';
import 'package:connectix_vpn/viewmodels/dashboard_viewmodel.dart';
import 'package:connectix_vpn/widgets/connect_button_lite.dart';

/// v4.0.55 Widget Tests

void main() {
  group('Design System', () {
    test('AppColors pingColor', () {
      expect(AppColors.pingColor(null), AppColors.textTertiary);
      expect(AppColors.pingColor(0), AppColors.success);
      expect(AppColors.pingColor(50), AppColors.success);
      expect(AppColors.pingColor(150), AppColors.warning);
      expect(AppColors.pingColor(600), AppColors.error);
    });

    test('AppColors pingLabel', () {
      expect(AppColors.pingLabel(null), 'نامشخص');
      expect(AppColors.pingLabel(0), 'آماده');
      expect(AppColors.pingLabel(50), 'عالی');
      expect(AppColors.pingLabel(150), 'خوب');
      expect(AppColors.pingLabel(300), 'متوسط');
      expect(AppColors.pingLabel(600), 'ضعیف');
    });

    test('AppSpacing constants', () {
      expect(AppSpacing.xs, 4.0);
      expect(AppSpacing.lg, 16.0);
      expect(AppSpacing.radiusLg, 18.0);
    });
  });

  group('DashboardViewModel', () {
    test('initial state', () {
      final vm = DashboardViewModel();
      expect(vm.isConnected, false);
      expect(vm.isConnecting, false);
      expect(vm.servers, isEmpty);
      expect(vm.useLiteButton, true);
      expect(vm.splitTunnelingEnabled, true);
    });

    test('connection state transitions', () {
      final vm = DashboardViewModel();
      vm.setConnectionState(ConnectionState.connecting);
      expect(vm.isConnecting, true);
      vm.setConnectionState(ConnectionState.connected);
      expect(vm.isConnected, true);
      expect(vm.connectedSeconds, 0);
      vm.setConnectionState(ConnectionState.disconnected);
      expect(vm.isConnected, false);
    });
  });

  group('ConnectButtonLite Widget', () {
    testWidgets('renders disconnected state', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: ConnectButtonLite(
              state: LiteConnectState.disconnected,
              onTap: () {},
            ),
          ),
        ),
      );
      expect(find.byType(ConnectButtonLite), findsOneWidget);
      expect(find.text('اتصال'), findsOneWidget);
    });

    testWidgets('renders connected state', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: ConnectButtonLite(
              state: LiteConnectState.connected,
              onTap: () {},
            ),
          ),
        ),
      );
      expect(find.text('متصل'), findsOneWidget);
    });

    testWidgets('renders connecting state', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: ConnectButtonLite(
              state: LiteConnectState.connecting,
              onTap: () {},
            ),
          ),
        ),
      );
      expect(find.text('در حال اتصال'), findsOneWidget);
    });

    testWidgets('tap calls onTap', (tester) async {
      bool tapped = false;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: ConnectButtonLite(
              state: LiteConnectState.disconnected,
              onTap: () => tapped = true,
            ),
          ),
        ),
      );
      await tester.tap(find.byType(ConnectButtonLite));
      await tester.pumpAndSettle();
      expect(tapped, true);
    });
  });

  group('MVVM Provider Integration', () {
    testWidgets('Provider provides ViewModel', (tester) async {
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => DashboardViewModel()),
          ],
          child: MaterialApp(
            home: Consumer<DashboardViewModel>(
              builder: (context, vm, child) {
                return Text('Lite: ${vm.useLiteButton}');
              },
            ),
          ),
        ),
      );
      expect(find.text('Lite: true'), findsOneWidget);
    });
  });
}
