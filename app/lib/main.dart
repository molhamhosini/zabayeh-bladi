import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';
import 'config.dart';
import 'providers/auth_provider.dart';
import 'providers/cart_provider.dart';
import 'providers/favorites_provider.dart';
import 'providers/location_provider.dart';
import 'providers/settings_provider.dart';
import 'screens/home_screen.dart';
import 'screens/location_screen.dart';
import 'screens/splash_screen.dart';
import 'theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  if (Config.stripeKey.isNotEmpty && !kIsWeb) {
    Stripe.publishableKey = Config.stripeKey;
    Stripe.merchantIdentifier = Config.merchantId;
    await Stripe.instance.applySettings();
  }
  final prefs = await SharedPreferences.getInstance();
  final api = Api();

  final cart = CartProvider(prefs);
  // Branches deliver to specific areas, so a new area starts a new cart.
  final location = LocationProvider(prefs)..onAreaChanged = (_, _) => cart.clear();

  runApp(MultiProvider(
    providers: [
      Provider<Api>.value(value: api),
      ChangeNotifierProvider(create: (_) => SettingsProvider(prefs, api)),
      ChangeNotifierProvider(create: (_) => AuthProvider(prefs, api)),
      ChangeNotifierProvider.value(value: location),
      ChangeNotifierProvider.value(value: cart),
      ChangeNotifierProvider(create: (_) => FavoritesProvider(prefs)),
    ],
    child: const ZabayehApp(),
  ));
}

class ZabayehApp extends StatefulWidget {
  const ZabayehApp({super.key});

  @override
  State<ZabayehApp> createState() => _ZabayehAppState();
}

class _ZabayehAppState extends State<ZabayehApp> {
  bool _splashDone = false;

  @override
  Widget build(BuildContext context) {
    final locale = context.select<SettingsProvider, Locale>((s) => s.locale);
    final hasArea = context.select<LocationProvider, bool>((l) => l.hasArea);
    return MaterialApp(
      title: 'ذبائح بلادي',
      debugShowCheckedModeBanner: false,
      locale: locale,
      supportedLocales: const [Locale('ar'), Locale('en')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      theme: buildTheme(locale),
      home: AnimatedSwitcher(
        duration: const Duration(milliseconds: 450),
        child: !_splashDone
            ? SplashScreen(key: const ValueKey('splash'), onDone: () => setState(() => _splashDone = true))
            : hasArea
                ? const HomeScreen(key: ValueKey('home'))
                : const LocationScreen(key: ValueKey('location')),
      ),
    );
  }
}
