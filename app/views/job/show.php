<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['job_title'] ?? 'Job Details'); ?> - S.I.K.A.P. Hub</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    <link rel="stylesheet" href="/public/assets/css/theme.css">

    <script src="/public/assets/js/tailwind.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
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
</head>
<body class="bg-app-bg text-slate-800 antialiased min-h-screen flex flex-col">
    <!-- Navigation Bar -->
    <?php
        $backUrl = '/dashboard';
        $backLabel = '← Back to Dashboard';
        if (($_GET['from'] ?? '') === 'saved-jobs' || (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'saved-jobs') !== false)) {
            $backUrl = '/saved-jobs';
            $backLabel = '← Back to Saved Jobs';
        }
    ?>
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="/dashboard" class="flex items-center gap-2.5">
                <img src="/public/assets/images/logo-icon.png" alt="SikapHub" class="w-8 h-8 rounded-lg object-contain flex-shrink-0">
                <span class="text-[#173b72] font-extrabold text-xl tracking-tight">SIKAPHUB</span>
            </a>
            <a href="<?php echo $backUrl; ?>" class="text-sm font-semibold text-slate-600 hover:text-indigo-600 transition-colors"><?php echo $backLabel; ?></a>
        </div>
    </nav>

    <!-- Job Details Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex-grow w-full">
        <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900"><?php echo htmlspecialchars($job['job_title'] ?? ''); ?></h1>
                    <p class="text-primary font-bold text-sm mt-1">
                        <a href="/company/view?id=<?php echo htmlspecialchars($job['employer_id'] ?? 0); ?>" class="hover:underline flex items-center gap-1">
                            🏢 <?php echo htmlspecialchars($job['company_name'] ?? ''); ?>
                        </a>
                    </p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <?php echo htmlspecialchars($job['job_status'] ?? 'Open'); ?>
                </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-8 p-4 bg-slate-50 rounded-xl border border-slate-100 text-sm">
                <div>
                    <span class="text-slate-400 block text-xs">Location</span>
                    <span class="font-semibold text-slate-700">
                        <?php
                        $loc = $job['municipality_name'] ?? 'N/A';
                        if (!empty($job['province_name'])) {
                            $loc .= ', ' . $job['province_name'];
                        }
                        echo htmlspecialchars($loc);
                        ?>
                    </span>
                </div>
                <div>
                    <span class="text-slate-400 block text-xs">Job Type</span>
                    <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($job['employment_type'] ?? 'N/A'); ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-xs">Work Arrangement</span>
                    <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($job['work_arrangement'] ?? 'N/A'); ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-xs">Minimum Experience</span>
                    <span class="font-semibold text-slate-700">
                        <?php
                        $yrs = $job['min_years_experience'] ?? null;
                        echo $yrs === null
                            ? 'N/A'
                            : ((int) $yrs === 0
                                ? 'No experience required'
                                : (int) $yrs . ' year' . ((int) $yrs === 1 ? '' : 's'));
                        ?>
                    </span>
                </div>
                <div>
                    <span class="text-slate-400 block text-xs">Salary Range</span>
                    <span class="font-semibold text-slate-700">
                        <?php
                            $salVal = trim($job['salary_range'] ?? '');
                            if ($salVal === '') {
                                echo 'Not disclosed';
                            } elseif (preg_match('/^(₱|php|\$|eur|£)/i', $salVal)) {
                                echo htmlspecialchars($salVal);
                            } else {
                                echo '₱' . htmlspecialchars($salVal);
                            }
                        ?>
                    </span>
                </div>

            </div>

            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 mb-2">Job Description</h2>
                    <p class="text-slate-600 leading-relaxed whitespace-pre-line"><?php echo htmlspecialchars($job['job_description'] ?? 'No description provided.'); ?></p>
                </div>

                <?php if (!empty($job['company_description'])): ?>
                <div class="border-t border-slate-100 pt-6">
                    <h2 class="text-lg font-bold text-slate-900 mb-2">About the Company</h2>
                    <p class="text-slate-600 leading-relaxed"><?php echo htmlspecialchars($job['company_description']); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <input type="hidden" id="global-csrf-token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="border-t border-slate-100 mt-8 pt-6 flex justify-between items-center">
                <a href="<?php echo $backUrl; ?>" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Back</a>
                <div class="flex items-center gap-3">
                    <button type="button" id="btn-show-save" data-job-id="<?php echo htmlspecialchars($job['job_id'] ?? 0); ?>" class="px-4 py-2.5 border <?php echo !empty($is_saved) ? 'border-primary bg-blue-50 text-primary' : 'border-slate-200 hover:border-slate-300 text-slate-700'; ?> rounded-xl font-bold text-sm transition-all flex items-center gap-1.5 shadow-xs">
                        <svg class="w-4 h-4 <?php echo !empty($is_saved) ? 'text-primary fill-primary' : 'text-slate-400 fill-none'; ?>" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/></svg>
                        <span id="save-btn-text"><?php echo !empty($is_saved) ? '✓ Saved Job' : 'Save Job'; ?></span>
                    </button>
                    <?php if (!empty($has_applied)): ?>
                        <span class="px-6 py-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold text-sm rounded-xl shadow-xs inline-flex items-center gap-1.5">
                            ✓ Application Submitted
                        </span>
                    <?php else: ?>
                        <form method="POST" action="/apply" id="show-apply-form">
                            <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($job['job_id'] ?? 0); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-xl transition-colors shadow-sm">
                                1-Click Apply
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        window.addEventListener('pageshow', function (event) {
            if (event.persisted || (window.performance && window.performance.getEntriesByType && window.performance.getEntriesByType('navigation')[0] && window.performance.getEntriesByType('navigation')[0].type === 'back_forward')) {
                window.location.reload();
            }
        });

        const form = document.getElementById('show-apply-form');
        if (form) {
            form.addEventListener('submit', function () {
                const jobId = form.querySelector('input[name="job_id"]').value;
                try {
                    sessionStorage.setItem('sikap_applied_job_' + jobId, 'true');
                    localStorage.setItem('sikap_applied_job_' + jobId, 'true');
                } catch (e) {}
            });
        }

        const saveBtn = document.getElementById('btn-show-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                const jobId = this.getAttribute('data-job-id');
                const csrfInput = document.getElementById('global-csrf-token');
                const csrfToken = csrfInput ? csrfInput.value : '';
                if (!jobId || !csrfToken) return;

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
                    const svg = saveBtn.querySelector('svg');
                    const textSpan = document.getElementById('save-btn-text');

                    if (isSaved) {
                        saveBtn.className = "px-4 py-2.5 border border-primary bg-blue-50 text-primary font-bold text-sm rounded-xl transition-all flex items-center gap-1.5 shadow-xs";
                        if (svg) svg.setAttribute('class', 'w-4 h-4 text-primary fill-primary');
                        if (textSpan) textSpan.textContent = '✓ Saved Job';
                    } else {
                        saveBtn.className = "px-4 py-2.5 border border-slate-200 hover:border-slate-300 text-slate-700 font-bold text-sm rounded-xl transition-all flex items-center gap-1.5 shadow-xs";
                        if (svg) svg.setAttribute('class', 'w-4 h-4 text-slate-400 fill-none');
                        if (textSpan) textSpan.textContent = 'Save Job';
                    }
                });
            });
        }
    </script>
</body>
</html>