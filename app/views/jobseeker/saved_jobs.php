<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Jobs – S.I.K.A.P. Hub</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    <meta name="description" content="View and manage your bookmarked saved job opportunities on S.I.K.A.P. Hub.">

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
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col antialiased">

    <!-- Top Navigation Bar -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <a href="/sikaphub/dashboard" class="flex items-center gap-2.5">
                    <img src="/sikaphub/public/assets/images/logo-icon.png" alt="SikapHub" class="w-8 h-8 rounded-lg object-contain flex-shrink-0">
                    <span class="font-extrabold text-xl tracking-tight text-[#031a3f]">Sikap<span class="bg-gradient-to-r from-[#009cfb] via-[#1769ff] to-[#9035ff] bg-clip-text text-transparent">hub</span></span>
                    <span class="bg-blue-50 text-blue-700 text-[10px] font-extrabold px-2 py-0.5 rounded-md uppercase tracking-wider border border-blue-100 hidden sm:inline-block">Jobseeker Portal</span>
                </a>
                
                <div class="flex items-center gap-6">
                    <a href="/sikaphub/dashboard" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">Find Jobs</a>
                    <a href="/sikaphub/saved-jobs" class="text-sm font-bold text-primary border-b-2 border-primary py-5">Saved Jobs</a>
                    <a href="/sikaphub/my-applications" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">My Applications</a>
                    
                    <!-- User Avatar & Dropdown -->
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

                        <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-100 py-2 z-50">
                            <div class="px-4 py-2 border-b border-slate-100">
                                <p class="text-xs text-slate-400 font-medium">Signed in as</p>
                                <p class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Job Seeker'); ?></p>
                            </div>
                            <a href="/sikaphub/build-profile" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                Edit Profile
                            </a>
                            <a href="/sikaphub/saved-jobs" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/></svg>
                                Saved Jobs
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

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header Title Banner -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <span class="text-primary">🔖</span> Saved Jobs &amp; Bookmarks
                </h1>
                <p class="text-slate-500 text-xs sm:text-sm mt-1">Review opportunities you have saved for later and apply when you are ready.</p>
            </div>
            <a href="/sikaphub/dashboard" class="px-4 py-2.5 bg-primary hover:bg-primary-hover text-white text-xs font-bold rounded-xl shadow-xs transition-all flex items-center gap-1.5">
                Browse More Jobs &rarr;
            </a>
        </div>

        <?php if (!empty($saved_jobs)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="saved-jobs-grid">
                <?php foreach ($saved_jobs as $job): ?>
                    <?php
                        $matchPct = (int)($job['match_percentage'] ?? 0);
                        $isScored = $job['final_score'] !== null;
                        $detailUrl = "/sikaphub/job/view?id=" . (int)$job['job_id'] . "&from=saved-jobs";
                    ?>
                    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs hover:border-slate-300 hover:shadow-md transition-all flex flex-col justify-between group" id="saved-card-<?php echo $job['job_id']; ?>">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="bg-blue-50 text-blue-700 border border-blue-100 text-[11px] font-bold px-2.5 py-0.5 rounded-full">
                                        <?php echo htmlspecialchars($job['employment_type'] ?? 'Full-time'); ?>
                                    </span>
                                    <?php if ($isScored): ?>
                                    <span class="bg-indigo-50 text-indigo-700 border border-indigo-100 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full">
                                        🔗 Match <?php echo $matchPct; ?>%
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn-remove-saved p-1.5 rounded-full text-rose-500 hover:bg-rose-50 transition-colors" data-job-id="<?php echo $job['job_id']; ?>" title="Remove Bookmark">
                                    <svg class="w-5 h-5 fill-rose-500 text-rose-500" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/></svg>
                                </button>
                            </div>

                            <h3 class="text-base font-extrabold text-slate-900 mb-1 leading-snug">
                                <button type="button" class="btn-open-modal text-left hover:text-primary transition-colors focus:outline-none" data-job-id="<?php echo $job['job_id']; ?>">
                                    <?php echo htmlspecialchars($job['job_title']); ?>
                                </button>
                            </h3>
                            <p class="text-xs font-bold text-slate-600 mb-3 flex items-center justify-between">
                                <span>
                                    <a href="/sikaphub/company/view?id=<?php echo htmlspecialchars($job['employer_id'] ?? 0); ?>" class="text-primary hover:underline font-bold">
                                        🏢 <?php echo htmlspecialchars($job['company_name']); ?>
                                    </a>
                                    <span class="font-normal text-slate-400">• <?php echo htmlspecialchars($job['municipality_name'] ?? 'Guimba'); ?></span>
                                </span>
                            </p>

                            <?php if (!empty($job['job_description'])): ?>
                            <p class="text-xs text-slate-500 line-clamp-2 mb-4 leading-relaxed">
                                <?php echo htmlspecialchars(mb_strimwidth(strip_tags($job['job_description']), 0, 120, '...')); ?>
                            </p>
                            <?php endif; ?>

                            <div class="flex flex-wrap items-center gap-2 mb-4 text-xs font-semibold text-slate-600">
                                <span class="bg-slate-100 px-2.5 py-1 rounded-md">📍 <?php echo htmlspecialchars($job['work_arrangement'] ?? 'On-site'); ?></span>
                                <span class="bg-emerald-50 text-emerald-700 font-extrabold px-2.5 py-1 rounded-md">₱ <?php echo htmlspecialchars($job['salary_range']); ?></span>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-2 flex-wrap">
                            <button type="button" class="btn-open-modal px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-all flex items-center gap-1 cursor-pointer" data-job-id="<?php echo $job['job_id']; ?>">
                                View Details &rarr;
                            </button>
                            
                            <?php if (!empty($job['application_id'])): ?>
                                <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200">✓ Applied</span>
                            <?php else: ?>
                                <form method="POST" action="/sikaphub/apply" class="inline-block">
                                    <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <button type="submit" class="px-4 py-1.5 bg-primary hover:bg-primary-hover text-white text-xs font-bold rounded-lg shadow-xs transition-all flex items-center gap-1">
                                        Apply Now &rarr;
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl p-12 text-center border border-slate-200 shadow-xs max-w-lg mx-auto">
                <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-3xl mx-auto mb-4">🔖</div>
                <h3 class="text-lg font-bold text-slate-900 mb-1">No Saved Jobs Yet</h3>
                <p class="text-xs text-slate-500 mb-6">Click the bookmark icon on any job card in your feed to save opportunities here.</p>
                <a href="/sikaphub/dashboard" class="px-6 py-3 bg-primary hover:bg-primary-hover text-white text-xs font-bold rounded-xl shadow-xs transition-all inline-block">
                    Explore Job Feed
                </a>
            </div>
        <?php endif; ?>

    </main>

    <!-- Job Details Modal Overlay -->
    <div id="job-details-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-slate-100 relative my-8 animate-in fade-in zoom-in-95 duration-200" id="modal-container">
            <button type="button" id="modal-close-btn" class="absolute top-5 right-5 text-slate-400 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 p-2 rounded-full transition-colors cursor-pointer" aria-label="Close modal">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <div id="modal-body-content">
                <!-- Populated dynamically via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Hidden Data Store for Instant Modal Rendering -->
    <script id="saved-jobs-data-json" type="application/json">
        <?php
            $jobsDict = [];
            if (!empty($saved_jobs)) {
                foreach ($saved_jobs as $sj) {
                    $jobsDict[$sj['job_id']] = [
                        'id' => (int)$sj['job_id'],
                        'employer_id' => (int)($sj['employer_id'] ?? 0),
                        'title' => $sj['job_title'],
                        'company' => $sj['company_name'],
                        'company_description' => $sj['company_description'] ?? '',
                        'verified' => ($sj['verified_status'] ?? '') === 'Verified',
                        'municipality' => $sj['municipality_name'] ?? 'Guimba, Nueva Ecija',
                        'employment_type' => $sj['employment_type'] ?? 'Full-time',
                        'work_arrangement' => $sj['work_arrangement'] ?? 'On-site',
                        'experience' => (int)($sj['min_years_experience'] ?? 0),
                        'salary' => !empty($sj['salary_range']) ? (preg_match('/^(₱|php|\$|eur|£)/i', trim($sj['salary_range'])) ? trim($sj['salary_range']) : '₱ ' . trim($sj['salary_range'])) : 'Negotiable',
                        'date_posted' => date('M d, Y', strtotime($sj['date_posted'])),
                        'saved_at' => date('M d, Y', strtotime($sj['saved_at'])),
                        'description' => $sj['job_description'] ?? 'No job description provided.',
                        'has_applied' => !empty($sj['application_id']),
                        'is_saved' => true,
                        'match_percentage' => $sj['match_percentage'] !== null ? (int)$sj['match_percentage'] : null
                    ];
                }
            }
            echo json_encode($jobsDict, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
    </script>

    <script>
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        const savedJobsData = JSON.parse(document.getElementById('saved-jobs-data-json').textContent || '{}');
        const modalBackdrop = document.getElementById('job-details-modal');
        const modalContent = document.getElementById('modal-body-content');
        const modalCloseBtn = document.getElementById('modal-close-btn');

        function openJobModal(jobId) {
            const job = savedJobsData[jobId];
            if (!job) {
                window.location.href = '/sikaphub/job/view?id=' + jobId + '&from=saved-jobs';
                return;
            }

            const expText = job.experience === 0 ? 'No experience required' : job.experience + ' year' + (job.experience === 1 ? '' : 's');

            let actionBtnHtml = '';
            if (job.has_applied) {
                actionBtnHtml = `
                    <span class="px-6 py-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold text-sm rounded-xl inline-flex items-center gap-1.5 shadow-xs">
                        ✓ Application Submitted
                    </span>
                `;
            } else {
                actionBtnHtml = `
                    <form method="POST" action="/sikaphub/apply" class="inline-block" id="modal-apply-form">
                        <input type="hidden" name="job_id" value="${job.id}">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" class="px-6 py-2.5 bg-primary hover:bg-primary-hover text-white font-bold text-sm rounded-xl transition-all shadow-sm flex items-center gap-2 cursor-pointer">
                            Apply Now &rarr;
                        </button>
                    </form>
                `;
            }

            modalContent.innerHTML = `
                <div class="mb-6 border-b border-slate-100 pb-5">
                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                        <span class="bg-blue-50 text-blue-700 border border-blue-100 text-[11px] font-bold px-2.5 py-0.5 rounded-full">
                            ${escapeHtml(job.employment_type)}
                        </span>
                        ${job.match_percentage !== null ? `
                        <span class="bg-indigo-50 text-indigo-700 border border-indigo-100 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full">
                            🔗 Match ${job.match_percentage}%
                        </span>` : ''}
                        ${job.verified ? `
                        <span class="bg-emerald-50 text-emerald-700 border border-emerald-100 text-[11px] font-bold px-2.5 py-0.5 rounded-full">
                            ✓ Verified Employer
                        </span>` : ''}
                    </div>

                    <h2 class="text-2xl font-black text-slate-900 leading-tight mb-1 pr-6">${escapeHtml(job.title)}</h2>
                    <p class="text-sm font-bold text-slate-600">
                        <a href="/sikaphub/company/view?id=${job.employer_id}" class="text-primary hover:underline font-bold">🏢 ${escapeHtml(job.company)}</a> <span class="font-normal text-slate-400">• ${escapeHtml(job.municipality)}</span>
                    </p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6 p-4 bg-slate-50 rounded-2xl border border-slate-100 text-xs">
                    <div>
                        <span class="text-slate-400 block mb-0.5">Salary Range</span>
                        <span class="font-extrabold text-emerald-700 text-sm">${escapeHtml(job.salary)}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-0.5">Work Arrangement</span>
                        <span class="font-bold text-slate-700">${escapeHtml(job.work_arrangement)}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-0.5">Minimum Experience</span>
                        <span class="font-bold text-slate-700">${escapeHtml(expText)}</span>
                    </div>
                </div>

                <div class="space-y-5 mb-8 max-h-[280px] overflow-y-auto pr-2">
                    <div>
                        <h4 class="text-xs uppercase font-extrabold text-slate-400 tracking-wider mb-2">Job Description</h4>
                        <div class="text-xs sm:text-sm text-slate-600 leading-relaxed whitespace-pre-line">${escapeHtml(job.description)}</div>
                    </div>

                    ${job.company_description ? `
                    <div class="border-t border-slate-100 pt-4">
                        <h4 class="text-xs uppercase font-extrabold text-slate-400 tracking-wider mb-2">About ${escapeHtml(job.company)}</h4>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">${escapeHtml(job.company_description)}</p>
                    </div>` : ''}
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3 flex-wrap">
                    <a href="/sikaphub/job/view?id=${job.id}&from=saved-jobs" onclick="window.location.href='/sikaphub/job/view?id=' + job.id + '&from=saved-jobs'; return false;" class="text-xs font-bold text-primary hover:text-primary-hover transition-colors underline cursor-pointer">
                        Open full page view ↗
                    </a>
                    <div class="flex items-center gap-3">
                        ${actionBtnHtml}
                    </div>
                </div>
            `;

            modalBackdrop.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeJobModal() {
            if (modalBackdrop) {
                modalBackdrop.classList.add('hidden');
                document.body.style.overflow = '';
            }
        }

        document.querySelectorAll('.btn-open-modal').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const jobId = this.getAttribute('data-job-id');
                if (jobId) {
                    openJobModal(jobId);
                }
            });
        });

        if (modalCloseBtn) {
            modalCloseBtn.addEventListener('click', closeJobModal);
        }

        if (modalBackdrop) {
            modalBackdrop.addEventListener('click', function (e) {
                if (e.target === modalBackdrop) {
                    closeJobModal();
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeJobModal();
            }
        });

        document.querySelectorAll('.btn-remove-saved').forEach(btn => {
            btn.addEventListener('click', function () {
                const jobId = this.getAttribute('data-job-id');
                if (!jobId) return;

                const formData = new FormData();
                formData.append('job_id', jobId);
                formData.append('csrf_token', "<?php echo $_SESSION['csrf_token']; ?>");

                fetch('/sikaphub/jobseeker/toggle-save-job', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && !data.is_saved) {
                        const card = document.getElementById('saved-card-' + jobId);
                        if (card) {
                            card.remove();
                        }
                        closeJobModal();
                        const grid = document.getElementById('saved-jobs-grid');
                        if (grid && grid.children.length === 0) {
                            window.location.reload();
                        }
                    }
                });
            });
        });

        // User menu dropdown toggle
        const menuBtn = document.getElementById('user-menu-button');
        const menuDropdown = document.getElementById('user-menu-dropdown');
        if (menuBtn && menuDropdown) {
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                menuDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', () => {
                menuDropdown.classList.add('hidden');
            });
        }
    </script>
</body>
</html>
