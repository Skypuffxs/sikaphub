<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose your account type — S.I.K.A.P. Hub</title>
    <link rel="stylesheet" href="/public/assets/css/theme.css">
    <script src="/public/assets/js/tailwind.js"></script>
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
        .role-card { cursor: pointer; transition: all 0.15s ease; }
        .role-card:has(input:checked) { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(23, 105, 255, 0.12); }
        .role-card:hover { border-color: #60a5fa; }
    </style>
</head>

<body class="bg-app-bg text-slate-800">

    <div class="min-h-screen flex items-center justify-center px-6 py-16">

        <div class="w-full max-w-lg">

            <a href="/" class="flex items-center gap-3 mb-10 justify-center">
                <img src="/public/assets/images/logo-icon.png" alt="SikapHub" class="w-10 h-10 object-contain flex-shrink-0">
                <span class="font-extrabold text-3xl tracking-tight text-[#031a3f]">Sikap<span class="bg-gradient-to-r from-[#009cfb] via-[#1769ff] to-[#9035ff] bg-clip-text text-transparent">hub</span></span>
            </a>

            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-8">

                <h1 class="text-2xl font-extrabold text-slate-900">How will you use S.I.K.A.P. Hub?</h1>
                <p class="text-slate-500 mt-2 text-sm">Pick one to continue. This can't be changed later.</p>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded-lg text-sm font-semibold mt-6">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="/select-role" method="POST" class="mt-6 space-y-4">
                    <?php echo CSRF::csrfField(); ?>

                    <label class="role-card block border-2 border-slate-200 rounded-xl p-5">
                        <div class="flex items-start gap-4">
                            <input type="radio" name="role" value="jobseeker" class="mt-1 accent-primary w-4 h-4" required>
                            <div>
                                <p class="font-bold text-slate-900">I'm looking for work</p>
                                <p class="text-sm text-slate-500 mt-1">
                                    Build a profile, get matched to local vacancies, and track your applications.
                                </p>
                            </div>
                        </div>
                    </label>

                    <label class="role-card block border-2 border-slate-200 rounded-xl p-5">
                        <div class="flex items-start gap-4">
                            <input type="radio" name="role" value="employer" class="mt-1 accent-primary w-4 h-4" required>
                            <div>
                                <p class="font-bold text-slate-900">I'm hiring</p>
                                <p class="text-sm text-slate-500 mt-1">
                                    Register your business, post vacancies, and review ranked applicants.
                                </p>
                            </div>
                        </div>
                    </label>

                    <button
                        type="submit"
                        class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 rounded-xl transition-colors mt-2 text-sm tracking-wide"
                    >
                        Continue
                    </button>
                </form>

            </div>

            <p class="text-sm text-slate-500 mt-6 text-center">
                <a href="/logout" class="hover:underline">Sign out</a>
            </p>

        </div>

    </div>

</body>

</html>
