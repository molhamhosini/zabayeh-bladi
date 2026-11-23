import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api.dart';

/// Signed-in customer (phone + SMS code) and the API token.
class AuthProvider extends ChangeNotifier {
  AuthProvider(this._prefs, this._api) {
    _api.token = _prefs.getString('token');
    final u = _prefs.getString('user');
    if (u != null) user = jsonDecode(u);
  }

  final SharedPreferences _prefs;
  final Api _api;
  Map<String, dynamic>? user;

  bool get loggedIn => _api.token != null;

  Future<void> requestCode(String phone) => _api.post('/auth/otp/request', {'phone': phone});

  Future<void> verifyCode(String phone, String code, {String? name}) async {
    final res = await _api.post('/auth/otp/verify', {'phone': phone, 'code': code, 'name': name});
    _api.token = res['token'];
    user = Map<String, dynamic>.from(res['user']);
    await _prefs.setString('token', res['token']);
    await _prefs.setString('user', jsonEncode(user));
    notifyListeners();
  }

  Future<void> signOut() async {
    try {
      await _api.post('/auth/logout');
    } catch (_) {}
    _api.token = null;
    user = null;
    await _prefs.remove('token');
    await _prefs.remove('user');
    notifyListeners();
  }
}
