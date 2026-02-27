import 'dart:convert';

import 'package:http/http.dart' as http;

import '../auth/session_store.dart';

class ApiException implements Exception {
  ApiException(this.statusCode, this.message);

  final int statusCode;
  final String message;

  @override
  String toString() => 'ApiException($statusCode): $message';
}

class ApiClient {
  ApiClient(this._session);

  final SessionStore _session;

  Uri _uri(String path) {
    final base = _session.apiBaseUrl.trim().replaceAll(RegExp(r'/+$'), '');
    final p = path.startsWith('/') ? path : '/$path';
    return Uri.parse('$base$p');
  }

  Map<String, String> _headers({bool auth = true}) {
    final h = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (auth) {
      final token = _session.accessToken;
      if (token != null && token.isNotEmpty) {
        h['Authorization'] = 'Bearer $token';
      }
    }
    return h;
  }

  Future<Map<String, dynamic>> postJson(
    String path, {
    Map<String, dynamic>? body,
    bool auth = true,
  }) async {
    final res = await http.post(
      _uri(path),
      headers: _headers(auth: auth),
      body: jsonEncode(body ?? const {}),
    );

    Map<String, dynamic> decoded = const {};
    if (res.body.isNotEmpty) {
      final tmp = jsonDecode(res.body);
      if (tmp is Map<String, dynamic>) {
        decoded = tmp;
      }
    }

    if (res.statusCode < 200 || res.statusCode >= 300) {
      final msg = (decoded['error'] ?? res.reasonPhrase ?? 'Request failed')
          .toString();
      throw ApiException(res.statusCode, msg);
    }

    return decoded;
  }

  Future<Map<String, dynamic>> getJson(
    String path, {
    Map<String, dynamic>? query,
    bool auth = true,
  }) async {
    final uri = _uri(path).replace(queryParameters: query?.map(
          (k, v) => MapEntry(k, v.toString()),
        ));

    final res = await http.get(
      uri,
      headers: _headers(auth: auth),
    );

    Map<String, dynamic> decoded = const {};
    if (res.body.isNotEmpty) {
      final tmp = jsonDecode(res.body);
      if (tmp is Map<String, dynamic>) {
        decoded = tmp;
      }
    }

    if (res.statusCode < 200 || res.statusCode >= 300) {
      final msg = (decoded['error'] ?? res.reasonPhrase ?? 'Request failed')
          .toString();
      throw ApiException(res.statusCode, msg);
    }

    return decoded;
  }
}
