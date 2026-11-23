import 'package:flutter/widgets.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api.dart';

/// App language and the payment methods the server has switched on.
class SettingsProvider extends ChangeNotifier {
  SettingsProvider(this._prefs, this._api) {
    locale = Locale(_prefs.getString('locale') ?? 'ar');
    _api.locale = locale.languageCode;
  }

  final SharedPreferences _prefs;
  final Api _api;
  late Locale locale;
  List<String> paymentMethods = const ['cod'];

  bool get isArabic => locale.languageCode == 'ar';

  void toggleLocale() {
    locale = Locale(isArabic ? 'en' : 'ar');
    _api.locale = locale.languageCode;
    _prefs.setString('locale', locale.languageCode);
    notifyListeners();
  }

  void setPaymentMethods(List<String> methods) {
    if (methods.isEmpty) return;
    paymentMethods = methods;
    notifyListeners();
  }
}
