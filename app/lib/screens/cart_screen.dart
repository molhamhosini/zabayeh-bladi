import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/brand.dart';
import '../widgets/product_card.dart';
import 'checkout_screen.dart';
import 'login_screen.dart';
import 'product_screen.dart' show QtyStepper;

class CartScreen extends StatelessWidget {
  const CartScreen({super.key});

  Future<void> _checkout(BuildContext context) async {
    final state = context.read<AppState>();
    if (!state.loggedIn) {
      final ok = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      if (ok != true || !context.mounted) return;
    }
    Navigator.push(context, MaterialPageRoute(builder: (_) => const CheckoutScreen()));
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final text = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: Text(context.t('cart'))),
      body: state.cart.isEmpty
          ? Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.shopping_bag_outlined, size: 64, color: Brand.muted),
                const SizedBox(height: 8),
                Text(context.t('emptyCart'), style: text.titleMedium?.copyWith(color: Brand.muted)),
              ]),
            )
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: state.cart.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, i) {
                final line = state.cart[i];
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: SizedBox(width: 72, height: 72, child: ProductImage(url: line.product['image_url'])),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(context.tr(line.product), style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                          if (line.variant != null) Text(context.tr(line.variant, 'label'), style: text.bodySmall?.copyWith(color: Brand.goldDark)),
                          for (final o in line.options)
                            Text('${context.tr(o, 'group')}: ${context.tr(o)}', style: text.bodySmall?.copyWith(color: Brand.muted)),
                          const SizedBox(height: 6),
                          Price(line.total),
                        ]),
                      ),
                      QtyStepper(value: line.quantity, onChanged: (q) => state.setQuantity(line, q)),
                    ]),
                  ),
                );
              },
            ),
      bottomNavigationBar: state.cart.isEmpty
          ? null
          : Container(
              color: Colors.white,
              padding: const EdgeInsets.all(16),
              child: SafeArea(
                top: false,
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Row(children: [
                    Text(context.t('subtotal'), style: text.titleMedium),
                    const Spacer(),
                    Price(state.cartSubtotal),
                  ]),
                  const SizedBox(height: 12),
                  FilledButton(onPressed: () => _checkout(context), child: Text(context.t('checkout'))),
                ]),
              ),
            ),
    );
  }
}
