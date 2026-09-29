<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIKAPHUB - Smart Job Portal</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>

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
            background: #fff;
            font-size: 18px;
            line-height: 1.6;
            position: relative;
        }



        a {
            text-decoration: none;
            color: inherit;
        }

        button {
            font-family: inherit;
            cursor: pointer;
        }

        .container {
            max-width: 1380px;
            margin: 0 auto;
            width: 100%;
            padding: 0 32px;
        }

        /* =========================
           NAVBAR
        ========================= */

        .navbar-wrap {
            background: white;
            border-bottom: 1px solid #eef2f7;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar {
            height: 94px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 30px;
            font-weight: 800;
            color: #173b72;
            letter-spacing: -0.5px;
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            background: #1769ff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 40px;
            margin-left: auto;
            margin-right: 45px;
        }

        .nav-links a {
            font-size: 17px;
            font-weight: 500;
            color: #4d6380;
            transition: .2s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #1769ff;
            font-weight: 600;
        }

        .nav-buttons {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .search-icon {
            font-size: 24px;
            margin-right: 8px;
            color: #173b72;
        }

        .login-btn {
            border: 2px solid #1769ff;
            background: white;
            color: #1769ff;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: .2s;
        }
        .login-btn:hover {
            background: #f4f8ff;
        }

        .signup-btn {
            border: 2px solid transparent;
            background: #1769ff;
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            box-shadow: 0 6px 20px rgba(23,105,255,.25);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: .2s;
        }
        .signup-btn:hover {
            background: #0053e6;
            box-shadow: 0 8px 24px rgba(23,105,255,.35);
        }

        /* =========================
           HERO
        ========================= */

        .hero-wrap {
            position: relative;
            background-image: url('/sikaphub/public/assets/images/bg-landing.png');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            padding: 100px 0 110px;
            overflow: hidden;
        }

        .hero-wrap::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.45) 0%, rgba(255, 255, 255, 0.65) 60%, #ffffff 100%);
            z-index: 1;
            pointer-events: none;
        }

        .hero {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 50px;
        }

        .hero-content {
            flex: 1;
            max-width: 700px;
            position: relative;
            z-index: 2;
        }

        .eyebrow {
            display: inline-block;
            background: #e4efff;
            color: #1769ff;
            padding: 10px 20px;
            border-radius: 24px;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 24px;
            letter-spacing: 0.3px;
        }

        .hero h1 {
            font-size: 64px;
            line-height: 1.1;
            letter-spacing: -2px;
            color: #123566;
            margin-bottom: 26px;
            font-weight: 800;
        }

        .hero h1 span {
            color: #1769ff;
        }

        .hero-description {
            max-width: 640px;
            color: #556c88;
            line-height: 1.7;
            font-size: 21px;
            margin-bottom: 36px;
        }

        /* SEARCH */

        .search-box {
            width: 100%;
            max-width: 660px;
            min-height: 74px;
            background: white;
            border-radius: 14px;
            display: flex;
            align-items: center;
            padding: 8px;
            box-shadow: 0 12px 35px rgba(30,80,140,.12);
            border: 1px solid #e2ebf6;
        }

        .search-field {
            flex: 1;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 0 20px;
            border-right: 1px solid #e6ebf2;
        }

        .search-field input {
            border: none;
            outline: none;
            width: 100%;
            font-family: inherit;
            font-size: 17px;
            color: #102d59;
        }

        .search-field span {
            color: #1769ff;
            font-size: 20px;
        }

        .search-button {
            height: 58px;
            border: none;
            background: #1769ff;
            color: white;
            padding: 0 34px;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 700;
            white-space: nowrap;
            transition: .2s;
        }

        .search-button:hover {
            background: #0053e6;
        }

        .popular {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 22px;
            font-size: 16px;
            color: #617895;
            flex-wrap: wrap;
        }

        .popular-tag {
            background: #e9f2ff;
            padding: 8px 18px;
            border-radius: 24px;
            color: #2b558c;
            font-weight: 600;
            font-size: 15px;
        }

        /* HERO VISUAL */

        .hero-visual {
            position: relative;
            width: 540px;
            height: 500px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
        }

        .circle {
            width: 440px;
            height: 440px;
            border-radius: 50%;
            background: linear-gradient(145deg, #d8e8ff, #edf5ff);
            position: absolute;
        }

        .person {
            width: 350px;
            height: 440px;
            border-radius: 175px 175px 28px 28px;
            background: linear-gradient(145deg, #152b47, #07192d);
            position: absolute;
            bottom: 10px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            color: white;
            font-size: 90px;
            padding-bottom: 45px;
            box-shadow: 0 20px 45px rgba(10,30,60,.18);
            z-index: 2;
            overflow: hidden;
        }

        .person img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center bottom;
        }

        .person::before {
            content: "👨🏻‍💻";
            position: absolute;
            top: 25px;
            font-size: 115px;
            z-index: -1;
        }

        /* MATCH CARDS (Floating in front with controlled opacity) */

        .match-card {
            position: absolute;
            right: -25px;
            background: rgba(255, 255, 255, 0.78);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            width: 270px;
            padding: 16px 18px;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(26,68,120,.12);
            border: 1px solid rgba(255, 255, 255, 0.7);
            z-index: 10;
            opacity: 0.82;
            transition: all 0.25s ease;
        }

        .match-card:hover {
            opacity: 0.98;
            z-index: 20;
            background: rgba(255, 255, 255, 0.94);
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(26,68,120,.2);
        }

        .match-card.ai {
            top: 15px;
            right: 0px;
            width: 240px;
            font-size: 14px;
            z-index: 11;
        }

        .match-card.job1 {
            top: 125px;
            right: -30px;
            z-index: 12;
        }

        .match-card.job2 {
            top: 240px;
            right: -15px;
            z-index: 13;
        }

        .match-card.job3 {
            top: 355px;
            right: -35px;
            z-index: 14;
        }

        .match-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #123566;
        }

        .match-company {
            font-size: 14px;
            color: #6a7d97;
        }

        .match-score {
            float: right;
            color: #1c9c67;
            background: #e7f8ef;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
        }

        /* =========================
           FEATURES
        ========================= */

        .features-wrap {
            padding: 95px 0;
            background: white;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 45px;
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            background: #edf5ff;
            color: #1769ff;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            font-size: 30px;
            margin-bottom: 24px;
        }

        .feature h3 {
            font-size: 22px;
            margin-bottom: 12px;
            font-weight: 700;
            color: #123566;
        }

        .feature p {
            font-size: 16px;
            line-height: 1.7;
            color: #5d7390;
        }

        /* =========================
           HOW IT WORKS
        ========================= */

        .how-wrap {
            background: #f5faff;
            padding: 105px 0;
        }

        .how-container {
            display: flex;
            align-items: flex-start;
            gap: 60px;
        }

        .how-intro {
            width: 32%;
            flex-shrink: 0;
        }

        .how-intro h2 {
            font-size: 44px;
            margin-bottom: 20px;
            font-weight: 800;
            color: #123566;
            line-height: 1.15;
        }

        .how-intro p {
            color: #5d7390;
            font-size: 18px;
            line-height: 1.7;
        }

        .steps {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .step {
            background: white;
            padding: 28px 24px;
            min-height: 200px;
            border: 1px solid #e3ecf6;
            border-radius: 14px;
            position: relative;
            box-shadow: 0 6px 20px rgba(20,50,90,.04);
        }

        .step-number {
            display: inline-flex;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #eaf2ff;
            color: #1769ff;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .step h4 {
            font-size: 18px;
            margin-bottom: 10px;
            font-weight: 700;
            color: #123566;
        }

        .step p {
            font-size: 15px;
            color: #6a7f9a;
            line-height: 1.6;
        }

        /* =========================
           COMPANIES
        ========================= */

        .companies-wrap {
            padding: 95px 0;
            text-align: center;
            background: white;
        }

        .companies h2 {
            font-size: 42px;
            margin-bottom: 16px;
            font-weight: 800;
            color: #123566;
        }

        .companies p {
            color: #5d7390;
            font-size: 18px;
            max-width: 680px;
            margin: 0 auto;
        }

        .company-logos {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #627794;
            font-size: 26px;
            font-weight: 800;
            opacity: .85;
            flex-wrap: wrap;
            gap: 25px;
        }

        /* =========================
           JOBS
        ========================= */

        .jobs-wrap {
            background: #f6faff;
            padding: 105px 0;
        }

        .jobs-section {
            display: flex;
            align-items: flex-start;
            gap: 60px;
        }

        .jobs-intro {
            width: 30%;
            flex-shrink: 0;
        }

        .jobs-intro h2 {
            font-size: 44px;
            margin: 14px 0 20px;
            font-weight: 800;
            color: #123566;
            line-height: 1.15;
        }

        .jobs-intro p {
            font-size: 17px;
            color: #5d7390;
            line-height: 1.7;
        }

        .view-jobs {
            display: inline-block;
            margin-top: 24px;
            color: #1769ff;
            font-size: 17px;
            font-weight: 700;
            transition: .2s;
        }
        .view-jobs:hover {
            color: #004ecc;
        }

        .job-list {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .job-card {
            background: white;
            border: 1px solid #e3ebf5;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 6px 20px rgba(20,50,90,.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .job-company {
            font-size: 14px;
            color: #6a7d97;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .job-card h3 {
            font-size: 22px;
            margin-bottom: 16px;
            color: #123566;
            font-weight: 700;
        }

        .job-info {
            color: #5d7390;
            font-size: 15px;
            line-height: 2;
            margin-bottom: 20px;
        }

        .apply-btn {
            width: 100%;
            border: none;
            background: #1769ff;
            color: white;
            padding: 14px;
            border-radius: 9px;
            font-size: 16px;
            font-weight: 700;
            transition: .2s;
        }
        .apply-btn:hover {
            background: #0053e6;
        }

        /* =========================
           FOOTER
        ========================= */

        footer {
            background: #071a34;
            color: white;
            padding: 60px 0 30px;
        }

        .footer-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 35px;
            border-bottom: 1px solid #233753;
        }

        .footer-logo {
            font-weight: 800;
            font-size: 26px;
        }

        .footer-logo small {
            font-size: 15px;
            font-weight: 400;
            color: #a2b4cd;
        }

        .footer-nav {
            display: flex;
            gap: 36px;
            font-size: 16px;
            color: #c2cede;
        }

        .socials {
            display: flex;
            gap: 22px;
            font-size: 20px;
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            padding-top: 25px;
            font-size: 15px;
            color: #8c9eb7;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media(max-width: 1200px) {

            .nav-links {
                gap: 25px;
                margin-right: 20px;
            }

            .hero h1 {
                font-size: 52px;
            }

            .steps {
                grid-template-columns: repeat(2, 1fr);
            }

            .job-list {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width: 980px) {

            .nav-links {
                display: none;
            }

            .hero {
                flex-direction: column;
            }

            .hero-content {
                max-width: 100%;
            }

            .hero-visual {
                width: 100%;
                margin-top: 20px;
            }

            .features {
                grid-template-columns: repeat(2, 1fr);
            }

            .how-container {
                flex-direction: column;
            }

            .how-intro,
            .steps {
                width: 100%;
            }

            .jobs-section {
                flex-direction: column;
            }

            .jobs-intro {
                width: 100%;
            }
        }

        @media(max-width: 680px) {

            .nav-buttons .search-icon {
                display: none;
            }

            .hero-wrap {
                padding: 50px 0;
            }

            .hero h1 {
                font-size: 40px;
            }

            .hero-description {
                font-size: 18px;
            }

            .search-box {
                min-height: auto;
                flex-direction: column;
                padding: 12px;
            }

            .search-field {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #e6ebf2;
                padding: 14px;
            }

            .search-button {
                width: 100%;
                margin-top: 12px;
                height: 52px;
            }

            .popular {
                flex-wrap: wrap;
            }

            .hero-visual {
                transform: scale(.85);
                margin-top: -30px;
            }

            .features {
                grid-template-columns: 1fr;
            }

            .steps {
                grid-template-columns: 1fr;
            }

            .company-logos {
                gap: 25px;
                justify-content: center;
            }

            .footer-main,
            .footer-bottom {
                flex-direction: column;
                gap: 25px;
                align-items: flex-start;
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
     HERO
========================= -->

<div class="hero-wrap">
    <section class="hero container">

        <div class="hero-content">

            <div class="eyebrow">
                Smarter Jobs. Brighter Futures.
            </div>

            <h1>
                Find Your Next<br>
                Opportunity with <span>SIKAPHUB</span>
            </h1>

            <p class="hero-description">
                SIKAPHUB is a next-generation job portal powered by AI,
                helping you find the right job, faster. Get matched with
                opportunities that fit your skills, goals, and future.
            </p>


            <div style="margin-top: 30px;">
                <a href="/sikaphub/login" class="signup-btn" style="padding: 16px 36px; font-size: 18px;">
                    Get Started →
                </a>
            </div>

        </div>


        <!-- HERO IMAGE / AI MATCHING -->
        <div class="hero-visual">

            <div class="circle"></div>

            <div class="person">
                <img src="/sikaphub/public/assets/images/aiimg.png" alt="Hero Illustration" onerror="this.style.display='none';">
            </div>

            <div class="match-card ai">
                ✦ <strong>AI-Powered Matching</strong>
                <br>
                <small style="color: #6a7d97;">The right job, for you.</small>
            </div>

            <div class="match-card job1">

                <span class="match-score">Match 98%</span>

                <div class="match-title">
                    Software Developer
                </div>

                <div class="match-company">
                    TechCorp Solutions
                </div>

                <small style="color: #6a7d97;">⌖ Remote</small>

            </div>


            <div class="match-card job2">

                <span class="match-score">Match 92%</span>

                <div class="match-title">
                    IT Support Specialist
                </div>

                <div class="match-company">
                    BrightPath Inc.
                </div>

                <small style="color: #6a7d97;">⌖ On-site</small>

            </div>


            <div class="match-card job3">

                <span class="match-score">Match 88%</span>

                <div class="match-title">
                    Data Analyst
                </div>

                <div class="match-company">
                    NextGen Analytics
                </div>

                <small style="color: #6a7d97;">⌖ Hybrid</small>

            </div>

        </div>

    </section>
</div>


<!-- =========================
     FEATURES
========================= -->

<div class="features-wrap" id="features">
    <section class="features container">

        <div class="feature">

            <div class="feature-icon">◎</div>

            <h3>AI-Powered Matching</h3>

            <p>
                Our smart algorithm connects you with jobs
                that match your skills, experience, and goals.
            </p>

        </div>


        <div class="feature">

            <div class="feature-icon">ϟ</div>

            <h3>Easy & Fast Apply</h3>

            <p>
                Apply in just a few clicks and track your
                progress in real time.
            </p>

        </div>


        <div class="feature">

            <div class="feature-icon">📑</div>

            <h3>AI Resume/CV Reader for Profile Building</h3>

            <p>
                Upload your resume and let our intelligent parser automatically
                extract your skills, education, and work experience.
            </p>

        </div>


        <div class="feature">

            <div class="feature-icon">▥</div>

            <h3>Career Growth</h3>

            <p>
                Get personalized recommendations, resources,
                and career tips to help you succeed.
            </p>

        </div>

    </section>
</div>


<!-- =========================
     HOW IT WORKS
========================= -->

<div class="how-wrap" id="how-it-works">
    <section class="how-section container">

        <div class="how-container">

            <div class="how-intro">

                <div class="eyebrow">
                    Simple Steps. Big Opportunities.
                </div>

                <h2>How It Works</h2>

                <p>
                    Getting your dream job is easy.
                    Just follow these simple steps and
                    let SIKAPHUB do the rest.
                </p>

            </div>


            <div class="steps">

                <div class="step">

                    <div class="step-number">1</div>

                    <h4>Create Your Profile</h4>

                    <p>
                        Showcase your skills,
                        experience, and goals.
                    </p>

                </div>


                <div class="step">

                    <div class="step-number">2</div>

                    <h4>Get Matched</h4>

                    <p>
                        Our AI finds the best jobs
                        for you.
                    </p>

                </div>


                <div class="step">

                    <div class="step-number">3</div>

                    <h4>Apply</h4>

                    <p>
                        Submit your application
                        in just a few clicks.
                    </p>

                </div>


                <div class="step">

                    <div class="step-number">4</div>

                    <h4>Get Hired</h4>

                    <p>
                        Start your next chapter
                        with confidence.
                    </p>

                </div>

            </div>

        </div>

    </section>
</div>


<!-- =========================
     COMPANIES
========================= -->

<div class="companies-wrap">
    <section class="companies container">

        <div class="eyebrow">
            Trusted by Great Companies
        </div>

        <h2>
            Join Thousands of Job Seekers
        </h2>

        <p>
            Top companies are already hiring on SIKAPHUB.
            Be part of a growing community.
        </p>


        <div class="company-logos">

            <span>Google</span>
            <span>Microsoft</span>
            <span>Apple</span>
            <span>accenture</span>
            <span>Meta</span>
            <span>amazon</span>
            <span>BPI</span>
            <span>Globe</span>

        </div>

    </section>
</div>


<!-- =========================
     FEATURED JOBS
========================= -->

<div class="jobs-wrap">
    <section class="jobs-section container">

        <div class="jobs-intro">

            <div class="eyebrow">
                Featured Jobs
            </div>

            <h2>
                Latest Opportunities
            </h2>

            <p>
                Explore the newest job openings from top
                companies and start your journey today.
            </p>

            <a href="/sikaphub/login" class="view-jobs">
                View all jobs →
            </a>

        </div>


        <div class="job-list">
            <?php if (!empty($featuredJobs)): ?>
                <?php foreach ($featuredJobs as $job): ?>
                    <div class="job-card">
                        <div>
                            <div class="job-company">
                                <?= htmlspecialchars($job['company_name'] ?? 'Verified Employer') ?>
                            </div>

                            <h3>
                                <?= htmlspecialchars($job['job_title'] ?? 'Open Position') ?>
                            </h3>

                            <div class="job-info">
                                ⌖ <?= htmlspecialchars($job['work_arrangement'] ?? 'On-site') ?>
                                <?php if (!empty($job['municipality_name'])): ?>
                                    (<?= htmlspecialchars($job['municipality_name']) ?>)
                                <?php endif; ?><br>
                                ◷ <?= htmlspecialchars($job['employment_type'] ?? 'Full-time') ?><br>
                                <?= !empty($job['salary_range']) ? htmlspecialchars($job['salary_range']) : 'Competitive Salary' ?>
                            </div>
                        </div>

                        <button class="apply-btn">
                            Apply Now
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="job-card">
                    <div>
                        <div class="job-company">Guimba AgriCorp</div>
                        <h3>Auditor</h3>
                        <div class="job-info">⌖ On-site (Guimba)<br>◷ Full-time<br>Competitive Salary</div>
                    </div>
                    <button class="apply-btn">Apply Now</button>
                </div>
                <div class="job-card">
                    <div>
                        <div class="job-company">Cabanatuan Tech Solutions</div>
                        <h3>Customer Service Representative</h3>
                        <div class="job-info">⌖ On-site (Cabanatuan)<br>◷ Full-time<br>Competitive Salary</div>
                    </div>
                    <button class="apply-btn">Apply Now</button>
                </div>
                <div class="job-card">
                    <div>
                        <div class="job-company">Cabanatuan Tech Solutions</div>
                        <h3>Web Developer</h3>
                        <div class="job-info">⌖ On-site (Cabanatuan)<br>◷ Full-time<br>Competitive Salary</div>
                    </div>
                    <button class="apply-btn">Apply Now</button>
                </div>
            <?php endif; ?>
        </div>

    </section>
</div>


<!-- =========================
     FOOTER
========================= -->

<footer>

    <div class="container">
        <div class="footer-main">

            <div class="footer-logo">
                <a href="/sikaphub/" style="display: inline-flex; align-items: center; gap: 10px; text-decoration: none;">
                    <img src="/sikaphub/public/assets/images/logo-icon.png" alt="SikapHub" style="height: 38px; width: auto; object-fit: contain;">
                    <span style="font-weight: 800; font-size: 1.45rem; letter-spacing: -0.03em; color: #ffffff;">Sikap<span style="background: linear-gradient(135deg, #009cfb 0%, #1769ff 45%, #9035ff 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">hub</span></span>
                </a>
                <small>Smarter Jobs. Brighter Futures.</small>
            </div>

            <div class="footer-nav">
                <a href="/sikaphub/">Home</a>
                <a href="/sikaphub/login">Jobs</a>
                <a href="/sikaphub/login">Employers</a>
                <a href="#how-it-works">About</a>
                <a href="#features">Resources</a>
            </div>

            <div class="socials">
                <span>f</span>
                <span>𝕏</span>
                <span>in</span>
                <span>◎</span>
                <span>▶</span>
            </div>

        </div>


        <div class="footer-bottom">

            <span>
                © 2026 SIKAPHUB. All rights reserved.
            </span>

            <span>
                <a href="/sikaphub/terms" style="color: inherit; text-decoration: underline;">Terms of Service</a> &nbsp;&nbsp;
                <a href="/sikaphub/privacy" style="color: inherit; text-decoration: underline;">Privacy Policy</a> &nbsp;&nbsp;
                Contact
            </span>

        </div>
    </div>

</footer>


<script>

function searchJobs() {

    const job = document.getElementById("jobSearch").value;
    const location = document.getElementById("location").value;

    if (job === "" && location === "") {

        alert("Please enter a job title, skill, company, or location.");

        return;
    }

    let message = "Searching for ";

    if (job !== "") {
        message += '"' + job + '"';
    }

    if (location !== "") {
        message += " in " + location;
    }

    alert(message + ". Redirecting to login...");
    window.location.href = "/sikaphub/login";

}


document.querySelectorAll(".apply-btn").forEach(button => {

    button.addEventListener("click", function() {

        alert("Please log in or create a SIKAPHUB account to apply.");
        window.location.href = "/sikaphub/login";

    });

});


</script>

</body>
</html>
