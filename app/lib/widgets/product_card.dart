import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../screens/product_screen.dart';
import '../providers/favorites_provider.dart';
import '../theme.dart';
import 'brand.dart';

/// Product photo, or a soft branded placeholder (category icon) until real photos are uploaded.
class ProductImage extends StatelessWidget {
  const ProductImage({super.key, required this.url, this.height, this.slug});
  final String? url;
  final double? height;
  final String? slug;
  @override
  Widget build(BuildContext context) {
    final placeholder = Container(
      height: height,
      decoration: const BoxDecoration(
        gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFFFBF7EF), Color(0xFFF1E6D1)]),
      ),
      alignment: Alignment.center,
      child: FractionallySizedBox(
        widthFactor: .55,
        child: AspectRatio(aspectRatio: 1, child: SectionIcon.asset(slug)),
      ),
    );
    if (url == null || url!.isEmpty) return placeholder;
    return Image.network(url!, height: height, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_, __, ___) => placeholder);
  }
}

/// Round icon for a section/category: uploaded image, else a bundled illustration.
class SectionIcon extends StatelessWidget {
  const SectionIcon({super.key, this.imageUrl, this.slug, this.size = 76, this.selected = false});
  final String? imageUrl;
  final String? slug;
  final double size;
  final bool selected;

  static const _known = {'naimi', 'harri', 'najdi', 'goat', 'veal', 'camel', 'udhiya', 'aqiqah', 'sadaqah', 'all'};

  static Widget asset(String? slug) =>
      SvgPicture.asset('assets/icons/${_known.contains(slug) ? slug : 'naimi'}.svg', fit: BoxFit.contain);

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white,
        border: Border.all(color: selected ? Brand.maroon : Colors.transparent, width: 2.5),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .08), blurRadius: 10, offset: const Offset(0, 4))],
      ),
      child: ClipOval(
        child: imageUrl != null && imageUrl!.isNotEmpty
            ? Image.network(imageUrl!, fit: BoxFit.cover, errorBuilder: (_, __, ___) => asset(slug))
            : asset(slug),
      ),
    );
  }
}

class OldPrice extends StatelessWidget {
  const OldPrice(this.amount, {super.key});
  final num amount;
  @override
  Widget build(BuildContext context) => Text(
        '${amount.toStringAsFixed(amount == amount.roundToDouble() ? 0 : 2)} ${context.t('aed')}',
        style: const TextStyle(color: Brand.muted, decoration: TextDecoration.lineThrough, fontSize: 13),
      );
}

int? discountPercent(Map<String, dynamic> p) {
  final price = double.tryParse('${p['price']}') ?? 0;
  final old = double.tryParse('${p['compare_at_price'] ?? ''}');
  if (old == null || old <= price || old == 0) return null;
  return ((old - price) / old * 100).round();
}

/// Offer-style product card: photo, favourite, discount badge, seller, name, price and quick add.
class ProductCard extends StatelessWidget {
  const ProductCard({super.key, required this.product, this.width});
  final Map<String, dynamic> product;
  final double? width;

  @override
  Widget build(BuildContext context) {
    final favorites = context.watch<FavoritesProvider>();
    final text = Theme.of(context).textTheme;
    final inStock = product['in_stock'] == true;
    final vendor = product['branch']?['vendor'];
    final discount = discountPercent(product);
    final fav = favorites.contains(product['id']);
    void open() => Navigator.push(context, MaterialPageRoute(builder: (_) => ProductScreen(productId: product['id'])));

    return SizedBox(
      width: width,
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: open,
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            AspectRatio(
              aspectRatio: 1.05,
              child: Stack(fit: StackFit.expand, children: [
                Padding(
                  padding: const EdgeInsets.all(8),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: ProductImage(url: product['image_url'], slug: product['category']?['slug']),
                  ),
                ),
                PositionedDirectional(
                  top: 12,
                  end: 12,
                  child: Material(
                    color: Colors.white,
                    shape: const CircleBorder(),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: () => favorites.toggle(product['id']),
                      child: Padding(
                        padding: const EdgeInsets.all(6),
                        child: Icon(fav ? Icons.favorite : Icons.favorite_border, size: 20, color: fav ? Brand.maroon : Brand.muted),
                      ),
                    ),
                  ),
                ),
                if (discount != null)
                  PositionedDirectional(
                    bottom: 14,
                    start: 14,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(color: Brand.maroon, borderRadius: BorderRadius.circular(8)),
                      child: Text('-$discount%', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12)),
                    ),
                  ),
                if (!inStock)
                  Container(
                    color: Colors.white.withValues(alpha: .7),
                    alignment: Alignment.center,
                    child: Text(context.t('outOfStock'), style: const TextStyle(color: Brand.maroon, fontWeight: FontWeight.w800)),
                  ),
              ]),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 2, 12, 10),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                if (vendor != null)
                  Text(context.tr(vendor), maxLines: 1, overflow: TextOverflow.ellipsis, style: text.bodySmall?.copyWith(color: Brand.goldDark, fontWeight: FontWeight.w600)),
                Text(context.tr(product), maxLines: 1, overflow: TextOverflow.ellipsis, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                if (product['weight_min_kg'] != null)
                  Text('${_n(product['weight_min_kg'])} - ${_n(product['weight_max_kg'])} ${context.t('kilo')}',
                      style: text.bodySmall?.copyWith(color: Brand.muted)),
                const SizedBox(height: 6),
                Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Price(double.parse('${product['price']}')),
                      if (discount != null) OldPrice(double.parse('${product['compare_at_price']}')),
                      Text(context.t('exclVat'), style: text.labelSmall?.copyWith(color: Brand.muted)),
                    ]),
                  ),
                  Material(
                    color: Brand.cream,
                    shape: const CircleBorder(side: BorderSide(color: Colors.black12)),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: inStock ? open : null,
                      child: const Padding(padding: EdgeInsets.all(8), child: Icon(Icons.add_shopping_cart_rounded, size: 20, color: Brand.maroon)),
                    ),
                  ),
                ]),
              ]),
            ),
          ]),
        ),
      ),
    );
  }

  static String formatKg(dynamic v) => _n(v);

  static String _n(dynamic v) {
    final d = double.tryParse('$v') ?? 0;
    return d == d.roundToDouble() ? d.toStringAsFixed(0) : d.toStringAsFixed(1);
  }
}
