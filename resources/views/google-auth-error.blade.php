<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lỗi xác thực - Google Calendar</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }

        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: shake 0.5s ease-out;
        }

        .icon svg {
            width: 40px;
            height: 40px;
            stroke: white;
            stroke-width: 3;
            fill: none;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-10px);
            }

            75% {
                transform: translateX(10px);
            }
        }

        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
        }

        .error-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }

        .error-code {
            font-family: 'Courier New', monospace;
            background: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            display: inline-block;
            margin: 10px 0;
            font-size: 13px;
            color: #d63031;
        }

        .error-message {
            color: #856404;
            line-height: 1.6;
            margin-top: 15px;
        }

        .solutions {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }

        .solutions h3 {
            color: #333;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .solution-item {
            display: flex;
            align-items: flex-start;
            margin: 12px 0;
            padding: 10px;
            background: white;
            border-radius: 8px;
        }

        .solution-item .number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .solution-item .text {
            color: #555;
            font-size: 14px;
            line-height: 1.5;
        }

        .buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.2s;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #e9ecef;
            color: #333;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #999;
            font-size: 13px;
        }

        code {
            background: #f1f3f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 13px;
            color: #e83e8c;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="icon">
            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
        </div>

        <h1>Xác thực thất bại ❌</h1>
        <p class="subtitle">Không thể kết nối với Google Calendar</p>

        <div class="error-box">
            <strong>Mã lỗi:</strong>
            <div class="error-code">{{ $error }}</div>

            @if($error_description)
            <div style="margin-top: 10px;">
                <strong>Chi tiết:</strong> {{ $error_description }}
            </div>
            @endif

            <div class="error-message">
                {{ $error_message }}
            </div>
        </div>

        <div class="solutions">
            <h3>🔧 Cách khắc phục:</h3>

            @if($error === 'redirect_uri_mismatch')
            <div class="solution-item">
                <div class="number">1</div>
                <div class="text">
                    Vào <strong>Google Cloud Console</strong> → Credentials → OAuth 2.0 Client
                </div>
            </div>
            <div class="solution-item">
                <div class="number">2</div>
                <div class="text">
                    Thêm chính xác Redirect URI: <code>{{ url('/api/auth/google/callback') }}</code>
                </div>
            </div>
            <div class="solution-item">
                <div class="number">3</div>
                <div class="text">
                    Click <strong>Save</strong> và đợi vài phút để Google cập nhật
                </div>
            </div>

            @elseif($error === 'access_denied')
            <div class="solution-item">
                <div class="number">1</div>
                <div class="text">
                    Thử lại và <strong>chấp nhận tất cả các quyền</strong> khi Google yêu cầu
                </div>
            </div>
            <div class="solution-item">
                <div class="number">2</div>
                <div class="text">
                    Ứng dụng cần quyền truy cập Google Calendar để tạo và quản lý cuộc họp
                </div>
            </div>

            @elseif($error === 'unauthorized_client')
            <div class="solution-item">
                <div class="number">1</div>
                <div class="text">
                    Vào <strong>OAuth consent screen</strong> trong Google Cloud Console
                </div>
            </div>
            <div class="solution-item">
                <div class="number">2</div>
                <div class="text">
                    Thêm email của bạn vào <strong>Test users</strong>
                </div>
            </div>
            <div class="solution-item">
                <div class="number">3</div>
                <div class="text">
                    Đảm bảo app đang ở chế độ <strong>Testing</strong> hoặc <strong>Published</strong>
                </div>
            </div>

            @else
            <div class="solution-item">
                <div class="number">1</div>
                <div class="text">
                    Kiểm tra file <code>credentials.json</code> có đúng không
                </div>
            </div>
            <div class="solution-item">
                <div class="number">2</div>
                <div class="text">
                    Đảm bảo Redirect URI đã được thêm vào Google Cloud Console
                </div>
            </div>
            <div class="solution-item">
                <div class="number">3</div>
                <div class="text">
                    Kiểm tra email đã được thêm vào Test users
                </div>
            </div>
            @endif
        </div>

        <div class="buttons">
            <a href="{{ url('/api/auth/google') }}" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                Thử lại
            </a>
            <button onclick="window.close()" class="btn btn-secondary">
                Đóng cửa sổ
            </button>
        </div>

        <div class="footer">
            <strong>Cần trợ giúp?</strong> Kiểm tra log tại <code>storage/logs/laravel.log</code>
            <br>
            Google Cloud Console: <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="color: #667eea;">console.cloud.google.com</a>
        </div>
    </div>
</body>

</html>