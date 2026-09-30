<!DOCTYPE html>
<html lang="en">

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
                        'app-bg': '#0f172a',
                        surface: '#1e293b',
                        border: '#334155',
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
                            900: '#0f172a'
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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0b1329; color: #f8fafc; }
        .input-field:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2); }
        .blob { position: absolute; border-radius: 50%; filter: blur(120px); opacity: 0.25; }
    </style>
</head>

<body class="bg-[#0b1329] text-slate-100 min-h-screen flex items-center justify-center relative overflow-hidden">

    <!-- Background decorative blur blobs -->
    <div class="blob w-96 h-96 bg-blue-600 top-[-100px] left-[-100px]"></div>
    <div class="blob w-96 h-96 bg-indigo-600 bottom-[-100px] right-[-100px]"></div>

    <div class="w-full max-w-md p-8 sm:p-10 bg-slate-900/90 border border-slate-800/80 backdrop-blur-xl rounded-3xl shadow-2xl relative z-10 my-8">

        <!-- Top branding logo & badge -->
        <div class="flex flex-col items-center text-center mb-8">
            <div class="w-16 h-16 bg-blue-950/60 border border-blue-500/30 rounded-2xl flex items-center justify-center mb-4 shadow-inner">
                <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-500/10 border border-blue-400/20 text-blue-400 mb-2 uppercase tracking-wider">
                PESO Guimba Administration
            </span>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">PESO Admin Portal</h1>
            <p class="text-xs text-slate-400 mt-1">Authorized personnel login for municipal verification & oversight</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl text-xs font-semibold mb-6 flex items-start gap-2.5">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['timeout'])): ?>
            <div class="bg-amber-500/10 border border-amber-500/30 text-amber-400 p-4 rounded-xl text-xs font-semibold mb-6">
                Your administrative session timed out. Please sign in again.
            </div>
        <?php endif; ?>

        <form action="/admin/login" method="POST" class="space-y-5">
            <?php echo CSRF::csrfField(); ?>

            <div>
                <label for="admin-username" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Admin Username</label>
                <input
                    id="admin-username"
                    type="text"
                    name="username"
                    required
                    autocomplete="username"
                    autofocus
                    placeholder="Username"
                    class="input-field w-full px-4 py-3.5 rounded-xl border border-slate-700 bg-slate-950/80 text-white placeholder-slate-500 text-sm outline-none transition-all"
                >
            </div>

            <div>
                <label for="admin-password" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Password</label>
                <input
                    id="admin-password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="input-field w-full px-4 py-3.5 rounded-xl border border-slate-700 bg-slate-950/80 text-white placeholder-slate-500 text-sm outline-none transition-all"
                >
            </div>

            <button
                type="submit"
                id="admin-login-submit-btn"
                class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-blue-600/20 text-sm tracking-wide flex items-center justify-center gap-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                </svg>
                Sign In to Admin Portal
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-800 text-center">
            <a href="/" class="text-xs font-medium text-slate-400 hover:text-white transition-colors inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Return to S.I.K.A.P. Hub Main Portal
            </a>
        </div>

    </div>

</body>

</html>
