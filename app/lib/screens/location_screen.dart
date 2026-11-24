import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../api.dart';
import '../providers/location_provider.dart';
import '../providers/settings_provider.dart';
import '../theme.dart';
import '../widgets/brand.dart';

/// First screen: the catalog depends on where the customer is.
class LocationScreen extends StatefulWidget {
  const LocationScreen({super.key, this.canPop = false});
  final bool canPop;
  @override
  State<LocationScreen> createState() => _LocationScreenState();
}

class _LocationScreenState extends State<LocationScreen> {
  bool _locating = false;
  String? _message;

  Future<void> _useGps() async {
    final api = context.read<Api>();
    final t = context.t;
    setState(() {
      _locating = true;
      _message = null;
    });
    try {
      var perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) perm = await Geolocator.requestPermission();
      if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever || !await Geolocator.isLocationServiceEnabled()) {
        setState(() => _message = t('locationDenied'));
        return;
      }
      final pos = await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.medium));
      final res = await api.get('/location/resolve', {'lat': pos.latitude, 'lng': pos.longitude});
      if (res['served'] == true) {
        _done(Map<String, dynamic>.from(res['area']));
      } else {
        setState(() => _message = t('notServed'));
      }
    } catch (e) {
      setState(() => _message = t('locationDenied'));
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  void _done(Map<String, dynamic> area) {
    context.read<LocationProvider>().setArea(area);
    if (widget.canPop) Navigator.pop(context);
  }

  Future<void> _pickManually() async {
    final area = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => const _AreaPicker(),
    );
    if (area != null) _done(area);
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return Scaffold(
      appBar: widget.canPop ? AppBar() : null,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(children: [
            const Spacer(),
            const BrandMark(size: 120),
            const SizedBox(height: 16),
            Text(context.t('appName'), style: text.headlineMedium?.copyWith(color: Brand.maroon, fontWeight: FontWeight.w800)),
            Text(context.t('tagline'), style: text.bodyLarge?.copyWith(color: Brand.goldDark)),
            const Spacer(),
            Text(context.t('whereDeliver'), style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            Text(context.t('whereDeliverHint'), textAlign: TextAlign.center, style: text.bodyMedium?.copyWith(color: Brand.muted)),
            const SizedBox(height: 24),
            if (_message != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text(_message!, textAlign: TextAlign.center, style: const TextStyle(color: Brand.maroon)),
              ),
            FilledButton.icon(
              onPressed: _locating ? null : _useGps,
              icon: _locating
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.my_location_rounded),
              label: Text(context.t(_locating ? 'locating' : 'useMyLocation')),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(onPressed: _pickManually, icon: const Icon(Icons.map_outlined), label: Text(context.t('pickManually'))),
            TextButton(onPressed: context.read<SettingsProvider>().toggleLocale, child: Text(context.t('language'))),
            const SizedBox(height: 16),
          ]),
        ),
      ),
    );
  }
}

class _AreaPicker extends StatefulWidget {
  const _AreaPicker();
  @override
  State<_AreaPicker> createState() => _AreaPickerState();
}

class _AreaPickerState extends State<_AreaPicker> {
  late Future<List> _emirates;
  Map<String, dynamic>? _emirate;

  @override
  void initState() {
    super.initState();
    _emirates = context.read<Api>().get('/emirates').then((v) => v as List);
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: MediaQuery.of(context).size.height * .7,
      child: FutureBuilder<List>(
        future: _emirates,
        builder: (context, snap) {
          if (snap.hasError) {
            return ErrorRetry(message: context.t('error'), onRetry: () => setState(() {
                  _emirates = context.read<Api>().get('/emirates').then((v) => v as List);
                }));
          }
          if (!snap.hasData) return const Center(child: CircularProgressIndicator());
          final list = _emirate == null ? snap.data! : (_emirate!['areas'] as List);
          return Column(children: [
            ListTile(
              leading: _emirate == null ? null : IconButton(icon: const BackButtonIcon(), onPressed: () => setState(() => _emirate = null)),
              title: Text(context.t(_emirate == null ? 'chooseEmirate' : 'chooseArea'), style: const TextStyle(fontWeight: FontWeight.w800)),
              subtitle: _emirate == null ? null : Text(context.tr(_emirate)),
            ),
            Expanded(
              child: ListView.separated(
                itemCount: list.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (context, i) {
                  final item = Map<String, dynamic>.from(list[i]);
                  return ListTile(
                    title: Text(context.tr(item)),
                    trailing: const Icon(Icons.chevron_left_rounded),
                    onTap: () {
                      if (_emirate == null) {
                        setState(() => _emirate = item);
                      } else {
                        Navigator.pop(context, {...item, 'emirate': {..._emirate!}..remove('areas')});
                      }
                    },
                  );
                },
              ),
            ),
          ]);
        },
      ),
    );
  }
}
