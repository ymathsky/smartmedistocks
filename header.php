<?php
// This file should be included at the top of every protected page.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
$__username = htmlspecialchars($_SESSION['username'] ?? 'User');
$__role     = htmlspecialchars($_SESSION['role'] ?? '');
$__avatar   = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2));
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

        /* ── Notification badge ── */
        #notification-badge {
            position: absolute; top: 4px; right: 4px;
            min-width: 16px; height: 16px; font-size: 9px; line-height: 16px;
            border-radius: 9999px; background: #ef4444; color: #fff;
            text-align: center; padding: 0 3px; font-weight: 700;
        }

        /* ── DataTables — modern corporate style ── */
        .dataTables_wrapper { font-size: 0.875rem; color: #334155; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter { margin-bottom: 1rem; }
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0; border-radius: 0.5rem;
            padding: 0.375rem 0.625rem; font-size: 0.8125rem;
            background: #fff; outline: none; transition: border-color .15s, box-shadow .15s;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,.12);
        }
        .dataTables_wrapper .dataTables_info { font-size: 0.78rem; color: #94a3b8; padding-top: 0.6rem; }
        .dataTables_wrapper .dataTables_paginate { padding-top: 0.6rem; }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.3rem 0.65rem !important; margin: 0 1px !important;
            border: 1px solid #e2e8f0 !important; border-radius: 0.45rem !important;
            font-size: 0.78rem !important; background: #fff !important;
            color: #475569 !important; transition: all .15s;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important;
            color: #1e293b !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #2563eb !important; color: #fff !important;
            border-color: #2563eb !important; font-weight: 600 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            opacity: .35; pointer-events: none; color: #94a3b8 !important;
        }
        table.dataTable { border-collapse: collapse !important; width: 100% !important; }
        table.dataTable thead th {
            background: #f8fafc !important; color: #475569 !important;
            font-size: 0.7rem !important; font-weight: 700 !important;
            text-transform: uppercase; letter-spacing: .06em;
            border-bottom: 2px solid #e2e8f0 !important;
            padding: 0.75rem 1rem !important; cursor: pointer;
            white-space: nowrap;
        }
        table.dataTable tbody td {
            padding: 0.8rem 1rem !important; font-size: 0.875rem !important;
            border-bottom: 1px solid #f1f5f9 !important; color: #334155;
            vertical-align: middle;
        }
        table.dataTable tbody tr { transition: background .1s; }
        table.dataTable tbody tr:hover > td { background: #f8fafc !important; }
        table.dataTable.no-footer { border-bottom: 1px solid #e2e8f0 !important; }

        /* ── Topbar dropdowns ── */
        .topbar-dropdown {
            position: absolute; right: 0; top: calc(100% + 6px);
            background: #fff; border: 1px solid #e2e8f0;
            border-radius: 0.75rem; box-shadow: 0 8px 24px rgba(15,23,42,.1);
            z-index: 999; min-width: 14rem;
        }

        /* ── Smooth page transitions ── */
        main { animation: fadeIn .18s ease; }
        @keyframes fadeIn { from { opacity: .85; transform: translateY(3px); } to { opacity: 1; transform: translateY(0); } }

        /* ── Sidebar + layout ── */
        #sidebar {
            position: fixed; top: 0; left: 0;
            width: 256px; height: 100vh; z-index: 50;
            background: #0f172a;
            display: flex; flex-direction: column;
            transform: translateX(-100%);
            transition: transform .25s cubic-bezier(.4,0,.2,1);
        }
        #sidebar.sidebar-open { transform: translateX(0); }
        #sidebar-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.55); z-index: 40;
            backdrop-filter: blur(2px);
        }
        #sidebar-overlay.sidebar-open { display: block; }
        #sidebar-close-btn { display: flex !important; }
        #page-content { margin-left: 0; }
        @media (min-width: 768px) {
            #sidebar   { transform: none !important; }
            #sidebar-overlay  { display: none !important; }
            #sidebar-close-btn { display: none !important; }
            #page-content { margin-left: 256px; }
        }
        #sidebar-nav::-webkit-scrollbar { width: 3px; }
        #sidebar-nav::-webkit-scrollbar-thumb { background: #334155; border-radius: 99px; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

<?php include_once 'sidebar.php'; ?>

<div id="page-content" class="flex flex-col min-h-screen">

        <!-- ═══ TOP BAR ═══ -->
        <header class="sticky top-0 z-30 h-16 bg-white border-b border-slate-200 flex items-center px-4 flex-shrink-0" style="box-shadow:0 1px 3px rgba(15,23,42,.06);">
            <div class="flex-1 flex items-center justify-between">

                <!-- Left: hamburger + date -->
                <div class="flex items-center gap-3">
                    <button id="hamburger-btn" onclick="openSidebar()" aria-label="Open menu"
                        class="md:hidden p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors focus:outline-none">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <span class="hidden md:block text-xs text-slate-400 font-medium"><?php echo date('l, F j, Y'); ?></span>
                </div>

                <!-- Right: notifications + user -->
                <div class="flex items-center gap-1">

                    <!-- ── Notification Bell ── -->
                    <div class="relative">
                        <button id="notification-bubble"
                            class="relative p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <span id="notification-badge" class="hidden"></span>
                        </button>
                        <div id="notification-dropdown" class="topbar-dropdown hidden" style="width:22rem;">
                            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-700">Notifications</h3>
                            </div>
                            <ul id="notification-list" class="divide-y divide-slate-100 max-h-80 overflow-y-auto">
                                <li class="px-4 py-5 text-center text-sm text-slate-400">Loading alerts…</li>
                            </ul>
                            <div class="px-4 py-2.5 border-t border-slate-100 text-center">
                                <a href="#" id="mark-all-read" class="text-xs text-blue-600 hover:underline font-medium">Mark all as read</a>
                            </div>
                        </div>
                    </div>

                    <!-- ── User Menu ── -->
                    <div class="relative ml-1" id="user-menu-container">
                        <button id="user-menu-btn"
                            class="flex items-center gap-2.5 pl-2.5 pr-3 py-1.5 rounded-xl hover:bg-slate-100 transition-colors focus:outline-none">
                            <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0 select-none">
                                <?php echo $__avatar; ?>
                            </div>
                            <div class="hidden sm:block text-left leading-none">
                                <div class="text-sm font-semibold text-slate-700"><?php echo $__username; ?></div>
                                <div class="text-xs text-slate-400 mt-0.5"><?php echo $__role; ?></div>
                            </div>
                            <svg class="w-3.5 h-3.5 text-slate-400 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div id="user-menu-dropdown" class="topbar-dropdown hidden">
                            <div class="px-4 py-3 border-b border-slate-100">
                                <div class="text-sm font-semibold text-slate-800"><?php echo $__username; ?></div>
                                <div class="text-xs text-slate-400 mt-0.5"><?php echo $__role; ?></div>
                            </div>
                            <div class="py-1">
                                <a href="change_password.php" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-800 transition-colors">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                    Change Password
                                </a>
                            </div>
                            <div class="border-t border-slate-100 py-1">
                                <a href="logout.php" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </header>
        <!-- ═══ END TOP BAR ═══ -->

        <!-- Main Content Start -->
        <main class="flex-1 bg-slate-50">
