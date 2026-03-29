<?php
// Filename: smart/inventory_abc_analysis.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Admins, Procurement, and Warehouse staff can view this page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}
?>

<!-- Include Google Charts loader (Replaces Chart.js) -->
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">ABC Inventory Analysis (Pareto)</h1>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <p class="text-gray-600 mb-6">This report classifies inventory items into A, B, and C groups based on their **Annual Consumption Value (ACV)** over the last 365 days, helping prioritize management efforts.</p>

        <!-- Loading Spinner -->
        <div id="loading_spinner" class="text-center py-10">
            <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">Calculating ACV and classifying items...</p>
        </div>

        <div id="report_content" class="hidden">
            <!-- KPI Cards Summary -->
            <div id="kpi-summary" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 text-center">
                <!-- Data will be injected here -->
            </div>

            <!-- Chart Container -->
            <div class="mb-8 p-4 border rounded-lg bg-gray-50">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">ACV Pareto Chart</h2>
                <!-- Canvas element replaced with a standard DIV for Google Charts -->
                <div id="abcChartContainer" style="height: 400px;"></div>
            </div>

            <!-- Data Table Container -->
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Item Classification Details</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border" id="abcTable">
                    <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Name (Code)</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm text-right">Annual Usage</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm text-right">Unit Cost (₱)</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm text-right">ACV (₱)</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm text-right">Cum. ACV %</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm text-center">Class</th>
                    </tr>
                    </thead>
                    <tbody class="text-gray-700" id="abc_table_body">
                    <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        <div id="error_message" class="hidden text-center py-10">
            <p class="text-red-500 font-bold">An error occurred.</p>
        </div>
    </div>
</div>

<script>
    // Load the visualization library immediately
    google.charts.load('current', {'packages':['corechart']});

    document.addEventListener('DOMContentLoaded', function() {
        const loadingSpinner = document.getElementById('loading_spinner');
        const reportContent = document.getElementById('report_content');
        const tableBody = document.getElementById('abc_table_body');
        const errorMessage = document.getElementById('error_message');
        const kpiSummary = document.getElementById('kpi-summary');
        let dataTableInstance = null;
        let itemsData = []; // Store item data globally

        // Define classification colors
        const classColors = {
            'A': { bg: 'rgba(239, 68, 68, 0.8)', text: 'bg-red-500', border: 'rgba(239, 68, 68, 1)' }, // Red
            'B': { bg: 'rgba(251, 191, 36, 0.8)', text: 'bg-yellow-500', border: 'rgba(251, 191, 36, 1)' }, // Yellow
            'C': { bg: 'rgba(59, 130, 246, 0.8)', text: 'bg-blue-500', border: 'rgba(59, 130, 246, 1)' }  // Blue
        };

        fetch('get_abc_analysis_data.php')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(jsonResponse => {
                loadingSpinner.classList.add('hidden');

                if (jsonResponse.error) {
                    errorMessage.querySelector('p').textContent = jsonResponse.error;
                    errorMessage.classList.remove('hidden');
                    return;
                }

                itemsData = jsonResponse.data;
                reportContent.classList.remove('hidden');

                // Process data and render
                processAndRenderReport(itemsData);

            })
            .catch(error => {
                loadingSpinner.classList.add('hidden');
                errorMessage.querySelector('p').textContent = 'An unexpected error occurred while fetching data: ' + error.message;
                errorMessage.classList.remove('hidden');
                console.error('Fetch Error:', error);
            });

        // Helper function for comma formatting
        function numberWithCommas(x) {
            return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function processAndRenderReport(items) {
            // --- 1. Populate KPI Summary ---
            kpiSummary.innerHTML = '';
            let classA = items.filter(i => i.abc_class === 'A');
            let classB = items.filter(i => i.abc_class === 'B');
            let classC = items.filter(i => i.abc_class === 'C');

            const summaryData = [
                { label: 'A-Class Items (Critical)', items: classA, color: 'border-red-500', desc: 'Require tight inventory control.' },
                { label: 'B-Class Items (Important)', items: classB, color: 'border-yellow-500', desc: 'Require moderate control.' },
                { label: 'C-Class Items (Buffer)', items: classC, color: 'border-blue-500', desc: 'Can use simpler control methods.' }
            ];

            summaryData.forEach(s => {
                const acv = s.items.reduce((sum, i) => sum + i.acv, 0);
                const percentOfItems = (s.items.length / items.length) * 100;

                kpiSummary.innerHTML += `
                    <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 ${s.color}">
                        <h2 class="text-gray-600 text-sm font-bold">${s.label}</h2>
                        <p class="text-3xl font-bold text-gray-800 mt-2">${s.items.length} items (${percentOfItems.toFixed(1)}%)</p>
                        <p class="text-lg text-gray-700 mt-1">Value: ₱${numberWithCommas(acv.toFixed(2))}</p>
                        <p class="text-xs text-gray-500 mt-2">${s.desc}</p>
                    </div>
                `;
            });


            // --- 2. Populate Table and Prepare Chart Data ---
            const chartDataArray = [
                ['Item Rank', 'Cumulative ACV Percentage', { role: 'style' }, 'A-Cutoff (80%)', 'B-Cutoff (95%)'] // Add cutoff columns
            ];

            tableBody.innerHTML = '';

            items.forEach((item, index) => {
                const rank = index + 1;
                const cumAcv = item.cumulative_acv_percent;
                const color = classColors[item.abc_class];

                // Prepare data for Google Charts
                chartDataArray.push([
                    rank.toString(),
                    cumAcv,
                    color.border.replace('rgba', 'rgb').replace(/,[\d\.]+?\)$/, ')'), // Line color for the item rank point (not strictly used for line chart but kept for consistency)
                    80, // A-Cutoff line value
                    95  // B-Cutoff line value
                ]);


                const row = `
                <tr class="border-b hover:bg-gray-100">
                    <td class="py-3 px-4">${item.item_name} <span class="font-mono text-xs text-gray-500">(${item.item_code})</span></td>
                    <td class="py-3 px-4 text-right">${numberWithCommas(item.usage)}</td>
                    <td class="py-3 px-4 text-right">₱${item.unit_cost}</td>
                    <td class="py-3 px-4 text-right font-semibold">₱${numberWithCommas(item.acv.toFixed(2))}</td>
                    <td class="py-3 px-4 text-right">${item.cumulative_acv_percent.toFixed(1)}%</td>
                    <td class="py-3 px-4 text-center">
                        <span class="text-white text-xs font-bold px-2.5 py-0.5 rounded-full ${color.text}">${item.abc_class}</span>
                    </td>
                </tr>
                `;
                tableBody.innerHTML += row;
            });

            // Initialize DataTables
            if (dataTableInstance) { dataTableInstance.destroy(); }
            dataTableInstance = $('#abcTable').DataTable({
                "pagingType": "full_numbers",
                "order": [[4, "asc"]], // Order by cumulative ACV %
                "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ]
            });

            // --- 3. Render Chart ---
            // Defer drawing until Google Charts is loaded
            google.charts.setOnLoadCallback(() => drawAbcChart(chartDataArray));
        }

        // NEW: Draw chart using Google Charts (Fixed)
        function drawAbcChart(dataArray) {
            const data = google.visualization.arrayToDataTable(dataArray);

            const options = {
                title: 'ABC Classification Pareto Curve (Cumulative ACV %)',
                height: 400,
                legend: { position: 'none' },
                hAxis: {
                    title: 'Items Ranked by Value',
                    viewWindow: { min: 0, max: dataArray.length }
                },
                vAxis: {
                    title: 'Cumulative ACV (%)',
                    minValue: 0,
                    maxValue: 100,
                    format: '#\'%\'' // Format as percentage
                },
                // --- FIX: Remove conflicting 'seriesType: bars' ---
                // Retain series definition for styling the main line and adding cutoff lines
                series: {
                    0: { // Main Cumulative ACV Line
                        type: 'line',
                        color: 'rgb(239, 68, 68)', // Red line
                        lineWidth: 3,
                        pointSize: 0,
                        curveType: 'function',
                        enableInteractivity: true // Allow interaction for the main line
                    },
                    1: { // A/B Cutoff Line (80%)
                        type: 'line',
                        color: 'rgb(251, 191, 36)', // Yellow/Orange line
                        lineWidth: 1,
                        lineDashStyle: [4, 4],
                        pointSize: 0,
                        enableInteractivity: false
                    },
                    2: { // B/C Cutoff Line (95%)
                        type: 'line',
                        color: 'rgb(59, 130, 246)', // Blue line
                        lineWidth: 1,
                        lineDashStyle: [4, 4],
                        pointSize: 0,
                        enableInteractivity: false
                    }
                },
                // Removed the complex annotations used to simulate lines in the previous version
                // The new data structure now includes the lines as separate series.
                chartArea: { width: '80%', height: '80%' }
            };

            // Use the LineChart class directly for the line chart
            const chart = new google.visualization.LineChart(document.getElementById('abcChartContainer'));
            chart.draw(data, options);
        }
    });
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
