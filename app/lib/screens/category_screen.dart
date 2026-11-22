import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/brand.dart';
import '../widgets/product_card.dart';

/// "الذبائح": round animal icons on top, product grid below. Also used for search results.
class CategoryScreen extends StatefulWidget {
  const CategoryScreen({super.key, this.categoryId, this.query, this.embedded = false});
  final int? categoryId;
  final String? query;
  final bool embedded; // shown as a bottom-nav tab (no back button)
  @override
  State<CategoryScreen> createState() => _CategoryScreenState();
}

class _CategoryScreenState extends State<CategoryScreen> {
  List _categories = [];
  List _products = [];
  int? _categoryId;
  int? _areaId;
  late final TextEditingController _search = TextEditingController(text: widget.query);
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _categoryId = widget.categoryId;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final areaId = context.watch<AppState>().area?['id'] as int?;
    if (areaId != _areaId) {
      _areaId = areaId;
      _load();
    }
  }

  Future<void> _load() async {
    final api = context.read<AppState>().api;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final r = await Future.wait([
        api.get('/categories'),
        api.get('/products', {'area_id': _areaId, 'category_id': _categoryId, 'q': _search.text.trim().isEmpty ? null : _search.text.trim()}),
      ]);
      setState(() {
        _categories = r[0] as List;
        _products = (r[1] as Map)['data'] as List;
      });
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final purpose = context.watch<AppState>().purpose;
    final title = switch (purpose) {
      'udhiya' => context.t('udhiyaSection'),
      'aqiqah' => context.t('aqiqahSection'),
      'sadaqah' => context.t('sadaqahSection'),
      _ => context.t('livestock'),
    };
    return Scaffold(
      appBar: AppBar(title: Text(title), centerTitle: false, automaticallyImplyLeading: !widget.embedded),
      body: RefreshIndicator(
        onRefresh: _load,
        child: CustomScrollView(slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
              child: TextField(
                controller: _search,
                textInputAction: TextInputAction.search,
                onSubmitted: (_) => _load(),
                decoration: InputDecoration(hintText: context.t('search'), prefixIcon: const Icon(Icons.search)),
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: SizedBox(
              height: 118,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                children: [
                  _animal(null, 'all', context.t('all'), null),
                  for (final c in _categories) _animal(c['id'], c['slug'], context.tr(c), c['image_url']),
                ],
              ),
            ),
          ),
          if (_loading)
            const SliverFillRemaining(child: Center(child: CircularProgressIndicator()))
          else if (_error != null)
            SliverFillRemaining(child: ErrorRetry(message: _error!, onRetry: _load))
          else if (_products.isEmpty)
            SliverFillRemaining(child: Center(child: Text(context.t('noProducts'), style: const TextStyle(color: Brand.muted))))
          else
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              sliver: SliverGrid(
                gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                    maxCrossAxisExtent: 240, mainAxisSpacing: 12, crossAxisSpacing: 12, mainAxisExtent: 320),
                delegate: SliverChildBuilderDelegate(
                  (context, i) => ProductCard(product: Map<String, dynamic>.from(_products[i])),
                  childCount: _products.length,
                ),
              ),
            ),
        ]),
      ),
    );
  }

  Widget _animal(int? id, String? slug, String label, String? image) {
    final selected = _categoryId == id;
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: () {
        setState(() => _categoryId = id);
        _load();
      },
      child: SizedBox(
        width: 86,
        child: Column(children: [
          SectionIcon(slug: slug, imageUrl: image, size: 70, selected: selected),
          const SizedBox(height: 6),
          Text(label, style: TextStyle(fontWeight: FontWeight.w700, color: selected ? Brand.maroon : Brand.ink)),
        ]),
      ),
    );
  }
}
