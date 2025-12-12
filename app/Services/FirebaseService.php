<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class FirebaseService
{
    protected $projectId;
    protected $accessToken;
    protected $credentialsPath;

    public function __construct()
    {
        $this->projectId = config('firebase.project_id');

        // Get credentials path from config
        $configPath = config('firebase.credentials');

        // If config returns relative path or empty, use storage_path
        if (empty($configPath) || !file_exists($configPath)) {
            $this->credentialsPath = storage_path('app/firebase/service-account.json');
        } else {
            $this->credentialsPath = $configPath;
        }

        // Log the resolved path for debugging
        Log::debug('Firebase credentials path resolved to: ' . $this->credentialsPath);
    }

    /**
     * Get OAuth2 access token from service account
     */
    protected function getAccessToken()
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            // Read service account JSON
            if (!file_exists($this->credentialsPath)) {
                throw new Exception("Firebase credentials file not found at: {$this->credentialsPath}");
            }

            $credentials = json_decode(file_get_contents($this->credentialsPath), true);

            // Create JWT
            $now = time();
            $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
            $payload = json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

            $base64UrlHeader = $this->base64UrlEncode($header);
            $base64UrlPayload = $this->base64UrlEncode($payload);
            $signature = '';

            openssl_sign(
                $base64UrlHeader . "." . $base64UrlPayload,
                $signature,
                $credentials['private_key'],
                OPENSSL_ALGO_SHA256
            );

            $base64UrlSignature = $this->base64UrlEncode($signature);
            $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (!$response->successful()) {
                throw new Exception('Failed to get access token: ' . $response->body());
            }

            $this->accessToken = $response->json('access_token');
            return $this->accessToken;
        } catch (Exception $e) {
            Log::error('Failed to get Firebase access token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Base64 URL encode
     */
    protected function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Send push notification to a single device
     */
    public function sendToDevice(string $fcmToken, string $title, string $body, array $data = [])
    {
        try {
            $accessToken = $this->getAccessToken();

            $message = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                ]
            ];

            if (!empty($data)) {
                $message['message']['data'] = $data;
            }

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $message);

            if (!$response->successful()) {
                Log::error('FCM API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to send notification: ' . $response->json('error.message', 'Unknown error'),
                ];
            }

            Log::info('FCM notification sent successfully', [
                'token' => substr($fcmToken, 0, 20) . '...',
                'title' => $title,
            ]);

            return [
                'success' => true,
                'message' => 'Notification sent successfully',
                'result' => $response->json(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to send FCM notification', [
                'error' => $e->getMessage(),
                'token' => substr($fcmToken, 0, 20) . '...',
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send push notification to multiple devices
     */
    public function sendToMultipleDevices(array $fcmTokens, string $title, string $body, array $data = [])
    {
        $successCount = 0;
        $failureCount = 0;
        $errors = [];

        foreach ($fcmTokens as $token) {
            $result = $this->sendToDevice($token, $title, $body, $data);

            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
                $errors[] = [
                    'token' => substr($token, 0, 20) . '...',
                    'error' => $result['message']
                ];
            }
        }

        Log::info('FCM multicast sent', [
            'total' => count($fcmTokens),
            'success' => $successCount,
            'failed' => $failureCount,
        ]);

        return [
            'success' => true,
            'message' => "Sent to {$successCount} devices, {$failureCount} failed",
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'errors' => $errors,
        ];
    }

    /**
     * Send push notification to a topic
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = [])
    {
        try {
            $accessToken = $this->getAccessToken();

            $message = [
                'message' => [
                    'topic' => $topic,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                ]
            ];

            if (!empty($data)) {
                $message['message']['data'] = $data;
            }

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $message);

            if (!$response->successful()) {
                Log::error('FCM Topic API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to send notification: ' . $response->json('error.message', 'Unknown error'),
                ];
            }

            Log::info('FCM topic notification sent', [
                'topic' => $topic,
                'title' => $title,
            ]);

            return [
                'success' => true,
                'message' => 'Notification sent to topic successfully',
                'result' => $response->json(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to send FCM topic notification', [
                'error' => $e->getMessage(),
                'topic' => $topic,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Subscribe device token to a topic
     */
    public function subscribeToTopic($fcmTokens, string $topic)
    {
        try {
            $tokens = is_array($fcmTokens) ? $fcmTokens : [$fcmTokens];
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post("https://iid.googleapis.com/iid/v1:batchAdd", [
                    'to' => "/topics/{$topic}",
                    'registration_tokens' => $tokens,
                ]);

            if (!$response->successful()) {
                Log::error('FCM Subscribe Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to subscribe: ' . $response->json('error', 'Unknown error'),
                ];
            }

            Log::info('Subscribed to topic', [
                'topic' => $topic,
                'token_count' => count($tokens),
            ]);

            return [
                'success' => true,
                'message' => 'Subscribed to topic successfully',
            ];
        } catch (Exception $e) {
            Log::error('Failed to subscribe to topic', [
                'error' => $e->getMessage(),
                'topic' => $topic,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to subscribe: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Unsubscribe device token from a topic
     */
    public function unsubscribeFromTopic($fcmTokens, string $topic)
    {
        try {
            $tokens = is_array($fcmTokens) ? $fcmTokens : [$fcmTokens];
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post("https://iid.googleapis.com/iid/v1:batchRemove", [
                    'to' => "/topics/{$topic}",
                    'registration_tokens' => $tokens,
                ]);

            if (!$response->successful()) {
                Log::error('FCM Unsubscribe Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to unsubscribe: ' . $response->json('error', 'Unknown error'),
                ];
            }

            Log::info('Unsubscribed from topic', [
                'topic' => $topic,
                'token_count' => count($tokens),
            ]);

            return [
                'success' => true,
                'message' => 'Unsubscribed from topic successfully',
            ];
        } catch (Exception $e) {
            Log::error('Failed to unsubscribe from topic', [
                'error' => $e->getMessage(),
                'topic' => $topic,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to unsubscribe: ' . $e->getMessage(),
            ];
        }
    }
}
