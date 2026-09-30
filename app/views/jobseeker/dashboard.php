<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Search & Recommendations — S.I.K.A.P. Hub</title>
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
        .job-card { transition: all 0.2s ease-in-out; cursor: pointer; }
        .job-card:hover { border-color: #3b82f6; transform: translateY(-1px); }
        .job-card.active-card { border-color: #2563eb !important; background-color: #eff6ff !important; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1) !important; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="text-slate-800 antialiased min-h-screen flex flex-col bg-slate-50">

    <!-- Top Navigation Bar -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <a href="/dashboard" class="flex items-center gap-2.5">
                    <img src="/public/assets/images/logo-icon.png" alt="SikapHub" class="w-8 h-8 rounded-lg object-contain flex-shrink-0">
                    <span class="font-extrabold text-xl tracking-tight text-[#031a3f]">Sikap<span class="bg-gradient-to-r from-[#009cfb] via-[#1769ff] to-[#9035ff] bg-clip-text text-transparent">hub</span></span>
                </a>
                <div class="flex items-center gap-6">
                    <a href="/dashboard" class="text-sm font-bold text-primary border-b-2 border-primary py-5">Find Jobs</a>
                    <a href="/saved-jobs" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">Saved Jobs</a>
                    <a href="/my-applications" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">My Applications</a>
                    
                    <!-- User Avatar & Dropdown -->
                    <div class="relative" id="user-menu-container">
                        <button id="user-menu-button" type="button" class="flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-primary/20 rounded-full">
                            <div class="w-9 h-9 rounded-full bg-indigo-100 border-2 border-primary flex items-center justify-center text-primary font-bold text-xs shadow-sm overflow-hidden">
                                <?php if (!empty($context['profile_photo'])): ?>
                                    <img src="/admin/view-document?file=<?php echo htmlspecialchars($context['profile_photo']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php
                                        $email = $_SESSION['email'] ?? 'User';
                                        $initials = strtoupper(substr($email, 0, 2));
                                        echo htmlspecialchars($initials);
                                    ?>
                                <?php endif; ?>
                            </div>
                        </button>

                        <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-100 py-2 z-50">
                            <div class="px-4 py-2 border-b border-slate-100">
                                <p class="text-xs text-slate-400 font-medium">Signed in as</p>
                                <p class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Job Seeker'); ?></p>
                            </div>
                            <a href="/build-profile" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                Edit Profile
                            </a>
                            <a href="/saved-jobs" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/></svg>
                                Saved Jobs
                            </a>
                            <a href="/my-applications" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                My Applications
                            </a>
                            <div class="border-t border-slate-100 my-1"></div>
                            <a href="/logout" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 transition-colors">
                                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Unified Indeed-Style Top Search Banner Section -->
    <header class="bg-gradient-to-b from-blue-50/70 to-slate-50 border-b border-slate-200/80 pt-8 pb-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            
            <!-- Center Unified Search Bar -->
            <form id="search-form" class="bg-white p-2 rounded-full shadow-lg border border-slate-200/90 flex flex-col md:flex-row items-center gap-2 max-w-4xl mx-auto">
                <div class="flex-1 flex items-center px-5 py-2.5 w-full">
                    <svg class="w-5 h-5 text-slate-400 mr-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    <input type="text" id="search-keyword" placeholder="Job title, keywords, or company" class="w-full bg-transparent border-none outline-none text-slate-800 placeholder-slate-400 text-sm font-semibold">
                </div>
                
                <div class="hidden md:block w-px h-8 bg-slate-200"></div>
                
                <div class="flex-1 flex items-center px-5 py-2.5 w-full">
                    <svg class="w-5 h-5 text-slate-400 mr-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                    <input type="text" id="search-location" placeholder="Guimba, Nueva Ecija or Philippines" class="w-full bg-transparent border-none outline-none text-slate-800 placeholder-slate-400 text-sm font-semibold">
                </div>
                
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-full shadow-md transition-all whitespace-nowrap w-full md:w-auto text-sm">
                    Find jobs
                </button>
            </form>

            <!-- Personal Greeting & Filter Badges -->
            <div class="mt-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <?php
                        $seekerName = !empty($context['first_name'])
                            ? htmlspecialchars(trim($context['first_name']))
                            : htmlspecialchars(explode('@', $_SESSION['email'] ?? 'Seeker')[0]);
                    ?>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Welcome, <?php echo $seekerName; ?></h1>

                    <p class="text-sm font-medium text-slate-500 mt-0.5">Explore personalized job matches powered by AI matrix recommendations</p>
                </div>

                <!-- Quick Filter Badges -->
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="filter-pill active-pill inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-bold bg-blue-600 text-white shadow-sm border border-blue-600 cursor-pointer" data-filter="all">
                        All Vacancies
                    </button>
                    <button type="button" class="filter-pill inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-bold bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 cursor-pointer transition-all" data-filter="verified">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        Verified Only
                    </button>
                    <button type="button" class="filter-pill inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-bold bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 cursor-pointer transition-all" data-filter="high-match">
                        🔗 High Match (80%+)
                    </button>
                    <button type="button" class="filter-pill inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-bold bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 cursor-pointer transition-all" data-filter="saved">
                        🔖 Saved Jobs
                    </button>
                    <a href="/build-profile" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-bold bg-white text-indigo-600 hover:bg-indigo-50 border border-indigo-200 transition-all">
                        ⚙️ Profile (<?php echo (int)($profileCompleteness ?? 0); ?>%)
                    </a>
                </div>
            </div>

        </div>
    </header>

    <!-- Main Content Container: Split View -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <?php if (empty($hasHomeMunicipality)): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 mb-6 flex items-start gap-3 shadow-sm">
            <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
            <p class="text-sm text-blue-900 font-medium">
                Complete your profile location to enhance geographic matching multipliers.
                <a href="/build-profile" class="font-bold underline text-blue-700 hover:text-blue-800">Add home municipality &rarr;</a>
            </p>
        </div>
        <?php endif; ?>

        <?php if (!empty($awaitingScoringCount)): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-amber-900 font-medium">
                <span class="font-bold"><?php echo (int)$awaitingScoringCount; ?></span> newly posted <?php echo $awaitingScoringCount === 1 ? 'vacancy is' : 'vacancies are'; ?> pending AI match score generation.
            </p>
        </div>
        <?php endif; ?>

        <?php if (empty($jobs)): ?>
        <div class="bg-white rounded-3xl p-16 border border-slate-200 shadow-sm text-center max-w-2xl mx-auto">
            <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">No active job listings</h3>
            <p class="text-slate-500 text-sm">There are no job vacancies matching your criteria right now. Check back soon!</p>
        </div>
        <?php else: ?>

        <!-- Split Layout Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- LEFT COLUMN: Job List Cards -->
            <div class="lg:col-span-5 space-y-4">
                <div class="flex items-center justify-between px-1 mb-1">
                    <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        Jobs for you
                        <span id="job-count-badge" class="bg-blue-100 text-blue-700 text-xs px-2.5 py-0.5 rounded-full font-extrabold"><?php echo count($jobs); ?></span>
                    </h2>
                    <span class="text-xs font-semibold text-slate-400">Sorted by AI Match</span>
                </div>

                <div id="job-list-container" class="space-y-3.5">
                    <?php
                        $formatSalary = function(?string $sal): string {
                            $s = trim($sal ?? '');
                            if ($s === '') return 'Negotiable';
                            if (preg_match('/^(₱|php|\$|eur|£)/i', $s)) return $s;
                            return '₱ ' . $s;
                        };
                    ?>
                    <?php foreach ($jobs as $index => $job): ?>
                    <?php
                        $isVerified = ($job['verified_status'] ?? '') === 'Verified';
                        $isScored = $job['final_score'] !== null;
                        $hasApplied = !empty($job['application_id']);
                        $isSaved = !empty($job['saved_job_id']);
                        $breakdown = $skillBreakdown[$job['job_id']] ?? ['matched' => [], 'unmatched' => [], 'pending' => []];
                        $matchPct = (int)($job['match_percentage'] ?? 0);
                    ?>
                    <div class="job-card bg-white rounded-2xl p-5 border border-slate-200/90 shadow-sm relative group <?php echo $index === 0 ? 'active-card' : ''; ?>"
                         data-job-id="<?php echo $job['job_id']; ?>"
                         data-verified="<?php echo $isVerified ? '1' : '0'; ?>"
                         data-score="<?php echo $matchPct; ?>"
                         data-saved="<?php echo $isSaved ? '1' : '0'; ?>">
                        
                        <!-- Card Header Badge Row -->
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="bg-blue-50 text-blue-700 border border-blue-100 text-[11px] font-bold px-2.5 py-0.5 rounded-full">Easily apply</span>
                                <?php if ($isVerified): ?>
                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-100 text-[11px] font-bold px-2.5 py-0.5 rounded-full flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                    Verified
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Bookmark & Dismiss Actions -->
                            <div class="flex items-center gap-1">
                                <button type="button" class="btn-toggle-save p-1 rounded-full hover:bg-slate-100 transition-colors" data-job-id="<?php echo $job['job_id']; ?>" title="<?php echo $isSaved ? 'Remove from Saved' : 'Save Job'; ?>">
                                    <svg class="w-5 h-5 <?php echo $isSaved ? 'text-primary fill-primary' : 'text-slate-300 fill-none'; ?>" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Job Title & Company -->
                        <h3 class="text-base font-extrabold text-slate-900 group-hover:text-blue-600 transition-colors leading-snug mb-1">
                            <?php echo htmlspecialchars($job['job_title'] ?? ''); ?>
                        </h3>
                        
                        <p class="text-xs font-bold text-slate-600 mb-2">
                            <a href="/company/view?id=<?php echo htmlspecialchars($job['employer_id'] ?? 0); ?>" class="hover:underline font-bold text-slate-700 hover:text-primary">
                                <?php echo htmlspecialchars($job['company_name'] ?? ''); ?>
                            </a>
                            <span class="font-normal text-slate-400">• <?php echo htmlspecialchars($job['municipality_name'] ?? 'Guimba'); ?></span>
                        </p>

                        <!-- Key Highlight Chips -->
                        <div class="flex flex-wrap gap-1.5 my-3">
                            <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold px-2.5 py-1 rounded-md">
                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                <?php echo htmlspecialchars($formatSalary($job['salary_range'])); ?>
                            </span>

                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 text-xs font-medium px-2.5 py-1 rounded-md">
                                <?php echo htmlspecialchars($job['employment_type'] ?? 'Full-time'); ?>
                            </span>
                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 text-xs font-medium px-2.5 py-1 rounded-md">
                                <?php echo htmlspecialchars($job['work_arrangement'] ?? 'On-site'); ?>
                            </span>
                        </div>

                        <!-- Card Footer Info -->
                        <div class="flex items-center justify-between pt-2 text-xs text-slate-400 font-semibold border-t border-slate-100">
                            <span class="flex items-center gap-1">
                                🔗 Match <?php echo $isScored ? $matchPct . '%' : 'Pending'; ?>
                            </span>
                            <span>Posted <?php echo htmlspecialchars(date('M d', strtotime($job['date_posted']))); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- RIGHT COLUMN: Sticky Master Detail Pane -->
            <div class="lg:col-span-7 sticky top-20">
                <div id="job-detail-pane" class="bg-white rounded-2xl border border-slate-200 p-6 md:p-8 shadow-sm min-h-[500px] flex flex-col justify-between">
                    
                    <!-- Dynamic Detail Content populated via JS -->
                    <div id="detail-content">
                        <!-- Default placeholder state -->
                        <div class="text-center py-20">
                            <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                            <p class="text-slate-500 font-medium">Loading job details...</p>
                        </div>
                    </div>

                </div>
            </div>

        </div>
        <?php endif; ?>

    </main>

    <!-- Hidden Store of Job Data for Instant JS Rendering -->
    <script id="jobs-data-json" type="application/json">
        <?php
            // Prepare clean JSON payload for JS master-detail interaction
            $formattedJobs = [];
            foreach ($jobs as $j) {
                $jId = $j['job_id'];
                $formattedJobs[$jId] = [
                    'id' => $j['job_id'],
                    'employer_id' => $j['employer_id'] ?? 0,
                    'title' => $j['job_title'],
                    'company' => $j['company_name'],
                    'verified' => ($j['verified_status'] ?? '') === 'Verified',
                    'municipality' => $j['municipality_name'] ?? 'Guimba, Nueva Ecija',
                    'work_arrangement' => $j['work_arrangement'] ?? 'On-site',
                    'employment_type' => $j['employment_type'] ?? 'Full-time',
                    'experience' => $j['min_years_experience'] ?? 0,
                    'salary' => $formatSalary($j['salary_range']),

                    'date_posted' => date('M d, Y', strtotime($j['date_posted'])),
                    'match_percentage' => $j['match_percentage'] !== null ? (int)$j['match_percentage'] : null,
                    'mandatory_met' => $j['mandatory_met'] ?? 0,
                    'mandatory_total' => $j['mandatory_total'] ?? 0,
                    'preferred_met' => $j['preferred_met'] ?? 0,
                    'preferred_total' => $j['preferred_total'] ?? 0,
                    'has_applied' => !empty($j['application_id']),
                    'is_saved' => !empty($j['saved_job_id']),
                    'skills' => $skillBreakdown[$jId] ?? ['matched' => [], 'unmatched' => [], 'pending' => []]
                ];
            }
            echo json_encode($formattedJobs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
    </script>

    <!-- Application Script: Master Detail Interaction & Filters -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";
            const jobsData = JSON.parse(document.getElementById('jobs-data-json').textContent || '{}');
            window.jobsData = jobsData;
            const jobCards = document.querySelectorAll('.job-card');
            const detailPane = document.getElementById('detail-content');

            function syncAppliedJobsFromStorage() {
                let currentSelectedId = null;
                jobCards.forEach(card => {
                    const jobId = card.getAttribute('data-job-id');
                    if (card.classList.contains('active-card')) {
                        currentSelectedId = jobId;
                    }
                    if (sessionStorage.getItem('sikap_applied_job_' + jobId) === 'true' || 
                        localStorage.getItem('sikap_applied_job_' + jobId) === 'true') {
                        if (jobsData[jobId]) {
                            jobsData[jobId].has_applied = true;
                        }
                    }
                });

                if (currentSelectedId && jobsData[currentSelectedId]) {
                    renderJobDetail(currentSelectedId);
                }
            }

            // 1. Function to Render Right Detail Pane
            function renderJobDetail(jobId) {
                const job = jobsData[jobId];
                if (!job) return;

                // Update Active Card Styling on Left List
                jobCards.forEach(card => {
                    if (card.getAttribute('data-job-id') == jobId) {
                        card.classList.add('active-card');
                    } else {
                        card.classList.remove('active-card');
                    }
                });

                // Build Skill Chips HTML
                let matchedSkillsHtml = '';
                if (job.skills.matched && job.skills.matched.length > 0) {
                    matchedSkillsHtml = job.skills.matched.map(s => 
                        `<span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-blue-600 text-white shadow-sm">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                            ${escapeHtml(s)}
                         </span>`
                    ).join(' ');
                }

                let unmatchedSkillsHtml = '';
                if (job.skills.unmatched && job.skills.unmatched.length > 0) {
                    unmatchedSkillsHtml = job.skills.unmatched.map(s => 
                        `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border border-slate-300 text-slate-500">
                            ${escapeHtml(s)}
                         </span>`
                    ).join(' ');
                }

                let pendingSkillsHtml = '';
                if (job.skills.pending && job.skills.pending.length > 0) {
                    pendingSkillsHtml = job.skills.pending.map(s => 
                        `<span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium border border-dashed border-amber-400 text-amber-600 bg-amber-50">
                            ${escapeHtml(s)} <span class="text-[10px] font-bold uppercase">awaiting approval</span>
                         </span>`
                    ).join(' ');
                }

                // Render Action Apply Button
                let actionBtnHtml = '';
                if (job.has_applied) {
                    actionBtnHtml = `
                        <div class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 font-bold px-6 py-3 rounded-full text-sm">
                            <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                            Application Submitted
                        </div>
                    `;
                } else {
                    actionBtnHtml = `
                        <form method="POST" action="/apply" class="inline-block">
                            <input type="hidden" name="job_id" value="${job.id}">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-8 py-3 rounded-full text-sm shadow-md transition-all">
                                Apply now
                            </button>
                        </form>
                    `;
                }

                // Render Detail Content
                detailPane.innerHTML = `
                    <div>
                        <!-- Header Title & Company -->
                        <div class="border-b border-slate-100 pb-6 mb-6">
                            <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 mb-2 leading-tight">${escapeHtml(job.title)}</h2>
                            
                            <div class="flex items-center gap-2 text-sm font-semibold text-slate-600 mb-4 flex-wrap">
                                <a href="/company/view?id=${job.employer_id}" class="text-primary hover:underline font-bold">🏢 ${escapeHtml(job.company)}</a>
                                <span>•</span>
                                <span>${escapeHtml(job.municipality)}</span>
                                <span>•</span>
                                <span class="text-slate-500 font-normal">${escapeHtml(job.work_arrangement)}</span>
                            </div>

                            <!-- Action Bar -->
                            <div class="flex items-center gap-3 pt-2">
                                ${actionBtnHtml}
                                
                                <button type="button" class="btn-toggle-save px-4 py-2.5 border ${job.is_saved ? 'border-primary bg-blue-50 text-primary' : 'border-slate-200 text-slate-600'} hover:border-slate-300 rounded-full transition-colors flex items-center gap-1.5 font-bold text-xs" data-job-id="${job.id}" title="${job.is_saved ? 'Remove from Saved' : 'Save Job'}">
                                    <svg class="w-4 h-4 ${job.is_saved ? 'text-primary fill-primary' : 'text-slate-400 fill-none'}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/></svg>
                                    <span>${job.is_saved ? 'Saved' : 'Save Job'}</span>
                                </button>
                                
                                <button type="button" class="p-3 border border-slate-200 hover:border-slate-300 rounded-full text-slate-600 transition-colors" title="Share Job">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5zm0-11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Job Overview Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-8">
                            <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-100">
                                <span class="text-xs font-bold text-slate-400 block uppercase mb-1">Pay Range</span>
                                <span class="text-sm font-extrabold text-emerald-700">₱ ${escapeHtml(job.salary)}</span>
                            </div>
                            <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-100">
                                <span class="text-xs font-bold text-slate-400 block uppercase mb-1">Job Type</span>
                                <span class="text-sm font-extrabold text-slate-800">${escapeHtml(job.employment_type)}</span>
                            </div>
                            <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-100">
                                <span class="text-xs font-bold text-slate-400 block uppercase mb-1">AI Match</span>
                                <span class="text-sm font-extrabold text-blue-600">${job.match_percentage !== null ? job.match_percentage + '%' : 'Pending'}</span>
                            </div>
                        </div>

                        <!-- AI Matrix Skills Match Breakdown -->
                        <div class="mb-8">
                            <h3 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider mb-3">AI Matrix Skills Evaluation</h3>
                            <div class="flex flex-wrap gap-2">
                                ${matchedSkillsHtml}
                                ${unmatchedSkillsHtml}
                                ${pendingSkillsHtml}
                                ${(!matchedSkillsHtml && !unmatchedSkillsHtml && !pendingSkillsHtml) ? '<span class="text-sm text-slate-400 font-medium">Standard requirements apply.</span>' : ''}
                            </div>
                        </div>

                        <!-- Full Job Description -->
                        <div class="space-y-4">
                            <h3 class="text-base font-extrabold text-slate-900">Job Overview</h3>
                            <div class="text-sm text-slate-600 leading-relaxed space-y-3">
                                <p>We are seeking a qualified candidate for the position of <strong>${escapeHtml(job.title)}</strong> at <strong>${escapeHtml(job.company)}</strong>. This role is offered as a <strong>${escapeHtml(job.employment_type)}</strong> opportunity situated in <strong>${escapeHtml(job.municipality)}</strong>.</p>
                                <p>Minimum required experience: <strong>${job.experience == 0 ? 'No prior experience required' : job.experience + ' year(s)'}</strong>.</p>
                                <p>Applications submitted through S.I.K.A.P. Hub are evaluated using automated point-in-time scoring against employer job requirements.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Details -->
                    <div class="mt-8 pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-slate-400">
                        <span>Employer Status: <strong class="${job.verified ? 'text-emerald-600' : 'text-amber-600'}">${job.verified ? 'Verified Employer' : 'Pending Verification'}</strong></span>
                        <span>Posted on ${job.date_posted}</span>
                    </div>
                `;
            }

            // 2. Select First Job by Default
            if (jobCards.length > 0) {
                const firstJobId = jobCards[0].getAttribute('data-job-id');
                renderJobDetail(firstJobId);
            }

            // 3. Event Listeners for Clicking Job Cards
            jobCards.forEach(card => {
                card.addEventListener('click', function () {
                    const jobId = this.getAttribute('data-job-id');
                    renderJobDetail(jobId);
                });
            });

            // 4. Live Search Filtering
            const searchForm = document.getElementById('search-form');
            const keywordInput = document.getElementById('search-keyword');
            const locationInput = document.getElementById('search-location');
            const countBadge = document.getElementById('job-count-badge');

            function filterJobs() {
                const kw = (keywordInput ? keywordInput.value : '').toLowerCase().trim();
                const loc = (locationInput ? locationInput.value : '').toLowerCase().trim();
                let visibleCount = 0;
                let firstVisibleId = null;

                jobCards.forEach(card => {
                    const jobId = card.getAttribute('data-job-id');
                    const job = jobsData[jobId];
                    if (!job) return;

                    const textStr = (job.title + ' ' + job.company + ' ' + job.municipality + ' ' + job.employment_type).toLowerCase();
                    const matchesKw = !kw || textStr.includes(kw);
                    const matchesLoc = !loc || textStr.includes(loc);

                    if (matchesKw && matchesLoc) {
                        card.style.display = '';
                        visibleCount++;
                        if (!firstVisibleId) firstVisibleId = jobId;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (countBadge) countBadge.textContent = visibleCount;

                if (firstVisibleId) {
                    renderJobDetail(firstVisibleId);
                }
            }

            if (keywordInput) keywordInput.addEventListener('input', filterJobs);
            if (locationInput) locationInput.addEventListener('input', filterJobs);
            if (searchForm) {
                searchForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    filterJobs();
                });
            }

            // 5. Quick Filter Pills
            const filterPills = document.querySelectorAll('.filter-pill');
            filterPills.forEach(pill => {
                pill.addEventListener('click', function () {
                    filterPills.forEach(p => {
                        p.classList.remove('bg-blue-600', 'text-white', 'active-pill');
                        p.classList.add('bg-white', 'text-slate-700');
                    });
                    this.classList.remove('bg-white', 'text-slate-700');
                    this.classList.add('bg-blue-600', 'text-white', 'active-pill');

                    const filterType = this.getAttribute('data-filter');
                    let visibleCount = 0;
                    let firstVisibleId = null;

                    jobCards.forEach(card => {
                        const isVerified = card.getAttribute('data-verified') === '1';
                        const score = parseInt(card.getAttribute('data-score') || '0', 10);
                        let matches = true;

                        if (filterType === 'verified' && !isVerified) matches = false;
                        if (filterType === 'high-match' && score < 80) matches = false;
                        if (filterType === 'saved' && card.getAttribute('data-saved') !== '1') matches = false;

                        if (matches) {
                            card.style.display = '';
                            visibleCount++;
                            if (!firstVisibleId) firstVisibleId = card.getAttribute('data-job-id');
                        } else {
                            card.style.display = 'none';
                        }
                    });

                    if (countBadge) countBadge.textContent = visibleCount;
                    if (firstVisibleId) renderJobDetail(firstVisibleId);
                });
            });

            // 5b. Toggle Save Job Click Handler (AJAX)
            document.addEventListener('click', function (e) {
                const saveBtn = e.target.closest('.btn-toggle-save');
                if (!saveBtn) return;
                
                e.preventDefault();
                e.stopPropagation();

                const jobId = saveBtn.getAttribute('data-job-id');
                if (!jobId) return;

                const formData = new FormData();
                formData.append('job_id', jobId);
                formData.append('csrf_token', csrfToken);

                fetch('/jobseeker/toggle-save-job', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const isSaved = data.is_saved;

                    if (jobsData[jobId]) {
                        jobsData[jobId].is_saved = isSaved;
                    }

                    document.querySelectorAll(`.btn-toggle-save[data-job-id="${jobId}"]`).forEach(btn => {
                        btn.setAttribute('title', isSaved ? 'Remove from Saved' : 'Save Job');
                        const svg = btn.querySelector('svg');
                        const textSpan = btn.querySelector('span');

                        if (isSaved) {
                            btn.classList.add('text-primary', 'bg-blue-50', 'border-primary');
                            btn.classList.remove('text-slate-300', 'text-slate-600', 'border-slate-200');
                            if (svg) svg.setAttribute('class', 'w-4 h-4 text-primary fill-primary');
                            if (textSpan) textSpan.textContent = 'Saved';
                        } else {
                            btn.classList.remove('text-primary', 'bg-blue-50', 'border-primary');
                            btn.classList.add('text-slate-600', 'border-slate-200');
                            if (svg) svg.setAttribute('class', 'w-4 h-4 text-slate-400 fill-none');
                            if (textSpan) textSpan.textContent = 'Save Job';
                        }
                    });

                    const leftCard = document.querySelector(`.job-card[data-job-id="${jobId}"]`);
                    if (leftCard) {
                        leftCard.setAttribute('data-saved', isSaved ? '1' : '0');
                    }
                })
                .catch(err => console.error('Failed to toggle save job:', err));
            });

            // 6. User Avatar Dropdown Toggle
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
            // 7. Auto Sync & BFCache Back Navigation Handler
            syncAppliedJobsFromStorage();

            window.addEventListener('pageshow', function (event) {
                const isBackNavigation = event.persisted || 
                    (window.performance && window.performance.getEntriesByType && 
                     window.performance.getEntriesByType('navigation')[0] && 
                     window.performance.getEntriesByType('navigation')[0].type === 'back_forward');

                if (isBackNavigation) {
                    window.location.reload();
                } else {
                    syncAppliedJobsFromStorage();
                }
            });

            // 8. Instant UI Update on Form Submit
            document.addEventListener('submit', function (e) {
                const form = e.target.closest('form[action*="/apply"]');
                if (form) {
                    const jobIdInput = form.querySelector('input[name="job_id"]');
                    if (jobIdInput && jobIdInput.value) {
                        const jobId = jobIdInput.value;
                        try {
                            sessionStorage.setItem('sikap_applied_job_' + jobId, 'true');
                            localStorage.setItem('sikap_applied_job_' + jobId, 'true');
                        } catch (err) {}

                        if (jobsData[jobId]) {
                            jobsData[jobId].has_applied = true;
                        }

                        const parent = form.parentNode;
                        if (parent) {
                            parent.innerHTML = `
                                <div class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 font-bold px-6 py-3 rounded-full text-sm">
                                    <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                    Application Submitted
                                </div>
                            `;
                        }
                    }
                }
            });
        });

        // Helper to escape HTML characters
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</body>
</html>