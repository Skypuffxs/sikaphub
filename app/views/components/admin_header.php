<?php
/**
 * Shared PESO Admin Header Component
 * $activeTab expected: 'dashboard' | 'verifications' | 'employers' | 'seekers' | 'jobs' | 'skills' | 'audit-logs'
 */
$activeTab = $activeTab ?? 'dashboard';
$adminName = $admin_name ?? ($_SESSION['admin_name'] ?? 'PESO Admin');
?>
<header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-50 shadow-lg backdrop-blur-md bg-opacity-95">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Brand Logo -->
            <a href="/admin/dashboard" class="flex items-center gap-3 group">
                <img src="/public/assets/images/logo-icon.png" alt="SikapHub" class="h-9 w-auto object-contain transition-transform group-hover:scale-105">
                <div class="flex flex-col">
                    <span class="font-extrabold text-lg tracking-tight text-white flex items-center gap-1.5">
                        Sikap<span class="bg-gradient-to-r from-blue-400 via-indigo-400 to-violet-400 bg-clip-text text-transparent">hub</span>
                    </span>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-indigo-400/80 -mt-1">PESO Admin Portal</span>
                </div>
            </a>

            <!-- Nav Items -->
            <nav class="hidden md:flex items-center gap-1 text-xs font-semibold">
                <a href="/admin/dashboard" class="px-3 py-2 rounded-lg transition-all <?php echo ($activeTab === 'dashboard') ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80'; ?>">Overview</a>
                <a href="/admin/employers" class="px-3 py-2 rounded-lg transition-all <?php echo ($activeTab === 'employers') ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80'; ?>">Employers</a>
                <a href="/admin/seekers" class="px-3 py-2 rounded-lg transition-all <?php echo ($activeTab === 'seekers') ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80'; ?>">Job Seekers</a>
                <a href="/admin/jobs" class="px-3 py-2 rounded-lg transition-all <?php echo ($activeTab === 'jobs') ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80'; ?>">Job Postings</a>
                <a href="/admin/skills" class="px-3 py-2 rounded-lg transition-all <?php echo ($activeTab === 'skills') ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80'; ?>">Master Skills</a>
                <a href="/admin/export" target="_blank" class="px-3 py-2 rounded-lg text-emerald-400 hover:bg-emerald-500/10 transition-all font-bold">📄 Export Report</a>
            </nav>

            <!-- User Info & Logout -->
            <div class="flex items-center gap-3">
                <div class="hidden sm:flex flex-col text-right">
                    <span class="text-xs font-bold text-slate-200"><?php echo htmlspecialchars($adminName); ?></span>
                    <span class="text-[10px] text-indigo-400 font-medium">PESO Administrator</span>
                </div>
                <a href="/admin/logout" class="px-3 py-1.5 rounded-lg bg-rose-500/10 text-rose-300 border border-rose-500/20 hover:bg-rose-500/20 text-xs font-bold transition-all flex items-center gap-1">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Logout
                </a>
            </div>
        </div>
    </div>
</header>
