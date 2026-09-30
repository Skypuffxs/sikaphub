<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Review — <?php echo htmlspecialchars(($app['first_name'] ?? 'Candidate') . ' ' . ($app['last_name'] ?? '')); ?> | S.I.K.A.P. Hub</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/theme.css">
    <script src="/public/assets/js/tailwind.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: {
                        primary: '#1769ff',
                        'primary-hover': '#0053e6',
                        secondary: '#173b72',
                        'app-bg': '#f8fafc',
                        surface: '#ffffff',
                        border: '#e2ebf6'
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #1e293b; }

        #toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.625rem; pointer-events: none; }
        .toast {
            display: flex; align-items: center; gap: 0.625rem;
            padding: 0.875rem 1.25rem; border-radius: 0.75rem;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
            color: #fff; font-weight: 700; font-size: 0.875rem;
            opacity: 0; transform: translateX(120%);
            pointer-events: auto;
            animation: slideIn 0.35s cubic-bezier(.22,1,.36,1) forwards,
                       fadeOut 0.35s ease forwards 4.65s;
        }
        .toast.success { background: #0f172a; border-left: 5px solid #10b981; }
        .toast.error   { background: #0f172a; border-left: 5px solid #ef4444; }
        @keyframes slideIn { to { opacity: 1; transform: translateX(0); } }
        @keyframes fadeOut { to { opacity: 0; transform: translateX(120%); } }
    </style>
</head>

<body class="bg-slate-50 min-h-screen flex flex-col antialiased">

    <!-- Top Navigation Bar -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <a href="/employer/dashboard" class="flex items-center gap-2.5">
                    <img src="/public/assets/images/logo-icon.png" alt="SikapHub" class="w-8 h-8 rounded-lg object-contain flex-shrink-0">
                    <span class="font-extrabold text-xl tracking-tight text-[#031a3f]">Sikap<span class="bg-gradient-to-r from-[#009cfb] via-[#1769ff] to-[#9035ff] bg-clip-text text-transparent">hub</span></span>
                    <span class="bg-indigo-50 text-indigo-700 text-[10px] font-extrabold px-2 py-0.5 rounded-md uppercase tracking-wider border border-indigo-100 hidden sm:inline-block">Employer ATS</span>
                </a>
                
                <div class="flex items-center gap-6">
                    <a href="/employer/dashboard" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">Dashboard</a>
                    <a href="/build-profile" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">Company Profile</a>
                    
                    <a href="/employer/dashboard" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 px-3.5 py-1.5 rounded-lg transition-all">
                        ← Back to Dashboard
                    </a>

                    <!-- User Avatar & Dropdown -->
                    <div class="relative" id="user-menu-container">
                        <button id="user-menu-button" type="button" onclick="event.stopPropagation(); const d=document.getElementById('user-menu-dropdown'); if(d) d.classList.toggle('hidden');" class="flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-primary/20 rounded-full cursor-pointer">
                            <div class="w-9 h-9 rounded-full bg-slate-900 border-2 border-primary flex items-center justify-center text-white font-bold text-xs shadow-sm overflow-hidden">
                                <?php
                                    $email = $_SESSION['email'] ?? 'Employer';
                                    $initials = strtoupper(substr($email, 0, 2));
                                    echo htmlspecialchars($initials);
                                ?>
                            </div>
                        </button>

                        <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-100 py-2 z-50">
                            <div class="px-4 py-2 border-b border-slate-100">
                                <p class="text-xs text-slate-400 font-medium">Signed in as</p>
                                <p class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Employer'); ?></p>
                            </div>
                            <a href="/build-profile" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5"/></svg>
                                Company Profile
                            </a>
                            <a href="/employer/dashboard" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25z"/></svg>
                                ATS Dashboard
                            </a>
                            <div class="border-t border-slate-100 my-1"></div>
                            <a href="/logout" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 font-bold transition-colors">
                                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Toast Notifications Container -->
    <div id="toast-container"></div>

    <!-- Main Workspace Container -->
    <main class="flex-grow max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        
        <!-- Candidate Overview Profile Header Card -->
        <div class="bg-white rounded-2xl p-6 md:p-8 border border-slate-200/90 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-100">
                
                <div class="flex items-center gap-5">
                    <!-- Profile Photo Avatar -->
                    <div class="w-20 h-20 rounded-full border-4 border-slate-100 shadow-md overflow-hidden bg-slate-100 flex items-center justify-center text-slate-700 font-extrabold text-xl shrink-0">
                        <?php if (!empty($app['profile_photo'])): ?>
                            <img src="/admin/view-document?file=<?php echo htmlspecialchars($app['profile_photo']); ?>" alt="Profile Photo" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?php echo strtoupper(substr($app['first_name'] ?? 'C', 0, 1) . substr($app['last_name'] ?? 'A', 0, 1)); ?>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 leading-tight">
                            <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                        </h1>
                        <p class="text-sm font-bold text-blue-600 mt-0.5">
                            Applying for: <?php echo htmlspecialchars($app['job_title']); ?>
                        </p>
                        
                        <?php
                        $locationParts = array_filter(
                            [$app['street_name'] ?? '', $app['municipality_name'] ?? ''],
                            fn($p) => trim((string) $p) !== ''
                        );
                        ?>
                        <div class="flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-500 mt-2">
                            <span class="flex items-center gap-1">
                                📞 <?php echo htmlspecialchars(!empty($app['contact_number']) ? $app['contact_number'] : 'Not provided'); ?>
                            </span>
                            <span>•</span>
                            <span class="flex items-center gap-1">
                                📍 <?php echo $locationParts ? htmlspecialchars(implode(', ', $locationParts)) : 'Guimba, Nueva Ecija'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- AI Score Box -->
                <?php
                    $matchPct = round((float) $app['ai_match_score'] * 100);
                    if ($matchPct >= 75) {
                        $scoreBadge = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                        $scoreTag = '🔗 High Fit';
                    } elseif ($matchPct >= 40) {
                        $scoreBadge = 'bg-blue-50 text-blue-700 border-blue-200';
                        $scoreTag = '🔗 Moderate Fit';
                    } else {
                        $scoreBadge = 'bg-rose-50 text-rose-700 border-rose-200';
                        $scoreTag = '🔗 Low Fit';
                    }
                ?>
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-center shrink-0 min-w-[140px]">
                    <span class="text-xs font-bold text-slate-400 block uppercase tracking-wider mb-1">AI Match Score</span>
                    <span class="text-3xl font-extrabold text-blue-600"><?php echo $matchPct; ?>%</span>
                    <span class="block text-[11px] font-bold mt-1 px-2.5 py-0.5 rounded-full border <?php echo $scoreBadge; ?>">
                        <?php echo $scoreTag; ?>
                    </span>
                </div>

            </div>

            <!-- Application Status Control Bar -->
            <div class="mt-6 pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200/80">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-extrabold text-slate-500 uppercase tracking-wider">Current Status:</span>
                    <?php
                        $appStatusColor = match(strtolower($app['application_status'] ?? '')) {
                            'accepted' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                            'rejected' => 'bg-rose-100 text-rose-800 border-rose-200',
                            'reviewed' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
                            default    => 'bg-slate-200 text-slate-700 border-slate-300',
                        };
                    ?>
                    <span class="text-xs font-extrabold px-3 py-1 rounded-full border <?php echo $appStatusColor; ?>">
                        <?php echo htmlspecialchars($app['application_status']); ?>
                    </span>
                </div>

                <form method="POST" action="" class="flex items-center gap-2">
                    <?php echo CSRF::csrfField(); ?>
                    <input type="hidden" name="app_id" value="<?php echo htmlspecialchars($app['application_id'] ?? 0); ?>">
                    <select name="status" class="bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none focus:border-blue-600 transition-all">
                        <option value="Pending" <?php if ($app['application_status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                        <option value="Reviewed" <?php if ($app['application_status'] == 'Reviewed') echo 'selected'; ?>>Reviewed</option>
                        <option value="Accepted" <?php if ($app['application_status'] == 'Accepted') echo 'selected'; ?>>Accepted</option>
                        <option value="Rejected" <?php if ($app['application_status'] == 'Rejected') echo 'selected'; ?>>Rejected</option>
                    </select>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold px-4 py-2 rounded-xl transition-all shadow-sm">
                        Update Status
                    </button>
                </form>
            </div>
        </div>

        <!-- Section: Résumé Document -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/90 shadow-sm space-y-4">
            <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                <span class="text-lg">📄</span> Candidate Résumé & Uploaded Assets
            </h2>

            <?php if (!empty($resume['stored_filename'])): ?>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-5">
                    <div class="flex items-center justify-between flex-wrap gap-4 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center text-lg font-bold">📄</div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($resume['original_filename'] ?? 'Uploaded_Resume.pdf'); ?></h3>
                                <span class="text-xs font-semibold text-emerald-600 flex items-center gap-1 mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                    Verified PDF Document
                                </span>
                            </div>
                        </div>

                        <a href="/admin/view-document?file=<?php echo htmlspecialchars($resume['stored_filename']); ?>" target="_blank"
                           class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-sm">
                            Open Full Résumé
                        </a>
                    </div>

                    <?php 
                    $isPdf = str_contains(strtolower($resume['mime_type'] ?? ''), 'pdf') || str_ends_with(strtolower($resume['stored_filename'] ?? ''), '.pdf');
                    if ($isPdf): 
                    ?>
                    <div class="pt-4 border-t border-slate-200">
                        <p class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-3">Inline Document Viewer:</p>
                        <iframe src="/admin/view-document?file=<?php echo htmlspecialchars($resume['stored_filename']); ?>"
                                class="w-full h-[550px] border border-slate-200 rounded-xl bg-white shadow-inner"></iframe>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="p-8 text-center text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                    <p class="text-sm font-semibold">No uploaded résumé on file.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section: Work Experience -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/90 shadow-sm space-y-4">
            <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                <span class="text-lg">💼</span> Work Experience
            </h2>

            <?php if (empty($experience)): ?>
                <p class="text-xs text-slate-400 font-medium italic py-2">No work experience history provided.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($experience as $exp): ?>
                        <div class="p-4 bg-slate-50/70 border border-slate-200 rounded-xl">
                            <h3 class="font-extrabold text-sm text-slate-900">
                                <?php echo htmlspecialchars($exp['job_title']); ?> <span class="text-blue-600">at <?php echo htmlspecialchars($exp['company_name']); ?></span>
                            </h3>
                            <span class="text-xs font-semibold text-slate-400 block mt-0.5">
                                <?php echo htmlspecialchars($exp['start_date']); ?> — <?php echo htmlspecialchars($exp['end_date'] ?: 'Present'); ?>
                            </span>
                            <?php if (!empty($exp['job_description'])): ?>
                                <p class="text-xs text-slate-600 mt-2 leading-relaxed whitespace-pre-line"><?php echo htmlspecialchars($exp['job_description']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section: Education -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/90 shadow-sm space-y-4">
            <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                <span class="text-lg">🎓</span> Education & Qualifications
            </h2>

            <?php if (empty($education)): ?>
                <p class="text-xs text-slate-400 font-medium italic py-2">No education history provided.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($education as $edu): ?>
                        <div class="p-4 bg-slate-50/70 border border-slate-200 rounded-xl">
                            <h3 class="font-extrabold text-sm text-slate-900">
                                <?php echo htmlspecialchars(html_entity_decode($edu['degree_level'] ?? '', ENT_QUOTES, 'UTF-8')); ?>
                            </h3>
                            <span class="text-xs font-semibold text-slate-400 block mt-0.5">
                                <?php echo htmlspecialchars($edu['school_name']); ?> • Class of <?php echo htmlspecialchars($edu['year_graduated']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Toast Handler Script -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const urlParams = new URLSearchParams(window.location.search);
            let message = '';
            let type = 'success';

            const FRIENDLY_ERRORS = {
                unauthorized: "You do not have permission to perform this action.",
                access_denied_or_not_found: "The applicant record could not be found or access was denied.",
                database_error: "A temporary system issue occurred. Please try again in a few moments.",
                invalid_status: "The selected status update was invalid.",
                invalid_request: "The request could not be processed. Please try again.",
                invalid_application: "The job application details could not be found."
            };

            if (urlParams.has('status_updated')) {
                message = "Application status successfully updated!";
            } else if (urlParams.has('error')) {
                type = 'error';
                const errCode = urlParams.get('error');
                message = FRIENDLY_ERRORS[errCode] || ("An issue occurred: " + errCode.replace(/_/g, ' '));
            }

            if (message !== '') {
                const container = document.getElementById('toast-container');
                const div = document.createElement('div');
                div.className = `toast ${type}`;
                div.textContent = (type === 'success' ? '✅ ' : '⚠️ ') + message;
                container.appendChild(div);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>

</body>
</html>