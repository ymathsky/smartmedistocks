<?php
// This file should be included at the top of every protected page.

// Start the session if it's not already started.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- CSRF Token Generation (NEW) ---
if (empty($_SESSION['csrf_token'])) {
    // Generate a new 32-byte token and convert to hexadecimal string
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// --- Security Check ---
// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartMediStocks</title>
    <link rel="shortcut icon" type="image/png" href="logo.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { font-family: 'Inter', sans-serif; }

        /* ── Layout: fixed sidebar + margin-based content ── */
        #sms-sidebar {
            position: fixed;
            top: 0; left: 0;
            width: 256px;
            height: 100%;
            z-index: 100;
            background: #0f172a;
            display: flex;
            flex-direction: column;
            transition: transform .25s cubic-bezier(.4,0,.2,1);
            transform: translateX(-100%);
        }
        #sms-sidebar.is-open { transform: translateX(0); }
        #sms-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.5);
            z-index: 90;
        }
        #sms-overlay.is-open { display: block; }
        #sms-content {
            margin-left: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        @media (min-width: 768px) {
            #sms-sidebar  { transform: none; }
            #sms-overlay  { display: none !important; }
            #sms-content  { margin-left: 256px; }
            #sms-close-btn { display: none !important; }
        }

        /* ── Notification badge ── */
        #notification-badge {
            position: absolute; top: 2px; right: 2px;
            min-width: 16px; height: 16px; font-size: 9px; line-height: 16px;
            border-radius: 9999px; background: #ef4444; color: #fff;
            text-align: center; padding: 0 3px; font-weight: 700;
        }

        /* ── Sidebar nav ── */
        #sms-sidebar nav::-webkit-scrollbar { width: 3px; }
        #sms-sidebar nav::-webkit-scrollbar-thumb { background: #334155; border-radius: 9px; }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px; border-radius: 8px; font-size: .8125rem;
            font-weight: 500; color: #94a3b8; text-decoration: none;
            transition: background .15s, color .15s;
            border-left: 2px solid transparent;
        }
        .nav-item:hover { background: rgba(255,255,255,.06); color: #e2e8f0; }
        .nav-item.active { background: rgba(59,130,246,.12); color: #fff; border-left-color: #3b82f6; }
        .nav-section { font-size: .65rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: #475569; padding: 16px 12px 4px; }

        /* ── DataTables ── */
        .dataTables_wrapper { font-size: .875rem; color: #334155; }
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0; border-radius: .5rem;
            padding: .3rem .6rem; font-size: .8rem; background: #fff;
        }
        .dataTables_wrapper .dataTables_filter input:focus { outline: none; border-color: #3b82f6; }
        .dataTables_wrapper .dataTables_info { font-size: .75rem; color: #94a3b8; }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: .25rem .65rem !important; margin: 0 1px !important;
            border: 1px solid #e2e8f0 !important; border-radius: .4rem !important;
            font-size: .78rem !important; background: #fff !important; color: #475569 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f1f5f9 !important; color: #1e293b !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #2563eb !important; color: #fff !important;
            border-color: #2563eb !important; font-weight: 600 !important;
        }
        table.dataTable { border-collapse: collapse !important; }
        table.dataTable thead th {
            background: #f8fafc !important; color: #475569 !important;
            font-size: .7rem !important; font-weight: 700 !important;
            text-transform: uppercase; letter-spacing: .05em;
            border-bottom: 2px solid #e2e8f0 !important; padding: .7rem 1rem !important;
        }
        table.dataTable tbody td {
            padding: .75rem 1rem !important; font-size: .875rem !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }
        table.dataTable tbody tr:hover > td { background: #f8fafc !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

<?php
$__username = htmlspecialchars($_SESSION['username'] ?? 'User');
$__role     = htmlspecialchars($_SESSION['role'] ?? '');
$__avatar   = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2));
?>

<?php include_once 'sidebar.php'; ?>

<!-- Mobile overlay -->
<div id="sms-overlay" onclick="closeSidebar()"></div>

<div id="sms-content">

    <!-- ── Top Bar ── -->
    <header class="bg-white border-b border-slate-200 flex items-center px-4 h-16 flex-shrink-0 sticky top-0 z-40" style="box-shadow:0 1px 3px rgba(15,23,42,.06);">
        <div class="flex-1 flex items-center justify-between">
            <!-- Left -->
            <div class="flex items-center gap-3">
                <button onclick="openSidebar()" aria-label="Open menu"
                    class="md:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 focus:outline-none">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <span class="hidden md:block text-xs font-medium text-slate-400"><?php echo date('l, F j, Y'); ?></span>
            </div>
            <!-- Right -->
            <div class="flex items-center gap-1">
                <!-- Notification Bell -->
                <div class="relative">
                    <button id="notification-bubble" class="relative p-2 rounded-lg text-slate-500 hover:bg-slate-100 focus:outline-none">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span id="notification-badge" class="hidden"></span>
                    </button>
                    <div id="notification-dropdown" class="hidden absolute right-0 top-full mt-1 w-80 bg-white rounded-xl shadow-xl border border-slate-200 z-50">
                        <div class="px-4 py-3 border-b border-slate-100">
                            <h3 class="text-sm font-semibold text-slate-700">Notifications</h3>
                        </div>
                        <ul id="notification-list" class="divide-y divide-slate-100 max-h-80 overflow-y-auto">
                            <li class="px-4 py-5 text-center text-sm text-slate-400">Loading alerts…</li>
                        </ul>
                        <div class="px-4 py-2 border-t border-slate-100 text-center">
                            <a href="#" id="mark-all-read" class="text-xs text-blue-600 hover:underline font-medium">Mark all as read</a>
                        </div>
                    </div>
                </div>
                <!-- User Menu -->
                <div class="relative ml-1">
                    <button id="user-menu-btn" class="flex items-center gap-2 pl-2 pr-3 py-1.5 rounded-xl hover:bg-slate-100 focus:outline-none">
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold select-none"><?php echo $__avatar; ?></div>
                        <div class="hidden sm:block text-left leading-none">
                            <div class="text-sm font-semibold text-slate-700"><?php echo $__username; ?></div>
                            <div class="text-xs text-slate-400 mt-0.5"><?php echo $__role; ?></div>
                        </div>
                        <svg class="w-3 h-3 text-slate-400 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="user-menu-dropdown" class="hidden absolute right-0 top-full mt-1 w-52 bg-white rounded-xl shadow-xl border border-slate-200 z-50">
                        <div class="px-4 py-3 border-b border-slate-100">
                            <div class="text-sm font-semibold text-slate-800"><?php echo $__username; ?></div>
                            <div class="text-xs text-slate-400 mt-0.5"><?php echo $__role; ?></div>
                        </div>
                        <div class="py-1">
                            <a href="change_password.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                Change Password
                            </a>
                        </div>
                        <div class="border-t border-slate-100 py-1">
                            <a href="logout.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- ── End Top Bar ── -->

    <!-- Main Content -->
    <main class="flex-1 bg-slate-50">
        <div class="container mx-auto px-6 py-8">
