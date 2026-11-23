import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../api.dart';
import '../providers/auth_provider.dart';
import '../theme.dart';
import '../widgets/brand.dart';
import 'login_screen.dart';
import 'order_screen.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});
  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  Future<List>? _orders;
  bool? _wasLoggedIn;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final loggedIn = context.watch<AuthProvider>().loggedIn;
    if (loggedIn != _wasLoggedIn) {
      _wasLoggedIn = loggedIn;
      if (loggedIn) _reload();
    }
  }

  void _reload() => setState(() => _orders = context.read<Api>().get('/orders').then((r) => (r as Map)['data'] as List));

  @override
  Widget build(BuildContext context) {
    final loggedIn = context.watch<AuthProvider>().loggedIn;
    final text = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: Text(context.t('myOrders'))),
      body: !loggedIn
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: FilledButton(
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen())),
                  child: Text(context.t('login')),
                ),
              ),
            )
          : FutureBuilder<List>(
              future: _orders,
              builder: (context, snap) {
                if (snap.hasError) return ErrorRetry(message: '${snap.error}', onRetry: _reload);
                if (!snap.hasData) return const Center(child: CircularProgressIndicator());
                final orders = snap.data!;
                if (orders.isEmpty) return Center(child: Text(context.t('noOrders'), style: const TextStyle(color: Brand.muted)));
                return RefreshIndicator(
                  onRefresh: () async => _reload(),
                  child: ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: orders.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, i) {
                      final o = orders[i];
                      return Card(
                        child: ListTile(
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => OrderScreen(orderId: o['id']))).then((_) => _reload()),
                          title: Text(o['number'], style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                          subtitle: Text('${context.tr(o['branch'])}\n${(o['items'] as List).map((it) => '${it['quantity']}× ${context.tr(it)}').join('، ')}'),
                          isThreeLine: true,
                          trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                            StatusPill(status: o['status']),
                            const SizedBox(height: 6),
                            Price(double.parse('${o['total']}'), style: text.bodyMedium?.copyWith(fontWeight: FontWeight.w800)),
                          ]),
                        ),
                      );
                    },
                  ),
                );
              },
            ),
    );
  }
}

class StatusPill extends StatelessWidget {
  const StatusPill({super.key, required this.status});
  final String status;
  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'delivered' => Brand.success,
      'cancelled' => Brand.muted,
      'pending' => Brand.goldDark,
      _ => Brand.maroon,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(20)),
      child: Text(context.t('st_$status'), style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 12)),
    );
  }
}
