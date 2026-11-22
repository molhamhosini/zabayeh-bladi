import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';

/// One line in the cart: a product with the chosen preparation options.
class CartLine {
  CartLine({required this.product, required this.options, required this.quantity});
  final Map<String, dynamic> product;
  final List<Map<String, dynamic>> options;
  int quantity;

  double get unitPrice =>
      double.parse('${product['price']}') + options.fold<double>(0, (s, o) => s + double.parse('${o['extra_price'] ?? 0}'));
  double get total => unitPrice * quantity;
  String get key => '${product['id']}:${(options.map((o) => o['id']).toList()..sort()).join(',')}';

  Map<String, dynamic> toJson() => {'product': product, 'options': options, 'quantity': quantity};
  static CartLine fromJson(Map<String, dynamic> j) => CartLine(
        product: Map<String, dynamic>.from(j['product']),
        options: List<Map<String, dynamic>>.from((j['options'] as List).map((e) => Map<String, dynamic>.from(e))),
        quantity: j['quantity'],
      );
}

class AppState extends ChangeNotifier {
  late SharedPreferences _prefs;
  final api = Api();

  Locale locale = const Locale('ar');
  Map<String, dynamic>? area; // {id, name_ar, name_en, emirate: {...}}
  Map<String, dynamic>? user;
  List<CartLine> cart = [];
  bool ready = false;

  bool get loggedIn => api.token != null;
  int? get cartBranchId => cart.isEmpty ? null : cart.first.product['branch_id'] as int;
  int get cartCount => cart.fold(0, (s, l) => s + l.quantity);
  double get cartSubtotal => cart.fold(0, (s, l) => s + l.total);

  Future<void> load() async {
    _prefs = await SharedPreferences.getInstance();
    locale = Locale(_prefs.getString('locale') ?? 'ar');
    api.locale = locale.languageCode;
    api.token = _prefs.getString('token');
    final a = _prefs.getString('area');
    if (a != null) area = jsonDecode(a);
    final u = _prefs.getString('user');
    if (u != null) user = jsonDecode(u);
    final c = _prefs.getString('cart');
    if (c != null) cart = (jsonDecode(c) as List).map((e) => CartLine.fromJson(Map<String, dynamic>.from(e))).toList();
    ready = true;
    notifyListeners();
  }

  void toggleLocale() {
    locale = Locale(locale.languageCode == 'ar' ? 'en' : 'ar');
    api.locale = locale.languageCode;
    _prefs.setString('locale', locale.languageCode);
    notifyListeners();
  }

  /// Changing area empties the cart, since branches deliver to specific areas.
  void setArea(Map<String, dynamic> a) {
    if (area?['id'] != a['id']) clearCart();
    area = a;
    _prefs.setString('area', jsonEncode(a));
    notifyListeners();
  }

  void signIn(String token, Map<String, dynamic> u) {
    api.token = token;
    user = u;
    _prefs.setString('token', token);
    _prefs.setString('user', jsonEncode(u));
    notifyListeners();
  }

  Future<void> signOut() async {
    try {
      await api.post('/auth/logout');
    } catch (_) {}
    api.token = null;
    user = null;
    await _prefs.remove('token');
    await _prefs.remove('user');
    notifyListeners();
  }

  void addToCart(CartLine line) {
    final existing = cart.where((l) => l.key == line.key).firstOrNull;
    if (existing != null) {
      existing.quantity += line.quantity;
    } else {
      cart.add(line);
    }
    _saveCart();
  }

  void setQuantity(CartLine line, int q) {
    if (q <= 0) {
      cart.remove(line);
    } else {
      line.quantity = q;
    }
    _saveCart();
  }

  void clearCart() {
    cart = [];
    _saveCart();
  }

  void _saveCart() {
    _prefs.setString('cart', jsonEncode(cart.map((l) => l.toJson()).toList()));
    notifyListeners();
  }
}
