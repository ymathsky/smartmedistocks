<?php
// Filename: data_hub.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Ensure an Admin is logged in.
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Data Hub & CSV Import</h1>

    <!-- Display Success/Error Messages -->
    <?php
    if (isset($_SESSION['upload_message'])) {
        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p class="font-bold">Import Successful</p><p>' . nl2br(htmlspecialchars($_SESSION['upload_message'])) . '</p></div>';
        unset($_SESSION['upload_message']);
    }
    if (isset($_SESSION['upload_error'])) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p class="font-bold">Import Error</p><p>' . nl2br(htmlspecialchars($_SESSION['upload_error'])) . '</p></div>';
        unset($_SESSION['upload_error']);
    }
    ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Import Items Card -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Import Items</h2>
            <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="upload_type" value="items">
                <div class="mb-4">
                    <label for="items_csv" class="block text-sm font-medium text-gray-700">Items CSV File</label>
                    <input type="file" name="csv_file" id="items_csv" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Upload Items</button>
            </form>
            <div class="mt-4 text-xs text-gray-500">
                <p class="font-semibold">Required Columns:</p>
                <p><code>name, item_code, description, category, brand_name, unit_of_measure, unit_cost, shelf_life_days, supplier_id</code></p>
                <p class="mt-2">Use <code>item_code</code> to update existing items.</p>
            </div>
        </div>

        <!-- Import Suppliers Card -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Import Suppliers</h2>
            <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="upload_type" value="suppliers">
                <div class="mb-4">
                    <label for="suppliers_csv" class="block text-sm font-medium text-gray-700">Suppliers CSV File</label>
                    <input type="file" name="csv_file" id="suppliers_csv" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100" required>
                </div>
                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Upload Suppliers</button>
            </form>
            <div class="mt-4 text-xs text-gray-500">
                <p class="font-semibold">Required Columns:</p>
                <p><code>name, contact_info, average_lead_time_days</code></p>
                <p class="mt-2">Use <code>name</code> to update existing suppliers.</p>
            </div>
        </div>

        <!-- Import Transactions Card -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Import Transactions</h2>
            <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="upload_type" value="transactions">
                <div class="mb-4">
                    <label for="transactions_csv" class="block text-sm font-medium text-gray-700">Transactions CSV File</label>
                    <input type="file" name="csv_file" id="transactions_csv" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100" required>
                </div>
                <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg">Upload Transactions</button>
            </form>
            <div class="mt-4 text-xs text-gray-500">
                <p class="font-semibold">Required Columns:</p>
                <p><code>item_id, quantity_used, transaction_date</code></p>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>
