import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../l10n.dart';
import '../theme.dart';

/// Opens Tabby's or Tamara's hosted page. Closes itself once the provider sends the
/// customer back to our return page; the order screen then reloads the real payment status.
class BnplCheckoutScreen extends StatefulWidget {
  const BnplCheckoutScreen({super.key, required this.url, required this.method});
  final String url;
  final String method;

  /// Returns when the customer is done (paid, cancelled or failed). The caller re-reads the order.
  static Future<void> open(BuildContext context, String url, String method) async {
    if (kIsWeb) {
      await launchUrl(Uri.parse(url), webOnlyWindowName: '_self');
      return;
    }
    await Navigator.push(context, MaterialPageRoute(builder: (_) => BnplCheckoutScreen(url: url, method: method)));
  }

  @override
  State<BnplCheckoutScreen> createState() => _BnplCheckoutScreenState();
}

class _BnplCheckoutScreenState extends State<BnplCheckoutScreen> {
  late final WebViewController _controller;
  int _progress = 0;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(NavigationDelegate(
        onProgress: (p) => setState(() => _progress = p),
        onPageFinished: (url) async {
          if (url.contains('/payments/${widget.method}/return/')) {
            await Future.delayed(const Duration(milliseconds: 1500)); // let the customer read the result
            if (mounted) Navigator.pop(context);
          }
        },
      ))
      ..loadRequest(Uri.parse(widget.url));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t(widget.method)),
        leading: IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(context)),
        bottom: _progress < 100
            ? PreferredSize(
                preferredSize: const Size.fromHeight(3),
                child: LinearProgressIndicator(value: _progress / 100, color: Brand.maroon, minHeight: 3),
              )
            : null,
      ),
      body: WebViewWidget(controller: _controller),
    );
  }
}
