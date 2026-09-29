<?php
// Ensure this view receives $master_skills, $municipalities, and $existing_profile from the Controller
$p = $existing_profile ?? [];

// Pre-extract core preferences safely
$userEmail = htmlspecialchars($p['email'] ?? ($_SESSION['email'] ?? ''));
$firstName = htmlspecialchars($p['first_name'] ?? '');
$lastName = htmlspecialchars($p['last_name'] ?? '');
$fullName = trim($firstName . ' ' . $lastName) ?: 'Job Seeker';
$desiredJobType = htmlspecialchars($p['desired_job_type'] ?? '');
$expectedSalary = isset($p['expected_salary']) && $p['expected_salary'] !== null ? htmlspecialchars($p['expected_salary']) : '';
$workSetup = htmlspecialchars($p['preferred_work_setup'] ?? 'On-site');
$homeMunicipalityId = isset($p['home_municipality_id']) ? (int) $p['home_municipality_id'] : '';
$homeBarangayId = isset($p['barangay_id']) ? (int) $p['barangay_id'] : '';
$visibility = ($p['profile_visibility'] ?? 'Public') === 'Private' ? 'Private' : 'Public';
$prefLocs = array_map('intval', $p['preferred_municipality_ids'] ?? []);

// Find home municipality name for display
$homeMunicipalityName = '';
if ($homeMunicipalityId) {
    foreach ($municipalities as $m) {
        if ((int)$m['municipality_id'] === $homeMunicipalityId) {
            $homeMunicipalityName = $m['municipality_name'];
            break;
        }
    }
}

// Seeker's current skills
$seekerSkills = $p['skills'] ?? [];
$approvedSkills = $master_skills ?? [];

// Dynamic Arrays & Flags
$isNewProfile = empty($p) || (empty($firstName) && empty($lastName));
$hasExperienceRows = !empty($p['experience']);
$experiences = $p['experience'] ?? [];
if (empty($experiences)) {
    $experiences = [['job_title' => '', 'company_name' => '', 'start_date' => '', 'end_date' => '']];
}

$educations = $p['education'] ?? [];
if (empty($educations)) {
    $educations = [['degree_level' => '', 'school_name' => '', 'year_graduated' => '']];
}

$activeTab = isset($_GET['mode']) && $_GET['mode'] === 'edit' ? 'edit' : ($isNewProfile ? 'edit' : 'overview');
$justSaved = isset($_GET['saved']) || isset($_GET['profile_updated']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isNewProfile ? 'Build Profile' : htmlspecialchars($fullName) . ' – Profile'; ?> | S.I.K.A.P. Hub</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    <meta name="description" content="Manage your S.I.K.A.P. Hub candidate profile, work experience, skills, and resume.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="/sikaphub/public/assets/css/theme.css">
    <script src="/sikaphub/public/assets/js/tailwind.js"></script>

    <link href="/sikaphub/public/assets/css/tom-select.css" rel="stylesheet">
    <script src="/sikaphub/public/assets/js/tom-select.js"></script>

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
        
        .radio-card input[type="radio"] { display: none; }
        .radio-card input[type="radio"]+label { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.125rem; border: 1.5px solid #e2e8f0; border-radius: 0.875rem; cursor: pointer; transition: all .2s; background: #fff; }
        .radio-card input[type="radio"]:checked+label { border-color: #1769ff; background: #eff6ff; color: #1769ff; font-weight: 600; }
        
        .step-connector { flex: 1; height: 2px; background: #e2e8f0; transition: background .4s; }
        .step-connector.active { background: #1769ff; }
        
        .field-base { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #cbd5e1; border-radius: 0.75rem; font-size: 0.9375rem; transition: all .2s; background: #fff; outline: none; }
        .field-base:focus { border-color: #1769ff; box-shadow: 0 0 0 4px rgba(23, 105, 255, .12); }
        
        select.field-base { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2364748b'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z' clip-rule='evenodd'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.85rem center; background-size: 1.25rem; padding-right: 2.75rem; }
        .ts-control { padding: 0.75rem 1rem !important; border: 1.5px solid #cbd5e1 !important; border-radius: 0.75rem !important; font-size: 0.9375rem !important; box-shadow: none !important; }
        .ts-control.focus { border-color: #1769ff !important; box-shadow: 0 0 0 4px rgba(23, 105, 255, .12) !important; }
        .ts-dropdown { border-radius: 0.75rem !important; border: 1px solid #e2e8f0 !important; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col antialiased">

    <!-- Top Navigation Bar -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <a href="/sikaphub/dashboard" class="flex items-center gap-2.5">
                    <img src="/sikaphub/public/assets/images/logo-icon.png" alt="SikapHub" class="w-8 h-8 rounded-lg object-contain flex-shrink-0">
                    <span class="font-extrabold text-xl tracking-tight text-[#031a3f]">Sikap<span class="bg-gradient-to-r from-[#009cfb] via-[#1769ff] to-[#9035ff] bg-clip-text text-transparent">hub</span></span>
                    <span class="bg-blue-50 text-blue-700 text-[10px] font-extrabold px-2 py-0.5 rounded-md uppercase tracking-wider border border-blue-100 hidden sm:inline-block">Jobseeker Portal</span>
                </a>
                
                <div class="flex items-center gap-6">
                    <a href="/sikaphub/dashboard" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">Job Feed</a>
                    <a href="/sikaphub/jobseeker/tracker" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">My Applications</a>
                    <a href="/sikaphub/build-profile" class="text-sm font-bold text-primary border-b-2 border-primary py-5">Profile</a>
                    
                    <!-- User Menu Dropdown -->
                    <div class="relative" id="user-menu-container">
                        <button id="user-menu-button" type="button" class="flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-primary/20 rounded-full">
                            <div class="w-9 h-9 rounded-full bg-slate-900 border-2 border-primary flex items-center justify-center text-white font-bold text-xs shadow-xs overflow-hidden">
                                <?php if (!empty($p['profile_photo'])): ?>
                                    <img src="/sikaphub/admin/view-document?file=<?php echo htmlspecialchars($p['profile_photo']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php
                                        $initials = strtoupper(substr($firstName ?: ($userEmail ?: 'U'), 0, 2));
                                        echo htmlspecialchars($initials);
                                    ?>
                                <?php endif; ?>
                            </div>
                        </button>

                        <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-100 py-2 z-50">
                            <div class="px-4 py-2 border-b border-slate-100">
                                <p class="text-xs text-slate-400 font-medium">Signed in as</p>
                                <p class="text-sm font-bold text-slate-800 truncate"><?php echo $userEmail; ?></p>
                            </div>
                            <a href="/sikaphub/build-profile" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 1114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                Edit Profile
                            </a>
                            <a href="/sikaphub/jobseeker/tracker" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                My Applications
                            </a>
                            <div class="border-t border-slate-100 my-1"></div>
                            <a href="/sikaphub/logout" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 transition-colors">
                                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <?php if ($justSaved): ?>
            <!-- Clean Success Notification Banner -->
            <div class="mb-6 bg-emerald-600 text-white rounded-2xl p-4 shadow-sm flex items-center justify-between animate-fade-in">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm">Profile Saved &amp; Updated</h4>
                        <p class="text-xs text-emerald-100 mt-0.5">Your candidate profile is active and synchronized with employer job match scores.</p>
                    </div>
                </div>
                <a href="/sikaphub/dashboard" class="px-4 py-2 bg-white text-emerald-700 font-bold text-xs rounded-xl hover:bg-emerald-50 transition-colors shadow-xs">Find Jobs</a>
            </div>
        <?php endif; ?>

        <!-- Executive Candidate Profile Header Card -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 mb-8 border border-slate-200 shadow-sm relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-primary to-secondary"></div>
            
            <div class="flex flex-col md:flex-row items-center md:items-start justify-between gap-6 text-center md:text-left mt-2">
                <div class="flex flex-col sm:flex-row items-center gap-6">
                    <div class="relative flex-shrink-0 group">
                        <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl border-2 border-slate-100 shadow-md overflow-hidden bg-slate-900 flex items-center justify-center text-white text-3xl font-extrabold">
                            <?php if (!empty($p['profile_photo'])): ?>
                                <img src="/sikaphub/admin/view-document?file=<?php echo htmlspecialchars($p['profile_photo']); ?>" alt="Profile Photo" class="photo-preview-img w-full h-full object-cover">
                            <?php else: ?>
                                <span class="photo-initials text-white"><?php echo strtoupper(substr($firstName ?: ($userEmail ?: 'U'), 0, 1)); ?></span>
                                <img class="photo-preview-img w-full h-full object-cover hidden" alt="Profile Photo Preview">
                            <?php endif; ?>
                        </div>
                        <label for="hero_photo_input" class="absolute -bottom-2 -right-2 bg-primary hover:bg-primary-hover text-white w-9 h-9 rounded-xl flex items-center justify-center shadow-md cursor-pointer transition-transform hover:scale-105 border-2 border-white" title="Upload New Photo">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0c-.693.047-1.332.428-1.736 1.039l-.821 1.316z" /></svg>
                        </label>
                        <form id="hero_photo_form" method="POST" action="/sikaphub/build-profile" enctype="multipart/form-data" class="hidden">
                            <?php echo CSRF::csrfField(); ?>
                            <input type="file" id="hero_photo_input" name="profile_photo" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()">
                        </form>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center justify-center md:justify-start gap-2.5 mb-1.5">
                            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight"><?php echo htmlspecialchars($fullName); ?></h1>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Active Candidate
                            </span>
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                <?php echo $visibility === 'Public' ? '🌐 Public Profile' : '🔒 Private Profile'; ?>
                            </span>
                        </div>
                        <p class="text-sm font-medium text-slate-500"><?php echo $userEmail; ?></p>
                        
                        <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 mt-3 text-xs text-slate-600 font-medium">
                            <?php if ($homeMunicipalityName): ?>
                                <span class="flex items-center gap-1 text-slate-700 font-semibold"><svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg> <?php echo htmlspecialchars($homeMunicipalityName); ?>, Nueva Ecija</span>
                            <?php endif; ?>
                            <?php if ($desiredJobType): ?>
                                <span class="flex items-center gap-1"><svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" /></svg> <?php echo htmlspecialchars($desiredJobType); ?> (<?php echo htmlspecialchars($workSetup); ?>)</span>
                            <?php endif; ?>
                            <?php if ($expectedSalary !== ''): ?>
                                <span class="flex items-center gap-1 text-slate-800 font-bold"><svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-6h6" /></svg> Expected: ₱<?php echo number_format((float)$expectedSalary); ?>/mo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Clean Mode Switcher Buttons -->
                <div class="flex flex-wrap items-center justify-center md:justify-end gap-3 w-full md:w-auto">
                    <button type="button" onclick="toggleViewMode()" id="toggle-view-btn" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl text-xs sm:text-sm shadow-xs flex items-center gap-2 transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                        <span id="toggle-btn-text"><?php echo $activeTab === 'edit' ? 'View Profile Overview' : 'Edit Profile Details'; ?></span>
                    </button>

                    <?php if (!empty($p['resume']['stored_filename'])): ?>
                        <a href="/sikaphub/admin/view-document?file=<?php echo htmlspecialchars($p['resume']['stored_filename']); ?>" target="_blank" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            View Resume
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODE 1: EXECUTIVE PROFILE OVERVIEW DISPLAY -->
        <!-- ========================================================================= -->
        <div id="overview-view" class="<?php echo $activeTab === 'edit' ? 'hidden' : ''; ?> space-y-8 animate-fade-in">
            
            <!-- Quick Metrics Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs flex items-center gap-4">
                    <div class="w-11 h-11 rounded-lg bg-blue-50 text-primary flex items-center justify-center font-bold text-lg flex-shrink-0">💡</div>
                    <div>
                        <div class="text-xl font-extrabold text-slate-900"><?php echo count($seekerSkills); ?></div>
                        <div class="text-xs font-semibold text-slate-500">Verified Skills</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs flex items-center gap-4">
                    <div class="w-11 h-11 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-lg flex-shrink-0">💼</div>
                    <div>
                        <div class="text-xl font-extrabold text-slate-900"><?php echo count(array_filter($experiences, fn($e) => !empty($e['job_title']))); ?></div>
                        <div class="text-xs font-semibold text-slate-500">Work Roles</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs flex items-center gap-4">
                    <div class="w-11 h-11 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-lg flex-shrink-0">🎓</div>
                    <div>
                        <div class="text-xl font-extrabold text-slate-900"><?php echo count(array_filter($educations, fn($e) => !empty($e['school_name']))); ?></div>
                        <div class="text-xs font-semibold text-slate-500">Education Records</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs flex items-center gap-4">
                    <div class="w-11 h-11 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-lg flex-shrink-0">📄</div>
                    <div>
                        <div class="text-xs font-bold text-slate-900"><?php echo !empty($p['resume']['stored_filename']) ? 'Attached' : 'No File'; ?></div>
                        <div class="text-[11px] font-semibold text-slate-500">Resume Status</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column (Primary Details) -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Core Skills Card -->
                    <div class="bg-white rounded-2xl p-6 sm:p-7 border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                                <span class="w-7 h-7 rounded-md bg-blue-50 text-primary flex items-center justify-center text-xs">💡</span>
                                Core Skills &amp; Competencies
                            </h3>
                            <button onclick="toggleViewMode('step-3')" class="text-xs font-bold text-primary hover:underline">Edit Skills</button>
                        </div>
                        <?php if (!empty($seekerSkills)): ?>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($seekerSkills as $sk): ?>
                                    <span class="px-3 py-1.5 rounded-lg bg-slate-100 border border-slate-200 text-slate-800 text-xs font-semibold flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                        <?php echo htmlspecialchars($sk['skill_name']); ?>
                                        <?php if (($sk['status'] ?? '') === 'Pending'): ?>
                                            <span class="text-[9px] bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-extrabold">PESO Review</span>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                <p class="text-xs text-slate-500">No skills added yet.</p>
                                <button onclick="toggleViewMode('step-3')" class="mt-2 text-xs font-bold text-primary hover:underline">Add skills &rarr;</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Work Experience Card -->
                    <div class="bg-white rounded-2xl p-6 sm:p-7 border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                                <span class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">💼</span>
                                Work Experience History
                            </h3>
                            <button onclick="toggleViewMode('step-2')" class="text-xs font-bold text-primary hover:underline">Edit Experience</button>
                        </div>

                        <?php 
                        $validExp = array_filter($experiences, fn($e) => !empty($e['job_title']));
                        if (!empty($validExp)): 
                        ?>
                            <div class="space-y-6 relative before:absolute before:inset-0 before:left-3.5 before:w-0.5 before:bg-slate-200">
                                <?php foreach ($validExp as $exp): ?>
                                    <div class="relative pl-8 group">
                                        <div class="absolute left-0 top-1 w-7 h-7 rounded-full bg-white border-2 border-primary flex items-center justify-center">
                                            <div class="w-2 h-2 rounded-full bg-primary"></div>
                                        </div>
                                        <h4 class="text-base font-bold text-slate-900"><?php echo htmlspecialchars($exp['job_title']); ?></h4>
                                        <div class="text-xs font-semibold text-slate-600 mt-0.5"><?php echo htmlspecialchars($exp['company_name'] ?: 'Company Not Specified'); ?></div>
                                        <div class="text-xs text-slate-400 mt-1 font-medium">
                                            <?php echo htmlspecialchars($exp['start_date'] ?: 'N/A'); ?> — <?php echo htmlspecialchars($exp['end_date'] ?: 'Present'); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                <span class="text-2xl block mb-1">🌱</span>
                                <p class="text-xs font-semibold text-slate-600">Fresh Graduate / No prior work experience declared.</p>
                                <button onclick="toggleViewMode('step-2')" class="mt-2 text-xs font-bold text-primary hover:underline">Add work experience &rarr;</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Education Card -->
                    <div class="bg-white rounded-2xl p-6 sm:p-7 border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                                <span class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">🎓</span>
                                Education History
                            </h3>
                            <button onclick="toggleViewMode('step-2')" class="text-xs font-bold text-primary hover:underline">Edit Education</button>
                        </div>

                        <?php 
                        $validEdu = array_filter($educations, fn($e) => !empty($e['school_name']));
                        if (!empty($validEdu)): 
                        ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach ($validEdu as $edu): ?>
                                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-emerald-100 text-emerald-800 tracking-wider">
                                            <?php echo htmlspecialchars($edu['degree_level'] ?: 'Degree'); ?>
                                        </span>
                                        <h4 class="text-sm font-bold text-slate-900 mt-2"><?php echo htmlspecialchars($edu['school_name']); ?></h4>
                                        <?php if (!empty($edu['year_graduated'])): ?>
                                            <p class="text-xs text-slate-500 mt-1 font-medium">Graduation Year: <?php echo htmlspecialchars($edu['year_graduated']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                <p class="text-xs text-slate-500">No education background added yet.</p>
                                <button onclick="toggleViewMode('step-2')" class="mt-2 text-xs font-bold text-primary hover:underline">Add education &rarr;</button>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Right Column (Preferences & CV Document) -->
                <div class="space-y-8">
                    
                    <!-- Career Preferences Card -->
                    <div class="bg-white rounded-2xl p-6 sm:p-7 border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                                <span class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center text-xs">🎯</span>
                                Career Preferences
                            </h3>
                            <button onclick="toggleViewMode('step-3')" class="text-xs font-bold text-primary hover:underline">Edit</button>
                        </div>

                        <div class="space-y-4 text-xs">
                            <div class="flex justify-between py-2 border-b border-slate-100">
                                <span class="text-slate-500 font-medium">Desired Job Type</span>
                                <span class="font-bold text-slate-800"><?php echo htmlspecialchars($desiredJobType ?: 'Any'); ?></span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-slate-100">
                                <span class="text-slate-500 font-medium">Work Setup</span>
                                <span class="font-bold text-slate-800"><?php echo htmlspecialchars($workSetup ?: 'On-site'); ?></span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-slate-100">
                                <span class="text-slate-500 font-medium">Expected Salary</span>
                                <span class="font-bold text-emerald-600"><?php echo $expectedSalary !== '' ? '₱' . number_format((float)$expectedSalary) . ' / mo' : 'Negotiable'; ?></span>
                            </div>
                            <div class="py-2">
                                <span class="text-slate-500 font-medium block mb-2">Preferred Work Locations</span>
                                <div class="flex flex-wrap gap-1.5">
                                    <?php if (!empty($prefLocs)): ?>
                                        <?php foreach ($municipalities as $m): ?>
                                            <?php if (in_array((int)$m['municipality_id'], $prefLocs)): ?>
                                                <span class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 font-semibold text-[11px] border border-slate-200">📍 <?php echo htmlspecialchars($m['municipality_name']); ?></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">Open to all locations</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resume File Card -->
                    <div class="bg-white rounded-2xl p-6 sm:p-7 border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                                <span class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">📄</span>
                                Resume / CV File
                            </h3>
                        </div>

                        <?php if (!empty($p['resume']['stored_filename'])): ?>
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-base flex-shrink-0">
                                        📄
                                    </div>
                                    <div class="flex-1 overflow-hidden">
                                        <div class="text-xs font-bold text-slate-900 truncate"><?php echo htmlspecialchars($p['resume']['original_filename'] ?? 'Resume.pdf'); ?></div>
                                        <div class="text-[10px] text-emerald-600 font-bold uppercase tracking-wider mt-0.5">Active Resume File</div>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center gap-2">
                                    <a href="/sikaphub/admin/view-document?file=<?php echo htmlspecialchars($p['resume']['stored_filename']); ?>" target="_blank" class="flex-1 py-2 text-center text-xs font-bold bg-white text-primary border border-slate-200 rounded-lg hover:bg-primary hover:text-white transition-all shadow-xs">
                                        View Document
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 mb-4 font-medium">
                                ⚠️ No resume file uploaded yet. Employers prefer candidates with an attached CV.
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="/sikaphub/build-profile" enctype="multipart/form-data">
                            <?php echo CSRF::csrfField(); ?>
                            <input type="hidden" name="first_name" value="<?php echo $firstName; ?>">
                            <input type="hidden" name="last_name" value="<?php echo $lastName; ?>">
                            <input type="hidden" name="home_municipality_id" value="<?php echo $homeMunicipalityId; ?>">
                            <input type="hidden" name="barangay_id" value="<?php echo $homeBarangayId; ?>">
                            <input type="hidden" name="profile_visibility" value="<?php echo $visibility; ?>">
                            
                            <label class="block text-xs font-bold text-slate-700 mb-2">Upload / Replace Resume File</label>
                            <input type="file" name="resume_file" accept=".pdf,.docx,.doc" onchange="this.form.submit()" class="field-base bg-white text-xs cursor-pointer">
                            <p class="text-[10px] text-slate-400 mt-1">PDF or DOCX under 5 MB. Uploading automatically updates your profile.</p>
                        </form>
                    </div>

                    <!-- AI Resume Auto-Fill Prompt Card -->
                    <div class="bg-slate-900 text-white rounded-2xl p-6 shadow-sm border border-slate-800">
                        <div class="flex items-center gap-2.5 mb-2">
                            <span class="text-primary font-bold text-base">🔗</span>
                            <h4 class="font-extrabold text-sm text-white">AI Resume Parser</h4>
                        </div>
                        <p class="text-xs text-slate-300 leading-relaxed mb-4">
                            Auto-fill your profile experience, skills, and education automatically using our AI parser.
                        </p>
                        <button onclick="toggleViewMode('step-1'); scrollToAIParser();" class="w-full py-2.5 bg-primary hover:bg-primary-hover text-white font-bold text-xs rounded-xl transition-colors shadow-xs">
                            Launch AI Resume Reader &rarr;
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODE 2: CLEAN FORM EDITOR -->
        <!-- ========================================================================= -->
        <div id="edit-view" class="<?php echo $activeTab === 'overview' ? 'hidden' : ''; ?> animate-fade-in">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 lg:p-10 max-w-4xl mx-auto">
                
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8 pb-6 border-b border-slate-100">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight">
                            <?php echo $isNewProfile ? 'Build Your Candidate Profile' : 'Edit Profile Details'; ?>
                        </h2>
                        <p class="text-slate-500 mt-1 text-xs sm:text-sm">
                            <?php echo $isNewProfile ? 'Complete your information to enable AI job matching across Nueva Ecija.' : 'Update your personal info, work experience, skills, and job preferences below.'; ?>
                        </p>
                    </div>

                    <?php if (!$isNewProfile): ?>
                        <button type="button" onclick="toggleViewMode()" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold text-xs flex items-center gap-1.5 transition-all flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            Cancel Editing
                        </button>
                    <?php endif; ?>
                </div>

                <!-- AI Resume Reader Upload Area -->
                <div id="ai-parser-container" class="bg-blue-50/60 border border-blue-200 rounded-2xl p-5 mb-8">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-lg bg-primary text-white flex items-center justify-center shadow-xs flex-shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                                    Auto-Fill with AI Resume Reader
                                    <span class="bg-primary text-white text-[10px] font-extrabold px-2 py-0.5 rounded-md uppercase tracking-wider">AI Powered</span>
                                </h3>
                                <p class="text-xs text-slate-600 mt-0.5">Upload your CV or Resume (PDF or DOCX). Our AI Engine will read your document and fill your profile!</p>
                            </div>
                        </div>
                        <div class="w-full sm:w-auto flex-shrink-0">
                            <label for="ai-resume-upload-input" class="cursor-pointer bg-primary hover:bg-primary-hover text-white text-xs font-bold py-2.5 px-4 rounded-xl flex items-center justify-center gap-2 shadow-xs transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                                <span id="ai-upload-btn-text">Upload CV / Resume</span>
                            </label>
                            <input type="file" id="ai-resume-upload-input" accept=".pdf,.docx,.doc" class="hidden">
                        </div>
                    </div>
                    <div id="ai-resume-status-box" class="hidden mt-4 pt-4 border-t border-blue-200 text-xs font-medium"></div>
                </div>

                <!-- Wizard Progress Steps -->
                <div class="flex items-center mb-8" id="progress-bar">
                    <div class="flex flex-col items-center cursor-pointer group" id="prog-step-1" onclick="goToStep(1)">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold shadow-xs bg-primary text-white ring-4 ring-blue-50 group-hover:scale-105 transition-transform">
                            <span class="step-num">1</span><svg class="w-4 h-4 hidden check-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        </div>
                        <span class="text-xs font-bold text-primary mt-1.5">Identity &amp; Location</span>
                    </div>
                    <div class="step-connector mx-2" id="connector-1-2"></div>
                    <div class="flex flex-col items-center cursor-pointer group" id="prog-step-2" onclick="goToStep(2)">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold shadow-xs bg-slate-200 text-slate-400 group-hover:scale-105 transition-transform">
                            <span class="step-num">2</span><svg class="w-4 h-4 hidden check-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        </div>
                        <span class="text-xs font-medium text-slate-400 mt-1.5">Experience &amp; Edu</span>
                    </div>
                    <div class="step-connector mx-2" id="connector-2-3"></div>
                    <div class="flex flex-col items-center cursor-pointer group" id="prog-step-3" onclick="goToStep(3)">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold shadow-xs bg-slate-200 text-slate-400 group-hover:scale-105 transition-transform">
                            <span class="step-num">3</span><svg class="w-4 h-4 hidden check-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        </div>
                        <span class="text-xs font-medium text-slate-400 mt-1.5">Skills &amp; Preferences</span>
                    </div>
                </div>

                <form method="POST" action="/sikaphub/build-profile" enctype="multipart/form-data" id="profile-wizard-form" novalidate>
                    <?php echo CSRF::csrfField(); ?>

                    <!-- STEP 1 – IDENTITY & PHOTO -->
                    <div id="step-1" class="step-panel">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-7 h-7 rounded-md bg-blue-50 text-primary font-bold flex items-center justify-center text-xs">1</div>
                            <div><h3 class="text-base font-bold text-slate-900">Identity, Photo &amp; Location</h3></div>
                        </div>

                        <!-- Profile Photo Upload -->
                        <div class="mb-6 bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-col sm:flex-row items-center gap-4">
                            <div class="relative group flex-shrink-0">
                                <div class="w-20 h-20 rounded-xl border-2 border-white shadow-md overflow-hidden bg-slate-900 flex items-center justify-center text-white text-2xl font-bold">
                                    <?php if (!empty($p['profile_photo'])): ?>
                                        <img src="/sikaphub/admin/view-document?file=<?php echo htmlspecialchars($p['profile_photo']); ?>" alt="Profile Photo" class="photo-preview-img w-full h-full object-cover">
                                    <?php else: ?>
                                        <span class="photo-initials text-white"><?php echo strtoupper(substr($firstName ?: ($userEmail ?: 'U'), 0, 1)); ?></span>
                                        <img class="photo-preview-img w-full h-full object-cover hidden" alt="Profile Photo Preview">
                                    <?php endif; ?>
                                </div>
                                <label for="profile_photo_input" class="absolute bottom-0 right-0 bg-primary hover:bg-primary-hover text-white w-7 h-7 rounded-full flex items-center justify-center shadow-md cursor-pointer transition-transform hover:scale-105" title="Upload Photo">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0c-.693.047-1.332.428-1.736 1.039l-.821 1.316z" /></svg>
                                </label>
                                <input type="file" id="profile_photo_input" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewProfilePhoto(this)">
                            </div>
                            <div class="text-center sm:text-left flex-1">
                                <h4 class="text-sm font-bold text-slate-800">Profile Picture / Avatar</h4>
                                <p class="text-xs text-slate-500 mt-0.5">JPG, PNG, or WebP up to 2 MB.</p>
                                <label for="profile_photo_input" class="inline-flex items-center gap-1.5 mt-2 text-xs font-bold text-primary hover:underline cursor-pointer">
                                    Choose Photo from Device
                                </label>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Signup Email Address</label>
                            <input type="email" value="<?php echo $userEmail; ?>" readonly class="field-base bg-slate-100 cursor-not-allowed text-slate-500 font-medium">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">First Name *</label>
                                <input type="text" id="first_name" name="first_name" required value="<?php echo $firstName; ?>" class="field-base">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" required value="<?php echo $lastName; ?>" class="field-base">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Home Municipality / City *</label>
                            <select name="home_municipality_id" id="home_municipality_id" required class="field-base bg-white">
                                <option value="">— Select Home Municipality/City —</option>
                                <?php
                                $currentProvince = '';
                                foreach ($municipalities as $mun) {
                                    if ($currentProvince !== $mun['province_name']) {
                                        if ($currentProvince !== '') echo '</optgroup>';
                                        $currentProvince = $mun['province_name'];
                                        echo '<optgroup label="' . htmlspecialchars($currentProvince) . '">';
                                    }
                                    $selected = ($mun['municipality_id'] == $homeMunicipalityId) ? 'selected' : '';
                                    echo '<option value="' . $mun['municipality_id'] . '" ' . $selected . '>' . htmlspecialchars($mun['municipality_name']) . '</option>';
                                }
                                if ($currentProvince !== '') echo '</optgroup>';
                                ?>
                            </select>
                        </div>

                        <div class="mb-6">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Barangay</label>
                            <select name="barangay_id" id="barangay_id" class="field-base bg-white" <?php echo $homeMunicipalityId === '' ? 'disabled' : ''; ?>>
                                <option value="">— Select barangay —</option>
                            </select>
                        </div>

                        <div class="mb-8">
                            <label class="block text-xs font-bold text-slate-700 mb-2.5">Profile Visibility</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="radio-card">
                                    <input type="radio" id="vis_public" name="profile_visibility" value="Public" <?php echo $visibility === 'Public' ? 'checked' : ''; ?>>
                                    <label for="vis_public"><span class="text-base">🌐</span><div><div class="text-xs font-bold">Public Profile</div><div class="text-[11px] text-slate-400">Visible to employers</div></div></label>
                                </div>
                                <div class="radio-card">
                                    <input type="radio" id="vis_private" name="profile_visibility" value="Private" <?php echo $visibility === 'Private' ? 'checked' : ''; ?>>
                                    <label for="vis_private"><span class="text-base">🔒</span><div><div class="text-xs font-bold">Private Profile</div><div class="text-[11px] text-slate-400">Hidden from talent search</div></div></label>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                            <button type="button" onclick="goToStep(2)" class="bg-primary hover:bg-primary-hover text-white font-bold flex-1 py-3 rounded-xl text-sm flex items-center justify-center gap-2 shadow-xs transition-colors">Next: Experience &amp; Edu &rarr;</button>
                            <?php if (!$isNewProfile): ?>
                                <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white font-bold py-3 px-6 rounded-xl text-xs sm:text-sm transition-colors">Save Changes</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- STEP 2 – EXPERIENCE & EDUCATION -->
                    <div id="step-2" class="hidden step-panel">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 font-bold flex items-center justify-center text-xs">2</div>
                            <div><h3 class="text-base font-bold text-slate-900">Work Experience &amp; Education</h3></div>
                        </div>

                        <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-6">
                            <div><p class="text-xs font-bold text-slate-800">I have prior work experience</p></div>
                            <label for="experience-toggle" class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="experience-toggle" name="has_work_experience" value="1" class="sr-only peer" <?php echo ($isNewProfile || $hasExperienceRows) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>

                        <div id="experience-container">
                            <label class="block text-xs font-bold text-slate-700 mb-2">Work Experience</label>
                            
                            <?php foreach ($experiences as $index => $exp): ?>
                            <div class="experience-row bg-white border border-slate-200 rounded-xl p-4 mb-4 relative shadow-xs">
                                <?php if ($index > 0): ?>
                                <div class="flex justify-end mb-2">
                                    <button type="button" class="text-xs text-rose-500 font-bold hover:text-rose-700 transition-colors flex items-center gap-1 btn-remove-row">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg> Remove
                                    </button>
                                </div>
                                <?php endif; ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Job Title</label>
                                        <input type="text" name="experience[<?php echo $index; ?>][job_title]" value="<?php echo htmlspecialchars($exp['job_title'] ?? ''); ?>" class="field-base">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Company / Organization</label>
                                        <input type="text" name="experience[<?php echo $index; ?>][company]" value="<?php echo htmlspecialchars($exp['company_name'] ?? ''); ?>" class="field-base">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Start Date</label>
                                        <input type="month" name="experience[<?php echo $index; ?>][start_date]" value="<?php echo htmlspecialchars($exp['start_date'] ?? ''); ?>" class="field-base">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">End Date</label>
                                        <input type="month" name="experience[<?php echo $index; ?>][end_date]" value="<?php echo htmlspecialchars($exp['end_date'] ?? ''); ?>" class="field-base">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <button type="button" id="add-experience-btn" class="w-full py-2.5 rounded-xl border border-dashed border-slate-300 text-primary text-xs font-bold hover:bg-blue-50/50 transition-colors flex items-center justify-center gap-1.5 mb-6">
                                + Add another work experience
                            </button>
                        </div>

                        <div id="education-container" class="mt-8">
                            <label class="block text-xs font-bold text-slate-700 mb-2">Education Background</label>
                            
                            <?php foreach ($educations as $index => $edu): ?>
                            <div class="education-row bg-white border border-slate-200 rounded-xl p-4 mb-4 relative shadow-xs">
                                <?php if ($index > 0): ?>
                                <div class="flex justify-end mb-2">
                                    <button type="button" class="text-xs text-rose-500 font-bold hover:text-rose-700 transition-colors flex items-center gap-1 btn-remove-row">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg> Remove
                                    </button>
                                </div>
                                <?php endif; ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="sm:col-span-2">
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Degree / Education Level</label>
                                        <select name="education[<?php echo $index; ?>][degree_level]" class="field-base ts-degree">
                                            <option value="">— Select level —</option>
                                            <?php
                                            $levels = ['Elementary', 'High School', 'Vocational / TESDA', "Bachelor's Degree", "Master's Degree", 'Doctorate'];
                                            foreach ($levels as $lvl) {
                                                $sel = (($edu['degree_level'] ?? '') === $lvl) ? 'selected' : '';
                                                echo "<option value=\"$lvl\" $sel>$lvl</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">School / Institution</label>
                                        <input type="text" name="education[<?php echo $index; ?>][institution]" value="<?php echo htmlspecialchars($edu['school_name'] ?? ''); ?>" class="field-base ts-school">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Year Graduated</label>
                                        <input type="number" name="education[<?php echo $index; ?>][year_graduated]" value="<?php echo htmlspecialchars($edu['year_graduated'] ?? ''); ?>" class="field-base">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <button type="button" id="add-education-btn" class="w-full py-2.5 rounded-xl border border-dashed border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition-colors flex items-center justify-center gap-1.5 mb-6">
                                + Add education record
                            </button>
                        </div>

                        <div class="flex items-center gap-3 pt-6 border-t border-slate-100">
                            <button type="button" onclick="goToStep(1)" class="py-2.5 px-4 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition-all">&larr; Back</button>
                            <button type="button" onclick="goToStep(3)" class="bg-primary hover:bg-primary-hover text-white font-bold flex-1 py-3 rounded-xl text-xs sm:text-sm flex items-center justify-center gap-2 shadow-xs transition-colors">Next: Skills &amp; Preferences &rarr;</button>
                            <?php if (!$isNewProfile): ?>
                                <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white font-bold py-3 px-6 rounded-xl text-xs sm:text-sm transition-colors">Save Changes</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- STEP 3 – SKILLS & PREFERENCES -->
                    <div id="step-3" class="hidden step-panel">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 font-bold flex items-center justify-center text-xs">3</div>
                            <div><h3 class="text-base font-bold text-slate-900">Skills, Preferences &amp; Resume File</h3></div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Core Skills *</label>
                            <input type="text" id="skills" name="skills"
                                   value="<?php echo htmlspecialchars(implode(',', array_column($seekerSkills, 'skill_id'))); ?>"
                                   class="field-base">
                            <p class="text-[11px] text-slate-400 mt-1.5">Select approved skills from dropdown or type custom skills.</p>
                            <script>
                                window.APPROVED_SKILLS = <?php echo json_encode(array_map(fn($s) => ['value' => (string) $s['skill_id'], 'text' => $s['skill_name']], $approvedSkills)); ?>;
                                window.SEEKER_SKILLS = <?php echo json_encode(array_map(fn($s) => ['value' => (string) $s['skill_id'], 'text' => $s['skill_name']], $seekerSkills)); ?>;
                            </script>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Desired Job Type</label>
                                <select id="desired_job_type" name="desired_job_type" class="field-base bg-white">
                                    <option value="" disabled <?php echo empty($desiredJobType) ? 'selected' : ''; ?>>— Select type —</option>
                                    <?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship'] as $jt): ?>
                                        <option value="<?php echo $jt; ?>" <?php echo $desiredJobType === $jt ? 'selected' : ''; ?>><?php echo $jt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Preferred Work Setup</label>
                                <select id="preferred_work_setup" name="preferred_work_setup" class="field-base bg-white">
                                    <option value="" disabled <?php echo empty($workSetup) ? 'selected' : ''; ?>>— Select setup —</option>
                                    <?php foreach (['On-site', 'Hybrid', 'Remote'] as $ws): ?>
                                        <option value="<?php echo $ws; ?>" <?php echo $workSetup === $ws ? 'selected' : ''; ?>><?php echo $ws; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Expected Monthly Salary</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none"><span class="text-xs font-bold text-slate-400">₱</span></div>
                                    <input type="number" name="expected_salary" value="<?php echo $expectedSalary; ?>" min="0" placeholder="e.g. 25000" class="field-base pl-8">
                                </div>
                            </div>

                            <div class="col-span-1 md:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Preferred Work Locations *</label>
                                <select id="preferred_locations" name="preferred_municipality_ids[]" multiple required class="field-base">
                                    <?php
                                    $currentProvincePref = '';
                                    foreach ($municipalities as $mun) {
                                        if ($currentProvincePref !== $mun['province_name']) {
                                            if ($currentProvincePref !== '') echo '</optgroup>';
                                            $currentProvincePref = $mun['province_name'];
                                            echo '<optgroup label="' . htmlspecialchars($currentProvincePref) . '">';
                                        }
                                        $selectedPref = in_array($mun['municipality_id'], $prefLocs) ? 'selected' : '';
                                        echo '<option value="' . $mun['municipality_id'] . '" ' . $selectedPref . '>' . htmlspecialchars($mun['municipality_name']) . '</option>';
                                    }
                                    if ($currentProvincePref !== '') echo '</optgroup>';
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-8 bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <label class="block text-xs font-bold text-slate-800 mb-1">Resume / CV Document (PDF or DOCX)</label>
                            <p class="text-[11px] text-slate-500 mb-3">Attach your official resume file for employer review.</p>
                            <?php if (!empty($p['resume']['stored_filename'])): ?>
                                <div class="flex items-center gap-3 bg-white border border-slate-200 rounded-lg p-3 mb-3 shadow-xs">
                                    <span class="text-base">📄</span>
                                    <div class="flex-1 overflow-hidden">
                                        <div class="text-xs font-bold text-slate-800 truncate"><?php echo htmlspecialchars($p['resume']['original_filename'] ?? 'Uploaded Resume'); ?></div>
                                        <div class="text-[10px] text-emerald-600 font-bold">Active Resume File</div>
                                    </div>
                                    <a href="/sikaphub/admin/view-document?file=<?php echo htmlspecialchars($p['resume']['stored_filename']); ?>" target="_blank" class="text-xs font-bold text-primary hover:underline">
                                        View Document
                                    </a>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="resume_file" accept=".pdf,.docx,.doc" class="field-base bg-white text-xs cursor-pointer">
                        </div>

                        <div class="flex items-center justify-between pt-6 border-t border-slate-100">
                            <button type="button" onclick="goToStep(2)" class="py-2.5 px-4 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition-all">&larr; Back</button>
                            <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-extrabold flex-1 ml-4 py-3.5 rounded-xl text-sm flex items-center justify-center gap-2 shadow-xs transition-colors">
                                <?php echo $isNewProfile ? 'Complete Profile &amp; Activate Account' : 'Save Profile Changes'; ?>
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>

    </main>

    <footer class="bg-white border-t border-slate-200 py-6 mt-12 text-center text-xs text-slate-500 font-medium">
        <div class="max-w-7xl mx-auto px-4">
            &copy; <?php echo date('Y'); ?> S.I.K.A.P. Hub — Systems Integration &amp; Knowledge Advancement Platform. PESO Nueva Ecija.
        </div>
    </footer>

    <script>
    // Header User Menu Dropdown Toggle
    document.addEventListener('DOMContentLoaded', function () {
        const userMenuBtn = document.getElementById('user-menu-button');
        const userMenuDropdown = document.getElementById('user-menu-dropdown');
        if (userMenuBtn && userMenuDropdown) {
            userMenuBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                userMenuDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', function (e) {
                if (!userMenuDropdown.contains(e.target) && !userMenuBtn.contains(e.target)) {
                    userMenuDropdown.classList.add('hidden');
                }
            });
        }
    });

    // View/Edit Mode Toggle Logic
    window.toggleViewMode = function (targetStep) {
        const overview = document.getElementById('overview-view');
        const edit = document.getElementById('edit-view');
        const btnText = document.getElementById('toggle-btn-text');
        
        if (edit.classList.contains('hidden')) {
            overview.classList.add('hidden');
            edit.classList.remove('hidden');
            if (btnText) btnText.textContent = 'View Profile Overview';
            if (targetStep && window.goToStep) {
                if (targetStep === 'step-1') window.goToStep(1);
                else if (targetStep === 'step-2') window.goToStep(2);
                else if (targetStep === 'step-3') window.goToStep(3);
            }
        } else {
            edit.classList.add('hidden');
            overview.classList.remove('hidden');
            if (btnText) btnText.textContent = 'Edit Profile Details';
        }
    };

    window.scrollToAIParser = function () {
        const parser = document.getElementById('ai-parser-container');
        if (parser) parser.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    window.previewProfilePhoto = function (input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const imgs = document.querySelectorAll('.photo-preview-img');
                const initials = document.querySelectorAll('.photo-initials');
                imgs.forEach(function (img) {
                    img.src = e.target.result;
                    img.classList.remove('hidden');
                });
                initials.forEach(function (init) {
                    init.classList.add('hidden');
                });
            };
            reader.readAsDataURL(input.files[0]);
        }
    };

    (function () {
        'use strict';
        const TOTAL_STEPS = 3;
        let currentStep  = 1;
        const panels = { 1: document.getElementById('step-1'), 2: document.getElementById('step-2'), 3: document.getElementById('step-3') };
        const progSteps = { 1: document.getElementById('prog-step-1'), 2: document.getElementById('prog-step-2'), 3: document.getElementById('prog-step-3') };
        const connectors = { '1-2': document.getElementById('connector-1-2'), '2-3': document.getElementById('connector-2-3') };

        function setStepBubbleState(stepNum, state) {
            if (!progSteps[stepNum]) return;
            const bubble  = progSteps[stepNum].querySelector('div');
            const numSpan = bubble.querySelector('.step-num');
            const checkIcon = bubble.querySelector('.check-icon');
            bubble.className = bubble.className.replace(/bg-\S+|text-\S+|ring-\S+|from-\S+|to-\S+/g, '').trim();

            if (state === 'active') {
                bubble.classList.add('bg-primary', 'text-white', 'ring-4', 'ring-blue-50');
                if (numSpan) numSpan.classList.remove('hidden'); 
                if (checkIcon) checkIcon.classList.add('hidden');
            } else if (state === 'done') {
                bubble.classList.add('bg-primary', 'text-white');
                if (numSpan) numSpan.classList.add('hidden'); 
                if (checkIcon) checkIcon.classList.remove('hidden');
            } else {
                bubble.classList.add('bg-slate-200', 'text-slate-400');
                if (numSpan) numSpan.classList.remove('hidden'); 
                if (checkIcon) checkIcon.classList.add('hidden');
            }
        }

        function updateProgressBar(targetStep) {
            for (let s = 1; s <= TOTAL_STEPS; s++) {
                if (s < targetStep) setStepBubbleState(s, 'done');
                else if (s === targetStep) setStepBubbleState(s, 'active');
                else setStepBubbleState(s, 'idle');
            }
            if (connectors['1-2']) connectors['1-2'].classList.toggle('active', targetStep > 1);
            if (connectors['2-3']) connectors['2-3'].classList.toggle('active', targetStep > 2);
        }

        window.goToStep = function (targetStep) {
            if (targetStep < 1 || targetStep > TOTAL_STEPS) return;
            if (currentStep === 1 && targetStep > 1) {
                const firstName = document.getElementById('first_name');
                const lastName  = document.getElementById('last_name');
                let valid = true;
                [firstName, lastName].forEach(function (el) {
                    if (el && !el.value.trim()) {
                        el.classList.add('border-rose-400');
                        valid = false;
                    } else if (el) el.classList.remove('border-rose-400');
                });
                if (!valid) return;
            }
            if (panels[currentStep]) panels[currentStep].classList.add('hidden');
            if (panels[targetStep]) panels[targetStep].classList.remove('hidden');
            currentStep = targetStep;
            updateProgressBar(currentStep);
            document.getElementById('progress-bar').scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        ['first_name', 'last_name'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', function () { el.classList.remove('border-rose-400'); });
        });

        updateProgressBar(1);

        // Experience Toggle Logic
        const expToggle = document.getElementById('experience-toggle');
        const expContainer = document.getElementById('experience-container');
        if (expToggle && expContainer) {
            expToggle.addEventListener('change', function () {
                if (this.checked) expContainer.classList.remove('hidden');
                else {
                    expContainer.classList.add('hidden');
                    expContainer.querySelectorAll('input').forEach(inp => inp.value = '');
                }
            });
        }

        // Event Delegation for dynamically removing rows
        ['experience-container', 'education-container'].forEach(containerId => {
            const container = document.getElementById(containerId);
            if(container) {
                container.addEventListener('click', function(e) {
                    const removeBtn = e.target.closest('.btn-remove-row');
                    if (removeBtn) removeBtn.closest('div[class*="-row"]').remove();
                });
            }
        });

        function registerDynamicRows(btnId, containerId, rowSelector, arrayName) {
            const btn = document.getElementById(btnId);
            const container = document.getElementById(containerId);
            if (!btn || !container) return;

            btn.addEventListener('click', function () {
                const existingRows = container.querySelectorAll(rowSelector);
                const newIndex = existingRows.length;
                const template = existingRows[0];
                if (!template) return;
                const clone = template.cloneNode(true);

                clone.querySelectorAll('.tomselected').forEach(input => {
                    const wrapper = input.closest('.ts-wrapper');
                    if (wrapper && wrapper.parentNode) {
                        wrapper.parentNode.insertBefore(input, wrapper);
                    }
                    input.classList.remove('tomselected', 'hidden');
                    input.removeAttribute('hidden');
                    input.removeAttribute('tabindex');
                    input.style.display = '';
                    if(input.tagName === 'SELECT') input.selectedIndex = 0;
                    else input.value = '';
                });

                clone.querySelectorAll('.ts-wrapper').forEach(wrapper => wrapper.remove());

                clone.querySelectorAll('[name]').forEach(el => {
                    el.name = el.name.replace(new RegExp('\\[0\\]', 'g'), '[' + newIndex + ']');
                    if (el.tagName === 'SELECT') el.selectedIndex = 0;
                    else el.value = '';
                });

                if (!clone.querySelector('.btn-remove-row')) {
                    const removeDiv = document.createElement('div');
                    removeDiv.className = 'flex justify-end mb-2';
                    removeDiv.innerHTML = '<button type="button" class="text-xs text-rose-500 font-bold hover:text-rose-700 transition-colors flex items-center gap-1 btn-remove-row"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg> Remove</button>';
                    clone.insertBefore(removeDiv, clone.firstChild);
                }

                container.insertBefore(clone, btn);
            });
        }

        registerDynamicRows('add-experience-btn', 'experience-container', '.experience-row', 'experience');
        registerDynamicRows('add-education-btn',  'education-container',  '.education-row',  'education');

        if (document.getElementById('skills')) {
            var skillOptions = (window.APPROVED_SKILLS || []).concat(window.SEEKER_SKILLS || []);
            var skillItems = (window.SEEKER_SKILLS || []).map(function (s) { return s.value; });
            window.skillsTomSelectInstance = new TomSelect("#skills", {
                options: skillOptions,
                items: skillItems,
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                plugins: ['remove_button'],
                create: true,
                createOnBlur: true,
                persist: false,
                placeholder: 'Type to search 13,000+ online job skills or custom skill...',
                load: function(query, callback) {
                    if (!query.length || query.length < 2) return callback();
                    fetch('https://ec.europa.eu/esco/api/suggest?type=skill&term=' + encodeURIComponent(query) + '&language=en')
                        .then(function(r) { return r.json(); })
                        .then(function(json) {
                            var results = [];
                            if (json && json._embedded && json._embedded.results) {
                                results = json._embedded.results.map(function(item) {
                                    return { value: item.title, text: item.title };
                                });
                            }
                            callback(results);
                        })
                        .catch(function() {
                            callback();
                        });
                },
                render: {
                    item: function (data, escape) {
                        return '<div class="bg-blue-50 text-blue-800 rounded-md px-2 py-1 m-1 text-xs font-bold border border-blue-200">' + escape(data.text) + '</div>';
                    }
                }
            });
        }

        if (document.getElementById('preferred_locations')) {
            new TomSelect("#preferred_locations", { plugins: ['remove_button'], placeholder: 'Search and select preferred locations...', closeAfterSelect: false, render: { item: function(data, escape) { return '<div class="bg-slate-100 text-slate-800 rounded-md px-2 py-1 m-1 text-xs font-bold border border-slate-200">' + escape(data.text) + '</div>'; } } });
        }

        // Municipality -> barangay cascade
        var munSelect = document.getElementById('home_municipality_id');
        var brgySelect = document.getElementById('barangay_id');
        var PRESELECT_BRGY = <?php echo json_encode($homeBarangayId === '' ? null : (int) $homeBarangayId); ?>;

        function loadBarangays(municipalityId, preselect) {
            if (!brgySelect) return;
            if (!municipalityId) {
                brgySelect.innerHTML = '<option value="">— Select barangay —</option>';
                brgySelect.disabled = true;
                return;
            }
            fetch('/sikaphub/profile/barangays?municipality_id=' + encodeURIComponent(municipalityId))
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    brgySelect.innerHTML = '<option value="">— Select barangay —</option>';
                    var preselectStr = preselect ? String(preselect).toLowerCase().replace(/^brgy\.?\s*/i, '').replace(/^barangay\s*/i, '').trim() : null;
                    rows.forEach(function (b) {
                        var o = document.createElement('option');
                        o.value = b.barangay_id;
                        o.textContent = b.barangay_name;
                        if (preselect) {
                            if (String(preselect) === String(b.barangay_id)) {
                                o.selected = true;
                            } else if (preselectStr && preselectStr.length >= 3) {
                                var bName = b.barangay_name.toLowerCase().trim();
                                if (bName === preselectStr || bName.includes(preselectStr) || preselectStr.includes(bName)) {
                                    o.selected = true;
                                }
                            }
                        }
                        brgySelect.appendChild(o);
                    });
                    brgySelect.disabled = false;
                })
                .catch(function () { brgySelect.disabled = true; });
        }

        if (munSelect && brgySelect) {
            munSelect.addEventListener('change', function () { loadBarangays(munSelect.value, null); });
            if (munSelect.value) loadBarangays(munSelect.value, PRESELECT_BRGY);
        }

        // AI Resume Reader Upload Handler
        var resumeInput = document.getElementById('ai-resume-upload-input');
        var statusBox = document.getElementById('ai-resume-status-box');
        var uploadBtnText = document.getElementById('ai-upload-btn-text');

        if (resumeInput) {
            resumeInput.addEventListener('change', function () {
                var file = this.files[0];
                if (!file) return;

                var fname = file.name.toLowerCase();
                if (!fname.endsWith('.pdf') && !fname.endsWith('.docx') && !fname.endsWith('.doc')) {
                    statusBox.className = 'mt-4 pt-4 border-t border-rose-200 text-xs font-bold text-rose-600 block';
                    statusBox.innerHTML = '❌ Invalid file type. Please upload a PDF or DOCX resume document.';
                    return;
                }

                statusBox.className = 'mt-4 pt-4 border-t border-blue-200 text-xs font-bold text-primary block';
                statusBox.innerHTML = '<div class="flex items-center gap-2"><svg class="animate-spin h-4 w-4 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> 🔗 Reading resume with AI Engine... Please wait</div>';
                if (uploadBtnText) uploadBtnText.textContent = 'Parsing CV...';

                var formData = new FormData();
                formData.append('resume_file', file);

                var csrfInput = document.querySelector('input[name="csrf_token"]');
                if (csrfInput) {
                    formData.append('csrf_token', csrfInput.value);
                }

                fetch('/sikaphub/profile/parse-resume', {
                    method: 'POST',
                    body: formData
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (uploadBtnText) uploadBtnText.textContent = 'Upload CV / Resume';
                    if (!data.success || !data.profile) {
                        statusBox.className = 'mt-4 pt-4 border-t border-rose-200 text-xs font-bold text-rose-600 block';
                        statusBox.innerHTML = '❌ ' + (data.message || 'Failed to read resume details.');
                        return;
                    }

                    var p = data.profile;
                    var filledFields = [];

                    if (p.name && p.name !== 'Candidate Name Not Found') {
                        var cleanName = p.name.replace(/,/g, '').trim();
                        var nameParts = cleanName.split(/\s+/);
                        var firstNameEl = document.getElementById('first_name');
                        var lastNameEl = document.getElementById('last_name');

                        if (nameParts.length > 1) {
                            var lastName = nameParts.pop();
                            var firstParts = nameParts.filter(function(part) { return !/^[A-Za-z]\.?$/.test(part); });
                            var firstName = firstParts.length > 0 ? firstParts.join(' ') : nameParts.join(' ');
                            if (firstNameEl) firstNameEl.value = firstName;
                            if (lastNameEl) lastNameEl.value = lastName;
                        } else if (nameParts.length === 1 && firstNameEl) {
                            firstNameEl.value = nameParts[0];
                        }
                        filledFields.push('Name');
                    }

                    if (p.experience && p.experience.length > 0) {
                        var expContainer = document.getElementById('experience-container');
                        var expToggle = document.getElementById('experience-toggle');
                        var validExpList = [];
                        var seenExpKeys = {};

                        p.experience.forEach(function (exp) {
                            var title = (exp.title || '').trim();
                            var company = (exp.company_or_details || exp.company || '').trim();
                            if (!title && !company) return;
                            var expKey = (title.toLowerCase() + '|' + company.toLowerCase());
                            if (!seenExpKeys[expKey]) {
                                seenExpKeys[expKey] = true;
                                validExpList.push({ title: title, company: company });
                            }
                        });

                        if (validExpList.length > 0) {
                            if (expToggle && !expToggle.checked) expToggle.click();
                            if (expContainer) {
                                var existingExpRows = expContainer.querySelectorAll('.experience-row');
                                for (var er = 1; er < existingExpRows.length; er++) { existingExpRows[er].remove(); }
                                validExpList.forEach(function (expItem, idx) {
                                    var rows = expContainer.querySelectorAll('.experience-row');
                                    var row = rows[idx];
                                    if (!row) {
                                        var addExpBtn = document.getElementById('add-experience-btn');
                                        if (addExpBtn) addExpBtn.click();
                                        rows = expContainer.querySelectorAll('.experience-row');
                                        row = rows[idx];
                                    }
                                    if (row) {
                                        var titleInp = row.querySelector('input[name*="[job_title]"]');
                                        var compInp = row.querySelector('input[name*="[company]"]');
                                        if (titleInp) titleInp.value = expItem.title;
                                        if (compInp) compInp.value = expItem.company;
                                    }
                                });
                                filledFields.push('Work Experience (' + validExpList.length + ' items)');
                            }
                        }
                    }

                    if (p.education && p.education.length > 0) {
                        var eduContainer = document.getElementById('education-container');
                        if (eduContainer) {
                            var validEduList = [];
                            var seenEduKeys = {};

                            p.education.forEach(function (edu) {
                                var rawSchool = (edu.institution || edu.school_name || '').trim();
                                if (rawSchool.startsWith('•') || rawSchool.startsWith('-')) rawSchool = '';
                                var dText = ((edu.degree_level || '') + ' ' + (edu.degree_or_level || '') + ' ' + rawSchool).toLowerCase();
                                var level = "";
                                if (dText.includes('master')) level = "Master's Degree";
                                else if (dText.includes('doctor') || dText.includes('phd')) level = "Doctorate";
                                else if (dText.includes('high school') || dText.includes('highschool') || dText.includes('secondary') || dText.includes('shs')) level = "High School";
                                else if (dText.includes('elementary') || dText.includes('primary')) level = "Elementary";
                                else if (dText.includes('tesda') || dText.includes('vocational')) level = "Vocational / TESDA";
                                else if (dText.includes('bachelor') || dText.includes('college') || dText.includes('university') || dText.includes('degree') || dText.includes('information technology') || dText.includes('computer science')) level = "Bachelor's Degree";

                                var yearMatch = String(edu.year || edu.year_graduated || '').match(/\b(19\d{2}|20\d{2})\b/);
                                var year = yearMatch ? yearMatch[0] : '';
                                if (!rawSchool && !level && !year) return;

                                var dedupKey = (rawSchool.toLowerCase() + '|' + level.toLowerCase() + '|' + year);
                                if (!seenEduKeys[dedupKey]) {
                                    seenEduKeys[dedupKey] = true;
                                    validEduList.push({ school: rawSchool, level: level, year: year });
                                }
                            });

                            if (validEduList.length > 0) {
                                var existingEduRows = eduContainer.querySelectorAll('.education-row');
                                validEduList.forEach(function (eduItem, idx) {
                                    var row = existingEduRows[idx];
                                    if (!row) {
                                        var addBtn = document.getElementById('add-education-btn');
                                        if (addBtn) addBtn.click();
                                        existingEduRows = eduContainer.querySelectorAll('.education-row');
                                        row = existingEduRows[idx];
                                    }
                                    if (row) {
                                        var degSel = row.querySelector('select[name*="[degree_level]"]') || row.querySelector('select.ts-degree');
                                        var instInp = row.querySelector('input[name*="[institution]"]') || row.querySelector('input.ts-school');
                                        var yearInp = row.querySelector('input[name*="[year_graduated]"]');
                                        if (instInp && eduItem.school) instInp.value = eduItem.school;
                                        if (yearInp && eduItem.year) yearInp.value = eduItem.year;
                                        if (degSel && eduItem.level) degSel.value = eduItem.level;
                                    }
                                });
                                filledFields.push('Education (' + validEduList.length + ' items)');
                            }
                        }
                    }

                    if (p.skills && p.skills.all_skills && p.skills.all_skills.length > 0 && window.skillsTomSelectInstance) {
                        p.skills.all_skills.forEach(function (sk) {
                            var match = (window.APPROVED_SKILLS || []).find(function (opt) {
                                return opt.text.toLowerCase() === sk.toLowerCase();
                            });
                            if (match) {
                                window.skillsTomSelectInstance.addItem(match.value);
                            } else {
                                window.skillsTomSelectInstance.addOption({ value: sk, text: sk });
                                window.skillsTomSelectInstance.addItem(sk);
                            }
                        });
                        filledFields.push('Skills (' + p.skills.all_skills.length + ' detected)');
                    }

                    if (p.location && p.location.address && munSelect) {
                        var locText = (p.location.address + ' ' + (p.location.barangay || '')).toLowerCase();
                        var matchedMunId = null;
                        var matchedMunName = null;

                        for (var mIdx = 0; mIdx < munSelect.options.length; mIdx++) {
                            var opt = munSelect.options[mIdx];
                            if (!opt.value) continue;
                            var optName = opt.textContent.trim().toLowerCase();
                            var cleanOptName = optName.replace(/\b(city of|city)\b/g, '').trim();

                            if (cleanOptName.length >= 3 && (locText.includes(optName) || locText.includes(cleanOptName))) {
                                matchedMunId = opt.value;
                                matchedMunName = opt.textContent.trim();
                                break;
                            }
                        }

                        if (matchedMunId) {
                            munSelect.value = matchedMunId;
                            var brgySearchHint = p.location.barangay || p.location.address;
                            loadBarangays(matchedMunId, brgySearchHint);
                            filledFields.push('Location (' + matchedMunName + ')');
                        }
                    }

                    statusBox.className = 'mt-4 pt-4 border-t border-emerald-200 text-xs font-bold text-emerald-700 block';
                    statusBox.innerHTML = '✅ <strong>Resume read successfully!</strong> Auto-filled: ' + filledFields.join(', ') + '. Review details below.';
                })
                .catch(function (err) {
                    if (uploadBtnText) uploadBtnText.textContent = 'Upload CV / Resume';
                    statusBox.className = 'mt-4 pt-4 border-t border-rose-200 text-xs font-bold text-rose-600 block';
                    statusBox.innerHTML = '❌ Connection error or failed to process resume file.';
                });
            });
        }
    })();

    /* =====================================================================
       AUTO-SAVE PROFILE PROGRESS & DRAFT RESTORATION ENGINE
       Prevents losing form progress when refreshing or navigating away
    ===================================================================== */
    (function () {
        var userId = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;
        var role = <?php echo json_encode($_SESSION['role'] ?? 'jobseeker'); ?>;
        var DRAFT_KEY = 'sikaphub_seeker_draft_' + (userId || 'guest') + '_' + role;

        var form = document.querySelector('form');
        if (!form) return;

        var saveTimeout = null;
        function saveDraft() {
            if (saveTimeout) clearTimeout(saveTimeout);
            saveTimeout = setTimeout(function () {
                var formData = {};

                var inputs = form.querySelectorAll('input:not([type="file"]):not([type="hidden"]):not([name="csrf_token"]), select, textarea');
                inputs.forEach(function (el) {
                    if (!el.name) return;
                    if (el.type === 'checkbox') {
                        formData[el.name] = el.checked;
                    } else if (el.type === 'radio') {
                        if (el.checked) formData[el.name] = el.value;
                    } else {
                        formData[el.name] = el.value;
                    }
                });

                var eduRows = [];
                document.querySelectorAll('.education-row').forEach(function (row) {
                    var deg = (row.querySelector('select[name*="[degree_level]"]') || {}).value || '';
                    var inst = (row.querySelector('input[name*="[institution]"]') || {}).value || '';
                    var yr = (row.querySelector('input[name*="[year_graduated]"]') || {}).value || '';
                    if (deg || inst || yr) {
                        eduRows.push({ degree_level: deg, institution: inst, year_graduated: yr });
                    }
                });
                if (eduRows.length > 0) formData['__education_rows'] = eduRows;

                var expRows = [];
                document.querySelectorAll('.experience-row').forEach(function (row) {
                    var title = (row.querySelector('input[name*="[job_title]"]') || {}).value || '';
                    var company = (row.querySelector('input[name*="[company]"]') || {}).value || '';
                    var start = (row.querySelector('input[name*="[start_date]"]') || {}).value || '';
                    var end = (row.querySelector('input[name*="[end_date]"]') || {}).value || '';
                    if (title || company || start || end) {
                        expRows.push({ job_title: title, company: company, start_date: start, end_date: end });
                    }
                });
                if (expRows.length > 0) formData['__experience_rows'] = expRows;

                if (window.skillsTomSelectInstance) {
                    formData['skills'] = window.skillsTomSelectInstance.getValue();
                }

                formData['__timestamp'] = new Date().toISOString();
                localStorage.setItem(DRAFT_KEY, JSON.stringify(formData));
            }, 300);
        }

        function restoreDraft() {
            var raw = localStorage.getItem(DRAFT_KEY);
            if (!raw) return;

            try {
                var data = JSON.parse(raw);

                Object.keys(data).forEach(function (key) {
                    if (key.indexOf('__') === 0) return;
                    var val = data[key];
                    if (val === null || val === undefined) return;

                    var field = form.querySelector('[name="' + key + '"]');
                    if (field) {
                        if (field.type === 'checkbox') {
                            field.checked = Boolean(val);
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        } else if (field.type === 'radio') {
                            var targetRadio = form.querySelector('[name="' + key + '"][value="' + val + '"]');
                            if (targetRadio) targetRadio.checked = true;
                        } else {
                            field.value = val;
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                });

                var munSelect = form.querySelector('select[name="home_municipality_id"]') || form.querySelector('select[name="municipality_id"]');
                var brgySelect = form.querySelector('select[name="barangay_id"]');
                if (munSelect && data[munSelect.name]) {
                    munSelect.value = data[munSelect.name];
                    munSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    if (brgySelect && data[brgySelect.name]) {
                        setTimeout(function () {
                            brgySelect.value = data[brgySelect.name];
                        }, 400);
                    }
                }

                if (Array.isArray(data.__education_rows) && data.__education_rows.length > 0) {
                    var addEduBtn = document.getElementById('add-education-btn');
                    var rows = document.querySelectorAll('.education-row');
                    data.__education_rows.forEach(function (item, idx) {
                        if (!rows[idx] && addEduBtn) {
                            addEduBtn.click();
                            rows = document.querySelectorAll('.education-row');
                        }
                        var row = rows[idx];
                        if (row) {
                            var deg = row.querySelector('select[name*="[degree_level]"]');
                            var inst = row.querySelector('input[name*="[institution]"]');
                            var yr = row.querySelector('input[name*="[year_graduated]"]');
                            if (deg && item.degree_level) deg.value = item.degree_level;
                            if (inst && item.institution) inst.value = item.institution;
                            if (yr && item.year_graduated) yr.value = item.year_graduated;
                        }
                    });
                }

                if (Array.isArray(data.__experience_rows) && data.__experience_rows.length > 0) {
                    var expToggle = document.getElementById('toggle-no-experience') || form.querySelector('[name="has_work_experience"]');
                    if (expToggle && !expToggle.checked) {
                        expToggle.checked = true;
                        expToggle.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    var addExpBtn = document.getElementById('add-experience-btn');
                    var expRows = document.querySelectorAll('.experience-row');
                    data.__experience_rows.forEach(function (item, idx) {
                        if (!expRows[idx] && addExpBtn) {
                            addExpBtn.click();
                            expRows = document.querySelectorAll('.experience-row');
                        }
                        var row = expRows[idx];
                        if (row) {
                            var title = row.querySelector('input[name*="[job_title]"]');
                            var company = row.querySelector('input[name*="[company]"]');
                            var start = row.querySelector('input[name*="[start_date]"]');
                            var end = row.querySelector('input[name*="[end_date]"]');
                            if (title && item.job_title) title.value = item.job_title;
                            if (company && item.company) company.value = item.company;
                            if (start && item.start_date) start.value = item.start_date;
                            if (end && item.end_date) end.value = item.end_date;
                        }
                    });
                }

                if (data.skills && window.skillsTomSelectInstance) {
                    var skillsArr = Array.isArray(data.skills) ? data.skills : String(data.skills).split(',');
                    skillsArr.forEach(function (s) {
                        var sk = s.trim();
                        if (!sk) return;
                        if (!window.skillsTomSelectInstance.options[sk]) {
                            window.skillsTomSelectInstance.addOption({ value: sk, text: sk });
                        }
                        window.skillsTomSelectInstance.addItem(sk);
                    });
                }
            } catch (e) {
                console.error('Failed to restore draft:', e);
            }
        }

        form.addEventListener('input', saveDraft);
        form.addEventListener('change', saveDraft);

        document.addEventListener('click', function (e) {
            if (e.target && e.target.closest('#add-experience-btn, #add-education-btn, .btn-remove-row, #toggle-no-experience')) {
                setTimeout(saveDraft, 200);
            }
        });

        restoreDraft();
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', restoreDraft);
        }
    })();
    </script>
</body>
</html>