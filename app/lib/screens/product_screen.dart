import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/brand.dart';
import '../widgets/product_card.dart';

class ProductScreen extends StatefulWidget {
  const ProductScreen({super.key, required this.productId});
  final int productId;
  @override
  State<ProductScreen> createState() => _ProductScreenState();
}

class _ProductScreenState extends State<ProductScreen> {
  Map<String, dynamic>? _product;
  String? _error;
  final Map<int, Set<int>> _selected = {}; // group id -> option ids
  int _qty = 1;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final p = Map<String, dynamic>.from(await context.read<AppState>().api.get('/products/${widget.productId}'));
      setState(() {
        _product = p;
        _error = null;
        // Preselect the first option of required single-choice groups.
        for (final g in p['option_groups'] as List) {
          final opts = g['options'] as List;
          if (g['required'] == true && g['multiple'] != true && opts.isNotEmpty) _selected[g['id']] = {opts.first['id']};
        }
      });
    } catch (e) {
      setState(() => _error = e.toString());
    }
  }

  List<Map<String, dynamic>> get _chosenOptions => [
        for (final g in _product!['option_groups'] as List)
          for (final o in g['options'] as List)
            if (_selected[g['id']]?.contains(o['id']) ?? false)
              {...Map<String, dynamic>.from(o), 'group_ar': g['name_ar'], 'group_en': g['name_en']},
      ];

  double get _unit => double.parse('${_product!['price']}') + _chosenOptions.fold<double>(0, (s, o) => s + double.parse('${o['extra_price']}'));

  Future<void> _add() async {
    final state = context.read<AppState>();
    final t = context.t;
    for (final g in _product!['option_groups'] as List) {
      if (g['required'] == true && (_selected[g['id']]?.isEmpty ?? true)) {
        showError(context, '${t('chooseRequired')} ${context.tr(g)}');
        return;
      }
    }
    if (state.cartBranchId != null && state.cartBranchId != _product!['branch_id']) {
      final ok = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: Text(t('otherBranchTitle')),
          content: Text(t('otherBranchBody')),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c, false), child: Text(t('cancel'))),
            FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(t('clearAndAdd'))),
          ],
        ),
      );
      if (ok != true) return;
      state.clearCart();
    }
    final product = Map<String, dynamic>.from(_product!)..remove('option_groups');
    state.addToCart(CartLine(product: product, options: _chosenOptions, quantity: _qty));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('added')), backgroundColor: Brand.success));
    Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    if (_error != null) return Scaffold(appBar: AppBar(), body: ErrorRetry(message: _error!, onRetry: _load));
    if (_product == null) return Scaffold(appBar: AppBar(), body: const Center(child: CircularProgressIndicator()));
    final p = _product!;
    final inStock = p['in_stock'] == true;
    return Scaffold(
      body: CustomScrollView(slivers: [
        SliverAppBar(
          expandedHeight: 280,
          pinned: true,
          flexibleSpace: FlexibleSpaceBar(background: ProductImage(url: p['image_url'])),
        ),
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(context.tr(p), style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              Row(children: [
                const Icon(Icons.storefront_outlined, size: 18, color: Brand.goldDark),
                const SizedBox(width: 4),
                Text(context.tr(p['branch']?['vendor']), style: text.bodyMedium?.copyWith(color: Brand.goldDark)),
              ]),
              const SizedBox(height: 12),
              Row(children: [
                Price(double.parse('${p['price']}'), style: text.headlineSmall?.copyWith(color: Brand.maroon, fontWeight: FontWeight.w800)),
                const Spacer(),
                if (p['weight_min_kg'] != null)
                  Chip(
                    avatar: const Icon(Icons.scale_outlined, size: 18),
                    label: Text('${p['weight_min_kg']} - ${p['weight_max_kg']} ${context.t('kg')}'),
                  ),
              ]),
              if ((context.tr(p, 'description')).isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(context.tr(p, 'description'), style: text.bodyMedium?.copyWith(color: Brand.muted, height: 1.6)),
              ],
              for (final g in p['option_groups'] as List) _group(Map<String, dynamic>.from(g)),
              const SizedBox(height: 16),
              Row(children: [
                Text(context.t('quantity'), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                const Spacer(),
                QtyStepper(value: _qty, onChanged: (v) => setState(() => _qty = v)),
              ]),
              const SizedBox(height: 100),
            ]),
          ),
        ),
      ]),
      bottomSheet: Container(
        color: Colors.white,
        padding: EdgeInsets.fromLTRB(16, 12, 16, 12 + MediaQuery.of(context).padding.bottom),
        child: FilledButton(
          onPressed: inStock ? _add : null,
          child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            Text(context.t(inStock ? 'addToCart' : 'outOfStock')),
            if (inStock) ...[
              const Text('  ·  '),
              Price(_unit * _qty, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
            ],
          ]),
        ),
      ),
    );
  }

  Widget _group(Map<String, dynamic> g) {
    final text = Theme.of(context).textTheme;
    final id = g['id'] as int;
    final multiple = g['multiple'] == true;
    final sel = _selected[id] ??= {};
    return Padding(
      padding: const EdgeInsets.only(top: 20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Text(context.tr(g), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
          if (g['required'] == true) ...[
            const SizedBox(width: 8),
            Text(context.t('required'), style: text.bodySmall?.copyWith(color: Brand.maroon)),
          ],
        ]),
        const SizedBox(height: 8),
        Wrap(spacing: 8, runSpacing: 8, children: [
          for (final o in g['options'] as List)
            ChoiceChip(
              showCheckmark: multiple,
              selected: sel.contains(o['id']),
              labelStyle: TextStyle(color: sel.contains(o['id']) ? Colors.white : Brand.ink, fontWeight: FontWeight.w600),
              checkmarkColor: Colors.white,
              label: Text(double.parse('${o['extra_price']}') > 0
                  ? '${context.tr(o)}  +${double.parse('${o['extra_price']}').toStringAsFixed(0)} ${context.t('aed')}'
                  : context.tr(o)),
              onSelected: (on) => setState(() {
                if (multiple) {
                  on ? sel.add(o['id']) : sel.remove(o['id']);
                } else if (on) {
                  _selected[id] = {o['id']};
                } else if (g['required'] != true) {
                  sel.clear();
                }
              }),
            ),
        ]),
      ]),
    );
  }
}

class QtyStepper extends StatelessWidget {
  const QtyStepper({super.key, required this.value, required this.onChanged});
  final int value;
  final ValueChanged<int> onChanged;
  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(30), border: Border.all(color: Colors.black12)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          IconButton(onPressed: value > 1 ? () => onChanged(value - 1) : null, icon: const Icon(Icons.remove)),
          Text('$value', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
          IconButton(onPressed: value < 20 ? () => onChanged(value + 1) : null, icon: const Icon(Icons.add)),
        ]),
      );
}
