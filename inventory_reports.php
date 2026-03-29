<?php
// Filename: smart/inventory_reports.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Admins, Procurement, and Warehouse staff can view this page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Inventory Performance Reports</h1>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <p class="text-gray-600 mb-6">Analyze key performance indicators (KPIs) like **Inventory Turnover** and **Average Stock Age** to assess efficiency and identify slow-moving or aging stock.</p>

        <!-- Loading Spinner -->
        <div id="loading_spinner" class="text-center py-10">
            <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">Calculating inventory performance metrics...</p>
        </div>

        <!-- Data Table Container -->
        <div id="report_table_container" class="overflow-x-auto hidden">
            <table class="min-w-full bg-white border" id="performanceTable">
                <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Name (Code)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right">Current Stock</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right bg-blue-700">Avg. Stock Age (Days)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right bg-green-700">Turnover Rate (x)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right bg-red-700">Days in Inventory</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Status</th>
                </tr>
                </thead>
                <tbody class="text-gray-700" id="performance_table_body">
                <!-- Data will be populated by JavaScript -->
                </tbody>
            </table>
        </div>
        <div id="error_message" class="hidden text-center py-10">
            <p class="text-red-500 font-bold">An error occurred.</p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const loadingSpinner = document.getElementById('loading_spinner');
        const tableContainer = document.getElementById('report_table_container');
        const tableBody = document.getElementById('performance_table_body');
        const errorMessage = document.getElementById('error_message');
        let dataTableInstance = null; // To hold the DataTables object

        fetch('inventory_report_data.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(jsonResponse => {
                loadingSpinner.classList.add('hidden');

                if (jsonResponse.error) {
                    errorMessage.querySelector('p').textContent = jsonResponse.error;
                    errorMessage.classList.remove('hidden');
                    return;
                }

                const data = jsonResponse.data;

                if (data.length === 0) {
                    errorMessage.querySelector('p').textContent = 'No inventory data found to generate a report.';
                    errorMessage.classList.remove('hidden');
                    return;
                }

                // Destroy previous instance if it exists
                if (dataTableInstance) {
                    dataTableInstance.destroy();
                }

                // Populate the table
                data.forEach(item => {
                    let statusHtml = '';
                    let age = item.avg_stock_age_days;
                    let turnover = parseFloat(item.turnover_rate);

                    if (age !== 'N/A' && age > 180) { // Highlight stock older than 6 months
                        statusHtml = `<span class="bg-red-500 text-white text-xs font-bold px-2.5 py-0.5 rounded-full">AGED STOCK</span>`;
                    } else if (turnover > 12) { // High turnover (monthly or more)
                        statusHtml = `<span class="bg-green-500 text-white text-xs font-bold px-2.5 py-0.5 rounded-full">HIGH DEMAND</span>`;
                    } else if (turnover < 4 && turnover > 0) { // Low turnover (less than quarterly)
                        statusHtml = `<span class="bg-yellow-500 text-gray-800 text-xs font-bold px-2.5 py-0.5 rounded-full">SLOW MOVER</span>`;
                    } else if (item.current_stock_qty === '0') {
                        statusHtml = `<span class="bg-gray-500 text-white text-xs font-bold px-2.5 py-0.5 rounded-full">OUT OF STOCK</span>`;
                    } else {
                        statusHtml = `<span class="bg-blue-500 text-white text-xs font-bold px-2.5 py-0.5 rounded-full">NORMAL</span>`;
                    }

                    const row = `
                    <tr class="border-b hover:bg-gray-100">
                        <td class="py-3 px-4">${item.item_name} <span class="font-mono text-xs text-gray-500">(${item.item_code})</span></td>
                        <td class="py-3 px-4 text-right font-semibold">${item.current_stock_qty}</td>
                        <td class="py-3 px-4 text-right font-bold text-blue-800">${item.avg_stock_age_days}</td>
                        <td class="py-3 px-4 text-right font-bold text-green-800">${item.turnover_rate}</td>
                        <td class="py-3 px-4 text-right font-bold text-red-800">${item.days_in_inventory}</td>
                        <td class="py-3 px-4">${statusHtml}</td>
                    </tr>
                    `;
                    tableBody.innerHTML += row;
                });

                tableContainer.classList.remove('hidden');

                // Initialize DataTables
                dataTableInstance = $('#performanceTable').DataTable({
                    "pagingType": "full_numbers",
                    "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ]
                });
            })
            .catch(error => {
                loadingSpinner.classList.add('hidden');
                errorMessage.querySelector('p').textContent = 'An unexpected error occurred while fetching data. Please check the console.';
                errorMessage.classList.remove('hidden');
                console.error('Fetch Error:', error);
            });
    });
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
