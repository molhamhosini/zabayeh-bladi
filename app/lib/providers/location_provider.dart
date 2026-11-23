import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// The delivery area chosen on first launch; the catalog depends on it.
class LocationProvider extends ChangeNotifier {
  LocationProvider(this._prefs) {
    final a = _prefs.getString('area');
    if (a != null) area = jsonDecode(a);
  }

  final SharedPreferences _prefs;
  Map<String, dynamic>? area; // {id, name_ar, name_en, emirate: {...}}

  int? get areaId => area?['id'] as int?;
  bool get hasArea => area != null;

  /// Called with the old and new area id so other providers (the cart) can react.
  void Function(int? oldId, int newId)? onAreaChanged;

  void setArea(Map<String, dynamic> a) {
    final old = areaId;
    area = a;
    _prefs.setString('area', jsonEncode(a));
    if (old != a['id']) onAreaChanged?.call(old, a['id']);
    notifyListeners();
  }
}
