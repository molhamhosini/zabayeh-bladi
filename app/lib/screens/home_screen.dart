import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/brand.dart';
import '../widgets/product_card.dart';
import 'account_screen.dart';
import 'cart_screen.dart';
import 'location_screen.dart';
import 'orders_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final pages = [const _Catalog(), const CartScreen(), const OrdersScreen(), const AccountScreen()];
    return Scaffold(
      body: IndexedStack(index: _tab, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        backgroundColor: Colors.white,
        indicatorColor: Brand.gold.withValues(alpha: .35),
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: [
          NavigationDestination(icon: const Icon(Icons.storefront_outlined), selectedIcon: const Icon(Icons.storefront), label: context.t('home')),
          NavigationDestination(
            icon: Badge(isLabelVisible: state.cartCount > 0, label: Text('${state.cartCount}'), child: const Icon(Icons.shopping_bag_outlined)),
            selectedIcon: const Icon(Icons.shopping_bag),
            label: context.t('cart'),
          ),
          NavigationDestination(icon: const Icon(Icons.receipt_long_outlined), selectedIcon: const Icon(Icons.receipt_long), label: context.t('myOrders')),
          NavigationDestination(icon: const Icon(Icons.person_outline), selectedIcon: const Icon(Icons.person), label: context.t('account')),
        ],
      ),
    );
  }
}

class _Catalog extends StatefulWidget {
  const _Catalog();
  @override
  State<_Catalog> createState() => _CatalogState();
}

class _CatalogState extends State<_Catalog> {
  List _categories = [];
  List _products = [];
  int? _categoryId;
  int? _areaId;
  bool _loading = true;
  String? _error;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final areaId = context.watch<AppState>().area?['id'] as int?;
    if (areaId != _areaId) {
      _areaId = areaId;
      _load();
    }
  }

  Future<void> _load() async {
    final api = context.read<AppState>().api;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        api.get('/categories'),
        api.get('/products', {'area_id': _areaId, 'category_id': _categoryId}),
      ]);
      setState(() {
        _categories = results[0] as List;
        _products = (results[1] as Map)['data'] as List;
      });
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final text = Theme.of(context).textTheme;
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: _load,
        child: CustomScrollView(slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
              child: Row(children: [
                const BrandMark(size: 44),
                const SizedBox(width: 10),
                Expanded(
                  child: InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LocationScreen(canPop: true))),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(context.t('deliverTo'), style: text.bodySmall?.copyWith(color: Brand.muted)),
                      Row(children: [
                        Flexible(
                          child: Text('${context.tr(state.area?['emirate'])} - ${context.tr(state.area)}',
                              overflow: TextOverflow.ellipsis, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                        ),
                        const Icon(Icons.keyboard_arrow_down_rounded, color: Brand.maroon),
                      ]),
                    ]),
                  ),
                ),
              ]),
            ),
          ),
          SliverToBoxAdapter(
            child: Container(
              margin: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(20),
                gradient: const LinearGradient(colors: [Brand.maroon, Brand.maroonDark]),
              ),
              child: Row(children: [
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(context.t('appName'), style: text.headlineSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 4),
                    Text(context.t('tagline'), style: text.bodyMedium?.copyWith(color: Brand.gold)),
                  ]),
                ),
                const BrandMark(size: 64),
              ]),
            ),
          ),
          SliverToBoxAdapter(
            child: SizedBox(
              height: 52,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                children: [
                  _chip(context.t('all'), null),
                  for (final c in _categories) _chip(context.tr(c), c['id'] as int),
                ],
              ),
            ),
          ),
          if (_loading)
            const SliverFillRemaining(child: Center(child: CircularProgressIndicator()))
          else if (_error != null)
            SliverFillRemaining(child: ErrorRetry(message: _error!, onRetry: _load))
          else if (_products.isEmpty)
            SliverFillRemaining(child: Center(child: Text(context.t('noProducts'), style: const TextStyle(color: Brand.muted))))
          else
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              sliver: SliverGrid(
                gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                    maxCrossAxisExtent: 260, mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: .68),
                delegate: SliverChildBuilderDelegate(
                  (context, i) => ProductCard(product: Map<String, dynamic>.from(_products[i])),
                  childCount: _products.length,
                ),
              ),
            ),
        ]),
      ),
    );
  }

  Widget _chip(String label, int? id) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4),
        child: ChoiceChip(
          label: Text(label),
          selected: _categoryId == id,
          showCheckmark: false,
          labelStyle: TextStyle(color: _categoryId == id ? Colors.white : Brand.ink, fontWeight: FontWeight.w600),
          onSelected: (_) {
            setState(() => _categoryId = id);
            _load();
          },
        ),
      );
}
