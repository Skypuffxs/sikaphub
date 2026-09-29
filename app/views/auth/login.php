<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — S.I.K.A.P. Hub</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    <meta name="description" content="Sign in to S.I.K.A.P. Hub with a one-time code sent to your email.">

    <link rel="stylesheet" href="/sikaphub/public/assets/css/theme.css">
    <script src="/sikaphub/public/assets/js/tailwind.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1769ff',
                        'primary-hover': '#0053e6',
                        secondary: '#173b72',
                        'app-bg': '#f7fbff',
                        surface: '#ffffff',
                        border: '#e2ebf6',
                        slate: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#1e293b'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--background); color: var(--text-primary); }
        .input-field:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(67, 56, 202, 0.12); }
        .blob { position: absolute; border-radius: 50%; filter: blur(120px); opacity: 0.35; }
    </style>
</head>

<body class="bg-app-bg text-slate-800">

    <div class="grid grid-cols-1 lg:grid-cols-2 min-h-screen">

        <!-- ===== LEFT COLUMN: FORM ===== -->
        <div class="flex flex-col justify-center px-8 sm:px-16 md:px-24 lg:px-16 xl:px-24 relative py-16">

            <a href="/sikaphub/"
               class="absolute top-8 left-8 flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900 transition-colors font-medium group">
                <svg class="w-4 h-4 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Home
            </a>

            <a href="/sikaphub/" class="flex items-center gap-3 mb-12">
                <img src="/sikaphub/public/assets/images/logo-icon.png" alt="SikapHub" class="w-10 h-10 object-contain flex-shrink-0">
                <span class="font-extrabold text-3xl tracking-tight text-[#031a3f]">Sikap<span class="bg-gradient-to-r from-[#009cfb] via-[#1769ff] to-[#9035ff] bg-clip-text text-transparent">hub</span></span>
            </a>

            <h2 class="text-3xl font-extrabold text-slate-900">Sign in</h2>
            <p class="text-slate-500 mt-2">Enter your email and we'll send you a one-time code. No password needed.</p>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-lg text-sm font-semibold mb-2 mt-6 flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['timeout'])): ?>
                <div class="bg-amber-50 text-amber-700 p-4 rounded-lg text-sm font-semibold mb-2 mt-6">
                    Your session timed out. Sign in again to continue.
                </div>
            <?php endif; ?>

            <form action="/sikaphub/auth/otp/request" method="POST" class="mt-6">
                <?php echo CSRF::csrfField(); ?>

                <label for="email" class="block text-sm font-semibold text-slate-700 mb-2">Email address</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    autofocus
                    placeholder="you@example.com"
                    class="input-field w-full px-4 py-3 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none bg-slate-50 focus:bg-white text-slate-900 placeholder-slate-400"
                >

                <button
                    type="submit"
                    id="login-submit-btn"
                    class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 rounded-xl transition-colors mt-6 text-sm tracking-wide"
                >
                    Send me a code
                </button>
            </form>

            <p class="text-xs text-slate-500 mt-6 text-center leading-relaxed">
                By continuing with any of the options above, you agree to our
                <a href="/sikaphub/terms" class="text-primary font-medium underline hover:text-primary-hover">Terms of Service</a>
                and have read our
                <a href="/sikaphub/privacy" class="text-primary font-medium underline hover:text-primary-hover">Privacy Policy</a>.
            </p>

            <p class="text-xs text-slate-400 mt-3 text-center leading-relaxed">
                New here? Entering your email creates your account automatically once you verify the code.
            </p>

        </div>

        <!-- ===== RIGHT COLUMN: BRANDING ===== -->
        <div class="hidden lg:flex relative bg-slate-900 overflow-hidden items-center justify-center">
            <div class="blob w-96 h-96 bg-primary top-[-80px] left-[-60px]"></div>
            <div class="blob w-80 h-80 bg-secondary bottom-[-60px] right-[-40px]"></div>
            <div class="blob w-64 h-64 bg-primary bottom-[20%] left-[10%]" style="opacity: 0.25;"></div>
            <div class="blob w-48 h-48 bg-secondary top-[30%] right-[5%]" style="opacity: 0.2;"></div>

            <div class="backdrop-blur-md bg-white/10 border border-white/20 p-12 rounded-3xl max-w-md relative z-10 text-white shadow-2xl mx-8">
                <div class="mb-6">
                    <svg class="w-10 h-10 text-white/40" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
                    </svg>
                </div>
                <p class="text-xl font-semibold leading-relaxed text-white/90 mb-8">
                    Connecting Guimba's talent with verified municipal enterprises. Build your future here.
                </p>
                <div class="border-t border-white/20 pt-8">
                    <p class="text-sm font-semibold text-white">PESO Guimba, Nueva Ecija</p>
                    <p class="text-xs text-white/50">Public Employment Service Office</p>
                </div>
            </div>
        </div>

    </div>

</body>

</html>
