<?php
/**
 * View: Admin Audit Logs View
 */
$activeTab = 'audit-logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Audit Logs | S.I.K.A.P. Hub Admin</title>
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
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">System Audit & Security Logs</h1>
            <p class="text-slate-500 text-sm mt-0.5">Immutable audit trail recording administrative decisions, verification events, and data changes.</p>
        </div>

        <!-- Table Container -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-100/70 text-slate-700 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="p-4">Timestamp</th>
                            <th class="p-4">Action Event</th>
                            <th class="p-4">Description Detail</th>
                            <th class="p-4">User Initiator</th>
                            <th class="p-4">Target Entity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="p-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="text-3xl">📋</span>
                                        <p class="font-medium text-slate-500">No audit logs recorded yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors border-b border-slate-100">
                                    <td class="p-4 text-xs text-slate-500 font-mono font-medium"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 inline-block">
                                            ⚡ <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-slate-800 font-semibold"><?php echo htmlspecialchars($log['description']); ?></td>
                                    <td class="p-4 text-xs text-slate-600 font-medium"><?php echo htmlspecialchars($log['email'] ?? 'User #' . $log['user_id']); ?></td>
                                    <td class="p-4 text-xs text-slate-500 font-medium">
                                        <span class="bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                            <?php echo htmlspecialchars($log['entity_type'] ?? 'N/A'); ?>
                                            <?php if (!empty($log['entity_id'])): ?>
                                                <span class="font-mono text-slate-400 font-bold">(#<?php echo (int)$log['entity_id']; ?>)</span>
                                            <?php endif; ?>
                                        </span>
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
