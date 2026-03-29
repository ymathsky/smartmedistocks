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
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Data Hub: Import & Export</h1>

    <!-- Display Success/Error Messages for Import -->
    <?php
    if (isset($_SESSION['upload_message'])) {
        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p class="font-bold">Import Successful</p><p>' . nl2br(htmlspecialchars($_SESSION['upload_message'])) . '</p></div>';
        unset($_SESSION['upload_message']);
    }
    if (isset($_SESSION['upload_error'])) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p class="font-bold">Import Error</p><p>' . nl2br(htmlspecialchars($_SESSION['upload_error'])) . '</p></div>';
        unset($_SESSION['upload_error']);
    }
    // Display Success/Error Messages for Export (Optional, if export_handler sets them)
    if (isset($_SESSION['export_message'])) {
        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p class="font-bold">Export Info</p><p>' . htmlspecialchars($_SESSION['export_message']) . '</p></div>';
        unset($_SESSION['export_message']);
    }
    if (isset($_SESSION['export_error'])) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p class="font-bold">Export Error</p><p>' . htmlspecialchars($_SESSION['export_error']) . '</p></div>';
        unset($_SESSION['export_error']);
    }
    ?>

    <!-- ── Database Migrations ── -->
    <?php
    $migrations_dir = __DIR__ . '/migrations/';
    $all_migrations = glob($migrations_dir . '*.sql');
    sort($all_migrations);

    // Run a migration if requested
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">CSRF token mismatch.</div>';
        } else {
            $file = realpath($migrations_dir . basename($_POST['run_migration']));
            if ($file && str_starts_with($file, realpath($migrations_dir)) && file_exists($file)) {
                $sql = file_get_contents($file);
                $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => !empty($s));
                $ok = true;
                foreach ($statements as $stmt) {
                    if (!$conn->query($stmt)) {
                        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded"><strong>Error in ' . htmlspecialchars(basename($file)) . ':</strong> ' . htmlspecialchars($conn->error) . '</div>';
                        $ok = false; break;
                    }
                }
                if ($ok) echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded"><strong>✓ Migration applied:</strong> ' . htmlspecialchars(basename($file)) . '</div>';
            } else {
                echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">Invalid migration file.</div>';
            }
        }
    }
    ?>
    <div class="mb-10">
        <h2 class="text-2xl font-semibold text-gray-700 mb-4 border-b pb-2">Database Migrations</h2>
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <p class="text-sm text-gray-500 mb-4">Run a SQL migration file against the production database. Each migration is idempotent (uses <code>IF NOT EXISTS</code>) and safe to re-run.</p>
            <table class="w-full text-sm">
                <thead><tr class="border-b text-left text-gray-500"><th class="pb-2 pr-4">File</th><th class="pb-2"></th></tr></thead>
                <tbody>
                <?php foreach ($all_migrations as $mig): ?>
                <?php $mig_name = basename($mig); ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-2 pr-4 font-mono text-gray-700"><?php echo htmlspecialchars($mig_name); ?></td>
                    <td class="py-2">
                        <form method="POST" onsubmit="return confirm('Apply <?php echo htmlspecialchars($mig_name); ?>?')">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="run_migration" value="<?php echo htmlspecialchars($mig_name); ?>">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-1 px-3 rounded">Run</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Import Section -->
    <div class="mb-10">
        <h2 class="text-2xl font-semibold text-gray-700 mb-4 border-b pb-2">CSV Import</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Import Items Card -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Import Items</h3>
                <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="upload_type" value="items">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"> <!-- CSRF Token -->
                    <div class="mb-4">
                        <label for="items_csv" class="block text-sm font-medium text-gray-700">Items CSV File</label>
                        <input type="file" name="csv_file" id="items_csv" accept=".csv" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
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
                <h3 class="text-xl font-bold text-gray-800 mb-4">Import Suppliers</h3>
                <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="upload_type" value="suppliers">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"> <!-- CSRF Token -->
                    <div class="mb-4">
                        <label for="suppliers_csv" class="block text-sm font-medium text-gray-700">Suppliers CSV File</label>
                        <input type="file" name="csv_file" id="suppliers_csv" accept=".csv" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100" required>
                    </div>
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Upload Suppliers</button>
                </form>
                <div class="mt-4 text-xs text-gray-500">
                    <p class="font-semibold">Required Columns:</p>
                    <p><code>name, contact_info, address, average_lead_time_days</code></p>
                    <p class="mt-2">Use <code>name</code> to update existing suppliers.</p>
                </div>
            </div>

            <!-- Import Transactions Card -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Import Transactions</h3>
                <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="upload_type" value="transactions">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"> <!-- CSRF Token -->
                    <div class="mb-4">
                        <label for="transactions_csv" class="block text-sm font-medium text-gray-700">Transactions CSV File</label>
                        <input type="file" name="csv_file" id="transactions_csv" accept=".csv" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100" required>
                    </div>
                    <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg">Upload Transactions</button>
                </form>
                <div class="mt-4 text-xs text-gray-500">
                    <p class="font-semibold">Required Columns:</p>
                    <p><code>item_id, quantity_used, transaction_date</code></p>
                    <p class="mt-1">Date format: YYYY-MM-DD</p>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-8 border-gray-300">

    <!-- Export Section -->
    <div>
        <h2 class="text-2xl font-semibold text-gray-700 mb-4 border-b pb-2">CSV Export</h2>
        <p class="text-gray-600 mb-6">Download current data from the system as CSV files.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Export Items -->
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Export Items</h3>
                <p class="text-sm text-gray-600 mb-4">Download the complete item master list.</p>
                <a href="export_handler.php?type=items" class="inline-block bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-lg">
                    Download Items CSV
                </a>
            </div>

            <!-- Export Suppliers -->
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Export Suppliers</h3>
                <p class="text-sm text-gray-600 mb-4">Download the list of all registered suppliers.</p>
                <a href="export_handler.php?type=suppliers" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg">
                    Download Suppliers CSV
                </a>
            </div>

            <!-- Export Transactions -->
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Export Transactions</h3>
                <p class="text-sm text-gray-600 mb-4">Download the complete history of item usage.</p>
                <a href="export_handler.php?type=transactions" class="inline-block bg-green-600 hover:bg-teal-700 text-white font-bold py-2 px-6 rounded-lg">
                    Download Transactions CSV
                </a>
            </div>
        </div>
    </div>

</div>

<?php
require_once 'footer.php';
?>
