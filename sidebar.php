<?php
// Filename: sidebar.php
$userRole    = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
$__sbUser    = htmlspecialchars($_SESSION['username'] ?? 'User');
$__sbRole    = htmlspecialchars($userRole);
$__sbAvatar  = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2));

function navItem($href, $label, $icon, $currentPage, $exact = true) {
    $page   = basename($href);
    $active = $exact ? ($currentPage === $page || ($page === 'index.php' && $currentPage === ''))
                     : ($currentPage === $page);
    $base   = 'group flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150 border-l-2 ';
    $cls    = $active
        ? $base . 'border-blue-500 bg-blue-500/10 text-white'
        : $base . 'border-transparent text-slate-400 hover:text-slate-100 hover:bg-slate-800/70';
    echo '<a href="' . htmlspecialchars($href) . '" class="' . $cls . '">' . $icon . '<span>' . htmlspecialchars($label) . '</span></a>';
}

function sectionLabel($text) {
    echo '<p class="text-[10px] font-bold uppercase tracking-widest text-slate-500 px-3 pt-5 pb-1.5">' . htmlspecialchars($text) . '</p>';
}

// SVG icon helpers
$ic = [
    'dashboard'   => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
    'users'       => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    'suppliers'   => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>',
    'datahub'     => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>',
    'auditlog'    => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
    'settings'    => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    'forecast'    => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>',
    'whatif'      => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>',
    'order'       => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
    'po'          => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>',
    'policy'      => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
    'reports'     => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
    'abc'         => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>',
    'items'       => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20 7l-8-4-8 4m16 0v10l-8 4-8-4V7"/></svg>',
    'usage'       => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>',
    'history'     => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'stock'       => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 8h14M5 12h14M5 16h6"/></svg>',
    'receive'     => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>',
    'adjust'      => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>',
    'wastage'     => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>',
    'quarantine'  => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
    'move'        => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
    'location'    => '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
];
?>
<!-- Mobile overlay -->
<div id="sidebar-overlay" onclick="closeSidebar()"></div>

<aside id="sidebar">

    <!-- ── Brand ── -->
    <div class="h-16 flex items-center justify-between px-4 flex-shrink-0" style="border-bottom:1px solid rgba(255,255,255,.07);">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#2563eb;">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div class="leading-none">
                <div class="text-white font-bold text-sm tracking-tight">SmartMediStocks</div>
                <div class="text-xs mt-0.5" style="color:#64748b;">Inventory System</div>
            </div>
        </div>
        <button id="sidebar-close-btn" onclick="closeSidebar()" aria-label="Close"
            class="p-1.5 rounded-lg transition-colors focus:outline-none"
            style="color:#64748b;" onmouseover="this.style.color='#e2e8f0';this.style.background='rgba(255,255,255,.06)'" onmouseout="this.style.color='#64748b';this.style.background='transparent'">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <!-- ── Navigation ── -->
    <nav id="sidebar-nav" class="flex-1 overflow-y-auto px-3 py-3">

        <!-- OVERVIEW -->
        <?php sectionLabel('Overview'); ?>
        <?php navItem('index.php', 'Dashboard', $ic['dashboard'], $currentPage); ?>

        <!-- ADMINISTRATION (Admin only) -->
        <?php if ($userRole === 'Admin'): ?>
            <?php sectionLabel('Administration'); ?>
            <?php navItem('user_management.php',    'User Management',  $ic['users'],     $currentPage); ?>
            <?php navItem('supplier_management.php','Suppliers',        $ic['suppliers'], $currentPage); ?>
            <?php navItem('data_hub.php',            'Data Hub',         $ic['datahub'],   $currentPage); ?>
            <?php navItem('decision_log.php',        'Audit Log',        $ic['auditlog'],  $currentPage); ?>
            <?php navItem('global_settings.php',     'Global Settings',  $ic['settings'],  $currentPage); ?>
        <?php endif; ?>

        <!-- ANALYTICS (Admin, Pharmacist, Procurement) -->
        <?php if (in_array($userRole, ['Admin', 'Pharmacist', 'Procurement'])): ?>
            <?php sectionLabel('Analytics'); ?>
            <?php navItem('demand_forecast.php',     'Demand Forecast',      $ic['forecast'], $currentPage); ?>
            <?php navItem('what_if_simulator.php',   'What-If Simulator',    $ic['whatif'],   $currentPage); ?>
        <?php endif; ?>

        <?php if (in_array($userRole, ['Admin', 'Procurement'])): ?>
            <?php navItem('order_suggestion.php',    'Order Suggestions',    $ic['order'],    $currentPage); ?>
            <?php navItem('po_management.php',       'Purchase Orders',      $ic['po'],       $currentPage); ?>
            <?php navItem('inventory_policy.php',    'Inventory Policy',     $ic['policy'],   $currentPage); ?>
            <?php navItem('inventory_reports.php',   'Performance Reports',  $ic['reports'],  $currentPage); ?>
            <?php navItem('inventory_abc_analysis.php','ABC Analysis',       $ic['abc'],      $currentPage); ?>
        <?php endif; ?>

        <!-- INVENTORY (Admin, Pharmacist, Warehouse) -->
        <?php if (in_array($userRole, ['Admin', 'Pharmacist', 'Warehouse'])): ?>
            <?php sectionLabel('Inventory'); ?>
            <?php navItem('item_management.php',     'Item Management',      $ic['items'],   $currentPage); ?>
            <?php navItem('record_usage.php',        'Record Usage',         $ic['usage'],   $currentPage); ?>
            <?php navItem('transaction_history.php', 'Transaction History',  $ic['history'], $currentPage); ?>
        <?php endif; ?>

        <!-- WAREHOUSE OPERATIONS (Warehouse only) -->
        <?php if ($userRole === 'Warehouse'): ?>
            <?php sectionLabel('Warehouse'); ?>
            <?php navItem('items_inventory.php',     'Current Stock',        $ic['stock'],      $currentPage); ?>
            <?php navItem('receive_stock.php',       'Receive Stock',        $ic['receive'],    $currentPage); ?>
            <?php navItem('stock_adjustment.php',    'Stock Adjustment',     $ic['adjust'],     $currentPage); ?>
            <?php navItem('move_stock.php',          'Move Stock',           $ic['move'],       $currentPage); ?>
            <?php navItem('location_management.php', 'Locations',            $ic['location'],   $currentPage); ?>
            <?php navItem('wastage_writeoff.php',    'Wastage Write-off',    $ic['wastage'],    $currentPage); ?>
            <?php navItem('batch_quarantine.php',    'Batch Quarantine',     $ic['quarantine'], $currentPage); ?>
            <?php sectionLabel('Analytics'); ?>
            <?php navItem('demand_forecast_w.php',   'Demand Forecast',      $ic['forecast'],   $currentPage); ?>
            <?php navItem('what_if_simulator_w.php', 'What-If Simulator',    $ic['whatif'],     $currentPage); ?>
            <?php navItem('decision_log.php',        'Audit Log',            $ic['auditlog'],   $currentPage); ?>
        <?php endif; ?>

    </nav>

    <!-- ── User footer ── -->
    <div class="flex items-center gap-3 px-4 py-3 flex-shrink-0" style="border-top:1px solid rgba(255,255,255,.07);">
        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0 select-none">
            <?php echo $__sbAvatar; ?>
        </div>
        <div class="flex-1 min-w-0 leading-none">
            <div class="text-sm font-semibold text-white truncate"><?php echo $__sbUser; ?></div>
            <div class="text-xs mt-0.5 truncate" style="color:#64748b;"><?php echo $__sbRole; ?></div>
        </div>
        <a href="logout.php" title="Sign out"
            class="p-1.5 rounded-lg transition-colors flex-shrink-0"
            style="color:#64748b;"
            onmouseover="this.style.color='#f87171';this.style.background='rgba(239,68,68,.1)'"
            onmouseout="this.style.color='#64748b';this.style.background='transparent'">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        </a>
    </div>

</aside>

<script>
function openSidebar()  {
    document.getElementById('sidebar').classList.add('sidebar-open');
    document.getElementById('sidebar-overlay').classList.add('sidebar-open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('sidebar-open');
    document.getElementById('sidebar-overlay').classList.remove('sidebar-open');
}
</script>
<!-- Sidebar overlay (mobile only) -->
<div id="sidebar-overlay" onclick="closeSidebar()"></div>

<style>
#sidebar {
    position: fixed;
    top: 0; left: 0;
    height: 100vh;
    z-index: 50;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    overflow-y: auto;
}
#sidebar.sidebar-open { transform: translateX(0); }
#sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 40;
}
#sidebar-overlay.sidebar-open { display: block; }
#sidebar-close-btn { display: block; }
@media (min-width: 768px) {
    #sidebar {
        position: relative;
        transform: translateX(0) !important;
        flex-shrink: 0;
        height: 100vh;
    }
    #sidebar-overlay { display: none !important; }
    #sidebar-close-btn { display: none; }
}
</style>

<aside id="sidebar" class="w-64 bg-gray-800 text-white">
    <div class="p-4 border-b border-gray-700 flex items-center justify-between">
        <h2 class="text-xl font-bold flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h4M8 7a2 2 0 012-2h4a2 2 0 012 2v8a2 2 0 01-2 2H8a2 2 0 01-2-2z" />
            </svg>
            SMS Portal
        </h2>
        <button id="sidebar-close-btn" onclick="closeSidebar()" class="text-gray-400 hover:text-white focus:outline-none" aria-label="Close menu">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <nav class="p-4">
        <h3 class="text-xs uppercase text-gray-400 font-bold mb-2">Navigation</h3>
        <ul>
            <!-- Common Links -->
            <li>
                <a href="index.php" class="flex items-center py-2 px-3 rounded-md
                    <?php echo isActive('index.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    Dashboard
                </a>
            </li>

            <!-- Admin Links -->
            <?php if ($userRole == 'Admin'): ?>
                <li>
                    <a href="user_management.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('user_management.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                        </svg>
                        User Management
                    </a>
                </li>
                <li>
                    <a href="supplier_management.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('supplier_management.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                        </svg>
                        Supplier Management
                    </a>
                </li>
                <li>
                    <a href="data_hub.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('data_hub.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10m16-10v10M4 17h16M4 7a2 2 0 012-2h12a2 2 0 012 2m-4 4h.01M12 11h.01M8 11h.01M12 15h.01M8 15h.01M12 17h.01M15 17h.01M9 10h.01M12 10h.01M15 10h.01M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4z" />
                        </svg>
                        Data Hub
                    </a>
                </li>
                <li>
                    <a href="decision_log.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('decision_log.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Decision Log
                    </a>
                </li>
                <li>
                    <a href="global_settings.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('global_settings.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0 3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.096 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Global Settings
                    </a>
                </li>
            <?php endif; ?>

            <!-- Admin, Pharmacist & Procurement Links -->
            <?php if (in_array($userRole, ['Admin', 'Pharmacist', 'Procurement'])): ?>
                <li>
                    <a href="demand_forecast.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('demand_forecast.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        Demand Forecast
                    </a>
                </li>
                <li>
                    <a href="what_if_simulator.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('what_if_simulator.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01M9 14h.01M12 14h.01M15 14h.01M12 17h.01M15 17h.01M9 10h.01M12 10h.01M15 10h.01M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4z" />
                        </svg>
                        What-If Simulator
                    </a>
                </li>
            <?php endif; ?>

            <!-- Admin & Procurement Links -->
            <?php if (in_array($userRole, ['Admin', 'Procurement'])): ?>
                <li>
                    <a href="order_suggestion.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('order_suggestion.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Order Suggestions
                    </a>
                </li>

                <li>
                    <a href="po_management.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('po_management.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        PO Management
                    </a>
                </li>
                <li>
                    <a href="inventory_policy.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('inventory_policy.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-1 4l-2 2-2-2m-2-4l-2 2 2 2" />
                        </svg>
                        Inventory Policy
                    </a>
                </li>
                <li>
                    <a href="inventory_reports.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('inventory_reports.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-8v8m-4-8v8M4 16h16" />
                        </svg>
                        Performance Reports
                    </a>
                </li>
                <li>
                    <a href="inventory_abc_analysis.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('inventory_abc_analysis.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c1.657 0 3-1.79 3-4s-1.343-4-3-4-3 1.79-3 4 1.343 4 3 4zm0 1c-3.313 0-6 2.687-6 6h12c0-3.313-2.687-6-6-6z" />
                        </svg>
                        ABC Analysis
                    </a>
                </li>
            <?php endif; ?>

            <!-- Admin & Pharmacist Links -->
            <?php if (in_array($userRole, ['Admin', 'Pharmacist', 'Warehouse'])): ?>
                <li>
                    <a href="item_management.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('item_management.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        Item Management
                    </a>
                </li>
                <li>
                    <a href="record_usage.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('record_usage.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h3m-3-10h.01M9 17h.01M9 14h.01M12 14h.01M15 14h.01M12 17h.01M15 17h.01M9 10h.01M12 10h.01M15 10h.01M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4z" />
                        </svg>
                        Record Usage
                    </a>
                </li>
                <li>
                    <a href="transaction_history.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('transaction_history.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 1V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2V11m-2 1h-2m2-2h-2m2-2h-2m-3-2V3a2 2 0 00-2-2H9a2 2 0 00-2 2v2m4 0h4" />
                        </svg>
                        Transaction History
                    </a>
                </li>
            <?php endif; ?>

            <!-- Warehouse Links -->
            <?php if ($userRole == 'Warehouse'): ?>
                <li>
                    <a href="items_inventory.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('items_inventory.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 12h14M5 16h14" />
                        </svg>
                        Current Stock List
                    </a>
                </li>
                <li>
                    <a href="receive_stock.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('receive_stock.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Receive Stock
                    </a>
                </li>
                <li>
                    <a href="stock_adjustment.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('stock_adjustment.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6m-4 1H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-4a2 2 0 00-2 2v1" />
                        </svg>
                        Stock Adjustment
                    </a>
                </li>
                <li>
                    <a href="wastage_writeoff.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('wastage_writeoff.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Wastage Write-off
                    </a>
                </li>
                <li>
                    <a href="batch_quarantine.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('batch_quarantine.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Batch Quarantine
                    </a>
                </li>
                <li>
                    <a href="move_stock.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('move_stock.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Move Stock
                    </a>
                </li>
                <li>
                    <a href="location_management.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('location_management.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Location Management
                    </a>
                </li>
                <li>
                    <a href="decision_log.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('decision_log.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Decision Log
                    </a>
                </li>
                <li>
                    <a href="demand_forecast_w.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('demand_forecast_w.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        Demand Forecast
                    </a>
                </li>
                <li>
                    <a href="what_if_simulator_w.php" class="flex items-center py-2 px-3 rounded-md
                        <?php echo isActive('what_if_simulator_w.php', $currentPage) ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01M9 14h.01M12 14h.01M15 14h.01M12 17h.01M15 17h.01M9 10h.01M12 10h.01M15 10h.01M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4z" />
                        </svg>
                        What-If Simulator
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</aside>

<script>
function openSidebar()  { document.getElementById('sidebar').classList.add('sidebar-open'); document.getElementById('sidebar-overlay').classList.add('sidebar-open'); }
function closeSidebar() { document.getElementById('sidebar').classList.remove('sidebar-open'); document.getElementById('sidebar-overlay').classList.remove('sidebar-open'); }
</script>

