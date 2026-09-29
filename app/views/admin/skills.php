<?php
/**
 * View: Admin Master Skill List Management
 */
$activeTab = 'skills';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Skills Taxonomy | S.I.K.A.P. Hub Admin</title>
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
        <!-- Page Title & Quick Add Bar -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Master Skills Taxonomy Manager</h1>
                <p class="text-slate-500 text-sm mt-0.5">Approve custom seeker suggestions, add new master skills, or manage AI taxonomy.</p>
            </div>
            
            <!-- Add Skill Card Form -->
            <form method="POST" action="/sikaphub/admin/add-skill" class="flex flex-wrap items-center gap-2 bg-white p-2 rounded-2xl shadow-sm border border-slate-200">
                <?php echo CSRF::csrfField(); ?>
                <input type="text" name="skill_name" required placeholder="New skill name..." class="px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <select name="category_id" required class="px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo (int)$cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-xs flex items-center gap-1">
                    <span>+ Add Skill</span>
                </button>
            </form>
        </div>

        <!-- Search & Filter Bar -->
        <form method="GET" action="/sikaphub/admin/skills" class="flex items-center gap-2 mb-6">
            <div class="relative">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search skill name..." class="pl-9 pr-3 py-2 bg-white rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-xs w-56 md:w-64">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <select name="category_id" class="px-3 py-2 bg-white rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-xs">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo (int)$cat['category_id']; ?>" <?php echo $category_id == $cat['category_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition-all shadow-xs">Filter</button>
        </form>

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
                            <th class="p-4">Skill ID</th>
                            <th class="p-4">Skill Name</th>
                            <th class="p-4">Category</th>
                            <th class="p-4">Approval Status</th>
                            <th class="p-4 text-right">Moderation Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($skills)): ?>
                            <tr>
                                <td colspan="5" class="p-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="text-3xl">🛠️</span>
                                        <p class="font-medium text-slate-500">No master skills found matching filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($skills as $sk): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors border-b border-slate-100">
                                    <td class="p-4 text-xs font-mono text-slate-400 font-bold">#<?php echo (int)$sk['skill_id']; ?></td>
                                    <td class="p-4 font-bold text-slate-900 text-base"><?php echo htmlspecialchars($sk['skill_name']); ?></td>
                                    <td class="p-4 text-slate-600 font-semibold">
                                        <span class="inline-flex items-center gap-1 bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-200 text-xs">
                                            🏷️ <?php echo htmlspecialchars($sk['category_name'] ?? 'Uncategorised'); ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <?php
                                        $badge = match($sk['status']) {
                                            'approved' => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-bold',
                                            'pending'  => 'bg-amber-100 text-amber-800 border-amber-300 font-bold',
                                            default    => 'bg-slate-100 text-slate-700 border-slate-300 font-bold'
                                        };
                                        ?>
                                        <span class="px-2.5 py-1 text-xs rounded-full border shadow-2xs <?php echo $badge; ?>">
                                            <?php echo htmlspecialchars(ucfirst($sk['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="inline-flex gap-2">
                                            <?php if ($sk['status'] === 'pending'): ?>
                                                <form method="POST" action="/sikaphub/admin/approve-skill" class="inline">
                                                    <?php echo CSRF::csrfField(); ?>
                                                    <input type="hidden" name="skill_id" value="<?php echo (int)$sk['skill_id']; ?>">
                                                    <button type="submit" name="action" value="approve" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors shadow-xs">Approve</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="/sikaphub/admin/approve-skill" onsubmit="return confirm('Delete this skill permanently?')" class="inline">
                                                <?php echo CSRF::csrfField(); ?>
                                                <input type="hidden" name="skill_id" value="<?php echo (int)$sk['skill_id']; ?>">
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
</body>
</html>
