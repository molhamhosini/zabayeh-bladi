import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/brand.dart';
import 'location_screen.dart';
import 'login_screen.dart';

class AccountScreen extends StatelessWidget {
  const AccountScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final text = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: Text(context.t('account'))),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Card(
          child: ListTile(
            contentPadding: const EdgeInsets.all(16),
            leading: const BrandMark(size: 48),
            title: Text(state.user?['name'] ?? context.t('appName'), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
            subtitle: state.user != null ? Directionality(textDirection: TextDirection.ltr, child: Text(state.user!['phone'] ?? '', textAlign: context.isAr ? TextAlign.right : TextAlign.left)) : null,
          ),
        ),
        const SizedBox(height: 12),
        Card(
          child: Column(children: [
            ListTile(
              leading: const Icon(Icons.location_on_outlined, color: Brand.maroon),
              title: Text(context.t('deliverTo')),
              subtitle: Text('${context.tr(state.area?['emirate'])} - ${context.tr(state.area)}'),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LocationScreen(canPop: true))),
            ),
            ListTile(
              leading: const Icon(Icons.language, color: Brand.maroon),
              title: Text(context.t('language')),
              onTap: state.toggleLocale,
            ),
            if (state.loggedIn)
              ListTile(leading: const Icon(Icons.logout, color: Brand.maroon), title: Text(context.t('logout')), onTap: state.signOut)
            else
              ListTile(
                leading: const Icon(Icons.login, color: Brand.maroon),
                title: Text(context.t('login')),
                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen())),
              ),
          ]),
        ),
      ]),
    );
  }
}
