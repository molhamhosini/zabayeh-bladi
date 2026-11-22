import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/brand.dart';
import 'checkout_screen.dart';
import 'orders_screen.dart';

class OrderScreen extends StatefulWidget {
  const OrderScreen({super.key, required this.orderId});
  final int orderId;
  @override
  State<OrderScreen> createState() => _OrderScreenState();
}

class _OrderScreenState extends State<OrderScreen> {
  Map<String, dynamic>? _order;
  String? _error;

  static const _steps = ['pending', 'confirmed', 'slaughtering', 'packing', 'out_for_delivery', 'delivered'];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final o = await context.read<AppState>().api.get('/orders/${widget.orderId}');
      setState(() {
        _order = Map<String, dynamic>.from(o);
        _error = null;
      });
    } catch (e) {
      setState(() => _error = e.toString());
    }
  }

  Future<void> _cancel() async {
    try {
      await context.read<AppState>().api.post('/orders/${widget.orderId}/cancel');
      _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _pay() async {
    try {
      final res = await context.read<AppState>().api.post('/orders/${widget.orderId}/pay');
      if (!mounted) return;
      final ok = await payWithStripe(context, res['client_secret']);
      if (!mounted) return;
      if (!ok) showError(context, context.t('paymentFailed'));
      await Future.delayed(const Duration(seconds: 2)); // let the webhook land
      _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    if (_error != null) return Scaffold(appBar: AppBar(), body: ErrorRetry(message: _error!, onRetry: _load));
    if (_order == null) return Scaffold(appBar: AppBar(), body: const Center(child: CircularProgressIndicator()));
    final o = _order!;
    final status = o['status'] as String;
    final current = _steps.indexOf(status);
    final unpaidCard = o['payment_method'] == 'card' && o['payment_status'] != 'paid' && status != 'cancelled';

    return Scaffold(
      appBar: AppBar(title: Text('${context.t('order')} ${o['number']}')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: status == 'cancelled'
                  ? Row(children: [const Icon(Icons.cancel_outlined, color: Brand.muted), const SizedBox(width: 8), Text(context.t('st_cancelled'))])
                  : Column(children: [
                      for (var i = 0; i < _steps.length; i++)
                        Row(children: [
                          Icon(i <= current ? Icons.check_circle : Icons.radio_button_unchecked, color: i <= current ? Brand.maroon : Colors.black26),
                          const SizedBox(width: 12),
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            child: Text(context.t('st_${_steps[i]}'),
                                style: TextStyle(fontWeight: i == current ? FontWeight.w800 : FontWeight.w400, color: i <= current ? Brand.ink : Brand.muted)),
                          ),
                        ]),
                    ]),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(context.tr(o['branch']?['vendor']), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                for (final it in o['items'] as List) ...[
                  Row(children: [
                    Expanded(child: Text('${it['quantity']}× ${context.tr(it)}', style: const TextStyle(fontWeight: FontWeight.w700))),
                    Price(double.parse('${it['line_total']}'), style: text.bodyMedium),
                  ]),
                  for (final opt in (it['options'] as List? ?? []))
                    Text('${context.tr(opt, 'group')}: ${context.tr(opt)}', style: text.bodySmall?.copyWith(color: Brand.muted)),
                  const SizedBox(height: 8),
                ],
                const Divider(),
                _row(context.t('delivery'), Price(double.parse('${o['delivery_fee']}'), style: text.bodyMedium)),
                _row(context.t('vat'), Price(double.parse('${o['vat']}'), style: text.bodyMedium)),
                _row(context.t('total'), Price(double.parse('${o['total']}'))),
                _row(context.t('payment'),
                    Text('${context.t(o['payment_method'] == 'card' ? 'card' : 'cod')} · ${context.t(o['payment_status'] == 'paid' ? 'paid' : 'unpaid')}')),
              ]),
            ),
          ),
          const SizedBox(height: 16),
          if (unpaidCard && cardPaymentsAvailable) FilledButton(onPressed: _pay, child: Text(context.t('payNow'))),
          if (status == 'pending') TextButton(onPressed: _cancel, child: Text(context.t('cancelOrder'), style: const TextStyle(color: Brand.maroon))),
          const SizedBox(height: 8),
          Center(child: StatusPill(status: status)),
        ]),
      ),
    );
  }

  Widget _row(String label, Widget value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(children: [Text(label), const Spacer(), value]),
      );
}
