<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline Mode - SIKAP Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/theme.css">
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0b132b;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px;
            background-image: 
                radial-gradient(at 0% 0%, rgba(16, 45, 89, 0.6) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(14, 116, 144, 0.3) 0px, transparent 50%);
        }
        .offline-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(16px);
            border-radius: 24px;
            max-width: 580px;
            width: 100%;
            padding: 48px 40px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.6s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .brand-logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, #102d59, #1e40af);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(16, 45, 89, 0.4);
        }
        .brand-logo img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .status-pulse {
            width: 8px;
            height: 8px;
            background-color: #fbbf24;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.7);
            animation: pulse 1.8s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(251, 191, 36, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(251, 191, 36, 0); }
        }
        h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 12px;
            color: #ffffff;
            letter-spacing: -0.02em;
        }
        p.subtitle {
            font-size: 15px;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .info-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 20px;
            text-align: left;
            margin-bottom: 32px;
        }
        .info-box h3 {
            font-size: 14px;
            font-weight: 700;
            color: #38bdf8;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .info-list {
            list-style: none;
        }
        .info-list li {
            font-size: 14px;
            color: #cbd5e1;
            padding: 6px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-list li::before {
            content: "✓";
            color: #34d399;
            font-weight: bold;
        }
        .action-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 14px 28px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 15px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.6);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
            padding: 14px 24px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 15px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .footer-note {
            margin-top: 32px;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="offline-card">
        <div class="brand-logo">
            <img src="/public/assets/images/logo-icon.png" alt="SikapHub Logo" onerror="this.style.display='none'">
        </div>

        <div class="status-badge">
            <span class="status-pulse"></span>
            Offline Mode Active
        </div>

        <h1>You are currently offline</h1>
        <p class="subtitle">
            Don't worry! S.I.K.A.P. Hub is saved locally on your device. You can still navigate through previously loaded pages while offline.
        </p>

        <div class="info-box">
            <h3>Available Offline Features</h3>
            <ul class="info-list">
                <li>Access previously loaded job postings & descriptions</li>
                <li>Review your saved profile information</li>
                <li>Browse cached application history</li>
                <li>Automatic reconnection as soon as network returns</li>
            </ul>
        </div>

        <div class="action-buttons">
            <button type="button" class="btn-primary" onclick="window.sikapCheckConnection()">
                <span>⚡</span> Try Reconnecting
            </button>
            <a href="/" class="btn-secondary">
                Return to Home
            </a>
        </div>

        <p class="footer-note">PESO Guimba Job Matching Portal • Built with Offline PWA Technology</p>
    </div>
</body>
</html>
