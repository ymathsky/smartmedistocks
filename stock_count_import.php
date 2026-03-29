<?php
// Filename: stock_count_import.php
// Upload counted quantities CSV, preview variances, and apply bulk adjustments.
require_once 'header.php';
require_once 'db_connection.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

$variances = [];
$parse_error = '';

// Handle CSV upload for preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['count_csv']) && empty($_POST['apply'])) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $parse_error = "Security error: Invalid request token.";
    } elseif ($_FILES['count_csv']['error'] !== UPLOAD_ERR_OK) {
        $parse_error = "File upload failed. Please try again.";
    } else {
        $mime = mime_content_type($_FILES['count_csv']['tmp_name']);
        // Accept text/plain and text/csv
        if (!in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'])) {
            $parse_error = "Invalid file type. Please upload a CSV file.";
        } else {
            $handle = fopen($_FILES['count_csv']['tmp_name'], 'r');
            $header = fgetcsv($handle); // skip header row

            // Validate expected CSV columns (item_id, counted_qty are required)
            $id_col = array_search('item_id', array_map('strtolower', array_map('trim', $header)));
            $counted_col = array_search('counted_qty', array_map('strtolower', array_map('trim', $header)));

            if ($id_col === false || $counted_col === false) {
                $parse_error = "CSV must contain 'item_id' and 'counted_qty' columns. Please use the exported template from the Count Sheet page.";
            } else {
                $raw_rows = [];
                while (($cols = fgetcsv($handle)) !== false) {
                    $item_id   = filter_var(trim($cols[$id_col] ?? ''), FILTER_VALIDATE_INT);
                    $counted   = trim($cols[$counted_col] ?? '');
                    if ($item_id && $counted !== '') {
                        $raw_rows[(int)$item_id] = (int)$counted;
                    }
                }
                fclose($handle);

                if (empty($raw_rows)) {
                    $parse_error = "No rows with counted quantities found. Make sure to fill out the 'counted_qty' column.";
                } else {
                    // Fetch expected quantities for those item IDs
                    $ids = implode(',', array_keys($raw_rows));
                    $sql = "
                        SELECT i.item_id, i.item_code, i.name, i.unit_of_measure,
                               COALESCE(SUM(b.quantity), 0) AS expected_qty
                        FROM items i
                        LEFT JOIN item_batches b ON i.item_id = b.item_id AND b.quantity > 0
                        WHERE i.item_id IN ($ids)
                        GROUP BY i.item_id
                    ";
                    $res = $conn->query($sql);
                    while ($row = $res->fetch_assoc()) {
                        $iid      = (int)$row['item_id'];
                        $counted  = $raw_rows[$iid];
                        $expected = (int)$row['expected_qty'];
                        $variance = $counted - $expected;
                        if ($variance !== 0) {
                            $variances[] = [
                                'item_id'      => $iid,
                                'item_code'    => $row['item_code'],
                                'name'         => $row['name'],
                                'unit'         => $row['unit_of_measure'],
                                'expected_qty' => $expected,
                                'counted_qty'  => $counted,
                                'variance'     => $variance,
                                'type'         => $variance > 0 ? 'Increase' : 'Decrease',
                            ];
                        }
                    }
                    // Store in session for the apply step
                    $_SESSION['sc_variances'] = $variances;
                    if (empty($variances)) {
                        $_SESSION['message'] = "No variances found — counted quantities match system stock for all submitted items. No adjustments needed.";
                        header("Location: stock_count_import.php");
                        exit();
                    }
                }
            }
        }
    }
}

// Load variances from session if returning after upload
if (empty($variances) && !empty($_SESSION['sc_variances']) && empty($parse_error)) {
    $variances = $_SESSION['sc_variances'];
}

$conn->close();
?>

<div class="p-6 max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Import Count Results</h1>
            <p class="text-gray-500 text-sm mt-1">Upload your completed count CSV to review and apply stock adjustments.</p>
        </div>
        <a href="stock_count.php" class="text-blue-600 hover:underline text-sm font-semibold">&larr; Back to Count Sheet</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if ($parse_error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($parse_error); ?></div>
    <?php endif; ?>

    <!-- Upload Form -->
    <?php if (empty($variances)): ?>
    <div class="bg-white rounded-lg shadow-md p-8 max-w-lg">
        <h2 class="text-xl font-bold text-gray-700 mb-4">Upload Completed Count CSV</h2>
        <p class="text-sm text-gray-500 mb-4">Download the template from the <a href="stock_count.php?export=csv" class="text-blue-600 hover:underline">Count Sheet page</a>, fill in the <strong>counted_qty</strong> column, then upload it here.</p>
        <form action="stock_count_import.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-2">Select CSV File:</label>
                <input type="file" name="count_csv" accept=".csv,text/csv" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">Upload &amp; Preview</button>
        </form>
    </div>
    <?php else: ?>

    <!-- Variance Preview + Apply -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-4">
        <div class="p-4 bg-yellow-50 border-b border-yellow-200 flex justify-between items-center">
            <div>
                <p class="font-bold text-yellow-800">&#9888; <?php echo count($variances); ?> variance(s) detected</p>
                <p class="text-sm text-yellow-700">Review the adjustments below before applying.</p>
            </div>
            <div class="flex gap-2">
                <a href="stock_count_import.php?clear=1" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg text-sm">Start Over</a>
            </div>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-2 px-4 text-left">Item Code</th>
                    <th class="py-2 px-4 text-left">Item Name</th>
                    <th class="py-2 px-4 text-right">Expected Qty</th>
                    <th class="py-2 px-4 text-right">Counted Qty</th>
                    <th class="py-2 px-4 text-right">Variance</th>
                    <th class="py-2 px-4 text-center">Adjustment Type</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                <?php foreach ($variances as $v): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-2 px-4 font-mono"><?php echo htmlspecialchars($v['item_code']); ?></td>
                    <td class="py-2 px-4"><?php echo htmlspecialchars($v['name']); ?></td>
                    <td class="py-2 px-4 text-right"><?php echo number_format($v['expected_qty']); ?></td>
                    <td class="py-2 px-4 text-right font-semibold"><?php echo number_format($v['counted_qty']); ?></td>
                    <td class="py-2 px-4 text-right font-bold <?php echo $v['variance'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo ($v['variance'] > 0 ? '+' : '') . number_format($v['variance']); ?> <?php echo htmlspecialchars($v['unit']); ?>
                    </td>
                    <td class="py-2 px-4 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-bold <?php echo $v['type'] === 'Increase' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                            <?php echo $v['type']; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form action="stock_count_import_handler.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <div class="mb-3">
            <label class="block text-sm font-bold text-gray-700 mb-1">Reason for Adjustments (required):</label>
            <input type="text" name="reason" value="Physical inventory count - <?php echo date('Y-m-d'); ?>" required
                class="border border-gray-300 rounded-lg px-3 py-2 w-full max-w-lg focus:outline-none focus:border-blue-400 text-sm">
        </div>
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg"
            onclick="return confirm('Apply <?php echo count($variances); ?> stock adjustment(s)? This action cannot be undone.');">
            Apply All Adjustments
        </button>
        <a href="stock_count_import.php?clear=1" class="ml-3 text-gray-500 hover:text-gray-700 text-sm font-semibold">Cancel</a>
    </form>
    <?php endif; ?>
</div>

<?php
// Handle clear
if (isset($_GET['clear'])) {
    unset($_SESSION['sc_variances']);
    header("Location: stock_count_import.php");
    exit();
}
require_once 'footer.php';
?>
