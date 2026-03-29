<?php
// Filename: record_usage.php

require_once 'header.php';
require_once 'db_connection.php';

// Security check: Only Admins and Pharmacists can record usage
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Pharmacist' && $_SESSION['role'] != 'Warehouse')) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch all items with current stock to populate the dropdown
$items_result = $conn->query("
    SELECT i.item_id, i.name, i.item_code, i.is_controlled,
           COALESCE(SUM(b.quantity), 0) AS current_stock
    FROM items i
    LEFT JOIN item_batches b ON i.item_id = b.item_id
    GROUP BY i.item_id, i.name, i.item_code, i.is_controlled
    ORDER BY i.name ASC
");
?>

<style>
    .item-option-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.15s; }
    .item-option-row:hover, .item-option-row.selected { background: #eff6ff; }
    .item-option-row.selected { border-left: 3px solid #2563eb; }
    .stock-pill { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; white-space: nowrap; }
    .stock-pill.green  { background: #dcfce7; color: #16a34a; }
    .stock-pill.yellow { background: #fef9c3; color: #ca8a04; }
    .stock-pill.red    { background: #fee2e2; color: #dc2626; }
    #item_list_container { max-height: 260px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
    #item_list_container::-webkit-scrollbar { width: 6px; }
    #item_list_container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    .step-dot { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
    .step-dot.active { background: #2563eb; color: #fff; }
    .step-dot.done   { background: #22c55e; color: #fff; }
    .step-dot.idle   { background: #e2e8f0; color: #94a3b8; }
    .step-line { flex: 1; height: 2px; background: #e2e8f0; margin: 0 6px; }
    .step-line.done { background: #22c55e; }
    input[type="number"]::-webkit-inner-spin-button { opacity: 1; }
</style>

<div class="p-6 max-w-5xl mx-auto">

    <!-- Page Header -->
    <div class="flex flex-wrap justify-between items-center mb-6 gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Record Item Usage</h1>
            <p class="text-sm text-gray-500 mt-0.5">Log dispensed or consumed stock against a transaction date</p>
        </div>
        <a href="transaction_history.php" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 font-medium">
            View Transaction History
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>

    <!-- Alerts -->
    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 mb-5"><svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mt-0.5 flex-shrink-0 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><p class="text-sm font-medium">' . htmlspecialchars($_SESSION['message']) . '</p></div>';
        unset($_SESSION['message']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 mb-5"><svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mt-0.5 flex-shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg><p class="text-sm font-medium">' . htmlspecialchars($_SESSION['error']) . '</p></div>';
        unset($_SESSION['error']);
    }
    ?>

    <!-- Step Indicator -->
    <div class="flex items-center mb-8 px-2">
        <div class="step-dot active" id="step1_dot">1</div>
        <div class="step-line" id="line12"></div>
        <div class="step-dot idle" id="step2_dot">2</div>
        <div class="step-line" id="line23"></div>
        <div class="step-dot idle" id="step3_dot">3</div>
    </div>

    <form action="record_usage_handler.php" method="POST" id="usage_form">
        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <!-- Two-column layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- LEFT: Item Selection -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <h2 class="font-semibold text-gray-700">Step 1 — Select Item</h2>
                </div>

                <!-- Search -->
                <div class="relative mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-3 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
                    <input type="text" id="item_search" placeholder="Search by name or item code..."
                        class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        autocomplete="off">
                </div>

                <!-- Results count -->
                <p class="text-xs text-gray-400 mb-1.5" id="item_match_count"></p>

                <!-- Item list -->
                <div id="item_list_container">
                    <?php
                    if ($items_result && $items_result->num_rows > 0) {
                        while ($item = $items_result->fetch_assoc()) {
                            $stock = (int)$item['current_stock'];
                            if ($stock === 0) { $pill = 'red'; $pill_label = 'Out of stock'; }
                            elseif ($stock <= 10) { $pill = 'yellow'; $pill_label = $stock . ' left'; }
                            else { $pill = 'green'; $pill_label = $stock . ' units'; }
                            echo '<div class="item-option-row"'
                               . ' data-id="' . htmlspecialchars($item['item_id']) . '"'
                               . ' data-label="' . strtolower(htmlspecialchars($item['name'])) . ' ' . strtolower(htmlspecialchars($item['item_code'])) . '"'
                               . ' data-stock="' . $stock . '"'
                               . ' data-name="' . htmlspecialchars($item['name'], ENT_QUOTES) . '"'
                               . ' data-code="' . htmlspecialchars($item['item_code'], ENT_QUOTES) . '"'
                               . ' data-controlled="' . (int)$item['is_controlled'] . '"'
                               . '>'
                               . '<div>'
                               . '<div class="text-sm font-medium text-gray-800">' . htmlspecialchars($item['name']) . '</div>'
                               . '<div class="text-xs text-gray-400">' . htmlspecialchars($item['item_code']) . '</div>'
                               . '</div>'
                               . '<span class="stock-pill ' . $pill . '">' . $pill_label . '</span>'
                               . '</div>';
                        }
                    }
                    ?>
                </div>

                <!-- Hidden real select for form submission -->
                <input type="hidden" name="item_id" id="item_id" required>
            </div>

            <!-- RIGHT: Transaction Details -->
            <div class="flex flex-col gap-6">

                <!-- Selected Item Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z"/></svg>
                        </div>
                        <h2 class="font-semibold text-gray-700">Step 2 — Selected Item</h2>
                    </div>

                    <!-- Placeholder state -->
                    <div id="no_selection" class="text-center py-6 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0v10l-8 4-8-4V7"/></svg>
                        <p class="text-sm">No item selected yet.<br>Search and click an item on the left.</p>
                    </div>

                    <!-- Selected info state -->
                    <div id="item_info" class="hidden">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <p class="font-semibold text-gray-800" id="info_name"></p>
                                <p class="text-xs text-gray-400 mt-0.5" id="info_code"></p>
                            </div>
                            <button type="button" id="clear_selection" class="text-xs text-gray-400 hover:text-red-500 transition ml-2">&#x2715; Clear</button>
                        </div>
                        <!-- Stock meter -->
                        <div id="stock_display" class="rounded-lg p-4 flex items-center gap-4">
                            <div>
                                <p class="text-xs font-medium text-gray-500 mb-0.5">Available Stock</p>
                                <p class="text-3xl font-bold" id="stock_value"></p>
                                <p class="text-xs text-gray-400">units in store</p>
                            </div>
                            <div class="flex-1">
                                <div class="h-2 rounded-full bg-gray-200 overflow-hidden">
                                    <div id="stock_bar" class="h-2 rounded-full transition-all duration-500" style="width:0%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-400 mt-1">
                                    <span>0</span>
                                    <span id="stock_bar_max"></span>
                                </div>
                            </div>
                        </div>
                        <div id="stock_zero_alert" class="hidden mt-2 text-xs font-medium text-orange-700 bg-orange-50 border border-orange-200 rounded px-3 py-2">⚠ No stock available for this item. Recording usage will result in negative stock.</div>
                        <div id="stock_over_alert" class="hidden mt-2 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">⚠ Quantity entered exceeds available stock.</div>
                    </div>
                </div>

                <!-- Qty + Date card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center gap-2 mb-5">
                        <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h2 class="font-semibold text-gray-700">Step 3 — Usage Details</h2>
                    </div>

                    <div class="mb-5">
                        <label for="quantity_used" class="block text-sm font-medium text-gray-700 mb-1.5">Quantity Used <span class="text-red-500">*</span></label>
                        <input type="number" id="quantity_used" name="quantity_used" min="1" placeholder="Enter quantity"
                            class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    </div>

                    <div class="mb-6">
                        <label for="transaction_date" class="block text-sm font-medium text-gray-700 mb-1.5">Date of Usage <span class="text-red-500">*</span></label>
                        <input type="date" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    </div>

                    <button type="submit" id="submit_btn"
                        class="w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold py-3 px-6 rounded-lg shadow transition duration-200"
                        disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Record Transaction
                    </button>
                </div>

                <!-- Controlled Substance: Authorizer Section -->
                <div id="auth_section" class="hidden bg-white rounded-xl shadow-sm border border-red-300 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <h2 class="font-semibold text-gray-700">Step 4 — Second Authorizer Required</h2>
                    </div>
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-xs font-bold text-red-700">&#9888; CONTROLLED SUBSTANCE</p>
                        <p class="text-xs text-red-600 mt-1">A second staff member must authenticate to approve this dispensing. They must use a different account than yours.</p>
                    </div>
                    <div class="mb-4">
                        <label for="auth_username" class="block text-sm font-medium text-gray-700 mb-1.5">Authorizer Username <span class="text-red-500">*</span></label>
                        <input type="text" id="auth_username" name="auth_username"
                            class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            autocomplete="off" placeholder="Enter authorizer's username">
                    </div>
                    <div>
                        <label for="auth_password" class="block text-sm font-medium text-gray-700 mb-1.5">Authorizer Password <span class="text-red-500">*</span></label>
                        <input type="password" id="auth_password" name="auth_password"
                            class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            autocomplete="off" placeholder="Enter authorizer's password">
                    </div>
                </div>

            </div><!-- end right col -->
        </div><!-- end grid -->

    </form>
</div>

<script>
(function() {
    var searchInput  = document.getElementById('item_search');
    var countLabel   = document.getElementById('item_match_count');
    var rows         = Array.prototype.slice.call(document.querySelectorAll('.item-option-row'));
    var hiddenInput  = document.getElementById('item_id');
    var qtyInput     = document.getElementById('quantity_used');
    var submitBtn    = document.getElementById('submit_btn');

    var noSelection  = document.getElementById('no_selection');
    var itemInfo     = document.getElementById('item_info');
    var infoName     = document.getElementById('info_name');
    var infoCode     = document.getElementById('info_code');
    var stockDisplay = document.getElementById('stock_display');
    var stockVal     = document.getElementById('stock_value');
    var stockBar     = document.getElementById('stock_bar');
    var stockBarMax  = document.getElementById('stock_bar_max');
    var zeroAlert    = document.getElementById('stock_zero_alert');
    var overAlert    = document.getElementById('stock_over_alert');

    var step2Dot     = document.getElementById('step2_dot');
    var step3Dot     = document.getElementById('step3_dot');
    var line12       = document.getElementById('line12');
    var line23       = document.getElementById('line23');

    var currentStock = 0;
    var authSection  = document.getElementById('auth_section');
    var authUsername = document.getElementById('auth_username');
    var authPassword = document.getElementById('auth_password');
    var isControlled = false;

    function checkReady() {
        if (!hiddenInput.value) return;
        submitBtn.disabled = isControlled
            ? !(authUsername.value.trim() && authPassword.value.trim())
            : false;
    }

    function setStep(n) {
        // step 1 dot stays active always
        if (n >= 2) {
            step2Dot.className = 'step-dot active';
            line12.className   = 'step-line done';
        } else {
            step2Dot.className = 'step-dot idle';
            line12.className   = 'step-line';
        }
        if (n >= 3) {
            step3Dot.className = 'step-dot active';
            line23.className   = 'step-line done';
        } else {
            step3Dot.className = 'step-dot idle';
            line23.className   = 'step-line';
        }
    }

    function showStock(stock) {
        currentStock = parseInt(stock, 10);
        stockVal.textContent = currentStock;
        stockBarMax.textContent = Math.max(currentStock, 1);

        // Bar width capped at 100%
        var pct = currentStock > 0 ? Math.min(100, currentStock) : 0;
        stockBar.style.width = pct + '%';

        // Colours
        stockDisplay.className = 'rounded-lg p-4 flex items-center gap-4 ';
        if (currentStock === 0) {
            stockDisplay.className += 'bg-red-50';
            stockVal.className = 'text-3xl font-bold text-red-600';
            stockBar.className = 'h-2 rounded-full transition-all duration-500 bg-red-400';
            zeroAlert.classList.remove('hidden');
        } else if (currentStock <= 10) {
            stockDisplay.className += 'bg-yellow-50';
            stockVal.className = 'text-3xl font-bold text-yellow-600';
            stockBar.className = 'h-2 rounded-full transition-all duration-500 bg-yellow-400';
            zeroAlert.classList.add('hidden');
        } else {
            stockDisplay.className += 'bg-green-50';
            stockVal.className = 'text-3xl font-bold text-green-600';
            stockBar.className = 'h-2 rounded-full transition-all duration-500 bg-green-500';
            zeroAlert.classList.add('hidden');
        }
        checkQty();
    }

    function checkQty() {
        var qty = parseInt(qtyInput.value, 10);
        if (!isNaN(qty) && qty > 0 && currentStock > 0 && qty > currentStock) {
            overAlert.classList.remove('hidden');
        } else {
            overAlert.classList.add('hidden');
        }
    }

    function selectItem(row) {
        // Clear previous selection highlight
        rows.forEach(function(r) { r.classList.remove('selected'); });
        row.classList.add('selected');

        var id         = row.getAttribute('data-id');
        var name       = row.getAttribute('data-name');
        var code       = row.getAttribute('data-code');
        var stock      = row.getAttribute('data-stock');
        var controlled = row.getAttribute('data-controlled');

        isControlled = (controlled === '1');

        hiddenInput.value    = id;
        infoName.textContent = name;
        infoCode.textContent = code;

        noSelection.classList.add('hidden');
        itemInfo.classList.remove('hidden');

        if (isControlled) {
            authSection.classList.remove('hidden');
            authUsername.value = '';
            authPassword.value = '';
            submitBtn.disabled = true; // require auth credentials first
        } else {
            authSection.classList.add('hidden');
            authUsername.value = '';
            authPassword.value = '';
            submitBtn.disabled = false;
        }

        showStock(stock);
        setStep(2);

        // Scroll row into view
        row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    // Click select
    rows.forEach(function(row) {
        row.addEventListener('click', function() { selectItem(row); });
    });

    // Search filter
    searchInput.addEventListener('input', function() {
        var query   = this.value.toLowerCase().trim();
        var visible = 0;

        rows.forEach(function(row) {
            var label = row.getAttribute('data-label') || '';
            var match = !query || label.indexOf(query) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        countLabel.textContent = query ? visible + ' item(s) found' : '';

        // Auto-select single match
        if (visible === 1) {
            rows.forEach(function(row) {
                if (row.style.display !== 'none') { selectItem(row); }
            });
        }
    });

    // Qty validation
    qtyInput.addEventListener('input', function() {
        checkQty();
        if (hiddenInput.value && this.value > 0) {
            setStep(3);
            step3Dot.className = 'step-dot active';
        }
    });

    // Clear selection
    document.getElementById('clear_selection').addEventListener('click', function() {
        rows.forEach(function(r) { r.classList.remove('selected'); });
        hiddenInput.value = '';
        submitBtn.disabled = true;
        noSelection.classList.remove('hidden');
        itemInfo.classList.add('hidden');
        searchInput.value = '';
        countLabel.textContent = '';
        rows.forEach(function(r) { r.style.display = ''; });
        setStep(1);
        overAlert.classList.add('hidden');
        zeroAlert.classList.add('hidden');
        isControlled = false;
        authSection.classList.add('hidden');
        authUsername.value = '';
        authPassword.value = '';
    });

    // Enable submit when controlled substance auth fields are filled
    authUsername.addEventListener('input', checkReady);
    authPassword.addEventListener('input', checkReady);
})();
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
