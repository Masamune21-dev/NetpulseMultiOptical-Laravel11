<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FcmService
{
    /**
     * @return array{client_email:string, private_key:string, project_id:string}
     */
    private function credentials(): array
    {
        $path = (string) env('FIREBASE_SERVICE_ACCOUNT_JSON', '');
        if ($path === '') {
            throw new \RuntimeException('FIREBASE_SERVICE_ACCOUNT_JSON is not set');
        }

        if (!is_file($path)) {
            throw new \RuntimeException("Firebase service account json not found at: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid Firebase service account json');
        }

        $clientEmail = (string) ($decoded['client_email'] ?? '');
        $privateKey = (string) ($decoded['private_key'] ?? '');
        $projectId = (string) ($decoded['project_id'] ?? '');

        if ($clientEmail === '' || $privateKey === '' || $projectId === '') {
            throw new \RuntimeException('Firebase service account json missing client_email/private_key/project_id');
        }

        return [
            'client_email' => $clientEmail,
            'private_key' => $privateKey,
            'project_id' => $projectId,
        ];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function googleAccessToken(): string
    {
        $creds = $this->credentials();
        $now = time();

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_UNESCAPED_SLASHES));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_UNESCAPED_SLASHES));

        $signingInput = $header . '.' . $claims;

        $signature = '';
        $key = openssl_pkey_get_private($creds['private_key']);
        if ($key === false) {
            throw new \RuntimeException('Invalid private key');
        }

        $ok = openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);

        if (!$ok) {
            throw new \RuntimeException('Failed to sign JWT');
        }

        $jwt = $signingInput . '.' . $this->base64UrlEncode($signature);

        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$res->ok()) {
            $body = $res->json();
            $err = is_array($body) ? json_encode($body) : (string) $res->body();
            throw new \RuntimeException("Failed to fetch Google access token: HTTP {$res->status()} {$err}");
        }

        $token = (string) ($res->json('access_token') ?? '');
        if ($token === '') {
            throw new \RuntimeException('Missing access_token from Google');
        }

        return $token;
    }

    /**
     * @param array<string,string> $data
     * @return array<string,mixed>
     */
    public function sendToToken(string $deviceToken, string $title, string $body, array $data = []): array
    {
        $creds = $this->credentials();
        $accessToken = $this->googleAccessToken();

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => (object) $data,
                'android' => [
                    'priority' => 'HIGH',
                ],
            ],
        ];

        $url = "https://fcm.googleapis.com/v1/projects/{$creds['project_id']}/messages:send";

        $res = Http::withToken($accessToken)->post($url, $payload);

        $json = $res->json();
        if (!$res->ok()) {
            $err = is_array($json) ? json_encode($json) : (string) $res->body();
            throw new \RuntimeException("FCM send failed: HTTP {$res->status()} {$err}");
        }

        return is_array($json) ? $json : ['success' => true];
    }
}

