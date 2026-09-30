<?php
/**
 * View: Admin Job Postings Moderation Directory
 */
$activeTab = 'jobs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Postings Moderation | S.I.K.A.P. Hub Admin</title>
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
                    colors: { primary: '#4338ca', 'primary-hover': '#3730a3', secondary: '#0d9488' }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 min-h-screen font-sans text-slate-800 antialiased flex flex-col">

    <?php require_once BASE_PATH . 'app/views/components/admin_header.php'; ?>

    <main class="flex-1 max-w-7xl w-full mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Page Title & Filter Bar -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Job Postings Moderation</h1>
                <p class="text-slate-500 text-sm mt-0.5">Monitor all live, closed, or suspended vacancies across registered employers.</p>
            </div>
            <form method="GET" action="/admin/jobs" class="flex items-center gap-2">
                <div class="relative">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search job title or company..." class="pl-9 pr-3 py-2 bg-white rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-xs w-56 md:w-64">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <select name="status" class="px-3 py-2 bg-white rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-xs">
                    <option value="">All Statuses</option>
                    <option value="Open" <?php echo $status === 'Open' ? 'selected' : ''; ?>>Open</option>
                    <option value="Closed" <?php echo $status === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                    <option value="Suspended" <?php echo $status === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition-all shadow-xs">Filter</button>
            </form>
        </div>

        <!-- Simplified User Notifications -->
        <?php
        $succMsg = ErrorHelper::translate($_GET['success'] ?? '');
        $errMsg  = ErrorHelper::translate($_GET['error'] ?? '');
        ?>
        <?php if ($succMsg !== ''): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-semibold flex items-center gap-3 shadow-xs">
                <span class="text-base">✅</span>
                <span><?php echo htmlspecialchars($succMsg); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($errMsg !== ''): ?>
            <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-sm font-semibold flex items-center gap-3 shadow-xs">
                <span class="text-base">⚠️</span>
                <span><?php echo htmlspecialchars($errMsg); ?></span>
            </div>
        <?php endif; ?>

        <!-- Table Container -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-100/70 text-slate-700 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="p-4">Job Vacancy Title</th>
                            <th class="p-4">Employer Company</th>
                            <th class="p-4">Location & Work Setup</th>
                            <th class="p-4">Applications</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Moderation Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($jobs)): ?>
                            <tr>
                                <td colspan="6" class="p-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="text-3xl">💼</span>
                                        <p class="font-medium text-slate-500">No job postings found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jobs as $j): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors border-b border-slate-100">
                                    <td class="p-4 font-bold text-slate-900 text-base">
                                        <a href="/job/view?id=<?php echo (int)$j['job_id']; ?>" target="_blank" class="hover:text-indigo-600 transition-colors inline-flex items-center gap-1.5">
                                            <span><?php echo htmlspecialchars($j['job_title']); ?></span>
                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </td>
                                    <td class="p-4 text-slate-800 font-semibold"><?php echo htmlspecialchars($j['company_name']); ?></td>
                                    <td class="p-4 text-slate-600">
                                        <span class="inline-flex items-center gap-1">
                                            📍 <?php echo htmlspecialchars($j['municipality_name'] ?? 'Guimba'); ?> &bull; <?php echo htmlspecialchars($j['work_setup'] ?? 'On-site'); ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center gap-1 font-extrabold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-full text-xs border border-indigo-100">
                                            👥 <?php echo (int) $j['applicant_count']; ?> Applicants
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <?php
                                        $badge = match($j['job_status']) {
                                            'Open'      => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-bold',
                                            'Suspended' => 'bg-rose-100 text-rose-800 border-rose-300 font-bold',
                                            default     => 'bg-slate-100 text-slate-700 border-slate-300 font-bold'
                                        };
                                        ?>
                                        <span class="px-2.5 py-1 text-xs rounded-full border shadow-2xs <?php echo $badge; ?>">
                                            <?php echo htmlspecialchars($j['job_status']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <form method="POST" action="/admin/toggle-job-status" class="inline-flex gap-2">
                                            <?php echo CSRF::csrfField(); ?>
                                            <input type="hidden" name="job_id" value="<?php echo (int) $j['job_id']; ?>">
                                            <?php if ($j['job_status'] !== 'Open'): ?>
                                                <button type="submit" name="status" value="Open" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors shadow-xs">
                                                    Re-open
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($j['job_status'] !== 'Suspended'): ?>
                                                <button type="submit" name="status" value="Suspended" class="bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors shadow-xs">
                                                    Suspend
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
