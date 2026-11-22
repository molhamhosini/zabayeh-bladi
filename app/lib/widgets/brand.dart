import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../l10n.dart';
import '../theme.dart';

class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.size = 56});
  final double size;
  @override
  Widget build(BuildContext context) => SvgPicture.asset('assets/brand/logo-mark.svg', width: size, height: size);
}

class Price extends StatelessWidget {
  const Price(this.amount, {super.key, this.style});
  final num amount;
  final TextStyle? style;
  @override
  Widget build(BuildContext context) {
    final v = amount == amount.roundToDouble() ? amount.toStringAsFixed(0) : amount.toStringAsFixed(2);
    return Text('$v ${context.t('aed')}',
        style: style ?? Theme.of(context).textTheme.titleMedium?.copyWith(color: Brand.maroon, fontWeight: FontWeight.w800));
  }
}

class ErrorRetry extends StatelessWidget {
  const ErrorRetry({super.key, required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.wifi_off_rounded, size: 48, color: Brand.muted),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            TextButton(onPressed: onRetry, child: Text(context.t('retry'))),
          ]),
        ),
      );
}

void showError(BuildContext context, Object e) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: Brand.maroonDark));
}
