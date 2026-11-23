import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

class FavoritesProvider extends ChangeNotifier {
  FavoritesProvider(this._prefs) : ids = (_prefs.getStringList('favorites') ?? []).map(int.parse).toSet();

  final SharedPreferences _prefs;
  final Set<int> ids;

  bool contains(int id) => ids.contains(id);

  void toggle(int id) {
    ids.contains(id) ? ids.remove(id) : ids.add(id);
    _prefs.setStringList('favorites', ids.map((e) => '$e').toList());
    notifyListeners();
  }
}
