<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Permit Verification — S.I.K.A.P. Hub</title>
    <?php require_once BASE_PATH . 'app/views/components/pwa_head.php'; ?>
    <meta name="description" content="Upload and verify your business permit for PESO verification on S.I.K.A.P. Hub.">

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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .dropzone-active { border-color: #1769ff !important; background-color: #f0f7ff !important; }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Top Navbar -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <a href="/sikaphub/employer/dashboard" class="flex items-center gap-2.5">
                    <img src="/sikaphub/public/assets/images/logo-icon.png" alt="SikapHub" class="w-8 h-8 rounded-lg object-contain flex-shrink-0">
                    <span class="font-extrabold text-xl tracking-tight text-[#031a3f]">Sikap<span class="bg-gradient-to-r from-[#009cfb] via-[#1769ff] to-[#9035ff] bg-clip-text text-transparent">hub</span></span>
                    <span class="bg-indigo-50 text-indigo-700 text-[10px] font-extrabold px-2 py-0.5 rounded-md uppercase tracking-wider border border-indigo-100 hidden sm:inline-block">Employer Verification</span>
                </a>
                
                <div class="flex items-center gap-4">
                    <a href="/sikaphub/employer/dashboard" class="text-sm font-semibold text-slate-600 hover:text-primary transition-colors flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Back to Dashboard
                    </a>
                
                    <!-- User Avatar & Dropdown -->
                    <div class="relative" id="user-menu-container">
                        <button id="user-menu-button" type="button" class="flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-primary/20 rounded-full cursor-pointer">
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
                            <a href="/sikaphub/build-profile" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5"/></svg>
                                Company Profile
                            </a>
                            <div class="border-t border-slate-100 my-1"></div>
                            <a href="/sikaphub/logout" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 transition-colors font-semibold">
                                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Business Permit Verification</h1>
            <p class="text-slate-500 mt-1 text-sm sm:text-base">
                Upload your official Mayor's or Business Permit document. Our AI Engine extracts key credentials and flags potential anomalies to speed up PESO Guimba admin verification.
            </p>
        </div>

        <!-- Alert Banners -->
        <?php 
        $errDisplay = class_exists('ErrorHelper') ? ErrorHelper::translate($_GET['error'] ?? ($error ?? '')) : ($error ?? '');
        $succDisplay = class_exists('ErrorHelper') ? ErrorHelper::translate($_GET['success'] ?? ($success ?? '')) : ($success ?? '');
        ?>
        <?php if (!empty($errDisplay)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-medium flex items-start gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div><?php echo htmlspecialchars($errDisplay); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($succDisplay)): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm font-medium flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div><?php echo htmlspecialchars($succDisplay); ?></div>
            </div>
        <?php endif; ?>

        <!-- Current Verification Status Card -->
        <?php 
            $permitPath = $employer['permit_file_path'] ?? $employer['business_permit_file'] ?? '';
            $aiStatus = strtolower($employer['verification_status'] ?? 'pending');
            $adminDecision = strtolower($employer['admin_decision'] ?? $employer['verified_status'] ?? 'pending');
            $aiFeedback = $employer['ai_feedback'] ?? '';
            $extractedJson = $employer['extracted_permit_data'] ?? null;
            $extractedData = json_decode($extractedJson ?? '', true);
        ?>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4 bg-slate-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <h2 class="font-bold text-slate-900 text-base">Current Verification Record</h2>
                        <p class="text-xs text-slate-500">Company: <?php echo htmlspecialchars($employer['company_name'] ?? 'Your Business'); ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- AI Badge -->
                    <?php if ($aiStatus === 'green_flag'): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span> AI Green Flag (Valid)
                        </span>
                    <?php elseif ($aiStatus === 'red_flag'): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span> AI Red Flag (Audit Needed)
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span> AI Pending
                        </span>
                    <?php endif; ?>

                    <!-- Admin Decision Badge -->
                    <?php if (in_array($adminDecision, ['approved', 'verified'])): ?>
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-extrabold bg-blue-600 text-white shadow-sm">
                            ✓ Account Verified
                        </span>
                    <?php elseif ($adminDecision === 'rejected'): ?>
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-extrabold bg-slate-900 text-rose-300">
                            ✕ Verification Rejected
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            ⏳ Admin Audit Pending
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="p-6 space-y-6">
                <!-- Document Link -->
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Submitted Permit File</label>
                    <?php if (!empty($permitPath)): ?>
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-3 truncate">
                                <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <span class="text-sm font-semibold text-slate-800 truncate"><?php echo htmlspecialchars(basename($permitPath)); ?></span>
                            </div>
                            <a href="<?php echo htmlspecialchars($permitPath); ?>" target="_blank" rel="noopener" class="px-3 py-1.5 text-xs font-bold text-primary bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors flex-shrink-0">
                                View Document ↗
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-slate-400 italic">No business permit document uploaded yet.</p>
                    <?php endif; ?>
                </div>

                <!-- AI Feedback -->
                <?php if (!empty($aiFeedback)): ?>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1.5">AI Engine Audit Analysis</label>
                        <div class="p-4 rounded-xl text-sm leading-relaxed border <?php echo $aiStatus === 'green_flag' ? 'bg-emerald-50/60 border-emerald-200 text-emerald-950' : ($aiStatus === 'red_flag' ? 'bg-rose-50/60 border-rose-200 text-rose-950' : 'bg-slate-50 border-slate-200 text-slate-700'); ?>">
                            <?php echo nl2br(htmlspecialchars($aiFeedback)); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Extracted Data Breakdown -->
                <?php if (is_array($extractedData) && !empty($extractedData)): ?>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-2">Extracted Credentials (AI OCR)</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <?php foreach ($extractedData as $key => $val): ?>
                                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                    <span class="text-xs font-bold text-slate-400 block uppercase"><?php echo htmlspecialchars(str_replace('_', ' ', $key)); ?></span>
                                    <span class="font-semibold text-slate-800 mt-0.5 block"><?php echo htmlspecialchars(is_array($val) ? json_encode($val) : (string)$val); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upload Form Card -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <h3 class="text-lg font-bold text-slate-900 mb-1">Upload New Business Permit</h3>
            <p class="text-xs sm:text-sm text-slate-500 mb-6">Supported formats: <strong>PDF, JPG, PNG</strong> (Max file size: <strong>10MB</strong>).</p>

            <form action="/sikaphub/employer/upload-permit" method="POST" enctype="multipart/form-data" id="permit-upload-form">
                <?php echo CSRF::csrfField(); ?>

                <!-- Drag & Drop Zone -->
                <div id="dropzone" class="border-2 border-dashed border-slate-300 rounded-2xl p-8 sm:p-12 text-center transition-all bg-slate-50/50 hover:bg-slate-50 hover:border-slate-400 cursor-pointer">
                    <input type="file" id="permit_file" name="permit_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" required>

                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-blue-50 text-primary flex items-center justify-center shadow-inner">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    </div>

                    <p class="text-sm font-bold text-slate-800 mb-1" id="file-label-main">Click to select document or drag & drop here</p>
                    <p class="text-xs text-slate-400 mb-4" id="file-label-sub">Official Mayor's Permit or DTI/SEC registration document</p>

                    <button type="button" onclick="document.getElementById('permit_file').click()" class="inline-flex items-center px-4 py-2 text-xs font-bold text-primary bg-blue-50 hover:bg-blue-100 rounded-xl transition-colors border border-blue-100">
                        Browse Files
                    </button>
                </div>

                <!-- Client Validation Error Message -->
                <div id="js-error" class="hidden mt-4 p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold rounded-xl"></div>

                <!-- File Info Card (Visible after file selection) -->
                <div id="file-info" class="hidden mt-4 p-4 bg-blue-50/60 border border-blue-200 rounded-xl flex items-center justify-between">
                    <div class="flex items-center gap-3 truncate">
                        <div class="w-8 h-8 rounded-lg bg-blue-600 text-white font-bold flex items-center justify-center text-xs" id="file-icon">DOC</div>
                        <div class="truncate">
                            <p class="text-xs font-bold text-slate-900 truncate" id="selected-filename"></p>
                            <p class="text-[11px] text-slate-500" id="selected-filesize"></p>
                        </div>
                    </div>
                    <button type="button" id="clear-file" class="text-xs text-slate-400 hover:text-red-600 font-bold px-2 py-1">Remove</button>
                </div>

                <!-- Action Button -->
                <div class="mt-8 flex justify-end">
                    <button type="submit" id="submit-btn" class="w-full sm:w-auto px-8 py-3.5 bg-primary hover:bg-primary-hover text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/20 transition-all flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Upload & Analyze Document
                    </button>
                </div>
            </form>
        </div>

    </main>

    <!-- Client-Side Drag & Drop and File Validation Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('permit_file');
            const fileInfo = document.getElementById('file-info');
            const fileNameEl = document.getElementById('selected-filename');
            const fileSizeEl = document.getElementById('selected-filesize');
            const fileIconEl = document.getElementById('file-icon');
            const clearBtn = document.getElementById('clear-file');
            const jsError = document.getElementById('js-error');
            const form = document.getElementById('permit-upload-form');
            const submitBtn = document.getElementById('submit-btn');

            const allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            const allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
            const maxSize = 10 * 1024 * 1024; // 10MB

            // Drag events
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropzone.classList.add('dropzone-active');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropzone.classList.remove('dropzone-active');
                });
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    validateAndDisplay(files[0]);
                }
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    validateAndDisplay(fileInput.files[0]);
                }
            });

            clearBtn.addEventListener('click', () => {
                fileInput.value = '';
                fileInfo.classList.add('hidden');
                jsError.classList.add('hidden');
            });

            function validateAndDisplay(file) {
                jsError.classList.add('hidden');
                const ext = file.name.split('.').pop().toLowerCase();

                if (!allowedExts.includes(ext)) {
                    showJsError('Invalid file type! Please upload a PDF, JPG, or PNG document.');
                    fileInput.value = '';
                    fileInfo.classList.add('hidden');
                    return;
                }

                if (file.size > maxSize) {
                    showJsError('File size exceeds 10MB limit! Please upload a smaller document.');
                    fileInput.value = '';
                    fileInfo.classList.add('hidden');
                    return;
                }

                // Render file card
                fileNameEl.textContent = file.name;
                fileSizeEl.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                fileIconEl.textContent = ext.toUpperCase();
                fileInfo.classList.remove('hidden');
            }

            function showJsError(msg) {
                jsError.textContent = msg;
                jsError.classList.remove('hidden');
            }

            form.addEventListener('submit', () => {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Analyzing with AI Engine...
                `;
            });
        });
    </script>
<script>
        document.addEventListener('DOMContentLoaded', function() {
            const userBtn = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-menu-dropdown');
            const userContainer = document.getElementById('user-menu-container');

            if (userBtn && userDropdown) {
                userBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('hidden');
                });
                document.addEventListener('click', function (e) {
                    if (userContainer && !userContainer.contains(e.target)) {
                        userDropdown.classList.add('hidden');
                    }
                });
            }
        });
</script>
</body>

</html>
