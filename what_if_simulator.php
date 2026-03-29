<?php
// Filename: what_if_simulator_w.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// --- MODIFIED SQL QUERY ---
// Fetch only items eligible for simulation:
// Must have > 0 total usage AND transactions on >= 7 distinct days in the last 90 days.
$ninety_days_ago_for_filter = date('Y-m-d', strtotime('-90 days'));
$items_sql = "
    SELECT
        i.item_id, i.name, i.item_code
    FROM items i
    JOIN (
        SELECT
            item_id,
            SUM(quantity_used) as total_usage_90,
            COUNT(DISTINCT transaction_date) as transaction_days_90
        FROM transactions
        WHERE transaction_date >= ?
        GROUP BY item_id
    ) AS t ON i.item_id = t.item_id
    WHERE t.total_usage_90 > 0 AND t.transaction_days_90 >= 7
    ORDER BY i.name ASC
";
// --- END MODIFIED SQL QUERY ---

// Prepare and execute the statement
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("s", $ninety_days_ago_for_filter);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
// $items_stmt->close(); // Close statement later after fetching defaults if needed

// Fetch global settings to use as defaults for sliders
$settings = [];
$settings_result = $conn->query("SELECT setting_name, setting_value FROM settings");
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
}
$default_service_level = $settings['service_level'] ?? 95;
$default_holding_cost = $settings['holding_cost_rate'] ?? 25;
$default_ordering_cost = $settings['ordering_cost'] ?? 50;

?>

<div class="p-6">
    <div class="flex flex-wrap justify-between items-center mb-6 gap-3">
        <h1 class="text-3xl font-bold text-gray-800">What-If Scenario Simulator</h1>
        <div class="flex rounded-lg overflow-hidden border border-gray-300 text-sm font-semibold">
            <button id="tab_single" onclick="switchTab('single')" class="px-4 py-2 bg-blue-600 text-white">Single Item</button>
            <button id="tab_aclass" onclick="switchTab('aclass')" class="px-4 py-2 bg-white text-gray-700 hover:bg-gray-50">All A-Class Items</button>
        </div>
    </div>

    <!-- Single Item Mode -->
    <div id="mode_single">
    <div class="flex flex-col md:flex-row gap-8">
        <!-- Left Column: Controls -->
        <div class="w-full md:w-1/3">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-bold mb-4">Simulation Controls</h2>
                <!-- Item Selection -->
                <div class="mb-6">
                    <label for="item_select" class="block text-sm font-bold text-gray-700 mb-2">1. Select an Item:</label>
                    <select id="item_select" name="item_id" class="shadow-sm bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        <option value="">-- Please select an Item --</option>
                        <?php
                        // Check if the query returned results before looping
                        if ($items_result && $items_result->num_rows > 0) {
                            // Reset pointer just in case
                            $items_result->data_seek(0);
                            while ($item = $items_result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($item['item_id']) . '">' . htmlspecialchars($item['name']) . ' (' . htmlspecialchars($item['item_code']) . ')</option>';
                            }
                        } else {
                            echo '<option value="" disabled>No items currently meet simulation criteria</option>';
                        }
                        // Close the prepared statement here after looping through results
                        if (isset($items_stmt)) {
                            $items_stmt->close();
                        }
                        ?>
                    </select>
                    <!-- Updated note -->
                    <p class="text-xs text-gray-500 mt-1">Note: Only items eligible for simulation (sufficient transaction data) are listed.</p>
                </div>

                <!-- Sliders Panel -->
                <div id="controls_panel" class="hidden">
                    <h3 class="text-sm font-bold text-gray-700 mb-2">2. Adjust Parameters:</h3>
                    <div class="space-y-4">
                        <div class="mb-4">
                            <label for="service_level" class="block text-sm font-medium text-gray-700">Service Level: <span id="service_level_value" class="font-bold"><?php echo $default_service_level; ?></span>%</label>
                            <input type="range" id="service_level" min="80" max="99" value="<?php echo $default_service_level; ?>" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        </div>
                        <div class="mb-4">
                            <label for="holding_cost" class="block text-sm font-medium text-gray-700">Holding Cost: <span id="holding_cost_value" class="font-bold"><?php echo $default_holding_cost; ?></span>%</label>
                            <input type="range" id="holding_cost" min="5" max="50" value="<?php echo $default_holding_cost; ?>" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        </div>
                        <div class="mb-4">
                            <label for="ordering_cost" class="block text-sm font-medium text-gray-700">Ordering Cost: ₱<span id="ordering_cost_value" class="font-bold"><?php echo $default_ordering_cost; ?></span></label>
                            <input type="range" id="ordering_cost" min="10" max="500" value="<?php echo $default_ordering_cost; ?>" step="10" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        </div>
                        <div class="mb-4">
                            <label for="lead_time" class="block text-sm font-medium text-gray-700">Supplier Lead Time: <span id="lead_time_value" class="font-bold">7</span> Days</label>
                            <input type="range" id="lead_time" min="1" max="30" value="7" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Results -->
        <div class="w-full md:w-2/3">
            <div id="results_placeholder" class="flex items-center justify-center h-full bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg">
                <p class="text-gray-500">Please select an item to begin a simulation.</p>
            </div>
            <div id="results_container" class="hidden bg-white p-6 rounded-lg shadow-md">
                <div id="loading_spinner" class="text-center py-10 hidden">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-4 border-blue-600 mx-auto"></div>
                    <p class="mt-4 text-gray-600">Calculating...</p>
                </div>
                <div id="results_content" class="hidden">
                    <!-- Results will be injected here by JavaScript -->
                </div>
            </div>
        </div>
    </div>
    </div> <!-- /mode_single -->

    <!-- All A-Class Items Mode -->
    <div id="mode_aclass" class="hidden">
        <div class="bg-white p-6 rounded-lg shadow-md mb-4">
            <div class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Annual Holding Cost Rate (%)</label>
                    <input type="range" id="aclass_holding" min="5" max="50" value="<?= $default_holding_cost ?>" class="w-40 h-2 bg-gray-200 rounded-lg" oninput="document.getElementById('aclass_holding_val').textContent=this.value">
                    <span class="ml-1 text-sm font-semibold" id="aclass_holding_val"><?= $default_holding_cost ?></span>%
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Ordering Cost (&#8369;)</label>
                    <input type="range" id="aclass_ordering" min="10" max="500" step="10" value="<?= $default_ordering_cost ?>" class="w-40 h-2 bg-gray-200 rounded-lg" oninput="document.getElementById('aclass_ordering_val').textContent=this.value">
                    <span class="ml-1 text-sm font-semibold" id="aclass_ordering_val"><?= $default_ordering_cost ?></span>
                </div>
                <button onclick="runAClassSimulation()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Run Simulation</button>
            </div>
        </div>
        <div id="aclass_loading" class="hidden text-center py-12">
            <div class="animate-spin rounded-full h-14 w-14 border-b-4 border-blue-600 mx-auto"></div>
            <p class="mt-4 text-gray-500">Calculating across all A-class items&hellip;</p>
        </div>
        <div id="aclass_results" class="hidden"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Element References ---
        const itemSelect = document.getElementById('item_select');
        const controlsPanel = document.getElementById('controls_panel');
        const resultsPlaceholder = document.getElementById('results_placeholder');
        const resultsContainer = document.getElementById('results_container');
        const loadingSpinner = document.getElementById('loading_spinner');
        const resultsContent = document.getElementById('results_content');

        const sliders = {
            serviceLevel: document.getElementById('service_level'),
            holdingCost: document.getElementById('holding_cost'),
            orderingCost: document.getElementById('ordering_cost'),
            leadTime: document.getElementById('lead_time')
        };

        const values = {
            serviceLevel: document.getElementById('service_level_value'),
            holdingCost: document.getElementById('holding_cost_value'),
            orderingCost: document.getElementById('ordering_cost_value'),
            leadTime: document.getElementById('lead_time_value')
        };

        // --- State Management ---
        let currentItemId = null;
        let debounceTimer;

        // --- Core Functions ---
        const fetchSimulationData = () => {
            if (!currentItemId) return;

            loadingSpinner.classList.remove('hidden');
            resultsContent.classList.add('hidden'); // Hide old results while loading

            const params = new URLSearchParams({
                item_id: currentItemId,
                service_level: sliders.serviceLevel.value,
                holding_cost: sliders.holdingCost.value,
                ordering_cost: sliders.orderingCost.value,
                lead_time: sliders.leadTime.value
            });

            // Using the same backend script for both simulators
            fetch(`calculate_what_if.php?${params.toString()}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok.');
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        renderError(data.error);
                    } else {
                        // On the very first calculation for an item, update the lead time slider
                        if (data.default_lead_time && sliders.leadTime.value !== data.default_lead_time) {
                            sliders.leadTime.value = data.default_lead_time;
                            values.leadTime.textContent = data.default_lead_time;
                        }
                        renderResults(data);
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    renderError('An unexpected error occurred. Please try again.');
                })
                .finally(() => {
                    loadingSpinner.classList.add('hidden');
                    resultsContent.classList.remove('hidden');
                });
        };

        const renderResults = (data) => {
            const { avg_daily_demand, simulated_policy } = data;
            resultsContent.innerHTML = `
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800 border-b pb-2 mb-4">Simulation Results</h2>
                <p class="text-sm text-gray-600">Item: <strong>${data.item_name}</strong> (${data.item_code})</p>
                <p class="text-sm text-gray-600">Based on an historical average daily usage of <strong>${avg_daily_demand}</strong> units (over the last 90 days).</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200 shadow-md">
                    <h3 class="text-sm font-bold text-blue-800 uppercase">Safety Stock</h3>
                    <p class="text-4xl font-bold text-blue-600">${simulated_policy.safety_stock}</p>
                    <p class="text-xs text-gray-500">units</p>
                </div>
                <div class="bg-red-50 p-4 rounded-lg border border-red-200 shadow-md">
                    <h3 class="text-sm font-bold text-red-800 uppercase">Reorder Point</h3>
                    <p class="text-4xl font-bold text-red-600">${simulated_policy.reorder_point}</p>
                    <p class="text-xs text-gray-500">units</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg border border-green-200 shadow-md">
                    <h3 class="text-sm font-bold text-green-800 uppercase">Economic Order Qty (EOQ)</h3>
                    <p class="text-4xl font-bold text-green-600">${simulated_policy.eoq}</p>
                    <p class="text-xs text-gray-500">units</p>
                </div>
            </div>
            <div class="mt-6 p-4 bg-gray-50 rounded-lg border">
                <h4 class="font-bold text-gray-700">How to Interpret These Results:</h4>
                <ul class="list-disc list-inside mt-2 text-sm text-gray-600 space-y-1">
                    <li><strong>Safety Stock:</strong> This is the extra inventory kept to prevent stockouts caused by demand or lead time variability.</li>
                    <li><strong>Reorder Point:</strong> When your stock level for this item drops to this number, you should place a new order.</li>
                    <li><strong>EOQ:</strong> This is the ideal quantity to order to minimize total inventory costs (holding and ordering costs).</li>
                </ul>
            </div>`;
        };

        const renderError = (errorMessage) => {
            resultsContent.innerHTML = `<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow" role="alert"><p class="font-bold">Error:</p><p>${errorMessage}</p></div>`;
        };

        // --- Event Handlers ---
        const handleItemSelectChange = (event) => {
            currentItemId = event.target.value;
            if (currentItemId) {
                controlsPanel.classList.remove('hidden');
                resultsPlaceholder.classList.add('hidden');
                resultsContainer.classList.remove('hidden');
                // Reset to loading state
                resultsContent.innerHTML = '';
                loadingSpinner.classList.remove('hidden');
                resultsContent.classList.add('hidden');

                fetchSimulationData();
            } else {
                controlsPanel.classList.add('hidden');
                resultsPlaceholder.classList.remove('hidden');
                resultsContainer.classList.add('hidden');
            }
        };

        const handleSliderInput = (slider, display) => {
            display.textContent = slider.value;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(fetchSimulationData, 300); // Debounce API calls
        };

        // --- Attach Event Listeners ---
        itemSelect.addEventListener('change', handleItemSelectChange);

        Object.keys(sliders).forEach(key => {
            sliders[key].addEventListener('input', () => handleSliderInput(sliders[key], values[key]));
        });
    });

    // --- Tab Switcher ---
    function switchTab(tab) {
        const single = document.getElementById('mode_single');
        const aclass = document.getElementById('mode_aclass');
        const tSingle = document.getElementById('tab_single');
        const tAclass = document.getElementById('tab_aclass');
        if (tab === 'single') {
            single.classList.remove('hidden'); aclass.classList.add('hidden');
            tSingle.classList.add('bg-blue-600','text-white'); tSingle.classList.remove('bg-white','text-gray-700');
            tAclass.classList.remove('bg-blue-600','text-white'); tAclass.classList.add('bg-white','text-gray-700');
        } else {
            single.classList.add('hidden'); aclass.classList.remove('hidden');
            tAclass.classList.add('bg-blue-600','text-white'); tAclass.classList.remove('bg-white','text-gray-700');
            tSingle.classList.remove('bg-blue-600','text-white'); tSingle.classList.add('bg-white','text-gray-700');
        }
    }

    // --- A-Class Simulation ---
    function runAClassSimulation() {
        const holdingCost = document.getElementById('aclass_holding').value;
        const orderingCost = document.getElementById('aclass_ordering').value;
        const loading = document.getElementById('aclass_loading');
        const results = document.getElementById('aclass_results');

        loading.classList.remove('hidden');
        results.classList.add('hidden');

        fetch(`calculate_what_if_all_a.php?holding_cost=${holdingCost}&ordering_cost=${orderingCost}`)
            .then(r => r.json())
            .then(data => {
                loading.classList.add('hidden');
                if (data.error) {
                    results.innerHTML = `<div class="bg-red-100 text-red-700 p-4 rounded-lg">${data.error}</div>`;
                    results.classList.remove('hidden');
                    return;
                }
                const sl = data.scenario_labels;
                const totals = data.scenario_totals;
                let html = `
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-5">
                    <p class="text-sm font-bold text-gray-500 uppercase mb-3">Total Portfolio Holding Cost at Different Service Levels (${data.item_count} A-Class Items)</p>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        ${sl.map((label, i) => `
                        <div class="p-4 rounded-xl ${i===0?'bg-gray-50':i===1?'bg-blue-50':'bg-red-50'} border">
                            <p class="text-xs font-semibold text-gray-500 uppercase">Service Level ${label}</p>
                            <p class="text-2xl font-bold mt-1 ${i===0?'text-gray-700':i===1?'text-blue-700':'text-red-700'}">&#8369;${totals[i].total_annual_holding}</p>
                            <p class="text-xs text-gray-400">Annual Holding Cost</p>
                            <p class="text-sm font-semibold mt-1">&#8369;${totals[i].total_safety_cost} safety stock value</p>
                        </div>`).join('')}
                    </div>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-x-auto">
                    <table class="min-w-full text-sm" id="aclassTable">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="py-3 px-4 text-left">Item</th>
                                <th class="py-3 px-4 text-right">Unit Cost</th>
                                <th class="py-3 px-4 text-right">EOQ</th>
                                ${sl.map(l => `<th class="py-3 px-4 text-right">SS @ ${l}</th><th class="py-3 px-4 text-right">ROP @ ${l}</th><th class="py-3 px-4 text-right">Hold Cost @ ${l}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            ${data.rows.map(row => `
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 px-4">${row.item_name} <span class="font-mono text-xs text-gray-400">(${row.item_code})</span></td>
                                <td class="py-2 px-4 text-right">&#8369;${row.unit_cost}</td>
                                <td class="py-2 px-4 text-right font-semibold">${row.eoq}</td>
                                ${row.scenarios.map(s => `<td class="py-2 px-4 text-right">${s.safety_stock}</td><td class="py-2 px-4 text-right">${s.rop}</td><td class="py-2 px-4 text-right text-green-700">&#8369;${s.holding_cost}</td>`).join('')}
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>`;
                results.innerHTML = html;
                results.classList.remove('hidden');
                if (typeof $ !== 'undefined') {
                    $('#aclassTable').DataTable({ pageLength: 25, order: [[1, 'desc']] });
                }
            })
            .catch(() => {
                loading.classList.add('hidden');
                results.innerHTML = `<div class="bg-red-100 text-red-700 p-4 rounded-lg">An error occurred. Please try again.</div>`;
                results.classList.remove('hidden');
            });
    }
</script>

<?php
$conn->close();
require_once 'footer.php';
?>

