<?php
/**
 * View: PESO Admin Command Center Dashboard
 * Sprint 6 — Admin Command Center Overhaul
 *
 * Variables expected from AdminController:
 *   @var array $kpis             ['total_seekers', 'total_employers', 'verified_employers', 'pending_employers', 'active_jobs', 'pending_skills']
 *   @var array $geography        [ ['municipality_name' => ..., 'seeker_count' => ...], ... ]
 *   @var array $top_skills       [ ['skill_name' => ..., 'demand_count' => ...], ... ]
 *   @var array $pending_employers[ ['employer_id', 'company_name', 'contact_person', 'company_phone', 'business_permit_file', 'verification_status', 'ai_feedback'], ... ]
 *   @var array $pending_skills   [ ['skill_id', 'skill_name', 'suggested_by', 'category_name'], ... ]
 *   @var string $admin_name      Display name for the logged-in admin.
 */
$activeTab = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="S.I.K.A.P. Hub V2 — PESO Admin Command Center. Manage employer verifications, custom skill approvals, and platform analytics.">
    <title>Admin Command Center | S.I.K.A.P. Hub V2</title>

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
                    colors: { primary: '#4338ca', 'primary-hover': '#3730a3', secondary: '#0d9488' }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 min-h-screen font-sans text-slate-800 antialiased flex flex-col">

    <?php require_once BASE_PATH . 'app/views/components/admin_header.php'; ?>

    <main class="flex-1 max-w-7xl w-full mx-auto py-8 px-4 sm:px-6 lg:px-8">

        <!-- Page Header Banner -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-100 text-indigo-800 text-xs font-bold border border-indigo-200 mb-2">
                    <span>🔐 PESO Administrator Command Center</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Executive Platform Overview</h1>
                <p class="text-slate-500 text-sm mt-0.5">Real-time platform analytics, verification queues, and AI skill moderation.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="/sikaphub/admin/export" target="_blank" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export Executive PDF Report
                </a>
            </div>
        </div>

        <!-- KPI Stat Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <!-- Card 1: Job Seekers -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition-all flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-2xl font-bold flex-shrink-0">
                    👨‍💼
                </div>
                <div>
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Total Job Seekers</div>
                    <div class="text-2xl font-extrabold text-slate-900 mt-0.5"><?php echo number_format($kpis['total_seekers'] ?? 0); ?></div>
                </div>
            </div>

            <!-- Card 2: Verified Employers -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition-all flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-teal-50 text-teal-600 border border-teal-100 flex items-center justify-center text-2xl font-bold flex-shrink-0">
                    🏢
                </div>
                <div>
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Verified Employers</div>
                    <div class="text-2xl font-extrabold text-slate-900 mt-0.5">
                        <?php echo number_format($kpis['verified_employers'] ?? 0); ?>
                        <span class="text-xs font-semibold text-slate-400">/ <?php echo number_format($kpis['total_employers'] ?? 0); ?></span>
                    </div>
                </div>
            </div>

            <!-- Card 3: Active Vacancies -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition-all flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center text-2xl font-bold flex-shrink-0">
                    💼
                </div>
                <div>
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Active Job Vacancies</div>
                    <div class="text-2xl font-extrabold text-slate-900 mt-0.5"><?php echo number_format($kpis['active_jobs'] ?? 0); ?></div>
                </div>
            </div>

            <!-- Card 4: Pending Actions -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition-all flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center text-2xl font-bold flex-shrink-0">
                    ⏳
                </div>
                <div>
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Pending Actions</div>
                    <div class="text-2xl font-extrabold text-rose-600 mt-0.5">
                        <?php echo number_format(($kpis['pending_employers'] ?? 0) + ($kpis['pending_skills'] ?? 0)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metrics Section: Regional Seeker Distribution & Skill Demand -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Seeker Geographic Distribution -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden flex flex-col">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="text-xl">📍</span>
                        <div>
                            <h3 class="font-extrabold text-slate-900 text-base">Seekers by Municipality</h3>
                            <p class="text-xs text-slate-400">Geographic distribution across Guimba & Nueva Ecija</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 flex-1">
                    <?php if (empty($geography)): ?>
                        <div class="p-8 text-center text-slate-400">No geographic distribution data recorded yet.</div>
                    <?php else:
                        $maxGeo = max(array_column($geography, 'seeker_count')) ?: 1;
                    ?>
                        <div class="space-y-3">
                            <?php foreach ($geography as $i => $geo): ?>
                                <div class="flex items-center gap-3 text-xs font-semibold">
                                    <span class="w-5 text-slate-400 text-right font-mono"><?php echo $i + 1; ?></span>
                                    <span class="w-36 text-slate-800 truncate font-bold"><?php echo htmlspecialchars($geo['municipality_name']); ?></span>
                                    <div class="flex-1 bg-slate-100 h-2.5 rounded-full overflow-hidden shadow-inner">
                                        <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-500" style="width: <?php echo round(($geo['seeker_count'] / $maxGeo) * 100); ?>%;"></div>
                                    </div>
                                    <span class="w-8 text-right font-extrabold text-indigo-700 font-mono"><?php echo htmlspecialchars($geo['seeker_count']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top 5 In-Demand Skills -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden flex flex-col">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="text-xl">🏅</span>
                        <div>
                            <h3 class="font-extrabold text-slate-900 text-base">Top In-Demand Skills</h3>
                            <p class="text-xs text-slate-400">Highest requested skills from active job postings</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 flex-1">
                    <?php if (empty($top_skills)): ?>
                        <div class="p-8 text-center text-slate-400">No skill demand data recorded yet.</div>
                    <?php else:
                        $maxSkill = max(array_column($top_skills, 'demand_count')) ?: 1;
                    ?>
                        <div class="space-y-3">
                            <?php foreach ($top_skills as $rank => $skill): ?>
                                <div class="flex items-center gap-3 text-xs font-semibold">
                                    <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-800 font-extrabold flex items-center justify-center text-[10px] flex-shrink-0">
                                        #<?php echo $rank + 1; ?>
                                    </span>
                                    <span class="w-36 text-slate-800 truncate font-bold"><?php echo htmlspecialchars($skill['skill_name']); ?></span>
                                    <div class="flex-1 bg-slate-100 h-2.5 rounded-full overflow-hidden shadow-inner">
                                        <div class="bg-amber-500 h-2.5 rounded-full transition-all duration-500" style="width: <?php echo round(($skill['demand_count'] / $maxSkill) * 100); ?>%;"></div>
                                    </div>
                                    <span class="w-8 text-right font-extrabold text-amber-700 font-mono"><?php echo htmlspecialchars($skill['demand_count']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Verification Queue: Pending Employers -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden mb-8">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="text-xl">🏢</span>
                    <div>
                        <h3 class="font-extrabold text-slate-900 text-base">Employer Verification Queue</h3>
                        <p class="text-xs text-slate-400">Review business permits and authorize employer accounts</p>
                    </div>
                </div>
                <?php if (!empty($pending_employers)): ?>
                    <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-extrabold border border-amber-200">
                        <?php echo count($pending_employers); ?> Pending Review
                    </span>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-100/70 text-slate-700 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="p-4">Company Name</th>
                            <th class="p-4">Contact Info</th>
                            <th class="p-4">Business Permit & AI Status</th>
                            <th class="p-4 text-right">Verification Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($pending_employers)): ?>
                            <tr>
                                <td colspan="4" class="p-10 text-center text-slate-400 font-medium">
                                    ✨ All employer business permits have been verified. Verification queue is empty!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_employers as $emp): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="p-4 font-bold text-slate-900 text-base">
                                        <?php echo htmlspecialchars($emp['company_name']); ?>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($emp['contact_person']); ?></div>
                                        <div class="text-xs text-slate-400"><?php echo htmlspecialchars($emp['company_phone']); ?></div>
                                    </td>
                                    <td class="p-4">
                                        <?php
                                        $vStatus = $emp['verification_status'] ?? 'pending';
                                        $vBadgeClass = match($vStatus) {
                                            'green_flag' => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-extrabold',
                                            'red_flag'   => 'bg-rose-100 text-rose-800 border-rose-300 font-extrabold',
                                            default      => 'bg-amber-100 text-amber-800 border-amber-300 font-bold'
                                        };
                                        $vBadgeText = match($vStatus) {
                                            'green_flag' => '🟢 Green Flag',
                                            'red_flag'   => '🔴 Red Flag',
                                            default      => '🟡 Pending Review'
                                        };
                                        ?>
                                        <div class="flex items-center gap-2">
                                            <span class="px-2.5 py-0.5 text-xs rounded-full border shadow-2xs <?php echo $vBadgeClass; ?>">
                                                <?php echo $vBadgeText; ?>
                                            </span>
                                            <?php if (!empty($emp['business_permit_file'])): ?>
                                                <a href="javascript:void(0)" onclick="openPermitModal('/sikaphub/admin/view-document?file=<?php echo urlencode($emp['business_permit_file']); ?>', '<?php echo htmlspecialchars(addslashes($emp['company_name']), ENT_QUOTES); ?>')" class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200 hover:bg-indigo-100 cursor-pointer">
                                                    📄 View Document
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <form method="POST" action="/sikaphub/admin/verify-employer" class="inline-flex gap-2">
                                            <?php echo CSRF::csrfField(); ?>
                                            <input type="hidden" name="employer_id" value="<?php echo (int)$emp['employer_id']; ?>">
                                            <button type="submit" name="status" value="Verified" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors shadow-xs">
                                                Approve
                                            </button>
                                            <button type="submit" name="status" value="Rejected" class="bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors shadow-xs">
                                                Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Skill Moderation Queue: Pending Custom Skills -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden mb-8">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="text-xl">💡</span>
                    <div>
                        <h3 class="font-extrabold text-slate-900 text-base">Pending Custom Skill Suggestions</h3>
                        <p class="text-xs text-slate-400">Review skills proposed by job seekers and approve into master taxonomy</p>
                    </div>
                </div>
                <?php if (!empty($pending_skills)): ?>
                    <span class="px-3 py-1 rounded-full bg-indigo-100 text-indigo-800 text-xs font-extrabold border border-indigo-200">
                        <?php echo count($pending_skills); ?> Awaiting Approval
                    </span>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-100/70 text-slate-700 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="p-4">Proposed Skill Name</th>
                            <th class="p-4">Category</th>
                            <th class="p-4">Suggested By</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($pending_skills)): ?>
                            <tr>
                                <td colspan="4" class="p-10 text-center text-slate-400 font-medium">
                                    🧩 No custom seeker skills pending approval.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_skills as $ps): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="p-4 font-bold text-slate-900 text-base">
                                        <?php echo htmlspecialchars($ps['skill_name']); ?>
                                    </td>
                                    <td class="p-4 text-slate-600 font-semibold">
                                        <span class="bg-slate-100 text-slate-700 px-2.5 py-1 rounded-lg border border-slate-200 text-xs">
                                            <?php echo htmlspecialchars($ps['category_name'] ?? 'Uncategorised'); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-xs text-slate-500 font-medium">
                                        <?php echo htmlspecialchars($ps['suggested_by'] ?? 'Registered Job Seeker'); ?>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="inline-flex gap-2">
                                            <form method="POST" action="/sikaphub/admin/approve-skill" class="inline">
                                                <?php echo CSRF::csrfField(); ?>
                                                <input type="hidden" name="skill_id" value="<?php echo (int)$ps['skill_id']; ?>">
                                                <button type="submit" name="action" value="approve" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors shadow-xs">Approve</button>
                                            </form>
                                            <form method="POST" action="/sikaphub/admin/approve-skill" onsubmit="return confirm('Delete this custom skill proposal?')" class="inline">
                                                <?php echo CSRF::csrfField(); ?>
                                                <input type="hidden" name="skill_id" value="<?php echo (int)$ps['skill_id']; ?>">
                                                <button type="submit" name="action" value="delete" class="bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors shadow-xs">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- In-Page Business Permit Viewer Modal -->
    <div id="permitModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 sm:p-6 transition-all duration-200">
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-5xl h-[85vh] flex flex-col overflow-hidden">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
                        📄
                    </div>
                    <div>
                        <h3 id="permitModalTitle" class="text-base font-bold text-slate-900">Business Permit Document</h3>
                        <p class="text-xs text-slate-500">PESO Verified Municipal Business License / Mayor's Permit</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a id="permitModalExternalLink" href="#" target="_blank" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-3 py-1.5 rounded-lg border border-indigo-200 transition-colors flex items-center gap-1">
                        ↗ Open in New Window
                    </a>
                    <button type="button" onclick="closePermitModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 font-bold transition-colors">
                        ✕
                    </button>
                </div>
            </div>

            <!-- Modal Content (Document Viewer Iframe) -->
            <div class="flex-1 bg-slate-100 relative p-2">
                <iframe id="permitModalFrame" src="about:blank" class="w-full h-full rounded-xl border border-slate-200 bg-white" title="Business Permit Preview"></iframe>
            </div>
        </div>
    </div>

    <script>
        function openPermitModal(url, companyName) {
            const modal   = document.getElementById('permitModal');
            const frame   = document.getElementById('permitModalFrame');
            const title   = document.getElementById('permitModalTitle');
            const extLink = document.getElementById('permitModalExternalLink');

            if (title)   title.textContent = (companyName ? companyName + ' — ' : '') + 'Business Permit';
            if (extLink) extLink.href = url;
            if (frame)   frame.src = url;

            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        }

        function closePermitModal() {
            const modal = document.getElementById('permitModal');
            const frame = document.getElementById('permitModalFrame');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
            if (frame) {
                frame.src = 'about:blank';
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closePermitModal();
        });
    </script>
</body>
</html>