<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diamond PBN Password Reset</title>
</head>

<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                    style="max-width:620px; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#1f2937; padding:18px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="left" style="font-size:20px; font-weight:700; color:#ffffff;">
                                        Diamond PBN
                                        <div style="font-size:12px; font-weight:400; color:#cbd5e1; margin-top:2px;">
                                            Admin Security Center
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 24px 10px;">
                            <h1 style="margin:0 0 14px; font-size:24px; line-height:1.3; color:#111827;">Admin Password Reset</h1>
                            <p style="margin:0 0 14px; font-size:14px; line-height:1.7; color:#374151;">
                                Hello Admin,
                            </p>
                            <p style="margin:0 0 18px; font-size:14px; line-height:1.7; color:#374151;">
                                We received a request to reset your Diamond PBN admin password. Click the button below to continue.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">
                                <tr>
                                    <td align="center" style="border-radius:8px; background:#ec5629; box-shadow:0 2px 0 #c73f18;">
                                        <a href="{{ route('admin.reset.form', $token) }}"
                                            style="display:inline-block; padding:12px 22px; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700; letter-spacing:0.2px;">
                                            Reset Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="margin:0 0 18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                                <tr>
                                    <td style="padding:12px 14px;">
                                        <p style="margin:0 0 8px; font-size:12px; color:#334155; font-weight:700;">
                                            Security tip
                                        </p>
                                        <p style="margin:0; font-size:12px; line-height:1.7; color:#64748b;">
                                            If the button does not work, copy and paste this link into your browser:
                                        </p>
                                        <p style="margin:8px 0 0; font-size:12px; line-height:1.6; word-break:break-all;">
                                            <a href="{{ route('admin.reset.form', $token) }}" style="color:#2563eb; text-decoration:none;">
                                                {{ route('admin.reset.form', $token) }}
                                            </a>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 18px; font-size:13px; line-height:1.7; color:#6b7280;">
                                If you did not request this reset, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 24px 24px;">
                            <div style="height:1px; background:#e5e7eb; margin-bottom:14px;"></div>
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#6b7280;">
                                Regards,<br>
                                <strong style="color:#111827;">Diamond PBN Team</strong>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
