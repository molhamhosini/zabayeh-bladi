import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// One line in the cart: a product, its chosen weight and preparation options.
class CartLine {
  CartLine({required this.product, this.variant, required this.options, required this.quantity});
  final Map<String, dynamic> product;
  final Map<String, dynamic>? variant;
  final List<Map<String, dynamic>> options;
  int quantity;

  double get unitPrice =>
      double.parse('${variant?['price'] ?? product['price']}') +
      options.fold<double>(0, (s, o) => s + double.parse('${o['extra_price'] ?? 0}'));
  double get total => unitPrice * quantity;
  String get key => '${product['id']}:${variant?['id']}:${(options.map((o) => o['id']).toList()..sort()).join(',')}';

  Map<String, dynamic> toJson() => {'product': product, 'variant': variant, 'options': options, 'quantity': quantity};
  static CartLine fromJson(Map<String, dynamic> j) => CartLine(
        product: Map<String, dynamic>.from(j['product']),
        variant: j['variant'] == null ? null : Map<String, dynamic>.from(j['variant']),
        options: List<Map<String, dynamic>>.from((j['options'] as List).map((e) => Map<String, dynamic>.from(e))),
        quantity: j['quantity'],
      );

  Map<String, dynamic> toOrderItem() => {
        'product_id': product['id'],
        'variant_id': variant?['id'],
        'quantity': quantity,
        'option_ids': options.map((o) => o['id']).toList(),
      };
}

/// Cart (one branch at a time) and the order purpose picked from a home section.
class CartProvider extends ChangeNotifier {
  CartProvider(this._prefs) {
    final c = _prefs.getString('cart');
    if (c != null) lines = (jsonDecode(c) as List).map((e) => CartLine.fromJson(Map<String, dynamic>.from(e))).toList();
  }

  final SharedPreferences _prefs;
  List<CartLine> lines = [];
  String purpose = 'normal'; // normal | udhiya | aqiqah | sadaqah

  bool get isEmpty => lines.isEmpty;
  int? get branchId => lines.isEmpty ? null : lines.first.product['branch_id'] as int;
  int get count => lines.fold(0, (s, l) => s + l.quantity);
  double get subtotal => lines.fold(0, (s, l) => s + l.total);
  List<Map<String, dynamic>> get orderItems => lines.map((l) => l.toOrderItem()).toList();

  bool conflictsWith(int productBranchId) => branchId != null && branchId != productBranchId;

  void add(CartLine line) {
    final existing = lines.where((l) => l.key == line.key).firstOrNull;
    if (existing != null) {
      existing.quantity += line.quantity;
    } else {
      lines.add(line);
    }
    _save();
  }

  void setQuantity(CartLine line, int q) {
    q <= 0 ? lines.remove(line) : line.quantity = q;
    _save();
  }

  void clear() {
    lines = [];
    _save();
  }

  void setPurpose(String p) {
    purpose = p;
    notifyListeners();
  }

  void _save() {
    _prefs.setString('cart', jsonEncode(lines.map((l) => l.toJson()).toList()));
    notifyListeners();
  }
}
