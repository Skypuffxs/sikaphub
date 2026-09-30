<?php
$code = $code ?? 500;
$message = $message ?? 'Something went wrong on our side. Please try again.';
$titles = [400 => 'Bad request', 403 => 'Not allowed', 404 => 'Not found', 500 => 'Something went wrong'];
$heading = $titles[$code] ?? 'Something went wrong';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($heading); ?> — S.I.K.A.P. Hub</title>
    <link rel="stylesheet" href="/public/assets/css/theme.css">
    <script src="/public/assets/js/tailwind.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--background); color: var(--text-primary); } </style>
</head>
<body class="bg-app-bg text-slate-800">
    <div class="min-h-screen flex items-center justify-center px-6 py-16">
        <div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-sm p-8 text-center">
            <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-5">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.34 3.94l-8.4 14.55A1.5 1.5 0 003.24 21h17.52a1.5 1.5 0 001.3-2.51L13.66 3.94a1.5 1.5 0 00-2.6 0z"/>
                </svg>
            </div>
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400"><?php echo (int) $code; ?></p>
            <h1 class="text-xl font-extrabold text-slate-900 mt-1"><?php echo htmlspecialchars($heading); ?></h1>
            <p class="text-slate-500 mt-2 text-sm leading-relaxed"><?php echo htmlspecialchars($message); ?></p>
            <a href="/build-profile" class="inline-block mt-6 text-sm text-primary font-bold text-indigo-600 hover:underline">Go back</a>
        </div>
    </div>
</body>
</html>
