<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>رمز التحقق - PAC Team</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:Arial,sans-serif;">

<table width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f6f9;padding:30px 0;">
    <tr>
        <td align="center">

            <table width="100%" max-width="520" cellspacing="0" cellpadding="0"
                   style="background:#ffffff;border-radius:12px;padding:30px;max-width:520px;
                          border:1px solid #e0e0e0;box-shadow:0 4px 10px rgba(0,0,0,0.05);">

                <!-- Logo -->
                <tr>
                    <td align="center" style="padding-bottom:20px;">
                        <h2 style="margin:0;color:#0d6efd;">PAC Team</h2>
                    </td>
                </tr>

                <!-- Title -->
                <tr>
                    <td align="center" style="padding-bottom:10px;">
                        <h3 style="margin:0;color:#333;">تفعيل الحساب</h3>
                    </td>
                </tr>

                <!-- Description -->
                <tr>
                    <td align="center" style="color:#666;font-size:15px;padding-bottom:25px;line-height:1.7;">
                        يرجى استخدام رمز التحقق التالي لإكمال عملية التفعيل:
                    </td>
                </tr>

                <!-- OTP Code -->
                <tr>
                    <td align="center" style="padding-bottom:25px;">
                        <div style="
                            background:#f0f6ff;
                            border:1px dashed #0d6efd;
                            display:inline-block;
                            padding:16px 32px;
                            font-size:32px;
                            font-weight:bold;
                            letter-spacing:6px;
                            color:#0d6efd;
                            border-radius:8px;
                            direction:ltr;">
                            {{ $otp_code }}
                        </div>
                    </td>
                </tr>

                <!-- Timer -->
                <tr>
                    <td align="center" style="color:#dc3545;font-size:14px;padding-bottom:20px;">
                        هذا الرمز صالح لمدة 15 دقيقة فقط
                    </td>
                </tr>

                <!-- User Info -->
                <tr>
                    <td style="background:#f9fafc;border:1px solid #e0e0e0;border-radius:8px;padding:15px;">
                        <p style="margin:0 0 8px 0;font-size:14px;color:#555;">
                            👤 <strong>{{ $user->name }}</strong>
                        </p>
                        <p style="margin:0;font-size:14px;color:#555;">
                            📧 <strong>{{ $user->email }}</strong>
                        </p>
                    </td>
                </tr>

                <!-- Warning -->
                <tr>
                    <td align="center" style="padding-top:25px;color:#777;font-size:13px;line-height:1.7;">
                        لا تشارك هذا الرمز مع أي شخص.<br>
                        في حال لم تطلب هذا الرمز يرجى تجاهل الرسالة.
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td align="center" style="padding-top:25px;color:#aaa;font-size:12px;">
                        © {{ date('Y') }} PAC Team
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
