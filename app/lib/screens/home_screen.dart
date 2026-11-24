import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../api.dart';
import '../providers/cart_provider.dart';
import '../providers/location_provider.dart';
import '../providers/settings_provider.dart';
import '../theme.dart';
import '../widgets/brand.dart';
import '../widgets/product_card.dart';
import 'account_screen.dart';
import 'cart_screen.dart';
import 'category_screen.dart';
import 'location_screen.dart';
import 'orders_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  /// Selected bottom tab; other screens can switch it (e.g. to the cart after adding a product).
  static final tab = ValueNotifier<int>(0);
  static const cartTab = 4;
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _ordersVisit = 0; // a fresh orders list every time the tab is opened
  int _lastTab = 0;

  @override
  Widget build(BuildContext context) {
    // Listening in build (not initState) so a tab change from another screen always lands here.
    return ValueListenableBuilder<int>(valueListenable: HomeScreen.tab, builder: (context, tab, _) => _scaffold(context, tab));
  }

  Widget _scaffold(BuildContext context, int _tab) {
    if (_tab == 2 && _lastTab != 2) _ordersVisit++;
    _lastTab = _tab;
    final cartCount = context.watch<CartProvider>().count;
    final pages = [const _Home(), const CategoryScreen(embedded: true), OrdersScreen(key: ValueKey(_ordersVisit)), const AccountScreen(), const CartScreen()];
    return Scaffold(
      body: IndexedStack(index: _tab, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        backgroundColor: Colors.white,
        indicatorColor: Brand.gold.withValues(alpha: .35),
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        onDestinationSelected: (i) => HomeScreen.tab.value = i,
        destinations: [
          NavigationDestination(icon: const Icon(Icons.home_outlined), selectedIcon: const Icon(Icons.home_rounded), label: context.t('home')),
          NavigationDestination(icon: const Icon(Icons.grid_view_outlined), selectedIcon: const Icon(Icons.grid_view_rounded), label: context.t('sections')),
          NavigationDestination(icon: const Icon(Icons.receipt_long_outlined), selectedIcon: const Icon(Icons.receipt_long), label: context.t('myOrders')),
          NavigationDestination(icon: const Icon(Icons.person_outline), selectedIcon: const Icon(Icons.person), label: context.t('account')),
          NavigationDestination(
            icon: Badge(isLabelVisible: cartCount > 0, label: Text('$cartCount'), child: const Icon(Icons.shopping_cart_outlined)),
            selectedIcon: const Icon(Icons.shopping_cart),
            label: context.t('cart'),
          ),
        ],
      ),
    );
  }
}

class _Home extends StatefulWidget {
  const _Home();
  @override
  State<_Home> createState() => _HomeState();
}

class _HomeState extends State<_Home> {
  Map<String, dynamic>? _data;
  String? _error;
  int? _areaId;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final areaId = context.watch<LocationProvider>().areaId;
    if (areaId != _areaId) {
      _areaId = areaId;
      _load();
    }
  }

  Future<void> _load() async {
    try {
      final settings = context.read<SettingsProvider>();
      final d = await context.read<Api>().get('/home', {'area_id': _areaId});
      settings.setPaymentMethods(List<String>.from(d['payment_methods'] ?? const ['cod']));
      setState(() {
        _data = Map<String, dynamic>.from(d);
        _error = null;
      });
    } catch (e) {
      setState(() => _error = e.toString());
    }
  }

  void _openCategory({int? categoryId, String? query, String purpose = 'normal'}) {
    context.read<CartProvider>().setPurpose(purpose);
    Navigator.push(context, MaterialPageRoute(builder: (_) => CategoryScreen(categoryId: categoryId, query: query)));
  }

  @override
  Widget build(BuildContext context) {
    final area = context.watch<LocationProvider>().area;
    final text = Theme.of(context).textTheme;
    if (_error != null && _data == null) return SafeArea(child: ErrorRetry(message: _error!, onRetry: _load));
    if (_data == null) return const Center(child: CircularProgressIndicator());
    final d = _data!;
    final categories = d['categories'] as List;
    final offers = d['offers'] as List;
    final popular = d['popular'] as List;

    return SafeArea(
      child: RefreshIndicator(
        onRefresh: _load,
        child: ListView(padding: const EdgeInsets.only(bottom: 24), children: [
          // Brand + search
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: Row(children: [
              const BrandMark(size: 42),
              const SizedBox(width: 8),
              Text(context.t('appName'), style: text.titleLarge?.copyWith(color: Brand.maroon, fontWeight: FontWeight.w800)),
            ]),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: TextField(
              textInputAction: TextInputAction.search,
              onSubmitted: (q) => q.trim().isEmpty ? null : _openCategory(query: q.trim()),
              decoration: InputDecoration(
                hintText: context.t('search'),
                prefixIcon: const Icon(Icons.search),
                contentPadding: const EdgeInsets.symmetric(vertical: 14),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: Colors.black.withValues(alpha: .12))),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: Colors.black.withValues(alpha: .12))),
              ),
            ),
          ),
          // Delivery area
          InkWell(
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LocationScreen(canPop: true))),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
              child: Row(children: [
                const Icon(Icons.location_on_outlined, color: Brand.maroon),
                const SizedBox(width: 6),
                Text('${context.t('deliverTo')}: ', style: text.titleSmall),
                Expanded(
                  child: Text('${context.tr(area?['emirate'])} ${context.tr(area)}',
                      overflow: TextOverflow.ellipsis, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                ),
                const Icon(Icons.keyboard_arrow_down_rounded),
              ]),
            ),
          ),
          _BannerCarousel(banners: d['banners'] as List, onTap: (catId) => catId == null ? null : _openCategory(categoryId: catId)),
          // Sections
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 22, 16, 0),
            child: Text(context.t('goToSections'), style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 2, 16, 10),
            child: Text(context.t('whatToOrder'), style: text.bodyMedium?.copyWith(color: Brand.muted)),
          ),
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 12),
            padding: const EdgeInsets.symmetric(vertical: 16),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(24)),
            child: Column(children: [
              _IconRow(items: [
                _IconItem('all', context.t('livestock'), () => _openCategory()),
                _IconItem('udhiya', context.t('udhiyaSection'), () => _openCategory(purpose: 'udhiya')),
                _IconItem('aqiqah', context.t('aqiqahSection'), () => _openCategory(purpose: 'aqiqah')),
                _IconItem('sadaqah', context.t('sadaqahSection'), () => _openCategory(purpose: 'sadaqah')),
              ]),
              const SizedBox(height: 14),
              _IconRow(items: [
                for (final c in categories)
                  _IconItem(c['slug'], context.tr(c), () => _openCategory(categoryId: c['id']), imageUrl: c['image_url']),
              ]),
            ]),
          ),
          if (offers.isNotEmpty) ...[
            _SectionHeader(title: context.t('offers'), ribbon: true, onSeeAll: () => _openCategory()),
            _ProductRow(products: offers),
          ],
          if (popular.isNotEmpty) ...[
            _SectionHeader(title: context.t('popular'), onSeeAll: () => _openCategory()),
            _ProductRow(products: popular),
          ],
          if (offers.isEmpty && popular.isEmpty)
            Padding(
              padding: const EdgeInsets.all(32),
              child: Text(context.t('noProducts'), textAlign: TextAlign.center, style: const TextStyle(color: Brand.muted)),
            ),
        ]),
      ),
    );
  }
}

class _BannerCarousel extends StatefulWidget {
  const _BannerCarousel({required this.banners, required this.onTap});
  final List banners;
  final void Function(int? categoryId) onTap;
  @override
  State<_BannerCarousel> createState() => _BannerCarouselState();
}

class _BannerCarouselState extends State<_BannerCarousel> {
  final _controller = PageController(viewportFraction: .88);
  int _page = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!_controller.hasClients || widget.banners.length < 2) return;
      _controller.animateToPage((_page + 1) % widget.banners.length, duration: const Duration(milliseconds: 450), curve: Curves.easeOut);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  static const _gradients = [
    [Brand.maroon, Brand.maroonDark],
    [Color(0xFF6B4A1E), Color(0xFF3B2810)],
    [Color(0xFF7A2E3A), Color(0xFF3A1219)],
  ];

  @override
  Widget build(BuildContext context) {
    if (widget.banners.isEmpty) return const SizedBox.shrink();
    final text = Theme.of(context).textTheme;
    return Column(children: [
      SizedBox(
        height: 170,
        child: PageView.builder(
          controller: _controller,
          itemCount: widget.banners.length,
          onPageChanged: (i) => setState(() => _page = i),
          itemBuilder: (context, i) {
            final b = Map<String, dynamic>.from(widget.banners[i]);
            final img = b['image_url'] as String?;
            return GestureDetector(
              onTap: () => widget.onTap(b['category_id']),
              child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 6),
                clipBehavior: Clip.antiAlias,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(20),
                  gradient: LinearGradient(colors: _gradients[i % _gradients.length]),
                ),
                child: img != null
                    ? Image.network(img, fit: BoxFit.cover)
                    : Stack(children: [
                        PositionedDirectional(
                          end: -20,
                          bottom: -20,
                          child: Opacity(opacity: .9, child: SectionIcon.asset(i.isEven ? 'naimi' : 'aqiqah')).sized(150),
                        ),
                        Padding(
                          padding: const EdgeInsetsDirectional.fromSTEB(20, 22, 140, 16),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                            Text(context.tr(b, 'title'), style: text.titleLarge?.copyWith(color: Colors.white, fontWeight: FontWeight.w800)),
                            const SizedBox(height: 6),
                            Text(context.tr(b, 'subtitle'), style: text.bodyMedium?.copyWith(color: Brand.gold)),
                          ]),
                        ),
                      ]),
              ),
            );
          },
        ),
      ),
      const SizedBox(height: 10),
      Row(mainAxisAlignment: MainAxisAlignment.center, children: [
        for (var i = 0; i < widget.banners.length; i++)
          AnimatedContainer(
            duration: const Duration(milliseconds: 250),
            margin: const EdgeInsets.symmetric(horizontal: 3),
            width: i == _page ? 18 : 8,
            height: 8,
            decoration: BoxDecoration(color: i == _page ? Brand.maroon : Colors.black12, borderRadius: BorderRadius.circular(4)),
          ),
      ]),
    ]);
  }
}

extension on Widget {
  Widget sized(double s) => SizedBox(width: s, height: s, child: this);
}

class _IconItem {
  _IconItem(this.slug, this.label, this.onTap, {this.imageUrl});
  final String? slug;
  final String label;
  final VoidCallback onTap;
  final String? imageUrl;
}

class _IconRow extends StatelessWidget {
  const _IconRow({required this.items});
  final List<_IconItem> items;
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 112,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(width: 12),
        itemBuilder: (context, i) {
          final it = items[i];
          return InkWell(
            onTap: it.onTap,
            borderRadius: BorderRadius.circular(16),
            child: SizedBox(
              width: 84,
              child: Column(children: [
                SectionIcon(slug: it.slug, imageUrl: it.imageUrl, size: 72),
                const SizedBox(height: 8),
                Text(it.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
              ]),
            ),
          );
        },
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, this.ribbon = false, this.onSeeAll});
  final String title;
  final bool ribbon;
  final VoidCallback? onSeeAll;
  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 24, 8, 10),
      child: Row(children: [
        if (ribbon)
          ClipPath(
            clipper: _RibbonClipper(Directionality.of(context)),
            child: Container(
              color: Brand.maroon,
              padding: const EdgeInsetsDirectional.fromSTEB(14, 4, 22, 4),
              child: Text(title, style: text.titleMedium?.copyWith(color: Colors.white, fontWeight: FontWeight.w800)),
            ),
          )
        else
          Text(title, style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
        if (ribbon) ...[
          const SizedBox(width: 8),
          Text(context.t('appName'), style: text.titleMedium?.copyWith(color: Brand.goldDark, fontWeight: FontWeight.w800)),
        ],
        const Spacer(),
        if (onSeeAll != null) TextButton(onPressed: onSeeAll, child: Text(context.t('seeAll'))),
      ]),
    );
  }
}

class _RibbonClipper extends CustomClipper<Path> {
  _RibbonClipper(this.dir);
  final TextDirection dir;
  @override
  Path getClip(Size s) {
    final notch = 10.0;
    final p = Path();
    if (dir == TextDirection.rtl) {
      p.moveTo(0, 0);
      p.lineTo(s.width, 0);
      p.lineTo(s.width, s.height);
      p.lineTo(0, s.height);
      p.lineTo(notch, s.height / 2);
    } else {
      p.moveTo(0, 0);
      p.lineTo(s.width, 0);
      p.lineTo(s.width - notch, s.height / 2);
      p.lineTo(s.width, s.height);
      p.lineTo(0, s.height);
    }
    return p..close();
  }

  @override
  bool shouldReclip(covariant CustomClipper<Path> oldClipper) => false;
}

class _ProductRow extends StatelessWidget {
  const _ProductRow({required this.products});
  final List products;
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 330,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: products.length,
        separatorBuilder: (_, _) => const SizedBox(width: 12),
        itemBuilder: (context, i) => ProductCard(product: Map<String, dynamic>.from(products[i]), width: 190),
      ),
    );
  }
}
