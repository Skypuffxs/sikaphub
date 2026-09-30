<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — SIKAPHUB</title>
    <meta name="description" content="Privacy Policy for SikapHub - Smart Integrated Knowledge & Ability Platform Hub.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #102d59;
            background: #f7fbff;
            font-size: 16px;
            line-height: 1.7;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .container {
            max-width: 1040px;
            margin: 0 auto;
            width: 100%;
            padding: 0 24px;
        }

        /* =========================
           NAVBAR
        ========================= */

        .navbar-wrap {
            background: white;
            border-bottom: 1px solid #e2ebf6;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar {
            height: 84px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 800;
            color: #173b72;
            letter-spacing: -0.5px;
        }

        .logo-icon {
            width: 36px;
            height: 36px;
            background: #1769ff;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .nav-buttons {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .back-link {
            font-size: 15px;
            font-weight: 600;
            color: #4d6380;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: .2s;
        }

        .back-link:hover {
            color: #1769ff;
        }

        .login-btn {
            border: 2px solid #1769ff;
            background: transparent;
            color: #1769ff;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
            display: inline-flex;
            align-items: center;
        }
        .login-btn:hover {
            background: #f4f8ff;
        }

        .signup-btn {
            border: 2px solid transparent;
            background: #1769ff;
            color: white;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 4px 12px rgba(23,105,255,.2);
        }
        .signup-btn:hover {
            background: #0053e6;
            box-shadow: 0 6px 16px rgba(23,105,255,.3);
        }

        /* =========================
           HEADER BANNER
        ========================= */

        .terms-header {
            background: linear-gradient(135deg, #173b72 0%, #0c2448 100%);
            color: white;
            padding: 60px 0;
            border-radius: 20px;
            margin-top: 40px;
            box-shadow: 0 10px 30px rgba(23,59,114,.15);
        }

        .terms-header h1 {
            font-size: 38px;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .terms-header p {
            font-size: 16px;
            color: #c2cede;
            margin-bottom: 20px;
        }

        .meta-badges {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .meta-badge {
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(5px);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: #e4efff;
            border: 1px solid rgba(255,255,255,0.15);
        }

        /* =========================
           CONTENT CARD
        ========================= */

        .content-card {
            background: white;
            border-radius: 20px;
            padding: 50px 60px;
            margin: 35px 0 60px;
            border: 1px solid #e2ebf6;
            box-shadow: 0 4px 20px rgba(16,45,89,.04);
        }

        .section-block {
            margin-bottom: 40px;
        }

        .section-block:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 20px;
            font-weight: 800;
            color: #123566;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-number {
            width: 30px;
            height: 30px;
            background: #e4efff;
            color: #1769ff;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .section-block p {
            color: #4d6380;
            font-size: 16px;
            margin-bottom: 12px;
            line-height: 1.8;
        }

        .section-block p strong {
            color: #123566;
        }

        .terms-list {
            list-style: none;
            padding: 0;
            margin: 14px 0;
        }

        .terms-list li {
            position: relative;
            padding-left: 28px;
            margin-bottom: 12px;
            color: #4d6380;
            font-size: 15px;
            line-height: 1.7;
        }

        .terms-list li::before {
            content: "•";
            position: absolute;
            left: 10px;
            top: 0;
            color: #1769ff;
            font-weight: 800;
            font-size: 18px;
        }

        .note-box {
            background: #f0f6ff;
            border-left: 4px solid #1769ff;
            padding: 20px 24px;
            border-radius: 0 12px 12px 0;
            margin-top: 30px;
        }

        .note-box p {
            color: #173b72;
            font-size: 15px;
            margin: 0;
            font-weight: 500;
        }

        /* =========================
           FOOTER
        ========================= */

        footer {
            background: #071a34;
            color: white;
            padding: 35px 0 25px;
        }

        .footer-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #233753;
        }

        .footer-logo {
            font-weight: 800;
            font-size: 20px;
        }

        .footer-logo small {
            font-size: 13px;
            font-weight: 400;
            color: #a2b4cd;
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            padding-top: 18px;
            font-size: 13px;
            color: #8c9eb7;
        }

        @media(max-width: 768px) {
            .content-card {
                padding: 30px 24px;
            }
            .terms-header h1 {
                font-size: 28px;
            }
            .terms-header {
                padding: 40px 24px;
            }
        }
    </style>
</head>

<body>

<!-- =========================
     NAVBAR
========================= -->

<div class="navbar-wrap">
    <header class="navbar container">

        <a href="/" class="logo" style="display: inline-flex; align-items: center; gap: 10px; text-decoration: none;">
            <img src="/public/assets/images/logo-icon.png" alt="SikapHub" style="height: 38px; width: auto; object-fit: contain;">
            <span style="font-weight: 800; font-size: 1.45rem; letter-spacing: -0.03em; color: #031a3f;">Sikap<span style="background: linear-gradient(135deg, #009cfb 0%, #1769ff 45%, #9035ff 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">hub</span></span>
        </a>

        <div class="nav-buttons">
            <a href="/" class="back-link">
                ← Back to Home
            </a>
            <a href="/login" class="login-btn">
                Log In
            </a>
            <a href="/register" class="signup-btn">
                Sign Up
            </a>
        </div>

    </header>
</div>


<!-- =========================
     MAIN CONTENT
========================= -->

<main class="container">

    <div class="terms-header">
        <div style="padding: 0 40px;">
            <h1>Privacy Policy</h1>
            <p>Smart Integrated Knowledge & Ability Platform Hub (SikapHub)</p>
            <div class="meta-badges">
                <span class="meta-badge">Effective Date: September 23, 2026</span>
                <span class="meta-badge">Last Updated: September 23, 2026</span>
            </div>
        </div>
    </div>

    <div class="content-card">

        <!-- Section 1 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">1</span>
                Acceptance of Privacy Policy
            </h2>
            <p>
                By accessing or using SikapHub (Smart Integrated Knowledge & Ability Platform Hub), you agree to the collection, processing, and storage of your personal data as outlined in this Privacy Policy. If you do not agree with these practices, please refrain from using the platform.
            </p>
        </div>

        <!-- Section 2 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">2</span>
                Information We Collect
            </h2>
            <p>
                To provide job matching and profile building services, SikapHub collects personal information including:
            </p>
            <ul class="terms-list">
                <li><strong>Account & Contact Data:</strong> Email address, name, phone number, and location details.</li>
                <li><strong>Profile & Resume Data:</strong> Work experience, education history, skills, certifications, and uploaded resume documents.</li>
                <li><strong>Application & Activity Logs:</strong> Job application tracking history, interactions with employer postings, and session authentication logs.</li>
            </ul>
        </div>

        <!-- Section 3 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">3</span>
                How We Use Your Information
            </h2>
            <p>User information collected on SikapHub is utilized strictly to:</p>
            <ul class="terms-list">
                <li>Facilitate AI-powered job matching and skill recommendation algorithms.</li>
                <li>Automatically extract skills and build candidate profiles via AI Resume/CV Parsing.</li>
                <li>Connect job seekers with verified employers and PESO employment programs.</li>
                <li>Send essential security authentication one-time codes (OTP) and application status notifications.</li>
            </ul>
        </div>

        <!-- Section 4 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">4</span>
                Data Sharing and Disclosure
            </h2>
            <p>
                SikapHub respects your data privacy and does not sell or rent user information to third parties. Information is shared only with:
            </p>
            <ul class="terms-list">
                <li><strong>Verified Employers:</strong> When a job seeker applies for a specific job posting or sets their profile visibility to public.</li>
                <li><strong>PESO Administrators:</strong> For official local employment reporting and candidate verification.</li>
            </ul>
        </div>

        <!-- Section 5 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">5</span>
                Data Security and Protection
            </h2>
            <p>
                We implement robust technical and organizational security measures—including encrypted database storage, secure HTTP-only sessions, and CSRF protection—to prevent unauthorized access, disclosure, or alteration of personal data.
            </p>
        </div>

        <!-- Section 6 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">6</span>
                User Rights and Control
            </h2>
            <p>
                Users maintain full control over their personal data on SikapHub. You have the right to update your profile details, edit or delete uploaded resumes, adjust profile visibility settings, or request account termination at any time.
            </p>
        </div>

        <!-- Section 7 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">7</span>
                Cookies and Local Storage
            </h2>
            <p>
                SikapHub utilizes essential session cookies and local storage exclusively for secure user authentication, CSRF token validation, and maintaining active user sessions.
            </p>
        </div>

        <!-- Section 8 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">8</span>
                Modifications to Privacy Policy
            </h2>
            <p>
                SikapHub reserves the right to amend or update this Privacy Policy as platform features or privacy regulations evolve. Continued use of the platform following updates constitutes formal acceptance of the revised policy.
            </p>
        </div>

        <!-- Note Box -->
        <div class="note-box">
            <p>
                <strong>Note:</strong> For inquiries, privacy requests, or reporting issues regarding this Privacy Policy, please contact the SikapHub administrative team through the platform's official support channel.
            </p>
        </div>

    </div>

</main>


<!-- =========================
     FOOTER
========================= -->

<footer>
    <div class="container">
        <div class="footer-main">
            <div class="footer-logo">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 30px; height: 30px; background: #1769ff; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; color: white;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3.5" y="3.5" width="17" height="17" rx="3" ry="3"></rect>
                            <rect x="8.5" y="8.5" width="7" height="7" rx="1.5" ry="1.5"></rect>
                        </svg>
                    </div>
                    <span>SIKAPHUB</span>
                </div>
                <small>Smarter Jobs. Brighter Futures.</small>
            </div>
            <div style="font-size: 13px; color: #c2cede;">
                <a href="/terms" style="color: #c2cede;">Terms of Service</a> &nbsp;&nbsp;|&nbsp;&nbsp;
                <a href="/privacy" style="color: #1769ff; font-weight: 600;">Privacy Policy</a> &nbsp;&nbsp;|&nbsp;&nbsp;
                <a href="/" style="color: #c2cede;">Home</a>
            </div>
        </div>

        <div class="footer-bottom">
            <span>© 2026 SIKAPHUB. All rights reserved.</span>
            <span>Terms of Service &nbsp;&nbsp; Privacy Policy &nbsp;&nbsp; Contact</span>
        </div>
    </div>
</footer>

</body>
</html>
