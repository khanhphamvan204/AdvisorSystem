<?php

namespace App\Http\Controllers;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Gmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    /**
     * Redirect người dùng đến trang xác thực Google
     * GET /api/auth/google
     */
    public function redirectToGoogle()
    {
        try {
            $credentialsPath = storage_path('app/google/credentials.json');

            if (!file_exists($credentialsPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chưa cấu hình credentials.json. Vui lòng đặt file này tại: storage/app/google/credentials.json',
                    'guide' => [
                        '1. Truy cập https://console.cloud.google.com/',
                        '2. Tạo project và bật Google Calendar API',
                        '3. Tạo OAuth 2.0 Client ID (Web application)',
                        '4. Thêm Redirect URI: ' . url('/api/auth/google/callback'),
                        '5. Download file JSON và đặt tại storage/app/google/credentials.json'
                    ]
                ], 500);
            }

            $client = new Client();
            $client->setApplicationName('Class Meeting Manager');
            $client->setScopes([
                Calendar::CALENDAR,
                Gmail::GMAIL_SEND  // Cho phép gửi email mời
            ]);
            $client->setAuthConfig($credentialsPath);
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');
            $client->setRedirectUri(url('/api/auth/google/callback'));

            $authUrl = $client->createAuthUrl();

            return redirect($authUrl);
        } catch (\Exception $e) {
            Log::error('Google auth redirect error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo link xác thực: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xử lý callback từ Google
     * GET /api/auth/google/callback
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            // Kiểm tra xem có lỗi từ Google không
            if ($request->has('error')) {
                $error = $request->get('error');
                $errorDescription = $request->get('error_description', 'Không có mô tả');

                Log::error('Google OAuth error: ' . $error . ' - ' . $errorDescription);

                return response()->view('google-auth-error', [
                    'error' => $error,
                    'error_description' => $errorDescription,
                    'error_message' => $this->getErrorMessage($error)
                ]);
            }

            if (!$request->has('code')) {
                return response()->view('google-auth-error', [
                    'error' => 'no_code',
                    'error_description' => 'Không nhận được authorization code từ Google',
                    'error_message' => 'Có thể bạn đã từ chối cấp quyền hoặc có lỗi xảy ra trong quá trình xác thực.'
                ]);
            }

            $credentialsPath = storage_path('app/google/credentials.json');
            $tokenPath = storage_path('app/google/token.json');

            $client = new Client();
            $client->setApplicationName('Class Meeting Manager');
            $client->setScopes([
                Calendar::CALENDAR,
                Gmail::GMAIL_SEND  // Cho phép gửi email mời
            ]);
            $client->setAuthConfig($credentialsPath);
            $client->setAccessType('offline');
            $client->setRedirectUri(url('/api/auth/google/callback'));

            // Exchange authorization code
            $accessToken = $client->fetchAccessTokenWithAuthCode($request->code);

            if (isset($accessToken['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi xác thực: ' . $accessToken['error_description']
                ], 400);
            }

            // Tạo thư mục nếu chưa có
            $directory = dirname($tokenPath);
            if (!file_exists($directory)) {
                mkdir($directory, 0700, true);
            }

            // Lưu token
            file_put_contents($tokenPath, json_encode($accessToken));

            Log::info('Google Calendar authentication successful');

            // Trả về trang HTML đẹp
            return response()->view('google-auth-success', [
                'has_refresh_token' => isset($accessToken['refresh_token']),
                'expires_in' => isset($accessToken['expires_in']) ? $accessToken['expires_in'] : null
            ]);
        } catch (\Exception $e) {
            Log::error('Google auth callback error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xác thực: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kiểm tra trạng thái xác thực
     * GET /api/auth/google/status
     */
    public function checkAuthStatus()
    {
        try {
            $tokenPath = storage_path('app/google/token.json');
            $credentialsPath = storage_path('app/google/credentials.json');

            $status = [
                'credentials_exists' => file_exists($credentialsPath),
                'token_exists' => file_exists($tokenPath),
                'is_authenticated' => false,
                'token_expired' => null,
                'has_refresh_token' => null
            ];

            if ($status['token_exists']) {
                $tokenData = json_decode(file_get_contents($tokenPath), true);

                $client = new Client();
                $client->setAuthConfig($credentialsPath);
                $client->setAccessToken($tokenData);

                $status['is_authenticated'] = true;
                $status['token_expired'] = $client->isAccessTokenExpired();
                $status['has_refresh_token'] = $client->getRefreshToken() !== null;

                if (isset($tokenData['created']) && isset($tokenData['expires_in'])) {
                    $expiresAt = $tokenData['created'] + $tokenData['expires_in'];
                    $status['expires_at'] = date('Y-m-d H:i:s', $expiresAt);
                    $status['expires_in_seconds'] = $expiresAt - time();
                }
            }

            return response()->json([
                'success' => true,
                'data' => $status,
                'message' => $status['is_authenticated']
                    ? 'Đã xác thực Google Calendar'
                    : 'Chưa xác thực Google Calendar'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi kiểm tra trạng thái: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa token và yêu cầu xác thực lại
     * DELETE /api/auth/google/revoke
     */
    public function revokeAuth(Request $request)
    {
        try {
            $tokenPath = storage_path('app/google/token.json');

            if (file_exists($tokenPath)) {
                // Đọc token trước khi xóa
                $tokenData = json_decode(file_get_contents($tokenPath), true);

                // Revoke token trên Google
                if (isset($tokenData['access_token'])) {
                    $client = new Client();
                    $client->revokeToken($tokenData['access_token']);
                }

                // Xóa file token
                unlink($tokenPath);

                Log::info('Google Calendar token revoked');

                return response()->json([
                    'success' => true,
                    'message' => 'Đã xóa xác thực thành công. Vui lòng xác thực lại để sử dụng.'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy token để xóa'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Google auth revoke error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa xác thực: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Chuyển đổi error code thành thông báo dễ hiểu
     */
    private function getErrorMessage($errorCode)
    {
        $messages = [
            'access_denied' => 'Bạn đã từ chối cấp quyền cho ứng dụng. Vui lòng thử lại và chấp nhận các quyền cần thiết.',
            'redirect_uri_mismatch' => 'Redirect URI không khớp. Vui lòng kiểm tra cấu hình trong Google Cloud Console.',
            'invalid_client' => 'Client ID không hợp lệ. Vui lòng kiểm tra file credentials.json.',
            'unauthorized_client' => 'Ứng dụng chưa được ủy quyền. Vui lòng kiểm tra OAuth Consent Screen.',
            'invalid_request' => 'Yêu cầu không hợp lệ. Vui lòng thử lại.',
        ];

        return $messages[$errorCode] ?? 'Đã xảy ra lỗi không xác định. Vui lòng thử lại.';
    }

    /**
     * Debug endpoint - Kiểm tra cấu hình
     * GET /api/auth/google/debug
     */
    public function debugConfig()
    {
        $credentialsPath = storage_path('app/google/credentials.json');
        $tokenPath = storage_path('app/google/token.json');

        $debug = [
            'credentials' => [
                'path' => $credentialsPath,
                'exists' => file_exists($credentialsPath),
                'readable' => file_exists($credentialsPath) && is_readable($credentialsPath),
            ],
            'token' => [
                'path' => $tokenPath,
                'exists' => file_exists($tokenPath),
                'readable' => file_exists($tokenPath) && is_readable($tokenPath),
            ],
            'redirect_uri' => url('/api/auth/google/callback'),
            'app_url' => config('app.url'),
        ];

        // Đọc thông tin credentials nếu có
        if ($debug['credentials']['exists']) {
            try {
                $credentials = json_decode(file_get_contents($credentialsPath), true);

                if (isset($credentials['web'])) {
                    $debug['credentials']['type'] = 'web';
                    $debug['credentials']['client_id'] = $credentials['web']['client_id'] ?? 'N/A';
                    $debug['credentials']['redirect_uris'] = $credentials['web']['redirect_uris'] ?? [];
                } elseif (isset($credentials['installed'])) {
                    $debug['credentials']['type'] = 'installed (desktop)';
                    $debug['credentials']['warning'] = '⚠️ Bạn đang dùng Desktop app credentials. Cần chuyển sang Web application!';
                }
            } catch (\Exception $e) {
                $debug['credentials']['error'] = $e->getMessage();
            }
        }

        // Kiểm tra redirect URI
        $expectedUri = url('/api/auth/google/callback');
        if (isset($debug['credentials']['redirect_uris'])) {
            $debug['redirect_uri_check'] = [
                'expected' => $expectedUri,
                'configured' => $debug['credentials']['redirect_uris'],
                'matched' => in_array($expectedUri, $debug['credentials']['redirect_uris'])
            ];
        }

        return response()->json([
            'success' => true,
            'debug' => $debug,
            'recommendations' => $this->getRecommendations($debug)
        ], 200);
    }

    /**
     * Đưa ra khuyến nghị dựa trên cấu hình
     */
    private function getRecommendations($debug)
    {
        $recommendations = [];

        if (!$debug['credentials']['exists']) {
            $recommendations[] = '❌ Chưa có file credentials.json. Vui lòng download từ Google Cloud Console.';
        }

        if (isset($debug['credentials']['type']) && $debug['credentials']['type'] !== 'web') {
            $recommendations[] = '⚠️ Credentials không phải loại "Web application". Vui lòng tạo lại OAuth Client ID.';
        }

        if (isset($debug['redirect_uri_check']) && !$debug['redirect_uri_check']['matched']) {
            $recommendations[] = '❌ Redirect URI không khớp. Vui lòng thêm: ' . $debug['redirect_uri_check']['expected'];
        }

        if (!$debug['token']['exists']) {
            $recommendations[] = '⚠️ Chưa xác thực. Truy cập /api/auth/google để xác thực.';
        }

        if (empty($recommendations)) {
            $recommendations[] = '✅ Cấu hình OK! Bạn có thể bắt đầu sử dụng.';
        }

        return $recommendations;
    }
}
