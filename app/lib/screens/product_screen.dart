import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
  Map<String, dynamic>? _variant;
  final Map<int, Set<int>> _selected = {}; // group id -> option ids
  final Set<int> _collapsed = {}; // collapsed section keys
  int _qty = 1;

  @override
  void initState() {
    super.initState();
    _load();
  }

  List get _variants => _product?['variants'] as List? ?? [];
  List get _groups => _product?['option_groups'] as List? ?? [];

  Future<void> _load() async {
    try {
      final p = Map<String, dynamic>.from(await context.read<AppState>().api.get('/products/${widget.productId}'));
      setState(() {
        _product = p;
        _error = null;
        final variants = (p['variants'] as List? ?? []).where((v) => v['in_stock'] == true).toList();
        _variant = variants.isEmpty ? null : Map<String, dynamic>.from(variants.first);
        for (final g in p['option_groups'] as List) {
          final opts = (g['options'] as List);
          final defaults = opts.where((o) => o['default_selected'] == true).map<int>((o) => o['id']).toSet();
          if (defaults.isEmpty && g['required'] == true && g['multiple'] != true && opts.isNotEmpty) defaults.add(opts.first['id']);
          _selected[g['id']] = g['multiple'] == true ? defaults : defaults.take(1).toSet();
        }
      });
    } catch (e) {
      setState(() => _error = e.toString());
    }
  }

  List<Map<String, dynamic>> get _chosenOptions => [
        for (final g in _groups)
          for (final o in g['options'] as List)
            if (_selected[g['id']]?.contains(o['id']) ?? false)
              {...Map<String, dynamic>.from(o), 'group_ar': g['name_ar'], 'group_en': g['name_en']},
      ];

  double get _unit =>
      double.parse('${_variant?['price'] ?? _product!['price']}') +
      _chosenOptions.fold<double>(0, (s, o) => s + double.parse('${o['extra_price']}'));

  Future<void> _add() async {
    final state = context.read<AppState>();
    final t = context.t;
    if (_variants.isNotEmpty && _variant == null) {
      showError(context, t('chooseWeight'));
      return;
    }
    for (final g in _groups) {
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
    final product = Map<String, dynamic>.from(_product!)
      ..remove('option_groups')
      ..remove('variants');
    state.addToCart(CartLine(product: product, variant: _variant, options: _chosenOptions, quantity: _qty));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('added')), backgroundColor: Brand.success));
    Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final state = context.watch<AppState>();
    if (_error != null) return Scaffold(appBar: AppBar(), body: ErrorRetry(message: _error!, onRetry: _load));
    if (_product == null) return Scaffold(appBar: AppBar(), body: const Center(child: CircularProgressIndicator()));
    final p = _product!;
    final inStock = p['in_stock'] == true;
    final fav = state.favorites.contains(p['id']);
    final price = double.parse('${_variant?['price'] ?? p['price']}');
    final old = double.tryParse('${_variant?['compare_at_price'] ?? ''}');

    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr(p)),
        centerTitle: false,
        actions: [
          _roundAction(Icons.ios_share_rounded, () {
            Clipboard.setData(ClipboardData(text: '${context.tr(p)} - ${context.t('appName')}'));
          }),
          _roundAction(fav ? Icons.favorite : Icons.favorite_border, () => state.toggleFavorite(p['id']), color: fav ? Brand.maroon : null),
          const SizedBox(width: 8),
        ],
      ),
      body: ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 120), children: [
        AspectRatio(
          aspectRatio: 1,
          child: Card(
            clipBehavior: Clip.antiAlias,
            child: ProductImage(url: p['image_url'], slug: p['category']?['slug']),
          ),
        ),
        const SizedBox(height: 16),
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(context.tr(p), style: text.headlineSmall?.copyWith(color: Brand.maroon, fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              Text('${context.t('by')} ${context.tr(p['branch']?['vendor'])}', style: text.bodyMedium?.copyWith(color: Brand.goldDark)),
            ]),
          ),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Price(price, style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800, color: Brand.ink)),
            if (old != null && old > price) OldPrice(old),
            Text(context.t('exclVat'), style: text.bodySmall?.copyWith(color: Brand.muted)),
            Text(context.tr(p['category']), style: text.bodySmall?.copyWith(color: Brand.muted)),
          ]),
        ]),
        if (context.tr(p, 'description').isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 10),
            child: Text(context.tr(p, 'description'), style: text.bodyMedium?.copyWith(color: Brand.muted, height: 1.6)),
          ),
        if (_variants.isNotEmpty)
          _section(-1, context.t('weights'), Card(
            child: Column(children: [
              for (var i = 0; i < _variants.length; i++) ...[
                if (i > 0) const Divider(height: 1, indent: 16, endIndent: 16),
                _variantTile(Map<String, dynamic>.from(_variants[i])),
              ],
            ]),
          )),
        for (final g in _groups) _section(g['id'], context.tr(g), _groupBody(Map<String, dynamic>.from(g))),
        const SizedBox(height: 16),
        Row(children: [
          Text(context.t('quantity'), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
          const Spacer(),
          QtyStepper(value: _qty, onChanged: (v) => setState(() => _qty = v)),
        ]),
      ]),
      bottomSheet: Container(
        color: Colors.white,
        padding: EdgeInsets.fromLTRB(16, 12, 16, 12 + MediaQuery.of(context).padding.bottom),
        child: FilledButton(
          onPressed: inStock ? _add : null,
          child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            const Icon(Icons.add_shopping_cart_rounded),
            const SizedBox(width: 8),
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

  Widget _roundAction(IconData icon, VoidCallback onTap, {Color? color}) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4),
        child: Material(
          color: Colors.white,
          shape: const CircleBorder(),
          child: InkWell(
            customBorder: const CircleBorder(),
            onTap: onTap,
            child: Padding(padding: const EdgeInsets.all(9), child: Icon(icon, size: 22, color: color ?? Brand.ink)),
          ),
        ),
      );

  /// Collapsible bordered header like "الأوزان المتاحة ⌃".
  Widget _section(int key, String title, Widget body) {
    final open = !_collapsed.contains(key);
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => setState(() => open ? _collapsed.add(key) : _collapsed.remove(key)),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: Brand.maroon.withValues(alpha: .45)),
            ),
            child: Row(children: [
              Expanded(child: Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17))),
              Icon(open ? Icons.keyboard_arrow_up_rounded : Icons.keyboard_arrow_down_rounded),
            ]),
          ),
        ),
        if (open) Padding(padding: const EdgeInsets.only(top: 10), child: body),
      ]),
    );
  }

  Widget _variantTile(Map<String, dynamic> v) {
    final selected = _variant?['id'] == v['id'];
    final available = v['in_stock'] == true;
    final age = context.tr(v, 'age');
    String n(dynamic x) => ProductCard.formatKg(x);
    return InkWell(
      onTap: available ? () => setState(() => _variant = v) : null,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
        child: Row(children: [
          Icon(selected ? Icons.radio_button_checked : Icons.radio_button_unchecked, color: selected ? Brand.maroon : Colors.black38),
          const SizedBox(width: 10),
          Expanded(
            flex: 5,
            child: Text('${n(v['weight_min_kg'])} - ${n(v['weight_max_kg'])} ${context.t('kilo')}',
                style: TextStyle(fontWeight: FontWeight.w700, color: available ? Brand.ink : Brand.muted)),
          ),
          Expanded(flex: 4, child: Text(age, style: const TextStyle(color: Brand.muted))),
          available
              ? Price(double.parse('${v['price']}'), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16))
              : Text(context.t('outOfStock'), style: const TextStyle(color: Brand.maroon, fontSize: 12)),
        ]),
      ),
    );
  }

  Widget _groupBody(Map<String, dynamic> g) {
    final id = g['id'] as int;
    final multiple = g['multiple'] == true;
    final sel = _selected[id] ??= {};
    final options = (g['options'] as List).map((o) => Map<String, dynamic>.from(o)).toList();
    void toggle(int oid, bool on) => setState(() {
          if (multiple) {
            on ? sel.add(oid) : sel.remove(oid);
          } else if (on) {
            _selected[id] = {oid};
          } else if (g['required'] != true) {
            sel.clear();
          }
        });

    switch (g['display']) {
      case 'checklist':
        return Card(
          child: Column(children: [
            for (var i = 0; i < options.length; i++) ...[
              if (i > 0) const Divider(height: 1, indent: 16, endIndent: 16),
              CheckboxListTile(
                value: sel.contains(options[i]['id']),
                onChanged: (v) => toggle(options[i]['id'], v ?? false),
                controlAffinity: ListTileControlAffinity.leading,
                activeColor: Brand.maroon,
                title: Text(context.tr(options[i]), style: const TextStyle(fontWeight: FontWeight.w600)),
                secondary: _extra(options[i]),
              ),
            ],
          ]),
        );
      case 'cards':
        return SizedBox(
          height: 210,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: options.length,
            separatorBuilder: (_, _) => const SizedBox(width: 10),
            itemBuilder: (context, i) {
              final o = options[i];
              final on = sel.contains(o['id']);
              return GestureDetector(
                onTap: () => toggle(o['id'], !on),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  width: 150,
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: on ? Brand.maroon : Colors.black12, width: on ? 2 : 1),
                  ),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: SizedBox(
                          width: double.infinity,
                          child: o['image_url'] != null
                              ? Image.network(o['image_url'], fit: BoxFit.cover)
                              : Container(
                                  color: Brand.cream,
                                  alignment: Alignment.center,
                                  child: Icon(_iconFor(g), size: 44, color: Brand.goldDark),
                                ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(children: [
                      Expanded(child: Text(context.tr(o), maxLines: 2, style: const TextStyle(fontWeight: FontWeight.w700, height: 1.2))),
                      if (on) const Icon(Icons.check_circle, color: Brand.maroon, size: 20),
                    ]),
                    if (_extra(o) != null) _extra(o)!,
                  ]),
                ),
              );
            },
          ),
        );
      default:
        return Wrap(spacing: 8, runSpacing: 8, children: [
          for (final o in options)
            ChoiceChip(
              showCheckmark: multiple,
              selected: sel.contains(o['id']),
              labelStyle: TextStyle(color: sel.contains(o['id']) ? Colors.white : Brand.ink, fontWeight: FontWeight.w600),
              checkmarkColor: Colors.white,
              label: Text(context.tr(o)),
              onSelected: (on) => toggle(o['id'], on),
            ),
        ]);
    }
  }

  Widget? _extra(Map<String, dynamic> o) {
    final p = double.tryParse('${o['extra_price']}') ?? 0;
    if (p <= 0) return null;
    return Text('+${p.toStringAsFixed(0)} ${context.t('aed')}', style: const TextStyle(color: Brand.maroon, fontWeight: FontWeight.w800));
  }

  IconData _iconFor(Map<String, dynamic> g) {
    final name = '${g['name_en']}'.toLowerCase();
    if (name.contains('cut')) return Icons.content_cut_rounded;
    if (name.contains('pack')) return Icons.inventory_2_outlined;
    return Icons.check_circle_outline;
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
