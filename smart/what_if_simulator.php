<?php
// Filename: what_if_simulator.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Procurement'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch items for the dropdown
$items_sql = "SELECT i.item_id, i.name, i.item_code FROM items i JOIN transactions t ON i.item_id = t.item_id GROUP BY i.item_id HAVING COUNT(t.transaction_id) >= 7 ORDER BY i.name ASC";
$items_result = $conn->query($items_sql);

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
    <h1 class="text-3xl font-bold text-gray-800 mb-6">What-If Scenario Simulator</h1>

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
                        if ($items_result && $items_result->num_rows > 0) {
                            while ($item = $items_result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($item['item_id']) . '">' . htmlspecialchars($item['name']) . ' (' . htmlspecialchars($item['item_code']) . ')</option>';
                            }
                        }
                        ?>
                    </select>
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
                <p class="text-sm text-gray-600">Based on an average daily usage of <strong>${avg_daily_demand}</strong> units.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <h3 class="text-sm font-bold text-blue-800 uppercase">Safety Stock</h3>
                    <p class="text-4xl font-bold text-blue-600">${simulated_policy.safety_stock}</p>
                    <p class="text-xs text-gray-500">units</p>
                </div>
                <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                    <h3 class="text-sm font-bold text-red-800 uppercase">Reorder Point</h3>
                    <p class="text-4xl font-bold text-red-600">${simulated_policy.reorder_point}</p>
                    <p class="text-xs text-gray-500">units</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                    <h3 class="text-sm font-bold text-green-800 uppercase">Economic Order Qty (EOQ)</h3>
                    <p class="text-4xl font-bold text-green-600">${simulated_policy.eoq}</p>
                    <p class="text-xs text-gray-500">units</p>
                </div>
            </div>
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h4 class="font-bold text-gray-700">How to Interpret These Results:</h4>
                <ul class="list-disc list-inside mt-2 text-sm text-gray-600 space-y-1">
                    <li><strong>Safety Stock:</strong> This is the extra inventory kept to prevent stockouts caused by demand or lead time variability.</li>
                    <li><strong>Reorder Point:</strong> When your stock level for this item drops to this number, you should place a new order.</li>
                    <li><strong>EOQ:</strong> This is the ideal quantity to order to minimize total inventory costs (holding and ordering costs).</li>
                </ul>
            </div>`;
        };

        const renderError = (errorMessage) => {
            resultsContent.innerHTML = `<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p class="font-bold">Error:</p><p>${errorMessage}</p></div>`;
        };

        // --- Event Handlers ---
        const handleItemSelectChange = (event) => {
            currentItemId = event.target.value;
            if (currentItemId) {
                controlsPanel.classList.remove('hidden');
                resultsPlaceholder.classList.add('hidden');
                resultsContainer.classList.remove('hidden');
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
</script>

<?php
$conn->close();
require_once 'footer.php';
?>

