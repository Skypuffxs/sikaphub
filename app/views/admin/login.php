<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PESO Admin Portal — S.I.K.A.P. Hub</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    <meta name="description" content="Authorized PESO Admin portal for S.I.K.A.P. Hub Guimba.">

    <link rel="stylesheet" href="/public/assets/css/theme.css">
    <script src="/public/assets/js/tailwind.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0f172a',
                        'primary-hover': '#1e293b',
                        secondary: '#1e3a8a',
                        'app-bg': '#070d1e',
                        surface: '#0f172a',
                        border: '#1e293b'
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at 50% -20%, #1e293b 0%, #070d1e 75%);
            color: #f8fafc;
        }
        .glass-card {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(59, 130, 246, 0.1);
        }
        .input-box:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15), 0 0 20px rgba(59, 130, 246, 0.1);
        }
        .glow-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(140px);
            opacity: 0.35;
            pointer-events: none;
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center relative overflow-x-hidden p-4 sm:p-6">

    <!-- Atmospheric Glow Orbs -->
    <div class="glow-orb w-[500px] h-[500px] bg-blue-600/30 -top-40 -left-40 animate-pulse"></div>
    <div class="glow-orb w-[450px] h-[450px] bg-indigo-600/25 -bottom-40 -right-40"></div>

    <div class="w-full max-w-md glass-card rounded-3xl p-6 sm:p-10 relative z-10 my-6 transition-all duration-300">

        <!-- Header Branding & Badge -->
        <div class="flex flex-col items-center text-center mb-8">
            <div class="relative mb-5 group">
                <div class="absolute -inset-1 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 blur opacity-40 group-hover:opacity-75 transition duration-300"></div>
                <div class="relative w-16 h-16 bg-slate-900 border border-slate-700/80 rounded-2xl flex items-center justify-center shadow-xl">
                    <img src="/public/assets/images/logo-icon.png" alt="SikapHub Logo" class="w-10 h-10 object-contain">
                </div>
            </div>

            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[11px] font-extrabold bg-blue-500/10 border border-blue-400/20 text-blue-400 uppercase tracking-wider mb-3">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-ping"></span>
                PESO Guimba Administration
            </div>

            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">PESO Admin Portal</h1>
            <p class="text-xs text-slate-400 mt-1.5 max-w-xs">Authorized municipal verification & administration login</p>
        </div>

        <!-- Alert Error Message -->
        <?php if (!empty($error)): ?>
            <div class="bg-rose-500/10 border border-rose-500/30 text-rose-300 p-4 rounded-2xl text-xs font-semibold mb-6 flex items-start gap-3 shadow-lg shadow-rose-950/20">
                <svg class="w-5 h-5 text-rose-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div class="flex-1">
                    <span class="font-bold block text-rose-200">Authentication Error</span>
                    <span class="text-rose-300/90 leading-relaxed"><?php echo htmlspecialchars($error); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['timeout'])): ?>
            <div class="bg-amber-500/10 border border-amber-500/30 text-amber-300 p-4 rounded-2xl text-xs font-semibold mb-6 flex items-center gap-3">
                <svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Your administrative session timed out. Please sign in again.</span>
            </div>
        <?php endif; ?>

        <!-- Form Submission -->
        <form action="" method="POST" class="space-y-5">
            <?php echo CSRF::csrfField(); ?>

            <!-- Username Field -->
            <div>
                <label for="admin-username" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2 flex items-center justify-between">
                    <span>Admin Username</span>
                    <span class="text-[10px] text-slate-500 font-normal">Registered Username or Email</span>
                </label>
                <div class="input-box relative flex items-center rounded-2xl border border-slate-700/80 bg-slate-950/70 transition-all">
                    <div class="pl-4 text-slate-400 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <input
                        id="admin-username"
                        type="text"
                        name="username"
                        required
                        autocomplete="username"
                        autofocus
                        placeholder="e.g. peso_admin or admin@guimba.gov.ph"
                        class="w-full px-3.5 py-3.5 bg-transparent text-white placeholder-slate-500 text-sm outline-none"
                    >
                </div>
            </div>

            <!-- Password Field with Toggle -->
            <div>
                <label for="admin-password" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Password</label>
                <div class="input-box relative flex items-center rounded-2xl border border-slate-700/80 bg-slate-950/70 transition-all">
                    <div class="pl-4 text-slate-400 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <input
                        id="admin-password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••••••"
                        class="w-full px-3.5 py-3.5 bg-transparent text-white placeholder-slate-500 text-sm outline-none"
                    >
                    <button type="button" id="toggle-pwd-btn" class="pr-4 text-slate-400 hover:text-slate-200 transition-colors" title="Toggle password visibility">
                        <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button
                type="submit"
                id="admin-login-submit-btn"
                class="w-full relative group overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-3.5 px-4 transition-all duration-300 shadow-lg shadow-blue-600/30 hover:shadow-blue-600/50 text-sm tracking-wide flex items-center justify-center gap-2 mt-2"
            >
                <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                </svg>
                <span>Sign In to PESO Admin Portal</span>
            </button>
        </form>

        <!-- Footer link back to Main Site -->
        <div class="mt-8 pt-6 border-t border-slate-800/80 text-center flex flex-col items-center gap-3">
            <div class="flex items-center gap-2 text-[11px] text-slate-500 font-medium">
                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span>AES-256 Encrypted Session & Audit Trail</span>
            </div>
            <a href="/" class="text-xs font-semibold text-slate-400 hover:text-white transition-colors inline-flex items-center gap-1.5 group">
                <svg class="w-3.5 h-3.5 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Return to S.I.K.A.P. Hub Main Portal
            </a>
        </div>

    </div>

    <!-- Password visibility toggle script -->
    <script>
        document.getElementById('toggle-pwd-btn')?.addEventListener('click', function() {
            const pwdInput = document.getElementById('admin-password');
            const eyeIcon = document.getElementById('eye-icon');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                eyeIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.018 10.018 0 013.682-.821c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m-4.092-4.092a3 3 0 11-4.243-4.243M3 3l18 18"/>`;
            } else {
                pwdInput.type = 'password';
                eyeIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
            }
        });
    </script>

</body>

</html>
