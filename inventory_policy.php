<?php
// Filename: inventory_policy.php

require_once 'header.php';
require_once 'db_connection.php';

// Security check: Admins, Pharmacists, and Procurement can view this page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Procurement'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Inventory Policy Recommendations</h1>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="mb-4">
            <p class="text-gray-600">This table calculates the optimal ordering policies for each item based on demand forecasts and global settings. These recommendations help minimize inventory costs and prevent stockouts.</p>
        </div>

        <!-- Loading Spinner -->
        <div id="loading_spinner" class="text-center py-10">
            <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">Calculating inventory policies for all items...</p>
        </div>

        <!-- Data Table -->
        <div id="policy_table_container" class="overflow-x-auto hidden">
            <table class="min-w-full bg-white" id="policy_table">
                <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Code</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Name</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right">Current Stock</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right bg-blue-700">Safety Stock</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right bg-red-700">Reorder Point (ROP)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right bg-green-700">EOQ</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Status</th>
                </tr>
                </thead>
                <tbody class="text-gray-700" id="policy_table_body">
                <!-- Data will be populated by JavaScript -->
                </tbody>
            </table>
        </div>
        <div id="error_message" class="hidden text-center py-10">
            <p class="text-red-500 font-bold"></p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const loadingSpinner = document.getElementById('loading_spinner');
        const tableContainer = document.getElementById('policy_table_container');
        const tableBody = document.getElementById('policy_table_body');
        const errorMessage = document.getElementById('error_message');

        fetch('get_inventory_policy_data.php')
            .then(response => response.json())
            .then(data => {
                loadingSpinner.classList.add('hidden');

                if (data.error) {
                    errorMessage.querySelector('p').textContent = data.error;
                    errorMessage.classList.remove('hidden');
                    return;
                }

                if (data.length === 0) {
                    errorMessage.querySelector('p').textContent = 'No items with sufficient transaction data found to calculate policies.';
                    errorMessage.classList.remove('hidden');
                    return;
                }

                // Populate the table
                data.forEach(item => {
                    let statusHtml = '';
                    if (item.current_stock <= item.reorder_point) {
                        statusHtml = `<span class="bg-red-500 text-white text-xs font-bold mr-2 px-2.5 py-0.5 rounded-full">REORDER NOW</span>`;
                    } else if (item.current_stock <= item.reorder_point + (item.reorder_point * 0.1)) { // Within 10% of ROP
                        statusHtml = `<span class="bg-yellow-400 text-gray-800 text-xs font-bold mr-2 px-2.5 py-0.5 rounded-full">REORDER SOON</span>`;
                    } else {
                        statusHtml = `<span class="bg-green-500 text-white text-xs font-bold mr-2 px-2.5 py-0.5 rounded-full">OK</span>`;
                    }


                    const row = `
                    <tr class="border-b hover:bg-gray-100">
                        <td class="py-3 px-4 font-mono">${item.item_code}</td>
                        <td class="py-3 px-4">${item.item_name}</td>
                        <td class="py-3 px-4 text-right font-semibold">${item.current_stock}</td>
                        <td class="py-3 px-4 text-right font-bold text-blue-800 bg-blue-50">${item.safety_stock}</td>
                        <td class="py-3 px-4 text-right font-bold text-red-800 bg-red-50">${item.reorder_point}</td>
                        <td class="py-3 px-4 text-right font-bold text-green-800 bg-green-50">${item.eoq}</td>
                        <td class="py-3 px-4">${statusHtml}</td>
                    </tr>
                `;
                    tableBody.innerHTML += row;
                });

                tableContainer.classList.remove('hidden');
            })
            .catch(error => {
                loadingSpinner.classList.add('hidden');
                errorMessage.querySelector('p').textContent = 'An unexpected error occurred while fetching data.';
                errorMessage.classList.remove('hidden');
                console.error('Fetch Error:', error);
            });
    });
</script>

<?php
require_once 'footer.php';
?>
