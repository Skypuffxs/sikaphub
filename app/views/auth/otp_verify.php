<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter your code — S.I.K.A.P. Hub</title>
    <link rel="stylesheet" href="/sikaphub/public/assets/css/theme.css">
    <script src="/sikaphub/public/assets/js/tailwind.js"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: {
                primary: '#1769ff', secondary: '#173b72',
                slate: { 50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1', 500: '#64748b', 600: '#475569', 800: '#1e293b', 900: '#1e293b' }
            } } }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--background); color: var(--text-primary); }
        .input-field:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(67, 56, 202, 0.12); }
        .code-input { letter-spacing: 0.6em; font-variant-numeric: tabular-nums; }
    </style>
</head>

<body class="bg-app-bg text-slate-800">

    <div class="min-h-screen flex items-center justify-center px-6 py-16">

        <div class="w-full max-w-md">

            <a href="/sikaphub/" class="flex items-center gap-3 mb-10 justify-center">
                <img src="/sikaphub/public/assets/images/logo-icon.png" alt="SikapHub" class="w-10 h-10 object-contain flex-shrink-0">
                <span class="font-extrabold text-3xl tracking-tight text-[#031a3f]">Sikap<span class="bg-gradient-to-r from-[#009cfb] via-[#1769ff] to-[#9035ff] bg-clip-text text-transparent">hub</span></span>
            </a>

            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-8">

                <h1 class="text-2xl font-extrabold text-slate-900">Enter your code</h1>
                <p class="text-slate-500 mt-2 text-sm leading-relaxed">
                    If that email address can receive a code, one is on its way. It's six digits and
                    expires in 10 minutes.
                </p>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded-lg text-sm font-semibold mt-6 flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="/sikaphub/auth/otp/verify" method="POST" class="mt-6">
                    <?php echo CSRF::csrfField(); ?>

                    <label for="code" class="block text-sm font-semibold text-slate-700 mb-2">Six-digit code</label>
                    <input
                        id="code"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        pattern="\d{6}"
                        maxlength="6"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        placeholder="000000"
                        class="code-input input-field w-full px-4 py-3 rounded-lg border border-slate-200 text-center text-lg font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none bg-slate-50 focus:bg-white text-slate-900 placeholder-slate-300"
                    >

                    <button
                        type="submit"
                        class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 rounded-xl transition-colors mt-6 text-sm tracking-wide"
                    >
                        Verify and continue
                    </button>
                </form>

                <p class="text-sm text-slate-500 mt-6 text-center">
                    Didn't get it?
                    <a href="/sikaphub/login" class="text-primary font-bold hover:underline">Request a new code</a>
                </p>

            </div>

        </div>

    </div>

</body>

</html>
