<?php
/**
 * View: Admin Job Seekers Directory
 */
$activeTab = 'seekers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Seekers Directory | S.I.K.A.P. Hub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/public/assets/css/theme.css">
    <link rel="stylesheet" href="https://sikaphub.com/public/assets/css/theme.css">
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
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Job Seekers Directory</h1>
                <p class="text-slate-500 text-sm mt-0.5">Monitor registered job seekers, location distribution, and profile completeness.</p>
            </div>
            <form method="GET" action="/admin/seekers" class="flex items-center gap-2">
                <div class="relative">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name or email..." class="pl-9 pr-3 py-2 bg-white rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-xs w-56 md:w-64">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <select name="municipality_id" class="px-3 py-2 bg-white rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-xs">
                    <option value="">All Locations</option>
                    <?php foreach ($municipalities as $mun): ?>
                        <option value="<?php echo $mun['municipality_id']; ?>" <?php echo $municipality_id == $mun['municipality_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mun['municipality_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition-all shadow-xs">Filter</button>
            </form>
        </div>

        <!-- Table Container -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-100/70 text-slate-700 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="p-4">Seeker Name</th>
                            <th class="p-4">Email</th>
                            <th class="p-4">Home Location</th>
                            <th class="p-4">Visibility</th>
                            <th class="p-4">Profile Completeness</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($seekers)): ?>
                            <tr>
                                <td colspan="5" class="p-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="text-3xl">👤</span>
                                        <p class="font-medium text-slate-500">No job seekers found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($seekers as $s): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors border-b border-slate-100">
                                    <td class="p-4 font-bold text-slate-900">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 font-extrabold flex items-center justify-center text-xs">
                                                <?php echo strtoupper(mb_substr($s['first_name'] ?? 'J', 0, 1)); ?>
                                            </div>
                                            <span><?php echo htmlspecialchars(trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')) ?: 'Registered Seeker'); ?></span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-slate-600 font-medium"><?php echo htmlspecialchars($s['email']); ?></td>
                                    <td class="p-4 text-slate-600 font-medium">
                                        <span class="inline-flex items-center gap-1">
                                            📍 <?php echo htmlspecialchars($s['municipality_name'] ?? 'Not Specified'); ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                                            <?php echo htmlspecialchars($s['profile_visibility'] ?? 'Public'); ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-32 bg-slate-200/80 rounded-full h-2 overflow-hidden shadow-inner">
                                                <div class="bg-indigo-600 h-2 rounded-full transition-all duration-500" style="width: <?php echo (int)($s['profile_completeness'] ?? 0); ?>%;"></div>
                                            </div>
                                            <span class="text-xs font-extrabold text-slate-700"><?php echo (int)($s['profile_completeness'] ?? 0); ?>%</span>
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
