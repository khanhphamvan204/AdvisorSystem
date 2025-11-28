<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Thông báo từ hệ thống' }}</title>
</head>

<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0"
                    style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- Header với logo và màu xanh -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0066cc 0%, #004d99 100%); padding: 0;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <!-- Logo section -->
                                    <td width="120" align="center" valign="middle"
                                        style="padding: 25px 20px 25px 40px;">
                                        <img src="{{ $message->embed(public_path('images/logo/logo-huit.jpg')) }}"
                                            alt="Logo Trường"
                                            style="width: 90px; height: 90px; display: block; border-radius: 8px; background-color: rgba(255,255,255,0.1); padding: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                    </td>
                                    <!-- Text section -->
                                    <td align="left" valign="middle" style="padding: 25px 40px 25px 10px;">
                                        <h1
                                            style="color: #ffffff; margin: 0 0 8px 0; font-size: 22px; font-weight: 700; line-height: 1.3; text-transform: uppercase; letter-spacing: 0.5px;">
                                            Trường Đại học<br>Công Thương TP.HCM
                                        </h1>
                                        <p
                                            style="color: #b3d9ff; margin: 0; font-size: 13px; font-weight: 500; letter-spacing: 0.3px;">
                                            🎓 Hệ thống quản lý công tác cố vấn học tập
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body content -->
                    <tr>
                        <td style="padding: 40px;">
                            <!-- Greeting -->
                            <div
                                style="background: linear-gradient(to right, #e3f2fd, #f5f5f5); padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #0066cc;">
                                <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0;">
                                    Kính chào <strong style="color: #0066cc;">{{ $studentName }}</strong>,
                                </p>
                                <p style="color: #666666; font-size: 14px; margin: 8px 0 0 0;">
                                    Bạn nhận được thông báo mới từ Hệ thống quản lý công tác cố vấn học tập
                                </p>
                            </div>

                            <!-- Main content based on type -->
                            @if($type === 'notification')
                            <div
                                style="background-color: #ffffff; border: 2px solid #e3f2fd; padding: 25px; margin-bottom: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,102,204,0.08);">
                                <div
                                    style="display: inline-block; background-color: #0066cc; color: #ffffff; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; margin-bottom: 15px;">
                                    📢 THÔNG BÁO
                                </div>
                                <h2 style="color: #0066cc; margin: 0 0 15px 0; font-size: 20px; font-weight: 700;">
                                    {{ $notificationTitle }}
                                </h2>
                                <p
                                    style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0; text-align: justify;">
                                    {{ $notificationContent }}
                                </p>
                            </div>

                            @if(isset($notificationLink))
                            <div style="text-align: center; margin: 25px 0;">
                                <a href="{{ $notificationLink }}"
                                    style="display: inline-block; background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 6px; font-size: 15px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,102,204,0.3); transition: all 0.3s;">
                                    Xem chi tiết →
                                </a>
                            </div>
                            @endif

                            @elseif($type === 'activity')
                            <div
                                style="background-color: #ffffff; border: 2px solid #e3f2fd; padding: 25px; margin-bottom: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,102,204,0.08);">
                                <div
                                    style="display: inline-block; background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%); color: #ffffff; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; margin-bottom: 15px;">
                                    🎯 HOẠT ĐỘNG
                                </div>
                                <h2 style="color: #0066cc; margin: 0 0 15px 0; font-size: 20px; font-weight: 700;">
                                    {{ $activityTitle }}
                                </h2>
                                <p
                                    style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0 0 20px 0; text-align: justify;">
                                    {{ $activityDescription }}
                                </p>

                                <table width="100%" cellpadding="0" cellspacing="0"
                                    style="background-color: #f8f9fa; border-radius: 6px; overflow: hidden;">
                                    <tr>
                                        <td style="padding: 12px 15px; border-bottom: 1px solid #e0e0e0;">
                                            <span style="color: #666666; font-size: 14px;">📍 <strong>Địa
                                                    điểm:</strong></span>
                                            <span
                                                style="color: #333333; font-size: 14px; margin-left: 10px;">{{ $activityLocation ?? 'Chưa xác định' }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px 15px; border-bottom: 1px solid #e0e0e0;">
                                            <span style="color: #666666; font-size: 14px;">📅 <strong>Thời
                                                    gian:</strong></span>
                                            <span
                                                style="color: #333333; font-size: 14px; margin-left: 10px;">{{ $activityTime ?? 'Chưa xác định' }}</span>
                                        </td>
                                    </tr>
                                    @if(isset($activityPoints))
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                            <span style="color: #666666; font-size: 14px;">⭐ <strong>Điểm
                                                    thưởng:</strong></span>
                                            <span
                                                style="display: inline-block; background-color: #ffd700; color: #b8860b; font-size: 14px; font-weight: 700; padding: 4px 12px; border-radius: 12px; margin-left: 10px;">{{ $activityPoints }}
                                                điểm</span>
                                        </td>
                                    </tr>
                                    @endif
                                </table>
                            </div>

                            <div style="text-align: center; margin: 25px 0;">
                                <a href="{{ $activityLink ?? '#' }}"
                                    style="display: inline-block; background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 6px; font-size: 15px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,102,204,0.3);">
                                    Xem chi tiết hoạt động →
                                </a>
                            </div>

                            @elseif($type === 'warning')
                            <div
                                style="background-color: #ffffff; border: 2px solid #ffe0b2; padding: 25px; margin-bottom: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(255,152,0,0.1);">
                                <div
                                    style="display: inline-block; background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: #ffffff; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; margin-bottom: 15px;">
                                    ⚠️ CẢNH BÁO
                                </div>
                                <h2 style="color: #e65100; margin: 0 0 15px 0; font-size: 20px; font-weight: 700;">
                                    {{ $warningTitle }}
                                </h2>
                                <p
                                    style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0 0 15px 0; text-align: justify;">
                                    {{ $warningContent }}
                                </p>
                                @if(isset($warningAdvice))
                                <div
                                    style="background-color: #fff8e1; padding: 15px; border-left: 3px solid #ffa726; border-radius: 4px; margin-top: 15px;">
                                    <p style="color: #666666; font-size: 14px; line-height: 1.6; margin: 0;">
                                        💡 <strong style="color: #f57c00;">Lời khuyên:</strong> {{ $warningAdvice }}
                                    </p>
                                </div>
                                @endif
                            </div>

                            @elseif($type === 'meeting')
                            <div
                                style="background-color: #ffffff; border: 2px solid #c8e6c9; padding: 25px; margin-bottom: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(76,175,80,0.1);">
                                <div
                                    style="display: inline-block; background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%); color: #ffffff; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; margin-bottom: 15px;">
                                    👥 CUỘC HỌP
                                </div>
                                <h2 style="color: #2e7d32; margin: 0 0 15px 0; font-size: 20px; font-weight: 700;">
                                    {{ $meetingTitle }}
                                </h2>
                                <p
                                    style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0 0 20px 0; text-align: justify;">
                                    {{ $meetingSummary ?? '' }}
                                </p>

                                <table width="100%" cellpadding="0" cellspacing="0"
                                    style="background-color: #f1f8e9; border-radius: 6px; overflow: hidden;">
                                    <tr>
                                        <td style="padding: 12px 15px; border-bottom: 1px solid #dcedc8;">
                                            <span style="color: #666666; font-size: 14px;">📍 <strong>Địa
                                                    điểm:</strong></span>
                                            <span
                                                style="color: #333333; font-size: 14px; margin-left: 10px;">{{ $meetingLocation }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                            <span style="color: #666666; font-size: 14px;">🕐 <strong>Thời
                                                    gian:</strong></span>
                                            <span
                                                style="color: #333333; font-size: 14px; margin-left: 10px;">{{ $meetingTime }}</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div style="text-align: center; margin: 25px 0;">
                                <a href="{{ $meetingLink ?? '#' }}"
                                    style="display: inline-block; background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 6px; font-size: 15px; font-weight: 600; box-shadow: 0 4px 12px rgba(76,175,80,0.3);">
                                    Tham gia cuộc họp →
                                </a>
                            </div>
                            @endif

                            <!-- Footer message -->
                            <div
                                style="margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 6px; border-left: 3px solid #0066cc;">
                                <p style="color: #666666; font-size: 14px; line-height: 1.6; margin: 0;">
                                    💬 <strong style="color: #333333;">Cần hỗ trợ?</strong><br>
                                    Nếu bạn có bất kỳ thắc mắc nào, vui lòng liên hệ với cố vấn học tập của lớp hoặc
                                    phản hồi qua hệ thống.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            style="background: linear-gradient(to bottom, #f8f9fa 0%, #e9ecef 100%); padding: 30px 40px; border-top: 3px solid #0066cc;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding-bottom: 20px;">
                                        <table cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td
                                                    style="color: #333333; font-size: 14px; font-weight: 700; padding-bottom: 12px; text-align: center;">
                                                    🏫 TRƯỜNG ĐẠI HỌC CÔNG THƯƠNG TP.HCM
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td width="33%" valign="top" style="padding: 0 10px;">
                                                    <div
                                                        style="text-align: center; padding: 12px; background-color: #ffffff; border-radius: 6px; margin-bottom: 10px;">
                                                        <div style="font-size: 20px; margin-bottom: 5px;">📧</div>
                                                        <div
                                                            style="font-size: 11px; color: #999999; margin-bottom: 3px;">
                                                            Email</div>
                                                        <a href="mailto:info@huit.edu.vn"
                                                            style="color: #0066cc; text-decoration: none; font-size: 12px; font-weight: 600;">info@huit.edu.vn</a>
                                                    </div>
                                                </td>
                                                <td width="33%" valign="top" style="padding: 0 10px;">
                                                    <div
                                                        style="text-align: center; padding: 12px; background-color: #ffffff; border-radius: 6px; margin-bottom: 10px;">
                                                        <div style="font-size: 20px; margin-bottom: 5px;">🌐</div>
                                                        <div
                                                            style="font-size: 11px; color: #999999; margin-bottom: 3px;">
                                                            Website</div>
                                                        <a href="https://huit.edu.vn"
                                                            style="color: #0066cc; text-decoration: none; font-size: 12px; font-weight: 600;">huit.edu.vn</a>
                                                    </div>
                                                </td>
                                                <td width="33%" valign="top" style="padding: 0 10px;">
                                                    <div
                                                        style="text-align: center; padding: 12px; background-color: #ffffff; border-radius: 6px; margin-bottom: 10px;">
                                                        <div style="font-size: 20px; margin-bottom: 5px;">📞</div>
                                                        <div
                                                            style="font-size: 11px; color: #999999; margin-bottom: 3px;">
                                                            Hotline</div>
                                                        <div style="color: #333333; font-size: 12px; font-weight: 600;">
                                                            028.3512.6222</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center;">
                                        <p style="color: #999999; font-size: 11px; margin: 0; line-height: 1.6;">
                                            Email này được gửi tự động từ Hệ thống quản lý công tác cố vấn học tập<br>
                                            © {{ date('Y') }} Trường Đại học Công Thương TP.HCM. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>