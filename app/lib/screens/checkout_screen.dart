import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_stripe/flutter_stripe.dart' hide Card;
import 'package:provider/provider.dart';

import '../config.dart';
import '../l10n.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/brand.dart';
import 'order_screen.dart';

bool get cardPaymentsAvailable => Config.stripeKey.isNotEmpty && !kIsWeb;

/// Pays an order with Stripe's PaymentSheet (cards, Apple Pay, Google Pay). Returns true when paid.
Future<bool> payWithStripe(BuildContext context, String clientSecret) async {
  try {
    await Stripe.instance.initPaymentSheet(
      paymentSheetParameters: SetupPaymentSheetParameters(
        paymentIntentClientSecret: clientSecret,
        merchantDisplayName: 'ذبائح بلادي',
        applePay: const PaymentSheetApplePay(merchantCountryCode: 'AE'),
        googlePay: const PaymentSheetGooglePay(merchantCountryCode: 'AE', currencyCode: 'AED'),
        appearance: const PaymentSheetAppearance(colors: PaymentSheetAppearanceColors(primary: Brand.maroon)),
      ),
    );
    await Stripe.instance.presentPaymentSheet();
    return true;
  } catch (_) {
    return false;
  }
}

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});
  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  List _addresses = [];
  List _slots = [];
  Map<String, dynamic>? _branch;
  int? _addressId;
  int? _slotId;
  String _purpose = 'normal';
  String _payment = cardPaymentsAvailable ? 'card' : 'cod';
  bool _donate = false;
  final _beneficiary = TextEditingController();
  final _notes = TextEditingController();
  bool _loading = true;
  bool _placing = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final state = context.read<AppState>();
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final branchId = state.cartBranchId!;
      final results = await Future.wait([
        state.api.get('/addresses'),
        state.api.get('/branches', {'area_id': state.area!['id']}),
        state.api.get('/branches/$branchId/slots'),
      ]);
      setState(() {
        _addresses = (results[0] as List).where((a) => a['area_id'] == state.area!['id']).toList();
        _addressId ??= _addresses.isNotEmpty ? _addresses.first['id'] : null;
        _branch = (results[1] as List).cast<Map>().map(Map<String, dynamic>.from).where((b) => b['id'] == branchId).firstOrNull;
        _slots = results[2] as List;
        _slotId ??= _slots.isNotEmpty ? _slots.first['id'] : null;
        if (_branch?['accepts_cod'] == false) _payment = 'card';
      });
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _addAddress() async {
    final state = context.read<AppState>();
    final details = TextEditingController();
    final label = TextEditingController();
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => Padding(
        padding: EdgeInsets.fromLTRB(16, 0, 16, 16 + MediaQuery.of(c).viewInsets.bottom),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text('${context.tr(state.area?['emirate'])} - ${context.tr(state.area)}', style: const TextStyle(fontWeight: FontWeight.w800)),
          const SizedBox(height: 12),
          TextField(controller: label, decoration: InputDecoration(labelText: context.t('addressLabel'))),
          const SizedBox(height: 12),
          TextField(controller: details, maxLines: 3, decoration: InputDecoration(labelText: context.t('addressDetails'))),
          const SizedBox(height: 16),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(context.t('save'))),
        ]),
      ),
    );
    if (saved != true || details.text.trim().isEmpty) return;
    try {
      final a = await state.api.post('/addresses', {'area_id': state.area!['id'], 'label': label.text, 'details': details.text});
      setState(() {
        _addresses.insert(0, a);
        _addressId = a['id'];
      });
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  double get _deliveryFee => double.tryParse('${_branch?['delivery_fee'] ?? 0}') ?? 0;

  Future<void> _place() async {
    final state = context.read<AppState>();
    final t = context.t;
    if (_addressId == null) {
      showError(context, t('addAddress'));
      return;
    }
    setState(() => _placing = true);
    try {
      final res = await state.api.post('/orders', {
        'branch_id': state.cartBranchId,
        'address_id': _addressId,
        'delivery_slot_id': _slotId,
        'purpose': _purpose,
        'beneficiary_name': _beneficiary.text.trim().isEmpty ? null : _beneficiary.text.trim(),
        'donate_to_charity': _donate,
        'payment_method': _payment,
        'notes': _notes.text.trim().isEmpty ? null : _notes.text.trim(),
        'items': [
          for (final l in state.cart)
            {'product_id': l.product['id'], 'quantity': l.quantity, 'option_ids': l.options.map((o) => o['id']).toList()},
        ],
      });
      final order = res['order'];
      state.clearCart();
      if (!mounted) return;
      if (_payment == 'card' && res['stripe'] != null) {
        final paid = await payWithStripe(context, res['stripe']['client_secret']);
        if (!mounted) return;
        if (!paid) showError(context, t('paymentFailed'));
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('orderPlaced')), backgroundColor: Brand.success));
      Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => OrderScreen(orderId: order['id'])));
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _placing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final text = Theme.of(context).textTheme;
    final subtotal = state.cartSubtotal;
    final vat = (subtotal + _deliveryFee) * .05;
    Widget section(String title, Widget child) => Padding(
          padding: const EdgeInsets.only(top: 20),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text(title, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            child,
          ]),
        );

    return Scaffold(
      appBar: AppBar(title: Text(context.t('checkout'))),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? ErrorRetry(message: _error!, onRetry: _load)
              : ListView(padding: const EdgeInsets.fromLTRB(16, 0, 16, 24), children: [
                  section(
                    context.t('address'),
                    Card(
                      child: Column(children: [
                        for (final a in _addresses)
                          RadioListTile<int>(
                            value: a['id'],
                            groupValue: _addressId,
                            onChanged: (v) => setState(() => _addressId = v),
                            title: Text((a['label'] ?? '').toString().isEmpty ? context.tr(a['area']) : a['label']),
                            subtitle: Text(a['details']),
                          ),
                        ListTile(leading: const Icon(Icons.add_location_alt_outlined, color: Brand.maroon), title: Text(context.t('addAddress')), onTap: _addAddress),
                      ]),
                    ),
                  ),
                  section(
                    context.t('orderType'),
                    Wrap(spacing: 8, children: [
                      for (final p in ['normal', 'udhiya', 'aqiqah', 'sadaqah'])
                        ChoiceChip(
                          label: Text(context.t(p)),
                          selected: _purpose == p,
                          showCheckmark: false,
                          labelStyle: TextStyle(color: _purpose == p ? Colors.white : Brand.ink, fontWeight: FontWeight.w600),
                          onSelected: (_) => setState(() => _purpose = p),
                        ),
                    ]),
                  ),
                  if (_purpose != 'normal') ...[
                    if (_purpose != 'sadaqah')
                      Padding(
                        padding: const EdgeInsets.only(top: 12),
                        child: TextField(controller: _beneficiary, decoration: InputDecoration(labelText: context.t('beneficiary'))),
                      ),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      value: _donate,
                      onChanged: (v) => setState(() => _donate = v),
                      title: Text(context.t('donate')),
                    ),
                  ],
                  section(
                    context.t('slot'),
                    _slots.isEmpty
                        ? Text(context.t('noSlots'), style: const TextStyle(color: Brand.muted))
                        : SizedBox(
                            height: 76,
                            child: ListView(scrollDirection: Axis.horizontal, children: [
                              for (final s in _slots)
                                Padding(
                                  padding: const EdgeInsetsDirectional.only(end: 8),
                                  child: ChoiceChip(
                                    showCheckmark: false,
                                    selected: _slotId == s['id'],
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
                                    labelStyle: TextStyle(color: _slotId == s['id'] ? Colors.white : Brand.ink),
                                    label: Column(mainAxisSize: MainAxisSize.min, children: [
                                      Text('${s['date']}'.substring(0, 10), style: const TextStyle(fontWeight: FontWeight.w700)),
                                      Text(context.tr(s, 'label')),
                                    ]),
                                    onSelected: (_) => setState(() => _slotId = s['id']),
                                  ),
                                ),
                            ]),
                          ),
                  ),
                  section(
                    context.t('payment'),
                    Card(
                      child: Column(children: [
                        RadioListTile<String>(
                          value: 'card',
                          groupValue: _payment,
                          onChanged: cardPaymentsAvailable ? (v) => setState(() => _payment = v!) : null,
                          secondary: const Icon(Icons.credit_card),
                          title: Text(context.t('card')),
                        ),
                        if (_branch?['accepts_cod'] != false)
                          RadioListTile<String>(
                            value: 'cod',
                            groupValue: _payment,
                            onChanged: (v) => setState(() => _payment = v!),
                            secondary: const Icon(Icons.payments_outlined),
                            title: Text(context.t('cod')),
                          ),
                      ]),
                    ),
                  ),
                  section(context.t('notes'), TextField(controller: _notes, maxLines: 2)),
                  const SizedBox(height: 20),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(children: [
                        _row(context.t('subtotal'), Price(subtotal, style: text.bodyLarge)),
                        _row(context.t('delivery'), _deliveryFee == 0 ? Text(context.t('free'), style: const TextStyle(color: Brand.success)) : Price(_deliveryFee, style: text.bodyLarge)),
                        _row(context.t('vat'), Price(vat, style: text.bodyLarge)),
                        const Divider(),
                        _row(context.t('total'), Price(subtotal + _deliveryFee + vat)),
                      ]),
                    ),
                  ),
                ]),
      bottomNavigationBar: _loading || _error != null
          ? null
          : Container(
              color: Colors.white,
              padding: const EdgeInsets.all(16),
              child: SafeArea(
                top: false,
                child: FilledButton(
                  onPressed: _placing ? null : _place,
                  child: _placing ? const CircularProgressIndicator(color: Colors.white) : Text(context.t('placeOrder')),
                ),
              ),
            ),
    );
  }

  Widget _row(String label, Widget value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(children: [Text(label), const Spacer(), value]),
      );
}
