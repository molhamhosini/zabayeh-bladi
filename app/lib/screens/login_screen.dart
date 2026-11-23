import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../providers/auth_provider.dart';
import '../theme.dart';
import '../widgets/brand.dart';

/// Phone + SMS code. Pops with `true` when signed in.
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _phone = TextEditingController();
  final _code = TextEditingController();
  final _name = TextEditingController();
  bool _codeSent = false;
  bool _busy = false;

  Future<void> _send() async {
    setState(() => _busy = true);
    try {
      await context.read<AuthProvider>().requestCode(_phone.text);
      setState(() => _codeSent = true);
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _verify() async {
    setState(() => _busy = true);
    try {
      await context.read<AuthProvider>().verifyCode(_phone.text, _code.text, name: _name.text);
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: Text(context.t('login'))),
      body: ListView(padding: const EdgeInsets.all(24), children: [
        const Center(child: BrandMark(size: 88)),
        const SizedBox(height: 24),
        if (!_codeSent) ...[
          Text(context.t('phone'), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          Directionality(
            textDirection: TextDirection.ltr,
            child: TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              autofocus: true,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(10)],
              decoration: const InputDecoration(prefixText: '+971  ', hintText: '50 123 4567'),
            ),
          ),
          const SizedBox(height: 12),
          TextField(controller: _name, decoration: InputDecoration(labelText: context.t('yourName'))),
          const SizedBox(height: 24),
          FilledButton(onPressed: _busy ? null : _send, child: Text(context.t('sendCode'))),
        ] else ...[
          Text('${context.t('enterCode')} +971 ${_phone.text}', style: text.titleMedium),
          const SizedBox(height: 12),
          Directionality(
            textDirection: TextDirection.ltr,
            child: TextField(
              controller: _code,
              autofocus: true,
              keyboardType: TextInputType.number,
              textAlign: TextAlign.center,
              style: text.headlineMedium?.copyWith(letterSpacing: 12, fontWeight: FontWeight.w800, color: Brand.maroon),
              inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
              onChanged: (v) => v.length == 6 ? _verify() : null,
            ),
          ),
          const SizedBox(height: 24),
          FilledButton(onPressed: _busy ? null : _verify, child: Text(context.t('verify'))),
          TextButton(onPressed: _busy ? null : () => setState(() => _codeSent = false), child: Text(context.t('phone'))),
        ],
      ]),
    );
  }
}
