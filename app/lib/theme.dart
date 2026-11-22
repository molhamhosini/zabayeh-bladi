import 'package:flutter/material.dart';

/// Brand: ذبائح بلادي — deep maroon, desert gold, warm cream.
class Brand {
  static const maroon = Color(0xFF8C1C2B);
  static const maroonDark = Color(0xFF4A0E17);
  static const gold = Color(0xFFD4AF6A);
  static const goldDark = Color(0xFFA9864A);
  static const cream = Color(0xFFF7F1E6);
  static const ink = Color(0xFF2B1A1C);
  static const muted = Color(0xFF7A6A6C);
  static const success = Color(0xFF2E7D4F);
}

ThemeData buildTheme(Locale locale) {
  final base = ThemeData(
    useMaterial3: true,
    fontFamily: 'Tajawal',
    colorScheme: ColorScheme.fromSeed(
      seedColor: Brand.maroon,
      primary: Brand.maroon,
      secondary: Brand.gold,
      surface: Colors.white,
    ),
    scaffoldBackgroundColor: Brand.cream,
  );
  final text = base.textTheme.apply(bodyColor: Brand.ink, displayColor: Brand.ink);
  return base.copyWith(
    textTheme: text,
    appBarTheme: AppBarTheme(
      backgroundColor: Brand.cream,
      foregroundColor: Brand.ink,
      elevation: 0,
      centerTitle: true,
      titleTextStyle: text.titleLarge?.copyWith(fontWeight: FontWeight.w800),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: Brand.maroon,
        minimumSize: const Size.fromHeight(52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: text.titleMedium?.copyWith(fontWeight: FontWeight.w700),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: Brand.maroon,
        minimumSize: const Size.fromHeight(52),
        side: const BorderSide(color: Brand.maroon),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: BorderSide(color: Colors.black.withValues(alpha: .06))),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Colors.black.withValues(alpha: .12))),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Colors.black.withValues(alpha: .12))),
    ),
    chipTheme: base.chipTheme.copyWith(
      selectedColor: Brand.maroon,
      labelStyle: text.bodyMedium,
      secondaryLabelStyle: text.bodyMedium?.copyWith(color: Colors.white),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
    ),
  );
}
