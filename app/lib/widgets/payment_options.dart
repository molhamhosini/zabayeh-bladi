import 'package:flutter/material.dart';

import '../l10n.dart';
import '../theme.dart';
import 'brand.dart';

/// Small wordmark-style badges so customers recognise the instalment providers.
class PaymentBadge extends StatelessWidget {
  const PaymentBadge(this.method, {super.key});
  final String method;
  @override
  Widget build(BuildContext context) {
    switch (method) {
      case 'tabby':
        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(color: const Color(0xFF3BFFC1), borderRadius: BorderRadius.circular(8)),
          child: const Text('tabby', textDirection: TextDirection.ltr, style: TextStyle(fontWeight: FontWeight.w900, color: Colors.black, fontSize: 15)),
        );
      case 'tamara':
        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            gradient: const LinearGradient(colors: [Color(0xFFF7B5A8), Color(0xFFD6B4F5), Color(0xFFA9D2F7)]),
          ),
          child: const Text('tamara', textDirection: TextDirection.ltr, style: TextStyle(fontWeight: FontWeight.w800, color: Colors.black, fontSize: 15)),
        );
      case 'card':
        return const Icon(Icons.credit_card, color: Brand.maroon);
      default:
        return const Icon(Icons.payments_outlined, color: Brand.maroon);
    }
  }
}

/// Radio list of the payment methods the server has enabled.
class PaymentOptions extends StatelessWidget {
  const PaymentOptions({super.key, required this.methods, required this.value, required this.onChanged, required this.total});
  final List<String> methods;
  final String value;
  final ValueChanged<String> onChanged;
  final double total;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Column(children: [
        for (var i = 0; i < methods.length; i++) ...[
          if (i > 0) const Divider(height: 1, indent: 16, endIndent: 16),
          InkWell(
            onTap: () => onChanged(methods[i]),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              child: Row(children: [
                Icon(value == methods[i] ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                    color: value == methods[i] ? Brand.maroon : Colors.black38),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(context.t(methods[i]), style: const TextStyle(fontWeight: FontWeight.w700)),
                    if (methods[i] == 'tabby' || methods[i] == 'tamara')
                      Row(children: [
                        Text('${context.t('splitIn4')} ', style: const TextStyle(color: Brand.muted, fontSize: 13)),
                        Price(total / 4, style: const TextStyle(color: Brand.ink, fontWeight: FontWeight.w700, fontSize: 13)),
                        Text(' ${context.t('perInstalment')}', style: const TextStyle(color: Brand.muted, fontSize: 13)),
                      ]),
                  ]),
                ),
                PaymentBadge(methods[i]),
              ]),
            ),
          ),
        ],
      ]),
    );
  }
}
