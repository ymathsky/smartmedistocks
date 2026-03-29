<?php
// Filename: transaction_history.php

require_once 'header.php';
// Remove require_once 'db_connection.php'; // Connection handled by header/footer now

// Security check: Admins and Pharmacists can view history
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// REMOVED: Initial data fetch is now handled by AJAX
?>

<div class="p-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-4 sm:mb-0">Transaction History</h1>
        <a href="record_usage.php" class="text-blue-600 hover:underline">&larr; Back to Record Usage</a>
    </div>

    <!-- User Feedback -->
    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert"><p>' . htmlspecialchars($_SESSION['message']) . '</p></div>';
        unset($_SESSION['message']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert"><p>' . htmlspecialchars($_SESSION['error']) . '</p></div>';
        unset($_SESSION['error']);
    }
    ?>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
            <p class="font-bold">Warning!</p>
            <p>Editing or deleting past transactions will create automatic stock adjustments to maintain inventory accuracy. These actions are logged and should only be used to correct data entry errors.</p>
        </div>

        <!-- NEW: Date Filter Buttons -->
        <div class="mb-4 flex flex-wrap gap-2">
            <button data-range="all" class="filter-btn bg-gray-500 hover:bg-gray-600 text-white font-semibold py-1 px-3 rounded text-sm active-filter">All Time</button>
            <button data-range="today" class="filter-btn bg-blue-500 hover:bg-blue-600 text-white font-semibold py-1 px-3 rounded text-sm">Today</button>
            <button data-range="week" class="filter-btn bg-blue-500 hover:bg-blue-600 text-white font-semibold py-1 px-3 rounded text-sm">This Week</button>
            <button data-range="month" class="filter-btn bg-blue-500 hover:bg-blue-600 text-white font-semibold py-1 px-3 rounded text-sm">This Month</button>
            <button data-range="3months" class="filter-btn bg-blue-500 hover:bg-blue-600 text-white font-semibold py-1 px-3 rounded text-sm">Past 3 Months</button>
        </div>
        <!-- END NEW -->

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white display" id="transactionTable">
                <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Date</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Code</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Name</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right">Quantity Used</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-center">Actions</th>
                </tr>
                </thead>
                <tbody class="text-gray-700">
                <!-- Data will be loaded by DataTables AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .active-filter {
        background-color: #1d4ed8; /* darker blue */
        font-weight: bold;
    }
</style>

<!-- ENTIRE SCRIPT BLOCK REMOVED FROM HERE -->

<?php
// REMOVED: $conn->close(); // Connection is closed in footer.php
require_once 'footer.php';
?>

