<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $brandName ?? 'Diamond PBN' }} - Login Verification Code</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f6fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:620px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#111827,#1f2937);padding:24px;text-align:center;">
                            <img src="{{ $logoUrl ?? url('/images/hero-network.svg') }}" alt="{{ $brandName ?? 'Diamond PBN' }} logo" style="height:42px;max-width:190px;display:block;margin:0 auto 10px auto;">
                            <div style="font-size:20px;line-height:1.3;color:#ffffff;font-weight:700;">{{ $brandName ?? 'Diamond PBN' }}</div>
                            <div style="font-size:13px;line-height:1.4;color:#d1d5db;margin-top:4px;">Secure admin login verification</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 24px 10px 24px;">
                            <p style="margin:0 0 12px 0;font-size:16px;line-height:1.6;">
                                Hello {{ $admin->name ?? 'Admin' }},
                            </p>
                            <p style="margin:0 0 18px 0;font-size:14px;line-height:1.7;color:#374151;">
                                We received a login request for your admin account. Use the verification code below to continue.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 18px 0;">
                                <tr>
                                    <td align="center" style="padding:16px;background:#f9fafb;border:1px dashed #cbd5e1;border-radius:10px;">
                                        <div style="font-size:30px;letter-spacing:6px;font-weight:800;color:#111827;">{{ $otp }}</div>
                                        <div style="font-size:12px;color:#6b7280;margin-top:8px;">This code expires in 10 minutes</div>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 18px 0;">
                                <tr>
                                    <td style="padding:14px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;">
                                        <div style="font-size:13px;color:#1e3a8a;line-height:1.7;">
                                            <strong>Security tips:</strong><br>
                                            - Never share this code with anyone.<br>
                                            - Diamond PBN team will never ask for OTP by phone/chat.<br>
                                            - If this wasn't you, change your password immediately.
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px 0;font-size:13px;line-height:1.7;color:#4b5563;">
                                If you did not attempt to log in, please ignore this email and review your account security.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 24px 24px 24px;border-top:1px solid #f3f4f6;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#9ca3af;text-align:center;">
                                This is an automated message from {{ $brandName ?? 'Diamond PBN' }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
