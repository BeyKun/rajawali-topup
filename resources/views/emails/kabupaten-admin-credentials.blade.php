<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kredensial Akun Admin Wilayah</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6fa;
            margin: 0;
            padding: 24px;
            color: #1e293b;
        }
        .container {
            max-width: 560px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 24px 32px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        .content {
            padding: 32px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .lead {
            font-size: 14px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 24px;
        }
        .credentials-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .field {
            margin-bottom: 12px;
            font-size: 14px;
        }
        .field:last-child {
            margin-bottom: 0;
        }
        .label {
            font-weight: 600;
            color: #64748b;
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .value {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 15px;
            color: #0f172a;
            font-weight: 600;
            display: inline-block;
            word-break: break-all;
        }
        .login-btn {
            display: block;
            width: 100%;
            text-align: center;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            box-sizing: border-box;
            margin-bottom: 20px;
        }
        .note {
            font-size: 12px;
            color: #64748b;
            line-height: 1.5;
            border-top: 1px solid #f1f5f9;
            padding-top: 16px;
        }
        .footer {
            background-color: #f8fafc;
            padding: 16px 32px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name', 'Rajawali Topup') }}</h1>
        </div>
        <div class="content">
            <div class="greeting">Halo, {{ $admin->name }}!</div>
            <div class="lead">
                Akun Admin Wilayah Anda untuk wilayah <strong>{{ $cityName }}</strong> telah berhasil didaftarkan. Gunakan kredensial di bawah ini untuk masuk ke dashboard:
            </div>

            <div class="credentials-box">
                <div class="field">
                    <span class="label">Wilayah Penugasan:</span>
                    <span style="font-weight: 600; color: #0f172a;">{{ $cityName }}</span>
                </div>
                <div class="field" style="margin-top: 12px;">
                    <span class="label">Email:</span>
                    <div class="value">{{ $admin->email }}</div>
                </div>
                <div class="field" style="margin-top: 12px;">
                    <span class="label">Password Sementara:</span>
                    <div class="value">{{ $plainPassword }}</div>
                </div>
            </div>

            <a href="{{ url('/login') }}" class="login-btn">Masuk ke Dashboard</a>

            <div class="note">
                <strong>Penting:</strong> Demi keamanan akun Anda, harap segera mengganti password sementara ini melalui menu <em>Reset Password</em> setelah Anda berhasil masuk ke dashboard.
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name', 'Rajawali Topup') }}. Hak cipta dilindungi.
        </div>
    </div>
</body>
</html>

