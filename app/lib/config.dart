/// Build-time settings. Pass with --dart-define, e.g.
/// flutter run --dart-define=API_URL=https://api.zabayehbladi.ae/api/v1 --dart-define=STRIPE_KEY=pk_live_...
class Config {
  static const apiUrl = String.fromEnvironment('API_URL', defaultValue: 'http://10.0.2.2:8000/api/v1');
  static const stripeKey = String.fromEnvironment('STRIPE_KEY', defaultValue: '');
  /// Web previews only: show the card option on web (the Stripe sheet itself runs on iOS/Android).
  static const previewCard = bool.fromEnvironment('PREVIEW_CARD');
  static const merchantId = 'merchant.ae.zabayehbladi';
}
