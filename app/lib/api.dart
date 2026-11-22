import 'dart:convert';

import 'package:http/http.dart' as http;

import 'config.dart';

class ApiException implements Exception {
  ApiException(this.message, [this.status = 0]);
  final String message;
  final int status;
  @override
  String toString() => message;
}

class Api {
  Api({this.token, this.locale = 'ar'});
  String? token;
  String locale;

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Accept-Language': locale,
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Uri _uri(String path, [Map<String, dynamic>? query]) => Uri.parse('${Config.apiUrl}$path').replace(
        queryParameters: query?.map((k, v) => MapEntry(k, '$v'))?..removeWhere((k, v) => v == 'null'),
      );

  Future<dynamic> get(String path, [Map<String, dynamic>? query]) async => _handle(await http.get(_uri(path, query), headers: _headers));

  Future<dynamic> post(String path, [Map<String, dynamic>? body]) async =>
      _handle(await http.post(_uri(path), headers: _headers, body: jsonEncode(body ?? {})));

  Future<dynamic> patch(String path, Map<String, dynamic> body) async =>
      _handle(await http.patch(_uri(path), headers: _headers, body: jsonEncode(body)));

  Future<dynamic> delete(String path) async => _handle(await http.delete(_uri(path), headers: _headers));

  dynamic _handle(http.Response res) {
    final body = res.body.isEmpty ? null : jsonDecode(utf8.decode(res.bodyBytes));
    if (res.statusCode >= 200 && res.statusCode < 300) return body;
    var message = 'Error ${res.statusCode}';
    if (body is Map) {
      final errors = body['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        message = first is List ? first.first.toString() : first.toString();
      } else if (body['message'] != null) {
        message = body['message'].toString();
      }
    }
    throw ApiException(message, res.statusCode);
  }
}
