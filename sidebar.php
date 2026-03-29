<?php
// Filename: sidebar.php
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';

// Determine current page for active highlighting (FIX)
$currentPage = basename($_SERVER['PHP_SELF']);

// Helper function to check if the current link is active
function isActive($pageName, $currentPage) {
    // Treat index.php and the root path as active for the Dashboard
    if ($pageName === 'index.php' && ($currentPage === 'index.php' || $currentPage === '')) {
        return true;
    }
    return $currentPage === $pageName;
}
?>
<aside class="w-64 bg-gray-800 text-white flex-shrink-0">
    <div class="p-4 border-b border-gray-700">
        <h2 class="text-xl font-bold flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h4M8 7a2 2 0 012-2h4a2 2 0 012 2v8a2 2 0 01-2 2H8a2 2 0 01-2-2z" />
            </svg>
            SMS Portal
        </h2>
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

