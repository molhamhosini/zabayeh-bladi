import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_stripe/flutter_stripe.dart' hide Card;
import 'package:provider/provider.dart';

import '../config.dart';
import '../l10n.dart';
import '../api.dart';
import '../providers/cart_provider.dart';
import '../providers/location_provider.dart';
import '../providers/settings_provider.dart';
import '../theme.dart';
import '../widgets/brand.dart';
import '../widgets/payment_options.dart';
import 'bnpl_checkout_screen.dart';
import 'order_screen.dart';

bool get cardPaymentsAvailable => Config.stripeKey.isNotEmpty && (!kIsWeb || Config.previewCard);

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
    _purpose = context.read<CartProvider>().purpose;
    final methods = context.read<SettingsProvider>().paymentMethods;
    _payment = methods.firstWhere((m) => m != 'card' || cardPaymentsAvailable, orElse: () => 'cod');
    _load();
  }

  Future<void> _load() async {
    final api = context.read<Api>();
    final areaId = context.read<LocationProvider>().areaId;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final branchId = context.read<CartProvider>().branchId;
      if (branchId == null) return;
      final results = await Future.wait([
        api.get('/addresses'),
        api.get('/branches', {'area_id': areaId}),
        api.get('/branches/$branchId/slots', {'area_id': areaId}),
      ]);
      setState(() {
        _addresses = (results[0] as List).where((a) => a['area_id'] == areaId).toList();
        _addressId ??= _addresses.isNotEmpty ? _addresses.first['id'] : null;
        _branch = (results[1] as List).cast<Map>().map(Map<String, dynamic>.from).where((b) => b['id'] == branchId).firstOrNull;
        _slots = results[2] as List;
        _slotId ??= _slots.isNotEmpty ? _slots.first['id'] : null;
        if (_branch?['accepts_cod'] == false && _payment == 'cod') _payment = 'card';
      });
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _addAddress() async {
    final api = context.read<Api>();
    final area = context.read<LocationProvider>().area;
    final details = TextEditingController();
    final label = TextEditingController();
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => Padding(
        padding: EdgeInsets.fromLTRB(16, 0, 16, 16 + MediaQuery.of(c).viewInsets.bottom),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text('${context.tr(area?['emirate'])} - ${context.tr(area)}', style: const TextStyle(fontWeight: FontWeight.w800)),
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
      final a = await api.post('/addresses', {'area_id': area!['id'], 'label': label.text, 'details': details.text});
      setState(() {
        _addresses.insert(0, a);
        _addressId = a['id'];
      });
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  /// Methods the server enabled, minus card on web, minus cash if this branch refuses it.
  List<String> get _methods => context
      .watch<SettingsProvider>()
      .paymentMethods
      .where((m) => (m != 'card' || cardPaymentsAvailable) && (m != 'cod' || _branch?['accepts_cod'] != false))
      .toList();

  double get _deliveryFee => double.tryParse('${_branch?['delivery_fee'] ?? 0}') ?? 0;

  Future<void> _place() async {
    if (_placing) return; // a second tap before the button rebuilds must not send a duplicate order
    final api = context.read<Api>();
    final cart = context.read<CartProvider>();
    final t = context.t;
    if (cart.isEmpty) {
      showError(context, t('emptyCart'));
      Navigator.of(context).pop();
      return;
    }
    if (_addressId == null) {
      showError(context, t('addAddress'));
      return;
    }
    _placing = true;
    setState(() {});
    Map<String, dynamic>? order;
    try {
      final res = await api.post('/orders', {
        'branch_id': cart.branchId,
        'address_id': _addressId,
        'delivery_slot_id': _slotId,
        'purpose': _purpose,
        'beneficiary_name': _beneficiary.text.trim().isEmpty ? null : _beneficiary.text.trim(),
        'donate_to_charity': _donate,
        'payment_method': _payment,
        'notes': _notes.text.trim().isEmpty ? null : _notes.text.trim(),
        'items': cart.orderItems,
      });
      order = Map<String, dynamic>.from(res['order']);
      if (!mounted) return;
      if (_payment == 'card' && res['stripe'] != null) {
        final paid = await payWithStripe(context, res['stripe']['client_secret']);
        if (!mounted) return;
        if (!paid) showError(context, t('paymentFailed'));
      } else if (res['checkout_url'] != null) {
        await BnplCheckoutScreen.open(context, res['checkout_url'], _payment);
        if (!mounted) return;
      } else if (res['payment_error'] != null) {
        showError(context, res['payment_error']);
      } else {
        showSuccess(context, t('orderPlaced'));
      }
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      // Once the server has the order, always leave checkout for the order page and empty the cart,
      // even if a payment step failed (the order page lets the customer pay again).
      if (order != null) {
        if (mounted) Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => OrderScreen(orderId: order!['id'])));
        cart.clear();
      } else {
        _placing = false;
        if (mounted) setState(() {});
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final text = Theme.of(context).textTheme;
    final subtotal = cart.subtotal;
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
      body: cart.isEmpty && !_placing
          ? Center(child: Text(context.t('emptyCart'), style: text.titleMedium?.copyWith(color: Brand.muted)))
          : _loading
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
                    PaymentOptions(
                      methods: _methods,
                      value: _payment,
                      total: subtotal + _deliveryFee + vat,
                      onChanged: (m) => setState(() => _payment = m),
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
      bottomNavigationBar: _loading || _error != null || (cart.isEmpty && !_placing)
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
