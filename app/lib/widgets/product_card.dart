import 'package:flutter/material.dart';

import '../l10n.dart';
import '../screens/product_screen.dart';
import '../theme.dart';
import 'brand.dart';

class ProductImage extends StatelessWidget {
  const ProductImage({super.key, required this.url, this.height});
  final String? url;
  final double? height;
  @override
  Widget build(BuildContext context) {
    final placeholder = Container(
      height: height,
      color: Brand.gold.withValues(alpha: .18),
      alignment: Alignment.center,
      child: const Opacity(opacity: .55, child: BrandMark(size: 64)),
    );
    if (url == null || url!.isEmpty) return placeholder;
    return Image.network(url!, height: height, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_, __, ___) => placeholder);
  }
}

class ProductCard extends StatelessWidget {
  const ProductCard({super.key, required this.product});
  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final inStock = product['in_stock'] == true;
    final vendor = product['branch']?['vendor'];
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ProductScreen(productId: product['id']))),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Expanded(
            child: Stack(fit: StackFit.expand, children: [
              ProductImage(url: product['image_url']),
              if (!inStock)
                Container(
                  color: Colors.white.withValues(alpha: .7),
                  alignment: Alignment.center,
                  child: Text(context.t('outOfStock'), style: const TextStyle(color: Brand.maroon, fontWeight: FontWeight.w800)),
                ),
            ]),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(context.tr(product), maxLines: 1, overflow: TextOverflow.ellipsis, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
              if (product['weight_min_kg'] != null)
                Text('${product['weight_min_kg']} - ${product['weight_max_kg']} ${context.t('kg')}',
                    style: text.bodySmall?.copyWith(color: Brand.muted)),
              if (vendor != null && vendor['is_own'] != true)
                Text('${context.t('by')} ${context.tr(vendor)}', maxLines: 1, overflow: TextOverflow.ellipsis, style: text.bodySmall?.copyWith(color: Brand.goldDark)),
              const SizedBox(height: 6),
              Price(double.parse('${product['price']}')),
            ]),
          ),
        ]),
      ),
    );
  }
}
