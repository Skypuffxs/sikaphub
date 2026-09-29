<?php
/**
 * View: Admin Employer Management Directory (with AI Permit Analytics & Feedback)
 * Place this file at: c:\xampp\htdocs\sikaphub\app\views\admin\employers.php
 */
$activeTab = 'employers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Directory | S.I.K.A.P. Hub Admin</title>
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
        <!-- Page Header & Filter Toolbar -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Employer Management Directory</h1>
                <p class="text-slate-500 text-sm mt-0.5">Review, verify, and monitor all registered businesses with AI Permit Audit Analytics.</p>
            </div>
            <form method="GET" action="/sikaphub/admin/employers" class="flex items-center gap-2">
                <div class="relative">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search company, contact..." class="pl-9 pr-3 py-2 bg-white rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-xs w-56 md:w-64">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <select name="status" class="px-3 py-2 bg-white rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-xs">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Verified" <?php echo $status === 'Verified' ? 'selected' : ''; ?>>Verified</option>
                    <option value="Rejected" <?php echo $status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition-all shadow-xs flex items-center gap-1.5">
                    Filter
                </button>
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

        <!-- Directory Table Container -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-100/70 text-slate-700 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="p-4">Company Name</th>
                            <th class="p-4">Contact Details</th>
                            <th class="p-4">Business Permit & AI Analytics</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($employers)): ?>
                            <tr>
                                <td colspan="5" class="p-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="text-3xl">🏢</span>
                                        <p class="font-medium text-slate-500">No employers found matching criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employers as $emp): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors border-b border-slate-100">
                                    <td class="p-4">
                                        <div class="font-bold text-slate-900 text-base"><?php echo htmlspecialchars($emp['company_name']); ?></div>
                                        <div class="text-xs text-slate-400 font-medium"><?php echo htmlspecialchars($emp['industry'] ?? 'General Industry'); ?></div>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($emp['contact_person'] ?? 'N/A'); ?></div>
                                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($emp['email'] ?? $emp['company_email'] ?? ''); ?></div>
                                        <div class="text-xs text-slate-400"><?php echo htmlspecialchars($emp['company_phone'] ?? ''); ?></div>
                                    </td>
                                    <td class="p-4">
                                        <?php if (!empty($emp['business_permit_file'])): ?>
                                            <div class="flex flex-col gap-1.5 items-start">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <a href="javascript:void(0)" onclick="openPermitModal('/sikaphub/admin/view-document?file=<?php echo urlencode($emp['business_permit_file']); ?>', '<?php echo htmlspecialchars(addslashes($emp['company_name']), ENT_QUOTES); ?>')" class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200 hover:bg-indigo-100 transition-colors shadow-xs cursor-pointer">
                                                        📄 View Permit
                                                    </a>
                                                    <form method="POST" action="/sikaphub/admin/reanalyze-permit" class="inline">
                                                        <?php echo CSRF::csrfField(); ?>
                                                        <input type="hidden" name="employer_id" value="<?php echo (int) $emp['employer_id']; ?>">
                                                        <button type="submit" title="Re-trigger AI Permit Audit" class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-700 bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-300 hover:bg-slate-200 transition-colors shadow-xs">
                                                            🔄 Re-run AI
                                                        </button>
                                                    </form>
                                                </div>
                                                <?php
                                                $aiRawStatus = strtolower((string) ($emp['verification_status'] ?? 'pending'));
                                                $aiFeedbackText = $emp['ai_feedback'] ?? '';

                                                $isRed   = ($aiRawStatus === 'red_flag' || $aiRawStatus === 'red');
                                                $isGreen = ($aiRawStatus === 'green_flag' || $aiRawStatus === 'green' || $aiRawStatus === 'verified');

                                                if ($isRed) {
                                                    $aiFlagTag     = '🔴 RED FLAG (AUDIT NEEDED)';
                                                    $aiBadgeClass  = 'bg-rose-100 text-rose-800 border-rose-300 ring-1 ring-rose-300 font-extrabold';
                                                    $aiCardBox     = 'bg-rose-50/80 border-rose-200 text-rose-950 font-medium';
                                                    $aiHeaderText  = '🔴 AI RED FLAG AUDIT RATIONALE & FEEDBACK';
                                                } elseif ($isGreen) {
                                                    $aiFlagTag     = '🟢 GREEN FLAG (VALID PERMIT)';
                                                    $aiBadgeClass  = 'bg-emerald-100 text-emerald-800 border-emerald-300 ring-1 ring-emerald-300 font-extrabold';
                                                    $aiCardBox     = 'bg-emerald-50/80 border-emerald-200 text-emerald-950 font-medium';
                                                    $aiHeaderText  = '🟢 AI GREEN FLAG VERIFICATION RATIONALE';
                                                } else {
                                                    $aiFlagTag     = '🟡 PENDING AI REVIEW';
                                                    $aiBadgeClass  = 'bg-amber-100 text-amber-800 border-amber-300 font-bold';
                                                    $aiCardBox     = 'bg-slate-50 border-slate-200 text-slate-800';
                                                    $aiHeaderText  = '🤖 AI AUDIT RATIONALE & FEEDBACK';
                                                }
                                                ?>
                                                <button type="button" onclick="toggleAiAnalytics('emp-ai-<?php echo (int)$emp['employer_id']; ?>')" class="inline-flex items-center gap-1.5 text-[11px] px-2.5 py-1 rounded-full border <?php echo $aiBadgeClass; ?> hover:opacity-90 transition-all shadow-xs">
                                                    <span><?php echo $aiFlagTag; ?></span>
                                                    <span class="text-[10px]">📊</span>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 font-medium italic">No Permit Uploaded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <?php
                                        $badge = match($emp['verified_status']) {
                                            'Verified' => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-bold',
                                            'Rejected' => 'bg-rose-100 text-rose-800 border-rose-300 font-bold',
                                            default    => 'bg-amber-100 text-amber-800 border-amber-300 font-bold'
                                        };
                                        ?>
                                        <span class="px-2.5 py-1 text-xs rounded-full border shadow-2xs <?php echo $badge; ?>">
                                            <?php echo htmlspecialchars($emp['verified_status']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <form method="POST" action="/sikaphub/admin/verify-employer" class="inline-flex gap-2">
                                            <?php echo CSRF::csrfField(); ?>
                                            <input type="hidden" name="employer_id" value="<?php echo (int) $emp['employer_id']; ?>">
                                            <?php if ($emp['verified_status'] !== 'Verified'): ?>
                                                <button type="submit" name="status" value="Verified" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors shadow-xs">
                                                    Approve
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($emp['verified_status'] !== 'Rejected'): ?>
                                                <button type="submit" name="status" value="Rejected" class="bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors shadow-xs">
                                                    Reject
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>

                                <!-- AI Analytics Expandable Panel -->
                                <tr id="emp-ai-<?php echo (int)$emp['employer_id']; ?>" class="hidden bg-slate-50/90 border-b border-slate-200">
                                    <td colspan="5" class="p-4">
                                        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm space-y-3">
                                            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-base">🤖</span>
                                                    <h4 class="font-bold text-slate-900 text-sm">AI Engine Business Permit Audit & Analytics</h4>
                                                    <span class="text-xs px-2.5 py-0.5 rounded-full font-extrabold border shadow-2xs <?php echo $aiBadgeClass; ?>">
                                                        <?php echo $aiFlagTag; ?>
                                                    </span>
                                                </div>
                                                <button type="button" onclick="toggleAiAnalytics('emp-ai-<?php echo (int)$emp['employer_id']; ?>')" class="text-xs text-slate-400 hover:text-slate-600 font-bold">✕ Close</button>
                                            </div>

                                            <!-- AI Feedback Rationale -->
                                            <div>
                                                <div class="text-[11px] font-extrabold uppercase tracking-wider mb-1 text-slate-700">
                                                    <?php echo $aiHeaderText; ?>
                                                </div>
                                                <div class="text-xs p-3 rounded-lg font-sans leading-relaxed border shadow-xs <?php echo $aiCardBox; ?>">
                                                    <?php echo htmlspecialchars($aiFeedbackText !== '' ? $aiFeedbackText : 'No AI feedback recorded for this permit yet.'); ?>
                                                </div>
                                            </div>

                                            <!-- Parsed Fields Grid -->
                                            <?php
                                            $extracted = [];
                                            if (!empty($emp['extracted_permit_data'])) {
                                                $decoded = json_decode($emp['extracted_permit_data'], true);
                                                if (is_array($decoded)) {
                                                    $extracted = $decoded;
                                                }
                                            }
                                            ?>
                                            <?php if (!empty($extracted)): ?>
                                                <div>
                                                    <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Parsed OCR Permit Details</div>
                                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                                                        <div class="bg-indigo-50/60 p-2.5 rounded-lg border border-indigo-100">
                                                            <div class="text-slate-400 text-[10px] uppercase font-bold">Business Name</div>
                                                            <div class="font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($extracted['business_name'] ?? 'N/A'); ?></div>
                                                        </div>
                                                        <div class="bg-indigo-50/60 p-2.5 rounded-lg border border-indigo-100">
                                                            <div class="text-slate-400 text-[10px] uppercase font-bold">Permit / Reg Number</div>
                                                            <div class="font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($extracted['permit_number'] ?? 'N/A'); ?></div>
                                                        </div>
                                                        <div class="bg-indigo-50/60 p-2.5 rounded-lg border border-indigo-100">
                                                            <div class="text-slate-400 text-[10px] uppercase font-bold">Expiry Date</div>
                                                            <div class="font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($extracted['expiry_date'] ?? 'N/A'); ?></div>
                                                        </div>
                                                        <div class="bg-indigo-50/60 p-2.5 rounded-lg border border-indigo-100">
                                                            <div class="text-slate-400 text-[10px] uppercase font-bold">Issuing Authority</div>
                                                            <div class="font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($extracted['issuing_authority'] ?? 'N/A'); ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
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
        function toggleAiAnalytics(rowId) {
            const el = document.getElementById(rowId);
            if (el) {
                el.classList.toggle('hidden');
            }
        }

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
