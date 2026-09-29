<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($company['company_name'] ?? 'Company Profile'); ?> – S.I.K.A.P. Hub</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    <link rel="stylesheet" href="/sikaphub/public/assets/css/theme.css">

    <script src="/sikaphub/public/assets/js/tailwind.js"></script>
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
                            900: '#0f172a'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-app-bg text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Top Navigation Bar -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="/sikaphub/dashboard" class="flex items-center gap-2.5">
                <img src="/sikaphub/public/assets/images/logo-icon.png" alt="SikapHub" class="w-8 h-8 rounded-lg object-contain flex-shrink-0">
                <span class="text-[#173b72] font-extrabold text-xl tracking-tight">SIKAPHUB</span>
            </a>
            <a href="javascript:history.back()" class="text-sm font-semibold text-slate-600 hover:text-primary transition-colors flex items-center gap-1.5">
                &larr; Back
            </a>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="flex-grow max-w-6xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Company Header Hero Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs mb-8">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
                <div class="flex items-start sm:items-center gap-5">
                    <!-- Logo / Avatar -->
                    <div class="w-20 h-20 rounded-2xl bg-indigo-100 border-2 border-primary/20 flex items-center justify-center text-primary font-black text-2xl shadow-sm flex-shrink-0 overflow-hidden">
                        <?php if (!empty($company['company_logo'])): ?>
                            <img src="/sikaphub/admin/view-document?file=<?php echo htmlspecialchars($company['company_logo']); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?php echo htmlspecialchars(strtoupper(substr($company['company_name'] ?? 'C', 0, 2))); ?>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap mb-1.5">
                            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                                <?php echo htmlspecialchars($company['company_name'] ?? 'Company'); ?>
                            </h1>
                            <?php if (($company['verified_status'] ?? '') === 'Verified'): ?>
                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold px-3 py-0.5 rounded-full inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                    Verified Employer
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 text-xs sm:text-sm font-semibold text-slate-600">
                            <?php if (!empty($company['municipality_name'])): ?>
                                <span class="flex items-center gap-1">
                                    📍 <?php echo htmlspecialchars($company['municipality_name']); ?><?php echo !empty($company['province_name']) ? ', ' . htmlspecialchars($company['province_name']) : ''; ?>
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($company['industry'])): ?>
                                <span>•</span>
                                <span>🏷️ <?php echo htmlspecialchars($company['industry']); ?></span>
                            <?php endif; ?>

                            <?php if (!empty($company['company_size'])): ?>
                                <span>•</span>
                                <span>👥 <?php echo htmlspecialchars($company['company_size']); ?> Employees</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto pt-4 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                    <span class="bg-blue-50 text-blue-700 font-extrabold text-xs px-4 py-2.5 rounded-xl border border-blue-100">
                        💼 <?php echo count($open_jobs); ?> Open Vacancies
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2-Cols: About & Vacancies -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- About Company Card -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs">
                    <h2 class="text-lg font-black text-slate-900 mb-3 tracking-tight flex items-center gap-2">
                        <span>🏢</span> About the Company
                    </h2>
                    <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line">
                        <?php echo htmlspecialchars($company['company_description'] ?? 'No company description provided.'); ?>
                    </p>
                </div>

                <!-- Open Vacancies List -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-black text-slate-900 tracking-tight flex items-center gap-2">
                            <span>💼</span> Active Job Openings
                            <span class="bg-slate-100 text-slate-700 text-xs px-2.5 py-0.5 rounded-full font-bold"><?php echo count($open_jobs); ?></span>
                        </h2>
                    </div>

                    <?php if (!empty($open_jobs)): ?>
                        <div class="space-y-4">
                            <?php foreach ($open_jobs as $job): ?>
                                <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs hover:border-slate-300 hover:shadow-md transition-all flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                                            <span class="bg-blue-50 text-blue-700 text-[11px] font-bold px-2.5 py-0.5 rounded-full border border-blue-100">
                                                <?php echo htmlspecialchars($job['employment_type'] ?? 'Full-time'); ?>
                                            </span>
                                            <span class="bg-slate-100 text-slate-700 text-[11px] font-semibold px-2.5 py-0.5 rounded-full">
                                                📍 <?php echo htmlspecialchars($job['work_arrangement'] ?? 'On-site'); ?>
                                            </span>
                                        </div>

                                        <h3 class="text-base font-extrabold text-slate-900 mb-1">
                                            <a href="/sikaphub/job/view?id=<?php echo $job['job_id']; ?>" class="hover:text-primary transition-colors">
                                                <?php echo htmlspecialchars($job['job_title']); ?>
                                            </a>
                                        </h3>
                                        
                                        <div class="flex items-center gap-3 text-xs font-semibold text-slate-500">
                                            <span class="text-emerald-700 font-extrabold bg-emerald-50 px-2 py-0.5 rounded-md">
                                                ₱ <?php echo htmlspecialchars($job['salary_range'] ?: 'Negotiable'); ?>
                                            </span>
                                            <span>Posted <?php echo date('M d, Y', strtotime($job['date_posted'])); ?></span>
                                        </div>
                                    </div>

                                    <a href="/sikaphub/job/view?id=<?php echo $job['job_id']; ?>" class="px-5 py-2.5 bg-primary hover:bg-primary-hover text-white text-xs font-bold rounded-xl shadow-xs transition-all flex items-center gap-1.5 w-full sm:w-auto justify-center">
                                        View Job &rarr;
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-2xl p-8 text-center border border-slate-200/80 shadow-xs">
                            <p class="text-sm font-semibold text-slate-500">No active job openings posted at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Right 1-Col: Company Metadata Sidebar -->
            <div class="space-y-6">
                <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs">
                    <h3 class="text-sm font-black text-slate-900 mb-4 uppercase tracking-wider text-slate-400">Company Highlights</h3>
                    
                    <div class="space-y-4 text-xs font-semibold text-slate-700">
                        <?php if (!empty($company['contact_person'])): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 text-sm">👤</div>
                                <div>
                                    <span class="text-slate-400 block text-[11px] font-normal">Contact Person</span>
                                    <span><?php echo htmlspecialchars($company['contact_person']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($company['company_phone'])): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 text-sm">📞</div>
                                <div>
                                    <span class="text-slate-400 block text-[11px] font-normal">Phone</span>
                                    <span><?php echo htmlspecialchars($company['company_phone']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($company['company_website'])): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 text-sm">🌐</div>
                                <div>
                                    <span class="text-slate-400 block text-[11px] font-normal">Website</span>
                                    <a href="<?php echo htmlspecialchars($company['company_website']); ?>" target="_blank" class="text-primary hover:underline truncate block max-w-[200px]">
                                        <?php echo htmlspecialchars($company['company_website']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($company['municipality_name'])): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 text-sm">📍</div>
                                <div>
                                    <span class="text-slate-400 block text-[11px] font-normal">Office Location</span>
                                    <span>
                                        <?php echo htmlspecialchars($company['street_name'] ? $company['street_name'] . ', ' : ''); ?>
                                        <?php echo htmlspecialchars($company['municipality_name']); ?>
                                        <?php echo !empty($company['province_name']) ? ', ' . htmlspecialchars($company['province_name']) : ''; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </main>
</body>
</html>
