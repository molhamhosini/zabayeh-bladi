import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:provider/provider.dart';

import 'config.dart';
import 'screens/home_screen.dart';
import 'screens/location_screen.dart';
import 'state.dart';
import 'theme.dart';
import 'widgets/brand.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  if (Config.stripeKey.isNotEmpty && !kIsWeb) {
    Stripe.publishableKey = Config.stripeKey;
    Stripe.merchantIdentifier = Config.merchantId;
    await Stripe.instance.applySettings();
  }
  final state = AppState();
  await state.load();
  runApp(ChangeNotifierProvider.value(value: state, child: const ZabayehApp()));
}

class ZabayehApp extends StatelessWidget {
  const ZabayehApp({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    return MaterialApp(
      title: 'ذبائح بلادي',
      debugShowCheckedModeBanner: false,
      locale: state.locale,
      supportedLocales: const [Locale('ar'), Locale('en')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      theme: buildTheme(state.locale),
      home: !state.ready
          ? const Scaffold(body: Center(child: BrandMark(size: 96)))
          : state.area == null
              ? const LocationScreen()
              : const HomeScreen(),
    );
  }
}
