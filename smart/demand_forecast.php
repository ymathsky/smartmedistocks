<?php
// Filename: demand_forecast.php
require_once 'header.php';
require_once 'db_connection.php';

// Security Check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Procurement'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch items for the dropdown
$items_sql = "SELECT item_id, name, item_code FROM items ORDER BY name ASC";
$items_result = $conn->query($items_sql);
?>

<!-- Include Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Main Content -->
<div class="flex-1 p-6 bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Demand Forecasting</h1>

        <div class="mb-4">
            <label for="item_select" class="block text-gray-700 text-sm font-bold mb-2">Select an Item to Forecast:</label>
            <select id="item_select" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <option value="">-- Please choose an item --</option>
                <?php while($item = $items_result->fetch_assoc()): ?>
                    <option value="<?php echo $item['item_id']; ?>">
                        <?php echo htmlspecialchars($item['name']) . " (" . htmlspecialchars($item['item_code']) . ")"; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Chart Container -->
        <div id="chart_container" class="mt-6" style="display: none;">
            <canvas id="forecastChart"></canvas>
        </div>

        <!-- Error/Info Message Container -->
        <div id="message_container" class="mt-6"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const itemSelect = document.getElementById('item_select');
        const chartContainer = document.getElementById('chart_container');
        const messageContainer = document.getElementById('message_container');
        let forecastChart = null; // Variable to hold the chart instance

        itemSelect.addEventListener('change', function() {
            const itemId = this.value;

            // Clear previous chart and messages
            if (forecastChart) {
                forecastChart.destroy();
            }
            chartContainer.style.display = 'none';
            messageContainer.innerHTML = '';

            if (!itemId) {
                return; // Do nothing if the placeholder is selected
            }

            // Show a loading message
            messageContainer.innerHTML = `<div class="text-center text-gray-500">Loading forecast data...</div>`;

            // Use fetch for a simple AJAX request
            fetch('get_forecast_data.php?item_id=' + itemId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    messageContainer.innerHTML = ''; // Clear loading message
                    if (data.error) {
                        messageContainer.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">${data.error}</div>`;
                    } else {
                        chartContainer.style.display = 'block';
                        const ctx = document.getElementById('forecastChart').getContext('2d');

                        forecastChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: data.labels,
                                datasets: [{
                                    label: 'Historical Usage',
                                    data: data.historical,
                                    borderColor: 'rgb(54, 162, 235)',
                                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                    tension: 0.1
                                }, {
                                    label: "Forecast (Damped Trend)",
                                    data: data.forecast,
                                    borderColor: 'rgb(255, 99, 132)',
                                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                                    borderDash: [5, 5], // Dashed line for forecast
                                    tension: 0.1
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    title: {
                                        display: true,
                                        text: 'Demand Forecast for the Next 30 Days'
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Quantity Used'
                                        }
                                    }
                                }
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching forecast data:', error);
                    messageContainer.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">An error occurred while fetching the data. Please check the console for details.</div>`;
                });
        });
    });
</script>

<?php
$conn->close();
require_once 'footer.php';
?>

