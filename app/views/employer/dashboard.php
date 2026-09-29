<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer ATS Dashboard – S.I.K.A.P. Hub</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    <meta name="description" content="Manage your job postings and review AI-ranked candidates on the S.I.K.A.P. Hub Employer ATS Dashboard.">

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

        /* Toast animations */
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

        .job-card { transition: all 0.2s ease-in-out; }
        .job-card:hover { border-color: #cbd5e1; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05); }
    </style>
</head>

<body class="bg-slate-50 min-h-screen flex flex-col antialiased">

    <!-- Top Navigation Bar -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <a href="/sikaphub/employer/dashboard" class="flex items-center gap-2.5">
                    <img src="/sikaphub/public/assets/images/logo-icon.png" alt="SikapHub" class="w-8 h-8 rounded-lg object-contain flex-shrink-0">
                    <span class="font-extrabold text-xl tracking-tight text-[#031a3f]">Sikap<span class="bg-gradient-to-r from-[#009cfb] via-[#1769ff] to-[#9035ff] bg-clip-text text-transparent">hub</span></span>
                    <span class="bg-indigo-50 text-indigo-700 text-[10px] font-extrabold px-2 py-0.5 rounded-md uppercase tracking-wider border border-indigo-100 hidden sm:inline-block">Employer ATS</span>
                </a>
                
                <div class="flex items-center gap-6">
                    <a href="/sikaphub/employer/dashboard" class="text-sm font-bold text-primary border-b-2 border-primary py-5">Dashboard</a>
                    <a href="/sikaphub/build-profile" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">Company Profile</a>
                    
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
                            <a href="/sikaphub/build-profile" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5"/></svg>
                                Company Profile
                            </a>
                            <div class="border-t border-slate-100 my-1"></div>
                            <a href="/sikaphub/logout" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 font-bold transition-colors">
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

    <!-- ATS Command Center Hero Header -->
    <header class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white pt-10 pb-16 px-4 sm:px-6 lg:px-8 relative overflow-hidden shadow-md">
        <div class="max-w-7xl mx-auto relative z-10">
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="bg-blue-500/20 text-blue-300 border border-blue-400/30 text-xs font-bold px-3 py-1 rounded-full flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
                            ATS Command Center
                        </span>
                        <?php if (($verified_status ?? 'Pending') === 'Verified'): ?>
                        <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 text-xs font-bold px-3 py-1 rounded-full flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                            Verified Employer
                        </span>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">Recruitment Dashboard</h1>
                    <p class="text-slate-300 text-sm md:text-base mt-1 max-w-2xl">
                        Manage active vacancies, evaluate AI point-in-time scored candidates, and streamline your recruitment pipeline.
                    </p>
                </div>

                <!-- CTA Action Button -->
                <div>
                    <?php if (($verified_status ?? 'Pending') === 'Verified'): ?>
                        <a href="/sikaphub/post-job" id="btn-post-job" class="inline-flex items-center gap-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-extrabold text-sm px-6 py-3.5 rounded-xl shadow-lg transition-all transform hover:-translate-y-0.5 whitespace-nowrap">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Post New Vacancy
                        </a>
                    <?php else: ?>
                        <span id="btn-post-job" title="Account pending verification by PESO Guimba" class="inline-flex items-center gap-2.5 bg-slate-800 text-slate-500 border border-slate-700 font-bold text-sm px-6 py-3.5 rounded-xl cursor-not-allowed pointer-events-none whitespace-nowrap select-none">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Post New Vacancy
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dashboard Stats Summary Widgets -->
            <?php
                $totalVacancies = count($jobs);
                $totalApplicants = 0;
                $topMatchesCount = 0;

                foreach ($jobs as $j) {
                    $apps = $j['applicants'] ?? [];
                    $totalApplicants += count($apps);
                    foreach ($apps as $a) {
                        if (($a['match_percentage'] ?? 0) >= 75) {
                            $topMatchesCount++;
                        }
                    }
                }
            ?>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8 pt-8 border-t border-slate-800">
                <div class="bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-sm">
                    <span class="text-xs font-bold text-slate-400 block uppercase tracking-wider mb-1">Active Vacancies</span>
                    <span class="text-2xl font-extrabold text-white"><?php echo $totalVacancies; ?></span>
                </div>
                <div class="bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-sm">
                    <span class="text-xs font-bold text-slate-400 block uppercase tracking-wider mb-1">Total Applicants</span>
                    <span class="text-2xl font-extrabold text-blue-400"><?php echo $totalApplicants; ?></span>
                </div>
                <div class="bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-sm">
                    <span class="text-xs font-bold text-slate-400 block uppercase tracking-wider mb-1">High AI Fit (75%+)</span>
                    <span class="text-2xl font-extrabold text-emerald-400"><?php echo $topMatchesCount; ?></span>
                </div>
            </div>

        </div>
    </header>

    <!-- Main Workspace Container -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Account Verification Warning Banner -->
        <?php if (($verified_status ?? 'Pending') !== 'Verified'): ?>
        <div role="alert" class="bg-amber-50 border border-amber-200 rounded-2xl p-5 flex items-start gap-4 shadow-sm">
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 text-lg font-bold">⚠️</div>
            <div class="text-sm">
                <h3 class="font-extrabold text-amber-900 mb-0.5">Account Under PESO Review</h3>
                <p class="text-amber-800 leading-relaxed">
                    Your business permit is currently being verified by PESO Guimba administrators.
                    You will be able to publish new job postings as soon as your employer verification is approved.
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Job Postings List & Candidate Table Section -->
        <section class="space-y-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <h2 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                    Job Vacancies & Applicant Pipeline
                    <span class="bg-blue-100 text-blue-800 text-xs px-2.5 py-0.5 rounded-full font-bold"><?php echo count($jobs); ?></span>
                </h2>

                <!-- Live Search / Filter Input -->
                <div class="relative max-w-xs w-full">
                    <input type="text" id="filter-input" placeholder="Search by job title or candidate..." class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-xs font-semibold outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                </div>
            </div>

            <?php if (empty($jobs)): ?>
                <!-- Empty State -->
                <div class="bg-white border border-slate-200 rounded-3xl p-16 flex flex-col items-center justify-center text-center gap-3 shadow-sm max-w-2xl mx-auto">
                    <div class="w-16 h-16 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-3xl font-bold mb-2">📋</div>
                    <h3 class="text-xl font-extrabold text-slate-800">No Job Vacancies Created Yet</h3>
                    <p class="text-sm text-slate-500 max-w-md">Publish your first job vacancy to start receiving AI-ranked candidate applications from registered job seekers.</p>
                </div>
            <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                <?php
                    $isJobOpen = strtolower($job['job_status'] ?? '') === 'open';
                    $statusBadgeClass = $isJobOpen 
                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
                        : 'bg-rose-50 text-rose-700 border-rose-200';
                    $applicantsList = $job['applicants'] ?? [];
                    
                    $formatSalary = function(?string $sal): string {
                        $s = trim($sal ?? '');
                        if ($s === '') return 'Negotiable';
                        if (preg_match('/^(₱|php|\$|eur|£)/i', $s)) return $s;
                        return '₱ ' . $s;
                    };
                ?>
                <div class="job-card bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">

                    <!-- Job Card Header -->
                    <div class="p-6 bg-slate-50/50 border-b border-slate-200/80 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3 flex-wrap mb-1">
                                <h3 class="text-lg font-extrabold text-slate-900 leading-snug">
                                    <?php echo htmlspecialchars($job['job_title']); ?>
                                </h3>
                                <span class="text-xs font-bold px-3 py-0.5 rounded-full border <?php echo $statusBadgeClass; ?>">
                                    <?php echo htmlspecialchars($job['job_status']); ?>
                                </span>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-500 mt-2">
                                <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 rounded-md font-bold">
                                    <?php echo htmlspecialchars($formatSalary($job['salary_range'] ?? '')); ?>
                                </span>
                                <span>•</span>
                                <span><?php echo htmlspecialchars($job['employment_type'] ?? 'Full-time'); ?></span>
                                <span>•</span>
                                <span><?php echo htmlspecialchars($job['work_arrangement'] ?? 'On-site'); ?></span>
                                <span>•</span>
                                <span><?php echo htmlspecialchars($job['municipality_name'] ?? 'Guimba'); ?></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 self-start md:self-auto">
                            <span class="text-xs font-bold text-slate-500 bg-white px-3 py-1.5 rounded-xl border border-slate-200 shadow-sm">
                                Posted <?php echo date('M d, Y', strtotime($job['date_posted'])); ?>
                            </span>
                            <span class="text-xs font-extrabold text-blue-700 bg-blue-50 px-3 py-1.5 rounded-xl border border-blue-100">
                                <?php echo count($applicantsList); ?> Applicant<?php echo count($applicantsList) === 1 ? '' : 's'; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Applicants Table Container -->
                    <div class="p-6">
                        <?php if (empty($applicantsList)): ?>
                            <div class="text-center py-8 text-slate-400">
                                <p class="text-sm font-semibold italic">No applicants submitted for this position yet.</p>
                                <p class="text-xs text-slate-400 mt-1">Qualified candidates will appear here ranked by AI matrix score.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-sm">
                                    <thead>
                                        <tr class="text-xs font-extrabold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-3">
                                            <th class="pb-3 pr-4">Candidate</th>
                                            <th class="pb-3 pr-4">Contact</th>
                                            <th class="pb-3 pr-4">AI Score</th>
                                            <th class="pb-3 pr-4">Status</th>
                                            <th class="pb-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach ($applicantsList as $applicant): ?>
                                            <?php
                                                $pct = (int)($applicant['match_percentage'] ?? 0);
                                                if ($pct >= 75) {
                                                    $scoreBadge = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                                    $scoreIcon = '🔗 High Fit';
                                                } elseif ($pct >= 40) {
                                                    $scoreBadge = 'bg-blue-50 text-blue-700 border-blue-200';
                                                    $scoreIcon = '🔗 Moderate Fit';
                                                } else {
                                                    $scoreBadge = 'bg-rose-50 text-rose-700 border-rose-200';
                                                    $scoreIcon = '🔗 Low Fit';
                                                }

                                                $appStatusColor = match(strtolower($applicant['application_status'] ?? '')) {
                                                    'accepted' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                                    'rejected' => 'bg-rose-100 text-rose-800 border-rose-200',
                                                    'reviewed' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
                                                    default    => 'bg-slate-100 text-slate-600 border-slate-200',
                                                };

                                                $candName = htmlspecialchars(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? ''));
                                            ?>
                                            <tr class="hover:bg-slate-50/80 transition-colors candidate-row">
                                                <!-- Candidate Name & Photo -->
                                                <td class="py-4 pr-4">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-9 h-9 rounded-full bg-slate-200 border border-slate-300 flex items-center justify-center font-bold text-slate-700 text-xs overflow-hidden shrink-0">
                                                            <?php if (!empty($applicant['profile_photo'])): ?>
                                                                <img src="/sikaphub/admin/view-document?file=<?php echo htmlspecialchars($applicant['profile_photo']); ?>" class="w-full h-full object-cover">
                                                            <?php else: ?>
                                                                <?php echo strtoupper(substr($applicant['first_name'] ?? 'C', 0, 1) . substr($applicant['last_name'] ?? 'A', 0, 1)); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <span class="font-extrabold text-slate-900 block leading-tight"><?php echo $candName; ?></span>
                                                        </div>
                                                    </div>
                                                </td>

                                                <!-- Contact Info -->
                                                <td class="py-4 pr-4 text-xs font-semibold text-slate-600">
                                                    <?php echo htmlspecialchars($applicant['contact_number'] ?? 'Not provided'); ?>
                                                </td>

                                                <!-- AI Match Percentage -->
                                                <td class="py-4 pr-4">
                                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold border <?php echo $scoreBadge; ?>">
                                                        <span><?php echo $scoreIcon; ?></span>
                                                        <span>• <?php echo $pct; ?>%</span>
                                                    </span>
                                                </td>

                                                <!-- Application Status -->
                                                <td class="py-4 pr-4">
                                                    <span class="text-xs font-extrabold px-3 py-1 rounded-full border <?php echo $appStatusColor; ?>">
                                                        <?php echo htmlspecialchars($applicant['application_status']); ?>
                                                    </span>
                                                </td>

                                                <!-- Action Review Button -->
                                                <td class="py-4 text-right">
                                                    <a href="/sikaphub/employer/review-candidate?app_id=<?php echo (int)$applicant['application_id']; ?>"
                                                       class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-sm">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                        Review Profile
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </section>
    </main>

    <!-- Interactive Script for Dropdown & Real-time Live Filter -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Toast Notification Handler
            const FRIENDLY_ERRORS = {
                unauthorized: "You do not have permission to access that area.",
                access_denied_or_not_found: "The requested item could not be found or access was denied.",
                database_error: "A temporary system issue occurred. Please try again in a few moments.",
                invalid_status: "The selected status update was invalid.",
                invalid_request: "The request could not be processed. Please try again.",
                company_not_found: "The company profile could not be found.",
                invalid_application: "The job application details could not be found."
            };

            const urlParams = new URLSearchParams(window.location.search);
            let message = '';
            let type = 'success';

            if (urlParams.has('job_posted')) {
                message = "✅ Job Opportunity successfully posted!";
            } else if (urlParams.has('profile_updated')) {
                message = "✅ Company Profile updated successfully!";
            } else if (urlParams.has('error')) {
                type    = 'error';
                const errCode = urlParams.get('error');
                message = "⚠️ " + (FRIENDLY_ERRORS[errCode] || ("An error occurred: " + errCode.replace(/_/g, ' ')));
            }

            if (message !== '') {
                const container = document.getElementById('toast-container');
                const div       = document.createElement('div');
                div.className   = `toast ${type}`;
                div.textContent = message;
                container.appendChild(div);
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            // User Dropdown Menu Toggle
            const userBtn = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-menu-dropdown');
            const userContainer = document.getElementById('user-menu-container');

            if (userBtn && userDropdown) {
                userBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('hidden');
                });
                document.addEventListener('click', function (e) {
                    if (userContainer && !userContainer.contains(e.target)) {
                        userDropdown.classList.add('hidden');
                    }
                });
            }

            // Live Search Filter for Employer Vacancies & Candidates
            const filterInput = document.getElementById('filter-input');
            const jobCards = document.querySelectorAll('.job-card');

            if (filterInput) {
                filterInput.addEventListener('input', function () {
                    const q = this.value.toLowerCase().trim();

                    jobCards.forEach(card => {
                        const cardText = card.innerText.toLowerCase();
                        if (!q || cardText.includes(q)) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>

</body>
</html>