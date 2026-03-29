<?php
// Filename: demand_forecast.php
require_once 'header.php';
require_once 'db_connection.php';

// Security Check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Procurement', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch items for the dropdown.
// Only select items that have at least one entry in the 'transactions' table,
// which ensures that demand calculation is possible.
$items_sql = "
    SELECT 
        i.item_id, i.name, i.item_code 
    FROM items i 
    JOIN transactions t ON i.item_id = t.item_id 
    GROUP BY i.item_id, i.name, i.item_code 
    ORDER BY i.name ASC
";
$items_result = $conn->query($items_sql);
?>

<!-- Include Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Include Chart.js Annotation Plugin -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.4.0/dist/chartjs-plugin-annotation.min.js"></script>

<!-- Main Content -->
<div class="flex-1 p-6 bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Demand Forecasting (Hybrid Model)</h1>

        <!-- Controls Section -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="md:col-span-1">
                <label for="item_select" class="block text-gray-700 text-sm font-bold mb-2">1. Select an Item to Forecast:</label>
                <select id="item_select" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">-- Please choose an item --</option>
                    <?php while($item = $items_result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($item['item_id']); ?>">
                            <?php echo htmlspecialchars($item['name']) . " (" . htmlspecialchars($item['item_code']) . ")"; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Only items with **historical usage data** are shown here.</p>
            </div>

            <!-- NEW: Forecast Horizon Selector -->
            <div class="md:col-span-1">
                <label for="forecast_horizon" class="block text-gray-700 text-sm font-bold mb-2">2. Forecast Horizon (Days):</label>
                <select id="forecast_horizon" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="30">30 Days (Default)</option>
                    <option value="7">7 Days (Weekly)</option>
                    <option value="90">90 Days (Quarterly)</option>
                    <option value="365">365 Days (Annual)</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Adjusts the prediction length.</p>
            </div>

            <!-- Model Results Display (Shifted to column 3 & 4) -->
            <div class="md:col-span-2 bg-gray-50 p-4 rounded-lg border" id="model_summary_display">
                <h3 class="text-sm font-bold text-gray-700 mb-2">3. Demand Classification & Model:</h3>
                <div class="flex space-x-6 text-sm">
                    <div>
                        <span class="block font-medium text-gray-600">Classification:</span>
                        <p id="classification_display" class="font-bold text-lg">--</p>
                    </div>
                    <div>
                        <span class="block font-medium text-gray-600">Forecasting Model:</span>
                        <p id="model_display" class="font-bold text-lg text-blue-700">--</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Optimization Results Display (Moved and condensed) -->
        <div class="bg-gray-50 p-4 rounded-lg border mb-8 hidden" id="optimization_results_summary">
            <h3 class="text-sm font-bold text-gray-700 mb-2 border-b pb-1">Optimization Parameters ($\alpha, \beta, \gamma$)</h3>
            <div class="grid grid-cols-3 gap-6 text-center text-sm">
                <div class="bg-white p-2 rounded shadow-sm">
                    <span class="block font-medium text-gray-600">Level ($\alpha$):</span>
                    <p id="alpha_display" class="font-bold text-blue-600 text-lg">--</p>
                </div>
                <div class="bg-white p-2 rounded shadow-sm">
                    <span class="block font-medium text-gray-600">Trend ($\beta$):</span>
                    <p id="beta_display" class="font-bold text-green-600 text-lg">--</p>
                </div>
                <div class="bg-white p-2 rounded shadow-sm">
                    <span class="block font-medium text-gray-600">Seasonal ($\gamma$):</span>
                    <p id="gamma_display" class="font-bold text-red-600 text-lg">--</p>
                </div>
            </div>
        </div>
        <!-- END Controls Section -->

        <!-- KPI Metrics & Error Display -->
        <div id="kpi_metrics" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 hidden">
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200 text-center">
                <h3 class="text-sm font-bold text-blue-800 uppercase">Mean Abs. % Error (MAPE)</h3>
                <p id="mape_display" class="text-2xl font-bold text-blue-600 mt-1">--</p>
                <p id="confidence_badge" class="inline-block mt-1 px-2 py-0.5 text-xs font-bold rounded-full"></p>
            </div>
            <div class="bg-red-50 p-4 rounded-lg border border-red-200 text-center">
                <h3 class="text-sm font-bold text-red-800 uppercase">Reorder Point (ROP)</h3>
                <p id="rop_display" class="text-2xl font-bold text-red-600 mt-1">--</p>
                <p class="text-xs text-gray-500">units</p>
            </div>
            <div class="bg-green-50 p-4 rounded-lg border border-green-200 text-center">
                <h3 class="text-sm font-bold text-green-800 uppercase">EOQ (Order Qty)</h3>
                <p id="eoq_display" class="text-2xl font-bold text-green-600 mt-1">--</p>
                <p class="text-xs text-gray-500">units</p>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200 text-center">
                <h3 class="text-sm font-bold text-yellow-800 uppercase">Avg. Forecasted Demand</h3>
                <p id="avg_demand_display" class="text-2xl font-bold text-yellow-600 mt-1">--</p>
                <p class="text-xs text-gray-500">units/day</p>
            </div>
        </div>

        <!-- Chart Container -->
        <div id="chart_container" class="mt-6" style="display: none;">
            <canvas id="forecastChart"></canvas>
        </div>

        <!-- Error/Info Message Container -->
        <div id="message_container" class="mt-6">
            <div class="text-center text-gray-500 py-10">Select an item above to generate a demand forecast.</div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- DOM Elements ---
        const itemSelect = document.getElementById('item_select');
        const horizonSelect = document.getElementById('forecast_horizon'); // NEW
        const chartContainer = document.getElementById('chart_container');
        const messageContainer = document.getElementById('message_container');
        const kpiMetrics = document.getElementById('kpi_metrics');
        let forecastChart = null;

        // Model displays
        const modelDisplay = document.getElementById('model_display');
        const classificationDisplay = document.getElementById('classification_display');
        const optimizationSummary = document.getElementById('optimization_results_summary');

        // Optimization parameters display
        const alphaDisplay = document.getElementById('alpha_display');
        const betaDisplay = document.getElementById('beta_display');
        const gammaDisplay = document.getElementById('gamma_display');

        let debounceTimer = null; // Debounce for multiple selector changes

        // --- Helper Functions ---
        const formatDateLabel = (dateString) => {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        };

        const renderKPIs = (metrics, model, classification) => {
            const mapeVal = parseFloat(metrics.mape);
            document.getElementById('mape_display').textContent = metrics.mape ? `${metrics.mape}%` : 'N/A';

            // Confidence badge based on MAPE thresholds
            const badge = document.getElementById('confidence_badge');
            badge.className = 'inline-block mt-1 px-2 py-0.5 text-xs font-bold rounded-full';
            if (!isNaN(mapeVal)) {
                if (mapeVal < 15) {
                    badge.textContent = '\u2714 High Confidence';
                    badge.classList.add('bg-green-100', 'text-green-800');
                } else if (mapeVal <= 30) {
                    badge.textContent = '\u26A0 Medium Confidence';
                    badge.classList.add('bg-yellow-100', 'text-yellow-800');
                } else {
                    badge.textContent = '\u2718 Low Confidence';
                    badge.classList.add('bg-red-100', 'text-red-800');
                }
            } else {
                badge.textContent = 'N/A';
                badge.classList.add('bg-gray-100', 'text-gray-500');
            }
            document.getElementById('rop_display').textContent = metrics.rop ?? 'N/A';
            document.getElementById('eoq_display').textContent = metrics.eoq ?? 'N/A';
            document.getElementById('avg_demand_display').textContent = metrics.avg_forecast_demand ? metrics.avg_forecast_demand.toFixed(2) : 'N/A';
            kpiMetrics.classList.remove('hidden');

            // Display Model and Classification
            modelDisplay.textContent = model ?? '--';
            classificationDisplay.textContent = classification ?? '--';

            // Display optimized parameters
            alphaDisplay.textContent = metrics.alpha ?? '--';
            betaDisplay.textContent = metrics.beta ?? '--';
            gammaDisplay.textContent = metrics.gamma ?? '--';

            // Show optimization summary only if it's the Holt-Winters model
            if (model.includes('Holt-Winters')) {
                optimizationSummary.classList.remove('hidden');
            } else {
                optimizationSummary.classList.add('hidden');
            }
        };

        const fetchForecastData = () => {
            const itemId = itemSelect.value;
            const forecastHorizon = horizonSelect.value; // NEW: Get forecast horizon

            if (!itemId) {
                kpiMetrics.classList.add('hidden');
                chartContainer.style.display = 'none';
                return;
            }

            // Clear previous chart and messages
            if (forecastChart) forecastChart.destroy();
            chartContainer.style.display = 'none';
            messageContainer.innerHTML = `<div class="text-center text-gray-500 py-10"><div class="animate-spin rounded-full h-12 w-12 border-b-4 border-blue-600 mx-auto"></div><p class="mt-4 text-gray-600">Running Demand Classification and Hybrid Forecast...</p></div>`;

            const params = new URLSearchParams({
                item_id: itemId,
                forecast_horizon: forecastHorizon // NEW: Pass horizon to backend
            });

            fetch('get_forecast_data.php?' + params.toString())
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    messageContainer.innerHTML = ''; // Clear loading message

                    // --- Error Handling ---
                    if (data.error) {
                        kpiMetrics.classList.add('hidden');
                        chartContainer.style.display = 'none'; // Ensure chart is hidden on error
                        messageContainer.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert"><p class="font-bold">Forecast Error:</p><p>${data.error}</p></div>`;
                        return;
                    }

                    // On success, show the containers
                    chartContainer.style.display = 'block';
                    kpiMetrics.classList.remove('hidden');

                    const ctx = document.getElementById('forecastChart').getContext('2d');

                    // Render KPIs, including optimized alpha/beta/gamma
                    if (data.metrics) {
                        renderKPIs(data.metrics, data.model, data.classification);
                    }

                    // Format all labels
                    const formattedLabels = data.labels.map(formatDateLabel);

                    // Prepare annotation lines (ROP and Safety Stock)
                    const annotations = [];
                    if (data.metrics && data.metrics.rop !== null) {
                        annotations.push({
                            type: 'line',
                            mode: 'horizontal',
                            scaleID: 'y',
                            value: data.metrics.rop,
                            borderColor: 'rgb(220, 38, 38)', // Tailwind red-700
                            borderWidth: 2,
                            borderDash: [6, 6],
                            label: {
                                enabled: true,
                                content: `ROP (${data.metrics.rop})`,
                                position: 'end',
                                backgroundColor: 'rgba(220, 38, 38, 0.8)',
                            }
                        });
                    }
                    if (data.metrics && data.metrics.safety_stock !== null) {
                        annotations.push({
                            type: 'line',
                            mode: 'horizontal',
                            scaleID: 'y',
                            value: data.metrics.safety_stock,
                            borderColor: 'rgb(59, 130, 246)', // Tailwind blue-600
                            borderWidth: 1,
                            borderDash: [3, 3],
                            label: {
                                enabled: true,
                                content: `Safety Stock (${data.metrics.safety_stock})`,
                                position: 'start',
                                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                            }
                        });
                    }


                    forecastChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: formattedLabels,
                            datasets: [{
                                label: 'Historical Usage (Past 180 Days)',
                                data: data.historical,
                                borderColor: 'rgb(54, 162, 235)',
                                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                tension: 0.1,
                                pointRadius: 1
                            }, {
                                label: `Forecast (Next ${forecastHorizon} Days) - ${data.model.includes('Croston') ? 'Intermittent' : 'Seasonal'}`,
                                data: data.forecast,
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                                borderDash: [5, 5],
                                tension: 0.1,
                                pointRadius: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            plugins: {
                                title: {
                                    display: true,
                                    text: `Demand Forecast and Historical Usage (180-Day History / ${forecastHorizon}-Day Forecast)`
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';

                                            if (label) {
                                                label += ': ';
                                            }
                                            if (context.parsed.y !== null) {
                                                label += Math.round(context.parsed.y) + ' units';
                                            }
                                            return label;
                                        }
                                    }
                                },
                                // Annotation Plugin Configuration
                                annotation: {
                                    annotations: annotations
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Quantity Used (Units)'
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            if (value % 1 === 0) return value;
                                        }
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Date'
                                    },
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 45,
                                        autoSkip: true,
                                        maxTicksLimit: 30
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error fetching forecast data:', error);
                    messageContainer.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">An unexpected network error occurred. Please check the console for details.</div>`;
                    kpiMetrics.classList.add('hidden');
                    chartContainer.style.display = 'none';
                });
        };

        // Function to handle changes with debouncing
        const handleSelectorChange = () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(fetchForecastData, 100);
        };


        // --- Attach Event Handlers ---
        itemSelect.addEventListener('change', handleSelectorChange);
        horizonSelect.addEventListener('change', handleSelectorChange); // NEW: Listen to horizon change

        // Auto-load forecast for the first item on page load
        if (itemSelect.options.length > 1) {
            itemSelect.selectedIndex = 1;
            fetchForecastData();
        }
    });
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
