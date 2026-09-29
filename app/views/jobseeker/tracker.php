<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Tracker — S.I.K.A.P. Hub</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/sikaphub/public/assets/css/theme.css">
    <script src="/sikaphub/public/assets/js/tailwind.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
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
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--background); color: var(--text-primary); }
    </style>
</head>
<body class="text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Sticky Navigation Bar -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <a href="/sikaphub/dashboard" class="flex items-center gap-2.5">
                    <img src="/sikaphub/public/assets/images/logo-icon.png" alt="SikapHub" class="w-8 h-8 rounded-lg object-contain flex-shrink-0">
                    <span class="font-extrabold text-xl tracking-tight text-[#031a3f]">Sikap<span class="bg-gradient-to-r from-[#009cfb] via-[#1769ff] to-[#9035ff] bg-clip-text text-transparent">hub</span></span>
                </a>
                <div class="flex items-center gap-6">
                    <a href="/sikaphub/dashboard" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">Find Jobs</a>
                    <a href="/sikaphub/saved-jobs" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">Saved Jobs</a>
                    <a href="/sikaphub/my-applications" class="text-sm font-semibold text-primary border-b-2 border-primary py-5">My Applications</a>
                    
                    <!-- User Avatar & Settings Dropdown -->
                    <div class="relative" id="user-menu-container">
                        <button id="user-menu-button" type="button" class="flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-primary/20 rounded-full">
                            <div class="w-9 h-9 rounded-full bg-indigo-100 border-2 border-primary flex items-center justify-center text-primary font-bold text-xs shadow-sm overflow-hidden">
                                <?php if (!empty($context['profile_photo'])): ?>
                                    <img src="/sikaphub/admin/view-document?file=<?php echo htmlspecialchars($context['profile_photo']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php
                                        $email = $_SESSION['email'] ?? 'User';
                                        $initials = strtoupper(substr($email, 0, 2));
                                        echo htmlspecialchars($initials);
                                    ?>
                                <?php endif; ?>
                            </div>

                        </button>

                        <!-- Dropdown Menu -->
                        <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-100 py-2 z-50">
                            <div class="px-4 py-2 border-b border-slate-100">
                                <p class="text-xs text-slate-400 font-medium">Signed in as</p>
                                <p class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Job Seeker'); ?></p>
                            </div>
                            <a href="/sikaphub/build-profile" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                Edit Profile
                            </a>
                            <a href="/sikaphub/my-applications" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                My Applications
                            </a>
                            <div class="border-t border-slate-100 my-1"></div>
                            <a href="/sikaphub/logout" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 transition-colors">
                                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-20 right-6 z-50 flex flex-col gap-2"></div>

    <!-- Main Content -->
    <main class="flex-grow max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header Banner -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Application Tracker</h1>
                <p class="text-slate-500 text-sm mt-1">Monitor your submitted job applications and employer status updates in real time.</p>
            </div>
            <a href="/sikaphub/dashboard" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-50 text-primary text-sm font-bold hover:bg-indigo-100 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                Browse More Vacancies
            </a>
        </div>

        <!-- Applications Table / Cards Container -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-200 flex justify-between items-center bg-slate-50">
                <h2 class="text-base font-bold text-slate-800">Submitted Applications</h2>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">
                    <?php echo count($applications ?? []); ?> Active
                </span>
            </div>

            <?php if (empty($applications)): ?>
                <div class="p-12 text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <h3 class="text-base font-bold text-slate-700 mb-1">No submitted applications yet</h3>
                    <p class="text-sm text-slate-400 mb-6">Explore job postings in Guimba and apply with a single click.</p>
                    <a href="/sikaphub/dashboard" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-hover transition-colors shadow-sm">
                        Find Opportunities
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/50 text-xs uppercase tracking-wider font-bold text-slate-500">
                                <th class="py-4 px-6">Role & Company</th>
                                <th class="py-4 px-6">Applied Date</th>
                                <th class="py-4 px-6 text-center">Locked Match</th>
                                <th class="py-4 px-6 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($applications as $app): ?>
                                <?php
                                    $status = $app['application_status'] ?? 'Pending';
                                    $badgeClass = match($status) {
                                        'Accepted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'Reviewed' => 'bg-sky-50 text-sky-700 border-sky-200',
                                        'Rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        default    => 'bg-amber-50 text-amber-700 border-amber-200'
                                    };
                                    $matchPct = round((float) ($app['match_percentage'] ?? ($app['ai_match_score'] * 100)));
                                ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-slate-900 text-base"><?php echo htmlspecialchars($app['job_title']); ?></div>
                                        <div class="text-xs font-semibold text-primary mt-0.5"><?php echo htmlspecialchars($app['company_name']); ?></div>
                                    </td>
                                    <td class="py-4 px-6 text-slate-500 font-medium text-xs">
                                        <?php echo date('M d, Y', strtotime($app['application_date'])); ?>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                            <?php echo $matchPct; ?>% Match
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-right">
                                        <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-bold border <?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- User Menu Dropdown Toggle Script -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const userMenuBtn = document.getElementById("user-menu-button");
            const userMenuDropdown = document.getElementById("user-menu-dropdown");

            if (userMenuBtn && userMenuDropdown) {
                userMenuBtn.addEventListener("click", function (e) {
                    e.stopPropagation();
                    userMenuDropdown.classList.toggle("hidden");
                });

                document.addEventListener("click", function (e) {
                    if (!userMenuDropdown.contains(e.target) && !userMenuBtn.contains(e.target)) {
                        userMenuDropdown.classList.add("hidden");
                    }
                });
            }

            // Toast message for application submission
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success') && urlParams.get('success') === 'applied') {
                const container = document.getElementById('toast-container');
                const toastHTML = `
                    <div class="flex items-center gap-3 bg-slate-900 text-white px-5 py-3.5 rounded-xl shadow-xl border-l-4 border-emerald-500 text-sm font-semibold animate-bounce">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Application submitted successfully! Your AI match score is locked.</span>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', toastHTML);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>