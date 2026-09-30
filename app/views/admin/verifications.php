<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Business Permit Auditing — PESO Admin</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    <meta name="description" content="Review employer business permit submissions and AI classification flags on S.I.K.A.P. Hub Admin.">

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
                        'app-bg': '#f8fafc'
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0f172a; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
    </style>
</head>

<body class="bg-slate-900 text-slate-100 antialiased min-h-screen flex flex-col">

<?php $activeTab = 'verifications'; require_once BASE_PATH . 'app/views/components/admin_header.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Top Header Banner -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Business Permit Verification Queue</h1>
                <p class="text-slate-400 text-sm mt-1">Review AI-assisted business permit audit classifications and authorize employer accounts.</p>
            </div>

            <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-slate-400">Welcome, <strong class="text-white"><?php echo htmlspecialchars($admin_name ?? 'PESO Admin'); ?></strong></span>
            </div>
        </div>

        <!-- Alert Banners -->
        <?php 
        $succText = ErrorHelper::translate($_GET['success'] ?? ($success ?? ''));
        $errText  = ErrorHelper::translate($_GET['error'] ?? ($error ?? ''));
        ?>
        <?php if (!empty($succText)): ?>
            <div class="mb-6 p-4 bg-emerald-950/80 border border-emerald-800 text-emerald-200 rounded-xl text-sm font-semibold flex items-center gap-3 shadow-lg">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><?php echo htmlspecialchars($succText); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errText)): ?>
            <div class="mb-6 p-4 bg-rose-950/80 border border-rose-800 text-rose-200 rounded-xl text-sm font-semibold flex items-center gap-3 shadow-lg">
                <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><?php echo htmlspecialchars($errText); ?></span>
            </div>
        <?php endif; ?>

        <!-- Queue Table Card -->
        <div class="glass-panel rounded-2xl shadow-xl overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Employer Submissions (<?php echo count($verifications ?? []); ?>)
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-800/90 text-xs font-bold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">Company Details</th>
                            <th class="px-6 py-3.5">Permit File</th>
                            <th class="px-6 py-3.5">AI Classification</th>
                            <th class="px-6 py-3.5">Admin Status</th>
                            <th class="px-6 py-3.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php if (empty($verifications)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-500 italic">No employer verification submissions found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($verifications as $emp): ?>
                                <?php 
                                    $aiStatus = strtolower($emp['verification_status'] ?? 'pending');
                                    $adminStatus = strtolower($emp['verified_status'] ?? $emp['admin_decision'] ?? 'pending');
                                    $permitFile = $emp['permit_file_path'] ?? $emp['business_permit_file'] ?? '';
                                ?>
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-white"><?php echo htmlspecialchars($emp['company_name'] ?? 'Unnamed Business'); ?></div>
                                        <div class="text-xs text-slate-400 mt-0.5"><?php echo htmlspecialchars($emp['user_email'] ?? $emp['company_email'] ?? ''); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if (!empty($permitFile)): ?>
                                            <a href="<?php echo htmlspecialchars($permitFile); ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-400 hover:text-sky-300 underline">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                <?php echo htmlspecialchars(basename($permitFile)); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-500 italic">Not Uploaded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($aiStatus === 'green_flag'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-950 text-emerald-300 border border-emerald-800">
                                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Green Flag (Valid)
                                            </span>
                                        <?php elseif ($aiStatus === 'red_flag'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-950 text-rose-300 border border-rose-800">
                                                <span class="w-2 h-2 rounded-full bg-rose-400"></span> Red Flag (Anomaly)
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-950 text-amber-300 border border-amber-800">
                                                <span class="w-2 h-2 rounded-full bg-amber-400"></span> Pending AI
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if (in_array($adminStatus, ['verified', 'approved'])): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-blue-900/80 text-blue-300 border border-blue-700">
                                                ✓ Verified
                                            </span>
                                        <?php elseif ($adminStatus === 'rejected'): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-800 text-rose-400 border border-rose-900">
                                                ✕ Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-800 text-amber-300 border border-slate-700">
                                                ⏳ Pending Decision
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="/admin/verifications?employer_id=<?php echo $emp['employer_id']; ?>" class="inline-flex items-center px-3.5 py-1.5 text-xs font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-sm transition-colors">
                                            Review Submission →
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detailed Employer Review Modal / Section -->
        <?php if (!empty($selectedEmployer)): ?>
            <?php 
                $emp = $selectedEmployer;
                $permitFile = $emp['permit_file_path'] ?? $emp['business_permit_file'] ?? '';
                $aiStatus = strtolower($emp['verification_status'] ?? 'pending');
                $adminStatus = strtolower($emp['verified_status'] ?? $emp['admin_decision'] ?? 'pending');
                $aiFeedback = $emp['ai_feedback'] ?? '';
                $extractedJson = $emp['extracted_permit_data'] ?? null;
                $extractedData = json_decode($extractedJson ?? '', true);
                $isPdf = strtolower(pathinfo($permitFile, PATHINFO_EXTENSION)) === 'pdf';
            ?>

            <div id="review-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
                <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-5xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                    
                    <!-- Modal Header -->
                    <div class="px-6 py-4 bg-slate-800 border-b border-slate-700 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-white">Business Permit Verification Audit</h3>
                            <p class="text-xs text-slate-400">Employer ID #<?php echo $emp['employer_id']; ?> — <?php echo htmlspecialchars($emp['company_name']); ?></p>
                        </div>
                        <a href="/admin/verifications" class="text-slate-400 hover:text-white font-bold text-lg px-2">✕</a>
                    </div>

                    <!-- Modal Body Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6 overflow-y-auto flex-1">
                        
                        <!-- LEFT COLUMN: Permit File Previewer -->
                        <div class="flex flex-col h-full bg-slate-950 rounded-xl border border-slate-800 overflow-hidden">
                            <div class="px-4 py-3 bg-slate-800/50 border-b border-slate-800 flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">Document Preview</span>
                                <?php if (!empty($permitFile)): ?>
                                    <a href="<?php echo htmlspecialchars($permitFile); ?>" target="_blank" rel="noopener" class="text-xs font-bold text-sky-400 hover:underline">Open Fullscreen ↗</a>
                                <?php endif; ?>
                            </div>

                            <div class="flex-1 min-h-[350px] flex items-center justify-center p-2 bg-slate-950">
                                <?php if (empty($permitFile)): ?>
                                    <div class="text-center text-slate-500 text-sm">No permit document uploaded.</div>
                                <?php elseif ($isPdf): ?>
                                    <iframe src="<?php echo htmlspecialchars($permitFile); ?>" class="w-full h-full min-h-[400px] rounded-lg border-0"></iframe>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($permitFile); ?>" alt="Business Permit" class="max-h-[450px] w-auto object-contain rounded-lg shadow">
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: AI Feedback & Extracted Data -->
                        <div class="space-y-6">
                            
                            <!-- AI Classification Banner -->
                            <div class="p-4 rounded-xl border <?php echo $aiStatus === 'green_flag' ? 'bg-emerald-950/70 border-emerald-800 text-emerald-200' : ($aiStatus === 'red_flag' ? 'bg-rose-950/70 border-rose-800 text-rose-200' : 'bg-slate-800 border-slate-700 text-slate-300'); ?>">
                                <div class="flex items-center gap-3 mb-2">
                                    <?php if ($aiStatus === 'green_flag'): ?>
                                        <span class="w-3 h-3 rounded-full bg-emerald-400 animate-pulse"></span>
                                        <span class="font-extrabold text-sm uppercase tracking-wide text-emerald-300">AI Green Flag — Valid Credentials Verified</span>
                                    <?php elseif ($aiStatus === 'red_flag'): ?>
                                        <span class="w-3 h-3 rounded-full bg-rose-500 animate-ping"></span>
                                        <span class="font-extrabold text-sm uppercase tracking-wide text-rose-300">AI Red Flag — Flagged for Admin Audit</span>
                                    <?php else: ?>
                                        <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                                        <span class="font-extrabold text-sm uppercase tracking-wide text-amber-300">AI Classification Pending</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs leading-relaxed text-slate-300"><?php echo htmlspecialchars($aiFeedback ?: 'No AI audit feedback generated yet.'); ?></p>
                            </div>

                            <!-- Extracted Fields Breakdown -->
                            <div>
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">AI OCR Extracted Credentials</h4>
                                <?php if (is_array($extractedData) && !empty($extractedData)): ?>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                        <?php foreach ($extractedData as $k => $v): ?>
                                            <div class="p-2.5 bg-slate-800/80 rounded-lg border border-slate-700/60">
                                                <span class="text-[10px] font-bold text-slate-400 uppercase block"><?php echo htmlspecialchars(str_replace('_', ' ', $k)); ?></span>
                                                <span class="font-bold text-white mt-0.5 block"><?php echo htmlspecialchars(is_array($v) ? json_encode($v) : (string)$v); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 bg-slate-800/40 rounded-lg text-xs text-slate-500 italic border border-slate-800">No structured fields extracted.</div>
                                <?php endif; ?>
                            </div>

                            <!-- Company Details Summary -->
                            <div class="p-4 bg-slate-800/40 rounded-xl border border-slate-800 text-xs space-y-2">
                                <div class="flex justify-between"><span class="text-slate-400">Company Name:</span> <span class="font-bold text-white"><?php echo htmlspecialchars($emp['company_name']); ?></span></div>
                                <div class="flex justify-between"><span class="text-slate-400">Contact Person:</span> <span class="font-bold text-white"><?php echo htmlspecialchars($emp['contact_person'] ?? 'N/A'); ?></span></div>
                                <div class="flex justify-between"><span class="text-slate-400">Contact Email:</span> <span class="font-bold text-white"><?php echo htmlspecialchars($emp['company_email'] ?? $emp['user_email']); ?></span></div>
                                <div class="flex justify-between"><span class="text-slate-400">Municipality:</span> <span class="font-bold text-white"><?php echo htmlspecialchars($emp['municipality_name'] ?? 'Guimba'); ?></span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer Actions -->
                    <div class="px-6 py-4 bg-slate-800 border-t border-slate-700 flex items-center justify-between gap-4">
                        <span class="text-xs text-slate-400">Current Status: <strong class="text-white uppercase"><?php echo htmlspecialchars($adminStatus); ?></strong></span>

                        <div class="flex items-center gap-3">
                            <!-- Reject Action Form -->
                            <form action="/admin/verify-employer" method="POST" onsubmit="return confirm('Reject this employer business permit verification?');">
                                <?php echo CSRF::csrfField(); ?>
                                <input type="hidden" name="employer_id" value="<?php echo $emp['employer_id']; ?>">
                                <input type="hidden" name="status" value="Rejected">
                                <button type="submit" class="px-5 py-2.5 text-xs font-bold text-rose-200 bg-rose-900/80 hover:bg-rose-900 rounded-xl border border-rose-700/80 transition-all shadow">
                                    ✕ Reject Account
                                </button>
                            </form>

                            <!-- Approve Action Form -->
                            <form action="/admin/verify-employer" method="POST" onsubmit="return confirm('Approve and verify this employer account?');">
                                <?php echo CSRF::csrfField(); ?>
                                <input type="hidden" name="employer_id" value="<?php echo $emp['employer_id']; ?>">
                                <input type="hidden" name="status" value="Verified">
                                <button type="submit" class="px-6 py-2.5 text-xs font-extrabold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow-lg shadow-emerald-600/20 transition-all">
                                    ✓ Approve Account
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        <?php endif; ?>

    </main>
</body>

</html>
