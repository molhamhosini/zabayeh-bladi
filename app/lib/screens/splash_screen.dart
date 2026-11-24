import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../l10n.dart';
import '../theme.dart';
import '../widgets/brand.dart';

/// Animated opening: the logo is drawn in with a gold ring, the name rises in, then a short promo of
/// product photos plays. The full promo runs on first launch; later launches show only the logo (~2 s).
/// Tap anywhere (or "Skip") to continue.
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key, required this.onDone});
  final VoidCallback onDone;

  static const _seenKey = 'splash.promoSeen';

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _Slide {
  const _Slide(this.image, this.caption);
  final String image;
  final String caption;
}

const _slides = [
  _Slide('assets/promo/naimi.jpg', 'promo1'),
  _Slide('assets/promo/goat-local.jpg', 'promo2'),
  _Slide('assets/promo/goat-boer.jpg', 'promo3'),
];

class _SplashScreenState extends State<SplashScreen> with TickerProviderStateMixin {
  late final AnimationController _c;
  late final AnimationController _ripple;
  bool _full = true;
  bool _finished = false;

  @override
  void initState() {
    super.initState();
    _ripple = AnimationController(vsync: this, duration: const Duration(milliseconds: 2400))..repeat();
    _c = AnimationController(vsync: this);
    _start();
  }

  Future<void> _start() async {
    final prefs = await SharedPreferences.getInstance();
    _full = !(prefs.getBool(SplashScreen._seenKey) ?? false);
    await prefs.setBool(SplashScreen._seenKey, true);
    if (!mounted) return;
    for (final s in _slides) {
      precacheImage(AssetImage(s.image), context);
    }
    _c.duration = Duration(milliseconds: _full ? 6400 : 2300);
    _c.addStatusListener((s) {
      if (s == AnimationStatus.completed) _finish();
    });
    setState(() {});
    _c.forward();
  }

  void _finish() {
    if (_finished) return;
    _finished = true;
    widget.onDone();
  }

  @override
  void dispose() {
    _c.dispose();
    _ripple.dispose();
    super.dispose();
  }

  /// Progress of [t] inside the window [a]..[b] (fractions of the whole run), eased.
  double _seg(double t, double a, double b, [Curve curve = Curves.easeOutCubic]) =>
      curve.transform(((t - a) / (b - a)).clamp(0.0, 1.0));

  @override
  Widget build(BuildContext context) {
    // Timeline (fractions). In short mode the intro fills the run and there is no promo.
    final introEnd = _full ? 0.30 : 0.80;
    double intro(double a, double b, [Curve c = Curves.easeOutCubic]) => _seg(_c.value, a * introEnd, b * introEnd, c);

    return Scaffold(
      backgroundColor: Brand.maroonDark,
      body: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: _finish,
        child: AnimatedBuilder(
          animation: Listenable.merge([_c, _ripple]),
          builder: (context, _) {
            final t = _c.value;
            final size = MediaQuery.sizeOf(context);

            final logoIn = intro(0.05, 0.75, Curves.elasticOut);
            final ring = intro(0.0, 0.70, Curves.easeInOutCubic);
            final nameIn = intro(0.55, 0.95);
            final lineIn = intro(0.70, 1.0);
            final tagIn = _seg(t, introEnd * 0.85, introEnd * 1.05);
            // Logo block lifts to make room for the promo.
            final lift = _full ? _seg(t, 0.34, 0.44, Curves.easeInOutCubic) : 0.0;
            final fadeOut = _seg(t, _full ? 0.93 : 0.86, 1.0, Curves.easeIn);

            return Opacity(
              opacity: 1 - fadeOut,
              child: Stack(fit: StackFit.expand, children: [
                // Background: deep maroon glow.
                const DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: RadialGradient(
                      center: Alignment(0, -0.25),
                      radius: 1.05,
                      colors: [Color(0xFFA32235), Brand.maroon, Brand.maroonDark],
                      stops: [0, .45, 1],
                    ),
                  ),
                ),
                CustomPaint(painter: _SparklePainter(_ripple.value, t)),
                // Logo, ring, name and tagline.
                Align(
                  alignment: Alignment(0, -0.12 - 0.42 * lift),
                  child: Transform.scale(
                    scale: 1 - 0.30 * lift,
                    child: Column(mainAxisSize: MainAxisSize.min, children: [
                      SizedBox(
                        width: 190,
                        height: 190,
                        child: Stack(alignment: Alignment.center, children: [
                          CustomPaint(size: const Size(190, 190), painter: _RipplePainter(_ripple.value, opacity: 1 - lift)),
                          CustomPaint(size: const Size(176, 176), painter: _RingPainter(ring)),
                          Opacity(
                            opacity: logoIn.clamp(0.0, 1.0),
                            child: Transform.scale(scale: 0.4 + 0.6 * logoIn, child: const BrandMark(size: 140)),
                          ),
                        ]),
                      ),
                      const SizedBox(height: 18),
                      Opacity(
                        opacity: nameIn,
                        child: Transform.translate(
                          offset: Offset(0, 24 * (1 - nameIn)),
                          child: const Text('ذبائح بلادي',
                              style: TextStyle(fontFamily: 'Tajawal', fontSize: 40, fontWeight: FontWeight.w800, color: Brand.cream, height: 1.1)),
                        ),
                      ),
                      const SizedBox(height: 6),
                      Container(width: 110 * lineIn, height: 2.5, decoration: BoxDecoration(color: Brand.gold, borderRadius: BorderRadius.circular(2))),
                      const SizedBox(height: 10),
                      Opacity(
                        opacity: tagIn * (1 - lift),
                        child: Text(context.t('splashTagline'),
                            style: const TextStyle(fontFamily: 'Tajawal', fontSize: 16, color: Brand.gold, letterSpacing: .2)),
                      ),
                    ]),
                  ),
                ),
                if (_full) _promo(t, size),
                // Skip.
                if (_full && t > 0.05 && t < 0.93)
                  PositionedDirectional(
                    top: MediaQuery.paddingOf(context).top + 8,
                    end: 12,
                    child: TextButton(
                      onPressed: _finish,
                      style: TextButton.styleFrom(foregroundColor: Brand.cream.withValues(alpha: .85)),
                      child: Text(context.t('skip')),
                    ),
                  ),
              ]),
            );
          },
        ),
      ),
    );
  }

  /// Three product photos, each sliding in on a cream disc with a slow zoom and a caption.
  Widget _promo(double t, Size size) {
    const start = 0.42, each = 0.17;
    final disc = math.min(size.width * 0.66, 280.0);
    final isAr = Directionality.of(context) == TextDirection.rtl;

    final children = <Widget>[];
    for (var i = 0; i < _slides.length; i++) {
      final a = start + i * each, b = a + each;
      if (t < a - 0.02 || t > b + 0.03) continue;
      final inP = _seg(t, a, a + 0.045);
      final outP = i == _slides.length - 1 ? 0.0 : _seg(t, b - 0.02, b + 0.025, Curves.easeInCubic);
      final zoom = 1.0 + 0.10 * _seg(t, a, b, Curves.linear);
      final dir = isAr ? -1.0 : 1.0; // slides enter from the reading side
      final dx = dir * (60 * (1 - inP) - 60 * outP);
      final captionIn = _seg(t, a + 0.025, a + 0.07);

      children.add(Opacity(
        opacity: (inP * (1 - outP)).clamp(0.0, 1.0),
        child: Transform.translate(
          offset: Offset(dx, 0),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: disc,
              height: disc,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white,
                border: Border.all(color: Brand.gold, width: 3),
                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .35), blurRadius: 30, offset: const Offset(0, 14))],
              ),
              child: ClipOval(
                child: Transform.scale(
                  scale: zoom,
                  child: Padding(padding: EdgeInsets.all(disc * 0.08), child: Image.asset(_slides[i].image, fit: BoxFit.contain)),
                ),
              ),
            ),
            const SizedBox(height: 22),
            Opacity(
              opacity: captionIn,
              child: Transform.translate(
                offset: Offset(0, 12 * (1 - captionIn)),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 9),
                  decoration: BoxDecoration(color: Brand.gold, borderRadius: BorderRadius.circular(30)),
                  child: Text(context.t(_slides[i].caption),
                      style: const TextStyle(fontFamily: 'Tajawal', fontSize: 17, fontWeight: FontWeight.w700, color: Brand.maroonDark)),
                ),
              ),
            ),
          ]),
        ),
      ));
    }

    // Progress dots.
    final active = ((t - start) / each).floor().clamp(0, _slides.length - 1);
    final dotsIn = _seg(t, start, start + 0.04);

    return Stack(children: [
      Align(alignment: const Alignment(0, 0.42), child: Stack(alignment: Alignment.center, children: children)),
      Align(
        alignment: const Alignment(0, 0.92),
        child: Opacity(
          opacity: dotsIn,
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            for (var i = 0; i < _slides.length; i++)
              AnimatedContainer(
                duration: const Duration(milliseconds: 300),
                margin: const EdgeInsets.symmetric(horizontal: 4),
                width: i == active ? 22 : 8,
                height: 8,
                decoration: BoxDecoration(
                  color: i == active ? Brand.gold : Brand.cream.withValues(alpha: .35),
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
          ]),
        ),
      ),
    ]);
  }
}

/// Gold ring drawn clockwise around the logo, with a bright head.
class _RingPainter extends CustomPainter {
  _RingPainter(this.progress);
  final double progress;

  @override
  void paint(Canvas canvas, Size size) {
    if (progress <= 0) return;
    final rect = Offset.zero & size;
    final sweep = 2 * math.pi * progress;
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3
      ..strokeCap = StrokeCap.round
      ..shader = SweepGradient(
        startAngle: -math.pi / 2,
        endAngle: -math.pi / 2 + math.max(sweep, 0.01),
        colors: [Brand.goldDark.withValues(alpha: .2), Brand.gold, const Color(0xFFF3DFA8)],
      ).createShader(rect);
    canvas.drawArc(rect.deflate(2), -math.pi / 2, sweep, false, paint);
  }

  @override
  bool shouldRepaint(_RingPainter old) => old.progress != progress;
}

/// Soft gold ripples expanding from the logo.
class _RipplePainter extends CustomPainter {
  _RipplePainter(this.v, {this.opacity = 1});
  final double v;
  final double opacity;

  @override
  void paint(Canvas canvas, Size size) {
    final c = size.center(Offset.zero);
    for (final phase in [0.0, 0.5]) {
      final p = (v + phase) % 1.0;
      final r = size.width / 2 * (0.95 + 0.75 * p);
      canvas.drawCircle(
        c,
        r,
        Paint()
          ..style = PaintingStyle.stroke
          ..strokeWidth = 1.2
          ..color = Brand.gold.withValues(alpha: (1 - p) * 0.35 * opacity),
      );
    }
  }

  @override
  bool shouldRepaint(_RipplePainter old) => old.v != v || old.opacity != opacity;
}

/// Tiny gold specks drifting upward across the screen.
class _SparklePainter extends CustomPainter {
  _SparklePainter(this.v, this.t);
  final double v;
  final double t;

  @override
  void paint(Canvas canvas, Size size) {
    final rnd = math.Random(7);
    final appear = (t * 6).clamp(0.0, 1.0);
    for (var i = 0; i < 28; i++) {
      final x = rnd.nextDouble() * size.width;
      final speed = 0.3 + rnd.nextDouble() * 0.7;
      final y = (rnd.nextDouble() - (v + t * 2) * speed) % 1.0 * size.height;
      final r = 0.8 + rnd.nextDouble() * 1.8;
      final twinkle = 0.5 + 0.5 * math.sin((v * 2 * math.pi) + i);
      canvas.drawCircle(Offset(x, y), r, Paint()..color = Brand.gold.withValues(alpha: 0.10 + 0.35 * twinkle * appear));
    }
  }

  @override
  bool shouldRepaint(_SparklePainter old) => true;
}
