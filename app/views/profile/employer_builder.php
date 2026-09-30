<?php
/**
 * Employer profile builder — GET/POST /build-profile (M2 §5.1 / §8).
 * One view, two modes:
 *   CREATE  (no employers row yet)  — full identity + location + business
 *           permit upload; on submit the row is inserted with
 *           verified_status 'Pending' and the account is activated.
 *   EDIT    (row present)           — identity anchors (company name, address,
 *           municipality) and the permit are read-only; everything else is
 *           editable. Scoped by session user_id in the controller/model.
 *
 * Receives: $existing_profile (array, [] when creating), $municipalities,
 *           optional $error.
 */
$p        = $existing_profile ?? [];
$isCreate = empty($p);
$err      = $error ?? null;

$municipalities = $municipalities ?? [];

$v = fn($k, $d = '') => htmlspecialchars((string) ($p[$k] ?? $d));
$companyName  = $v('company_name');
$contact      = $v('contact_person');
$email        = $v('company_email');
$phone        = $v('company_phone');
$street       = $v('street_name');
$industry     = $v('industry');
$companySize  = $v('company_size');
$companyDesc  = $v('company_description');
$website      = $v('company_website');
$municipalityId = (int) ($p['municipality_id'] ?? 0);
$barangayId     = (int) ($p['barangay_id'] ?? 0);

$sizeLabels = [
    '1-10' => '1-10 Employees', '11-50' => '11-50 Employees',
    '51-200' => '51-200 Employees', '201-500' => '201-500 Employees',
    '500+' => '500+ Employees',
];
$companySizeLabel = $sizeLabels[$companySize] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isCreate ? 'Build Your Company Profile' : 'Edit Company Profile'; ?> – S.I.K.A.P. Hub</title>
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
                    <span class="bg-indigo-50 text-indigo-700 text-[10px] font-extrabold px-2 py-0.5 rounded-md uppercase tracking-wider border border-indigo-100 hidden sm:inline-block">Employer Profile</span>
                </a>
                
                <div class="flex items-center gap-6">
                    <a href="/employer/dashboard" class="text-sm font-semibold text-slate-500 hover:text-primary transition-colors">Dashboard</a>
                    <a href="/build-profile" class="text-sm font-bold text-primary border-b-2 border-primary py-5">Company Profile</a>
                    
                    <a href="/employer/dashboard" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 px-3.5 py-1.5 rounded-lg transition-all">
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
            <h1 class="text-3xl font-extrabold tracking-tight">
                <?php echo $isCreate ? 'Build Your Official Company Profile' : 'Manage Company Profile'; ?>
            </h1>
            <p class="text-slate-300 text-sm mt-1.5 max-w-xl leading-relaxed">
                <?php echo $isCreate
                    ? 'Submit your business information and valid permit for PESO Guimba verification to begin publishing vacancies.'
                    : 'Update your contact information, industry details, and company overview.'; ?>
            </p>
        </div>
    </header>

    <!-- Main Workspace Container -->
    <main class="flex-grow max-w-4xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <?php if ($err): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl mb-6 flex items-center gap-3 text-sm font-bold shadow-sm">
                <span class="text-xl">⚠️</span>
                <span><?php echo htmlspecialchars($err); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

            <!-- Section 1: Business Identity & Contact -->
            <div class="form-card p-6 md:p-8 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-7 h-7 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center text-xs font-bold">1</span>
                        Business Identity & Contact Details
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Company Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="company_name" value="<?php echo $companyName; ?>"
                               placeholder="e.g., Guimba Agricultural Tech Solutions Inc."
                               <?php echo !$isCreate ? 'readonly' : 'required'; ?>
                               class="w-full <?php echo !$isCreate ? 'bg-slate-100/70 text-slate-500 cursor-not-allowed' : 'bg-slate-50/70'; ?> border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 transition-all">
                        <?php if (!$isCreate): ?>
                            <span class="text-[11px] font-medium text-slate-400 block">Identity anchor — contact PESO Guimba to update company registration name.</span>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Contact Person <span class="text-rose-500">*</span></label>
                        <input type="text" name="contact_person" value="<?php echo $contact; ?>"
                               placeholder="e.g., Maria Santos (HR Manager)" required
                               class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all">
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Company Email <span class="text-rose-500">*</span></label>
                        <input type="email" name="company_email" value="<?php echo $email; ?>"
                               placeholder="careers@company.com" required
                               class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all">
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Company Phone / Mobile <span class="text-rose-500">*</span></label>
                        <input type="text" name="company_phone" value="<?php echo $phone; ?>"
                               placeholder="09171234567" required
                               class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all">
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Industry Sector</label>
                        <input type="text" name="industry" value="<?php echo $industry; ?>"
                               placeholder="e.g., Agriculture, Information Technology, Retail"
                               class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all">
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Company Size</label>
                        <select name="company_size" class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all">
                            <option value="">Select workforce size...</option>
                            <?php foreach ($sizeLabels as $k => $label): ?>
                                <option value="<?php echo $k; ?>" <?php echo $companySize === $k ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Company Website (Optional)</label>
                        <input type="text" name="company_website" value="<?php echo $website; ?>"
                               placeholder="https://www.yourcompany.com"
                               class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all">
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Company Description</label>
                        <textarea name="company_description" rows="4" placeholder="Briefly describe your business, mission, and products/services..."
                                  class="w-full bg-slate-50/70 border border-slate-200 rounded-xl p-4 text-sm font-medium outline-none focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100 transition-all"><?php echo $companyDesc; ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 2: Location & Address -->
            <div class="form-card p-6 md:p-8 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-7 h-7 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-xs font-bold">2</span>
                        Location & Physical Address
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Street Address / Building <span class="text-rose-500">*</span></label>
                        <input type="text" name="street_name" value="<?php echo $street; ?>"
                               placeholder="e.g., Unit 4B, Commercial Complex, National Highway"
                               <?php echo !$isCreate ? 'readonly' : 'required'; ?>
                               class="w-full <?php echo !$isCreate ? 'bg-slate-100/70 text-slate-500 cursor-not-allowed' : 'bg-slate-50/70'; ?> border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 transition-all">
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Municipality <span class="text-rose-500">*</span></label>
                        <select name="municipality_id" <?php echo !$isCreate ? 'disabled' : 'required'; ?>
                                class="w-full <?php echo !$isCreate ? 'bg-slate-100/70 text-slate-500 cursor-not-allowed' : 'bg-slate-50/70'; ?> border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-blue-600 transition-all">
                            <option value="">Select Municipality...</option>
                            <?php
                            $curProv = '';
                            foreach ($municipalities as $m):
                                if ($curProv !== ($m['province_name'] ?? '')):
                                    if ($curProv !== '') echo '</optgroup>';
                                    $curProv = $m['province_name'] ?? '';
                                    echo '<optgroup label="' . htmlspecialchars($curProv) . '">';
                                endif;
                                $sel = ((int) $m['municipality_id'] === $municipalityId) ? 'selected' : '';
                                ?>
                                <option value="<?php echo (int) $m['municipality_id']; ?>" <?php echo $sel; ?>>
                                    <?php echo htmlspecialchars($m['municipality_name']); ?>
                                </option>
                            <?php endforeach;
                            if ($curProv !== '') echo '</optgroup>';
                            ?>
                        </select>
                        <?php if (!$isCreate): ?>
                            <!-- Keep bound municipality id in POST payload when disabled -->
                            <input type="hidden" name="municipality_id" value="<?php echo $municipalityId; ?>">
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section 3: Verification Business Permit -->
            <div class="form-card p-6 md:p-8 space-y-4">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="w-7 h-7 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center text-xs font-bold">3</span>
                        Business Registration & Permit Verification
                    </h2>
                </div>

                <?php if ($isCreate): ?>
                    <div class="bg-slate-50 border border-dashed border-slate-300 rounded-2xl p-6 text-center space-y-3">
                        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mx-auto text-xl font-bold">📁</div>
                        <h3 class="font-extrabold text-slate-800 text-sm">Upload Valid Business Permit Document</h3>
                        <p class="text-xs text-slate-500 max-w-md mx-auto">Upload a clear PDF, JPEG, or PNG copy of your Mayor's Permit or DTI/SEC registration document for PESO verification.</p>
                        
                        <input type="file" name="business_permit_file" accept=".pdf,.jpg,.jpeg,.png" required
                               class="block w-full max-w-xs mx-auto text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                    </div>
                <?php else: ?>
                    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-sm">✓</span>
                            <div>
                                <h3 class="font-extrabold text-emerald-900 text-sm">Business Permit On File</h3>
                                <p class="text-xs text-emerald-700">Your permit has been submitted and registered with PESO Guimba.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-base py-4 rounded-2xl shadow-lg transition-all transform hover:-translate-y-0.5">
                <?php echo $isCreate ? 'Save Profile & Submit for Verification' : 'Update Company Profile'; ?>
            </button>
        </form>
    </main>

    <!-- Auto-Save Profile Progress & Draft Restoration Script -->
    <script>
    (function () {
        var userId = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;
        var role = <?php echo json_encode($_SESSION['role'] ?? 'employer'); ?>;
        var DRAFT_KEY = 'sikaphub_employer_draft_' + (userId || 'guest') + '_' + role;

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

                formData['__timestamp'] = new Date().toISOString();
                try {
                    localStorage.setItem(DRAFT_KEY, JSON.stringify(formData));
                } catch (e) {}
            }, 150);
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
                    if (field && !field.readOnly && !field.disabled) {
                        if (field.type === 'checkbox') {
                            field.checked = Boolean(val);
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        } else if (field.type === 'radio') {
                            var targetRadio = form.querySelector('[name="' + key + '"][value="' + val + '"]');
                            if (targetRadio) targetRadio.checked = true;
                        } else {
                            if (val !== '' || !field.value) {
                                field.value = val;
                                field.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }
                    }
                });
            } catch (e) {
                console.error('Failed to restore draft:', e);
            }
        }

        form.addEventListener('input', saveDraft);
        form.addEventListener('change', saveDraft);

        restoreDraft();
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', restoreDraft);
        }
    })();
    </script>
</body>
</html>
