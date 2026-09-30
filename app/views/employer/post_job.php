<?php
// Passed by JobController::create(). $old / $old_skills repopulate the form
// after a validation failure; $error is the message to show.
$old = $old ?? [];
$error = $error ?? null;
$oldSkills = $old_skills ?? [];
$ov = fn($k) => htmlspecialchars((string) ($old[$k] ?? ''));
$employmentTypes  = ['Full-time', 'Part-time', 'Contract', 'Internship'];
$workArrangements = ['On-site', 'Remote', 'Hybrid'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a New Job Opportunity - S.I.K.A.P. Hub</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    
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
                        'app-bg': '#f8fafc',
                        surface: '#ffffff',
                        border: '#e2ebf6'
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .form-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03); }
    </style>
</head>

<body class="bg-slate-50 min-h-screen flex flex-col antialiased">

    <!-- Top Navigation Bar -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <a href="/employer/dashboard" class="flex items-center gap-2.5">
                    <img src="/public/assets/images/logo-icon.png" alt="SikapHub" class="w-8 h-8 rounded-lg object-contain flex-shrink-0">
                    <span class="font-extrabold text-xl tracking-tight text-[#031a3f]">Sikap<span class="bg-gradient-to-r from-[#009cfb] via-[#1769ff] to-[#9035ff] bg-clip-text text-transparent">hub</span></span>
                    <span class="bg-indigo-50 text-indigo-700 text-[10px] font-extrabold px-2 py-0.5 rounded-md uppercase tracking-wider border border-indigo-100 hidden sm:inline-block">Employer ATS</span>
                </a>
                
                <div class="flex items-center gap-6">
                    <a href="/employer/dashboard" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">Dashboard</a>
                    <a href="/build-profile" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">Company Profile</a>
                    
                    <a href="/employer/dashboard" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-all">
                        ← Back to Dashboard
                    </a>

                    <!-- User Avatar & Dropdown -->
                    <div class="relative" id="user-menu-container">
                        <button id="user-menu-button" type="button" onclick="event.stopPropagation(); const d=document.getElementById('user-menu-dropdown'); if(d) d.classList.toggle('hidden');" class="flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-primary/20 rounded-full cursor-pointer">
                            <div class="w-9 h-9 rounded-full bg-slate-900 border-2 border-primary flex items-center justify-center text-white font-bold text-xs shadow-sm overflow-hidden">
                                <?php
                                    $email = $_SESSION['email'] ?? 'Employer';
                                    $initials = strtoupper(substr($email, 0, 2));
                                    echo htmlspecialchars($initials);
                                ?>
                            </div>
                        </button>

                        <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-100 py-2 z-50">
                            <div class="px-4 py-2 border-b border-slate-100">
                                <p class="text-xs text-slate-400 font-medium">Signed in as</p>
                                <p class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Employer'); ?></p>
                            </div>
                            <a href="/build-profile" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5"/></svg>
                                Company Profile
                            </a>
                            <a href="/employer/dashboard" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25z"/></svg>
                                ATS Dashboard
                            </a>
                            <div class="border-t border-slate-100 my-1"></div>
                            <a href="/logout" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 font-bold transition-colors">
                                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Header Block Banner -->
    <header class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white py-10 px-4 sm:px-6 lg:px-8 shadow-md">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center gap-2 mb-2">
                <span class="bg-blue-500/20 text-blue-300 border border-blue-400/30 text-xs font-bold px-3 py-1 rounded-full">
                    Step 1 of 1 — Create Vacancy
                </span>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight">Post a New Vacancy</h1>
            <p class="text-slate-300 text-sm mt-1.5 max-w-xl leading-relaxed">
                Define your job role criteria and AI skills requirements. The AI Engine will calculate point-in-time scores to rank candidates.
            </p>
        </div>
    </header>

    <!-- Main Workspace Container -->
    <main class="flex-grow max-w-4xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl mb-6 flex items-center gap-3 text-sm font-bold shadow-sm">
                <span class="text-xl">⚠️</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="ai-trigger-form space-y-6"
            data-loader-msg="Analyzing Matrix and Ranking Active Candidates... Please wait.">

            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

            <!-- Section 1: Role Details -->
            <div class="form-card p-6 md:p-8 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-7 h-7 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center text-xs font-bold">1</span>
                        Role Details & Specifications
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">Specify core job parameters and compensation terms.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1.5">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Job Title <span class="text-rose-500">*</span></label>
                        <input type="text" name="job_title" value="<?php echo $ov('job_title'); ?>"
                               placeholder="e.g., Senior Software Developer" required
                               class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all">
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Employment Type <span class="text-rose-500">*</span></label>
                        <select name="employment_type" required
                                class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all">
                            <option value="">Select type...</option>
                            <?php foreach ($employmentTypes as $t): ?>
                                <option value="<?php echo $t; ?>" <?php echo ($old['employment_type'] ?? '') === $t ? 'selected' : ''; ?>>
                                    <?php echo $t; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Minimum Years of Experience <span class="text-rose-500">*</span></label>
                        <input type="number" name="min_years_experience" min="0" max="50" step="1"
                               value="<?php echo $ov('min_years_experience'); ?>" placeholder="e.g., 2" required
                               class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all">
                        <span class="text-[11px] font-medium text-slate-400 block">Enter 0 if no prior experience is required.</span>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Work Arrangement <span class="text-rose-500">*</span></label>
                        <select name="work_arrangement" required
                                class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all">
                            <option value="">Select arrangement...</option>
                            <?php foreach ($workArrangements as $w): ?>
                                <option value="<?php echo $w; ?>" <?php echo ($old['work_arrangement'] ?? '') === $w ? 'selected' : ''; ?>>
                                    <?php echo $w; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Salary Range (Optional)</label>
                        <input type="text" name="salary_range" value="<?php echo $ov('salary_range'); ?>"
                               placeholder="e.g., 25,000 - 35,000 or PHP 60,000 / month"
                               class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all">
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Target Location (Municipality) <span class="text-rose-500">*</span></label>
                        <select name="municipality_id" required
                                class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all">
                            <option value="">Select Municipality...</option>
                            <?php
                            $curProv = '';
                            foreach ($municipalities as $m):
                                if ($curProv !== ($m['province_name'] ?? '')):
                                    if ($curProv !== '') echo '</optgroup>';
                                    $curProv = $m['province_name'] ?? '';
                                    echo '<optgroup label="' . htmlspecialchars($curProv) . '">';
                                endif;
                                $sel = ((string) $m['municipality_id'] === ($old['municipality_id'] ?? '')) ? 'selected' : '';
                                ?>
                                <option value="<?php echo (int) $m['municipality_id']; ?>" <?php echo $sel; ?>>
                                    <?php echo htmlspecialchars($m['municipality_name']); ?>
                                </option>
                            <?php endforeach;
                            if ($curProv !== '') echo '</optgroup>';
                            ?>
                        </select>
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Job Description & Overview <span class="text-rose-500">*</span></label>
                        <textarea name="job_description" rows="5" placeholder="Provide a detailed description of key duties, expectations, and day-to-day work..."
                                  required class="w-full bg-slate-50/70 border border-slate-200 rounded-xl p-4 text-sm font-medium outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all"><?php echo $ov('job_description'); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 2: AI Skills Matrix Builder -->
            <div class="form-card p-6 md:p-8 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-7 h-7 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-xs font-bold">2</span>
                        AI Skills Matrix Builder
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Select skills required for this position. Mark key skills as <strong class="text-slate-700">Mandatory</strong> for high weight scoring.
                    </p>
                </div>

                <div class="bg-slate-50 border border-dashed border-slate-200 rounded-2xl p-4 flex flex-col sm:flex-row items-center gap-3">
                    <select id="master-skill-dropdown" class="flex-1 w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold outline-none focus:border-blue-600 transition-all">
                        <option value="">-- Select a skill from master dictionary --</option>
                        <?php
                        $currentCategory = '';
                        foreach ($skills as $skill):
                            if ($currentCategory !== $skill['category_name']):
                                if ($currentCategory !== '')
                                    echo '</optgroup>';
                                $currentCategory = $skill['category_name'];
                                $safeCategory = htmlspecialchars($currentCategory ?? 'Uncategorized');
                                echo "<optgroup label=\"{$safeCategory}\">";
                            endif;
                            ?>
                            <option value="<?php echo $skill['skill_id']; ?>">
                                <?php echo htmlspecialchars($skill['skill_name']); ?></option>
                        <?php endforeach;
                        if ($currentCategory !== '')
                            echo '</optgroup>';
                        ?>
                    </select>
                    <button type="button" id="btn-add-skill" class="w-full sm:w-auto bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs px-5 py-3 rounded-xl transition-all shadow-sm whitespace-nowrap">
                        + Add Skill
                    </button>
                </div>

                <?php
                $skillNameById = [];
                foreach ($skills as $s) { $skillNameById[(int) $s['skill_id']] = $s['skill_name']; }
                $oldSkillsForJs = [];
                foreach ($oldSkills as $os) {
                    $oldSkillsForJs[] = [
                        'id'   => (int) $os['skill_id'],
                        'name' => $skillNameById[(int) $os['skill_id']] ?? ('Skill #' . (int) $os['skill_id']),
                        'type' => in_array($os['requirement_type'], ['Mandatory', 'Preferred'], true) ? $os['requirement_type'] : 'Mandatory',
                    ];
                }
                ?>
                <script>window.OLD_SKILLS = <?php echo json_encode($oldSkillsForJs); ?>;</script>

                <div id="selected-skills-container" class="space-y-2.5 min-h-[60px]">
                    <p id="empty-skill-msg" class="text-center py-6 text-xs text-slate-400 font-medium">No skills added yet. Select a skill from the dropdown list above.</p>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-base py-4 rounded-2xl shadow-lg transition-all transform hover:-translate-y-0.5">
                Publish Job & Trigger AI Matcher
            </button>
        </form>
    </main>

    <?php
    if (file_exists(BASE_PATH . 'app/views/components/loader.php')) {
        require BASE_PATH . 'app/views/components/loader.php';
    }
    ?>

    <!-- Script for Dynamic Skill Row Management -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const dropdown = document.getElementById('master-skill-dropdown');
            const addBtn = document.getElementById('btn-add-skill');
            const container = document.getElementById('selected-skills-container');
            const emptyMsg = document.getElementById('empty-skill-msg');
            let addedSkills = new Set();

            function escapeHtml(s) {
                return String(s).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            function addSkillRow(skillId, skillName, requirementType) {
                if (!skillId || addedSkills.has(String(skillId))) return;
                if (emptyMsg) emptyMsg.style.display = 'none';
                addedSkills.add(String(skillId));
                const mSel = requirementType === 'Preferred' ? '' : ' selected';
                const pSel = requirementType === 'Preferred' ? ' selected' : '';
                const tagHtml = `
                    <div class="skill-tag bg-white border border-slate-200 p-3.5 rounded-xl flex items-center justify-between gap-4 shadow-sm" data-id="${escapeHtml(skillId)}">
                        <span class="font-bold text-sm text-slate-800 flex-1">${escapeHtml(skillName)}</span>
                        
                        <div class="flex items-center gap-3">
                            <select name="requirement_type[${escapeHtml(skillId)}]" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-xs font-bold outline-none focus:border-blue-600">
                                <option value="Mandatory"${mSel}>Mandatory</option>
                                <option value="Preferred"${pSel}>Preferred</option>
                            </select>
                            <input type="hidden" name="skills[]" value="${escapeHtml(skillId)}">
                            <button type="button" class="btn-remove-skill text-rose-500 hover:text-rose-700 font-extrabold text-lg px-2 rounded hover:bg-rose-50 transition-colors" title="Remove">&times;</button>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', tagHtml);
            }

            addBtn.addEventListener('click', function () {
                const skillId = dropdown.value;
                const skillName = dropdown.options[dropdown.selectedIndex].text;
                if (!skillId) {
                    alert("Please select a skill from the dropdown first.");
                    return;
                }
                if (addedSkills.has(skillId)) {
                    alert("You have already added this skill.");
                    return;
                }
                addSkillRow(skillId, skillName.trim(), 'Mandatory');
                dropdown.value = "";
            });

            (window.OLD_SKILLS || []).forEach(function (s) {
                addSkillRow(s.id, s.name, s.type);
            });

            container.addEventListener('click', function (e) {
                if (e.target.classList.contains('btn-remove-skill')) {
                    const tag = e.target.closest('.skill-tag');
                    const skillId = tag.getAttribute('data-id');
                    addedSkills.delete(skillId);
                    tag.remove();

                    if (addedSkills.size === 0 && emptyMsg) {
                        emptyMsg.style.display = 'block';
                    }
                }
            });
        });
    </script>
</body>
</html>