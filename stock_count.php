<?php
// Filename: stock_count.php
// Generates a physical inventory count sheet (printable + CSV export).
require_once 'header.php';
require_once 'db_connection.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// CSV export mode — streams file directly
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sql = "
        SELECT i.item_id, i.item_code, i.name, i.category, i.unit_of_measure,
               COALESCE(SUM(b.quantity), 0) AS expected_qty
        FROM items i
        LEFT JOIN item_batches b ON i.item_id = b.item_id AND b.quantity > 0
        GROUP BY i.item_id
        ORDER BY i.category, i.name
    ";
    $result = $conn->query($sql);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stock_count_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['item_id', 'item_code', 'item_name', 'category', 'unit_of_measure', 'expected_qty', 'counted_qty']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [
            $row['item_id'],
            $row['item_code'],
            $row['name'],
            $row['category'],
            $row['unit_of_measure'],
            $row['expected_qty'],
            '' // blank for physical count
        ]);
    }
    fclose($out);
    $conn->close();
    exit();
}

// Normal page load — fetch items for count sheet display
$sql = "
    SELECT i.item_id, i.item_code, i.name, i.category, i.unit_of_measure,
           COALESCE(SUM(b.quantity), 0) AS expected_qty
    FROM items i
    LEFT JOIN item_batches b ON i.item_id = b.item_id AND b.quantity > 0
    GROUP BY i.item_id
    ORDER BY i.category, i.name
";
$result = $conn->query($sql);
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$conn->close();
?>

<div class="p-6">
    <div class="flex flex-wrap justify-between items-center mb-6 gap-3">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Physical Inventory Count</h1>
            <p class="text-gray-500 text-sm mt-1">Print this sheet or export as CSV, fill in the counted quantities, then import the results.</p>
        </div>
        <div class="flex gap-2 no-print">
            <a href="stock_count.php?export=csv" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow">
                &#8595; Export CSV Template
            </a>
            <a href="stock_count_import.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow">
                &#8593; Import Count Results
            </a>
            <button onclick="window.print()" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-lg shadow">
                &#128438; Print Count Sheet
            </button>
        </div>
    </div>

    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 11px; }
            .sidebar { display: none !important; }
        }
        @page { size: A4 landscape; margin: 1.5cm; }
    </style>

    <div class="bg-white rounded-lg shadow-lg overflow-x-auto">
        <div class="p-4 bg-gray-50 border-b flex justify-between items-center no-print">
            <span class="text-sm text-gray-500 font-semibold">Count Sheet — <?php echo date('F j, Y'); ?> &nbsp;|&nbsp; <?php echo count($items); ?> items</span>
            <input type="text" id="filterInput" placeholder="Filter items..." onkeyup="filterTable()" class="border border-gray-300 rounded px-3 py-1 text-sm focus:outline-none focus:border-blue-400">
        </div>
        <table class="min-w-full text-sm" id="countTable">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-2 px-3 text-left">Item Code</th>
                    <th class="py-2 px-3 text-left">Item Name</th>
                    <th class="py-2 px-3 text-left">Category</th>
                    <th class="py-2 px-3 text-left">UoM</th>
                    <th class="py-2 px-3 text-right">Expected Qty</th>
                    <th class="py-2 px-3 text-right" style="min-width:120px;">Counted Qty</th>
                    <th class="py-2 px-3 text-right">Variance</th>
                    <th class="py-2 px-3 text-left">Notes</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                <?php foreach ($items as $item): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-2 px-3 font-mono"><?php echo htmlspecialchars($item['item_code'] ?? 'N/A'); ?></td>
                    <td class="py-2 px-3"><?php echo htmlspecialchars($item['name']); ?></td>
                    <td class="py-2 px-3"><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></td>
                    <td class="py-2 px-3"><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                    <td class="py-2 px-3 text-right font-bold"><?php echo number_format($item['expected_qty']); ?></td>
                    <td class="py-2 px-3 text-right"><span class="no-print text-gray-300 italic">__________</span></td>
                    <td class="py-2 px-3 text-right"><span class="no-print text-gray-300 italic">__________</span></td>
                    <td class="py-2 px-3"><span class="no-print text-gray-300 italic">___________________</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterTable() {
    const input = document.getElementById('filterInput').value.toLowerCase();
    const rows = document.querySelectorAll('#countTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
    });
}
</script>

<?php require_once 'footer.php'; ?>
