<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service — SIKAPHUB</title>
    <meta name="description" content="Terms of Service for SikapHub - Smart Integrated Knowledge & Ability Platform Hub.">

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

        <a href="/sikaphub/" class="logo" style="display: inline-flex; align-items: center; gap: 10px; text-decoration: none;">
            <img src="/sikaphub/public/assets/images/logo-icon.png" alt="SikapHub" style="height: 38px; width: auto; object-fit: contain;">
            <span style="font-weight: 800; font-size: 1.45rem; letter-spacing: -0.03em; color: #031a3f;">Sikap<span style="background: linear-gradient(135deg, #009cfb 0%, #1769ff 45%, #9035ff 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">hub</span></span>
        </a>

        <div class="nav-buttons">
            <a href="/sikaphub/" class="back-link">
                ← Back to Home
            </a>
            <a href="/sikaphub/login" class="login-btn">
                Log In
            </a>
            <a href="/sikaphub/register" class="signup-btn">
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
            <h1>Terms of Service</h1>
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
                Acceptance of Terms
            </h2>
            <p>
                By accessing or using SikapHub (Smart Integrated Knowledge & Ability Platform Hub), you agree to comply with and be bound by these Terms of Service. If you do not agree to these terms, please refrain from using the platform, creating an account, or accessing its services.
            </p>
        </div>

        <!-- Section 2 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">2</span>
                Description of Service
            </h2>
            <p>
                SikapHub is an integrated job portal and career matching platform designed to connect job seekers with employment opportunities, employers, and professional development resources. The platform provides tools for resume creation, job application tracking, profile management, and employer recruitment listings.
            </p>
        </div>

        <!-- Section 3 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">3</span>
                User Accounts and Registration
            </h2>
            <p>
                <strong>Eligibility:</strong> Users must be at least 18 years of age or possess legal parental/guardian consent to register and use the platform.
            </p>
            <p>
                <strong>Account Security:</strong> Users are responsible for maintaining the confidentiality of their login credentials and account information. SikapHub is not liable for any loss or damage arising from unauthorized account access.
            </p>
            <p>
                <strong>Accuracy of Information:</strong> Users agree to provide accurate, current, and complete information during registration and to update their profiles promptly if details change.
            </p>
        </div>

        <!-- Section 4 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">4</span>
                User Conduct and Responsibilities
            </h2>
            <p>Users agree not to use SikapHub to:</p>
            <ul class="terms-list">
                <li>Post false, misleading, fraudulent, or defamatory content, job listings, or resumes.</li>
                <li>Transmit any malicious code, viruses, or security exploits designed to disrupt or compromise system performance and data integrity.</li>
                <li>Harass, threaten, or defraud other users, employers, or platform administrators.</li>
                <li>Scrape, harvest, or extract data from the platform without explicit authorization.</li>
            </ul>
        </div>

        <!-- Section 5 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">5</span>
                Intellectual Property Rights
            </h2>
            <p>
                All source code, UI/UX designs, database architectures, logos, and platform documentation associated with SikapHub are the intellectual property of its developers and proponents. Unauthorized reproduction, distribution, or commercial exploitation of platform assets is strictly prohibited.
            </p>
        </div>

        <!-- Section 6 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">6</span>
                Privacy and Data Protection
            </h2>
            <p>
                SikapHub collects and processes personal data in accordance with applicable data privacy laws. User information—such as contact details, employment history, and application logs—is utilized solely to facilitate job matching, communication, and platform functionality.
            </p>
        </div>

        <!-- Section 7 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">7</span>
                Limitation of Liability
            </h2>
            <p>
                SikapHub is provided on an "as is" and "as available" basis. The platform proponents and administrators disclaim all warranties, express or implied. SikapHub shall not be held liable for any direct, indirect, incidental, or consequential damages resulting from employment outcomes, technical downtime, or data loss.
            </p>
        </div>

        <!-- Section 8 -->
        <div class="section-block">
            <h2 class="section-title">
                <span class="section-number">8</span>
                Modifications to Terms
            </h2>
            <p>
                SikapHub reserves the right to modify, amend, or update these Terms of Service at any time. Continued use of the platform following the posting of updated terms constitutes formal acceptance of those changes.
            </p>
        </div>

        <!-- Note Box -->
        <div class="note-box">
            <p>
                <strong>Note:</strong> For inquiries, support, or reporting violations regarding these Terms of Service, please contact the SikapHub administrative team through the platform's official support channel.
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
                <a href="/sikaphub/terms" style="color: #1769ff; font-weight: 600;">Terms of Service</a> &nbsp;&nbsp;|&nbsp;&nbsp;
                <a href="/sikaphub/privacy" style="color: #c2cede;">Privacy Policy</a> &nbsp;&nbsp;|&nbsp;&nbsp;
                <a href="/sikaphub/" style="color: #c2cede;">Home</a>
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
