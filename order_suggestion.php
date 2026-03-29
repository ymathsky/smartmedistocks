<?php
// Filename: smart/order_suggestion.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Admins and Procurement can view this page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Purchase Order Suggestions</h1>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <p class="text-gray-600 mb-6">This list displays items whose current stock is **at or below** the calculated Reorder Point (ROP). The suggested quantity is the **Economic Order Quantity (EOQ)**.</p>

        <!-- Loading Spinner -->
        <div id="loading_spinner" class="text-center py-10">
            <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">Calculating purchase suggestions...</p>
        </div>

        <!-- Data Table Container -->
        <div id="suggestion_table_container" class="overflow-x-auto hidden">
            <table class="min-w-full bg-white border" id="suggestionTable">
                <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Name (Code)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Supplier</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right">Current Stock</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right bg-red-700">Reorder Point (ROP)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right bg-green-700">Suggested Order Qty (EOQ)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right">Est. Cost (₱)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-center">Action</th>
                </tr>
                </thead>
                <tbody class="text-gray-700" id="suggestion_table_body">
                <!-- Data will be populated by JavaScript -->
                </tbody>
            </table>
        </div>
        <div id="message_container" class="hidden text-center py-10">
            <p class="text-green-500 font-bold">No items currently require reordering based on ROP.</p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const loadingSpinner = document.getElementById('loading_spinner');
        const tableContainer = document.getElementById('suggestion_table_container');
        const tableBody = document.getElementById('suggestion_table_body');
        const messageContainer = document.getElementById('message_container');
        let dataTableInstance = null;

        fetch('get_order_suggestions.php')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(jsonResponse => {
                loadingSpinner.classList.add('hidden');

                if (jsonResponse.error) {
                    messageContainer.querySelector('p').textContent = jsonResponse.error;
                    messageContainer.classList.remove('hidden');
                    messageContainer.classList.replace('text-green-500', 'text-red-500');
                    return;
                }

                const data = jsonResponse.data;

                if (data.length === 0) {
                    messageContainer.classList.remove('hidden');
                    return;
                }

                if (dataTableInstance) {
                    dataTableInstance.destroy();
                }

                data.forEach(item => {
                    const poUrl = `create_purchase_order.php?item_id=${item.item_id}&quantity=${item.suggested_order_qty}`;

                    const actionButton = item.supplier_name !== 'N/A' ?
                        // UPDATED: Changed button to an anchor tag pointing to the new PO creation page
                        `<a href="${poUrl}" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-1 px-3 rounded-md inline-block">Create PO</a>` :
                        `<span class="text-xs text-gray-500">No Supplier Set</span>`;

                    const row = `
                    <tr class="border-b hover:bg-gray-100">
                        <td class="py-3 px-4">${item.item_name} <span class="font-mono text-xs text-gray-500">(${item.item_code})</span></td>
                        <td class="py-3 px-4">${item.supplier_name}</td>
                        <td class="py-3 px-4 text-right font-semibold">${item.current_stock} ${item.unit_of_measure}</td>
                        <td class="py-3 px-4 text-right font-bold text-red-800 bg-red-50">${item.reorder_point}</td>
                        <td class="py-3 px-4 text-right font-bold text-green-800 bg-green-50">${item.suggested_order_qty}</td>
                        <td class="py-3 px-4 text-right font-mono">₱${item.order_cost}</td>
                        <td class="py-3 px-4 text-center">${actionButton}</td>
                    </tr>
                    `;
                    tableBody.innerHTML += row;
                });

                tableContainer.classList.remove('hidden');

                dataTableInstance = $('#suggestionTable').DataTable({
                    "pagingType": "full_numbers",
                    "order": [[3, "asc"]], // Order by ROP
                    "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ]
                });
            })
            .catch(error => {
                loadingSpinner.classList.add('hidden');
                messageContainer.querySelector('p').textContent = 'An unexpected error occurred while fetching data. Please check the console.';
                messageContainer.classList.replace('text-green-500', 'text-red-500');
                messageContainer.classList.remove('hidden');
                console.error('Fetch Error:', error);
            });
    });
</script>

<?php
require_once 'footer.php';
?>
