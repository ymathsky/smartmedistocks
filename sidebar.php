<?php
// sidebar.php - layout CSS lives in header.php <head>

$userRole    = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$currentPage = basename($_SERVER['PHP_SELF']);

function isActive($pageName, $currentPage) {
    if ($pageName === 'index.php' && ($currentPage === 'index.php' || $currentPage === '')) {
        return true;
    }
    return $currentPage === $pageName;
}

$__sidebarUser   = htmlspecialchars($_SESSION['username'] ?? '');
$__sidebarRole   = htmlspecialchars($_SESSION['role'] ?? '');
$__sidebarAvatar = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2));
?>

<aside id="sms-sidebar">

    <!-- Brand bar -->
    <div class="flex items-center justify-between px-4 h-16 border-b border-slate-700/50 flex-shrink-0">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-blue-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                </svg>
            </div>
            <span class="text-sm font-bold text-white tracking-tight">SmartMediStocks</span>
        </div>
        <button id="sms-close-btn" onclick="closeSidebar()" aria-label="Close menu"
            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 focus:outline-none">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">

        <span class="nav-section">Main</span>

        <a href="index.php" class="nav-item <?php echo isActive('index.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
            </svg>
            Dashboard
        </a>

        <?php if ($userRole === 'Admin'): ?>
        <span class="nav-section">Administration</span>

        <a href="user_management.php" class="nav-item <?php echo isActive('user_management.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            User Management
        </a>

        <a href="supplier_management.php" class="nav-item <?php echo isActive('supplier_management.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>
            </svg>
            Supplier Management
        </a>

        <a href="data_hub.php" class="nav-item <?php echo isActive('data_hub.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7z M8 7h8M8 11h8M8 15h5"/>
            </svg>
            Data Hub
        </a>

        <a href="decision_log.php" class="nav-item <?php echo isActive('decision_log.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Decision Log
        </a>

        <a href="global_settings.php" class="nav-item <?php echo isActive('global_settings.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.096 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Global Settings
        </a>
        <?php endif; ?>

        <?php if (in_array($userRole, ['Admin', 'Pharmacist', 'Procurement'])): ?>
        <span class="nav-section">Analytics</span>

        <a href="demand_forecast.php" class="nav-item <?php echo isActive('demand_forecast.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            Demand Forecast
        </a>

        <a href="what_if_simulator.php" class="nav-item <?php echo isActive('what_if_simulator.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            What-If Simulator
        </a>
        <?php endif; ?>

        <?php if (in_array($userRole, ['Admin', 'Procurement'])): ?>
        <span class="nav-section">Procurement</span>

        <a href="order_suggestion.php" class="nav-item <?php echo isActive('order_suggestion.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            Order Suggestions
        </a>

        <a href="po_management.php" class="nav-item <?php echo isActive('po_management.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            PO Management
        </a>

        <a href="inventory_policy.php" class="nav-item <?php echo isActive('inventory_policy.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Inventory Policy
        </a>

        <a href="inventory_reports.php" class="nav-item <?php echo isActive('inventory_reports.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Performance Reports
        </a>

        <a href="inventory_abc_analysis.php" class="nav-item <?php echo isActive('inventory_abc_analysis.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
            </svg>
            ABC Analysis
        </a>
        <?php endif; ?>

        <?php if (in_array($userRole, ['Admin', 'Pharmacist', 'Warehouse'])): ?>
        <span class="nav-section">Inventory</span>

        <a href="item_management.php" class="nav-item <?php echo isActive('item_management.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            Item Management
        </a>

        <a href="record_usage.php" class="nav-item <?php echo isActive('record_usage.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Record Usage
        </a>

        <a href="transaction_history.php" class="nav-item <?php echo isActive('transaction_history.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
            Transaction History
        </a>
        <?php endif; ?>

        <?php if ($userRole === 'Warehouse'): ?>
        <span class="nav-section">Warehouse</span>

        <a href="items_inventory.php" class="nav-item <?php echo isActive('items_inventory.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            Current Stock List
        </a>

        <a href="receive_stock.php" class="nav-item <?php echo isActive('receive_stock.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Receive Stock
        </a>

        <a href="stock_adjustment.php" class="nav-item <?php echo isActive('stock_adjustment.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
            </svg>
            Stock Adjustment
        </a>

        <a href="move_stock.php" class="nav-item <?php echo isActive('move_stock.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
            Move Stock
        </a>

        <a href="location_management.php" class="nav-item <?php echo isActive('location_management.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Location Management
        </a>

        <a href="decision_log.php" class="nav-item <?php echo isActive('decision_log.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Decision Log
        </a>

        <a href="demand_forecast_w.php" class="nav-item <?php echo isActive('demand_forecast_w.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            Demand Forecast
        </a>

        <a href="what_if_simulator_w.php" class="nav-item <?php echo isActive('what_if_simulator_w.php', $currentPage) ? 'active' : ''; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            What-If Simulator
        </a>
        <?php endif; ?>

    </nav>

    <!-- User strip -->
    <div class="flex items-center gap-3 px-4 py-3 border-t border-slate-700/50 flex-shrink-0">
        <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-xs font-bold text-white flex-shrink-0 select-none">
            <?php echo $__sidebarAvatar; ?>
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-white truncate"><?php echo $__sidebarUser; ?></div>
            <div class="text-xs text-slate-400 truncate"><?php echo $__sidebarRole; ?></div>
        </div>
    </div>

</aside>

<script>
function openSidebar()  {
    document.getElementById('sms-sidebar').classList.add('is-open');
    document.getElementById('sms-overlay').classList.add('is-open');
}
function closeSidebar() {
    document.getElementById('sms-sidebar').classList.remove('is-open');
    document.getElementById('sms-overlay').classList.remove('is-open');
}
</script>