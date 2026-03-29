<?php
// Filename: demo_data_reset.php
// Purpose: Admin-only tool to wipe and regenerate realistic synthetic transaction
//          data for demo/simulation purposes. Generates smooth usage patterns so
//          the demand forecast produces a low, meaningful MAPE.

session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    $_SESSION['error'] = "Only Admins can access the Demo Data Manager.";
    header("Location: index.php");
    exit();
}

// --- CSRF token bootstrap (header.php not included here, so we manage it manually)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error   = '';

// -----------------------------------------------------------------------
// POST: Generate demo data
// -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security error: invalid token.";
    } else {

        $action = $_POST['action'];

        if ($action === 'generate') {
            $days       = max(30, min(180, (int)($_POST['days'] ?? 90)));
            $items_raw  = $_POST['items'] ?? [];
            $item_ids   = array_filter(array_map('intval', $items_raw));

            if (empty($item_ids)) {
                $error = "Please select at least one item.";
            } else {
                $conn->begin_transaction();
                try {
                    // Delete existing transactions for selected items only
                    $id_list = implode(',', $item_ids);
                    $conn->query("DELETE FROM transactions WHERE item_id IN ($id_list)");

                    // Also ensure we have enough synthetic stock in item_batches
                    // Insert one large batch per item so stock never goes negative during generation
                    foreach ($item_ids as $iid) {
                        $conn->query("DELETE FROM item_batches WHERE item_id = $iid AND purchase_order_id = 'DEMO-SEED'");
                        $conn->query("INSERT INTO item_batches (item_id, quantity, received_date, purchase_order_id) VALUES ($iid, 999999, DATE_SUB(CURDATE(), INTERVAL " . ($days + 5) . " DAY), 'DEMO-SEED')");
                    }

                    // Fetch item names for the log
                    $item_names = [];
                    $res = $conn->query("SELECT item_id, name FROM items WHERE item_id IN ($id_list)");
                    while ($r = $res->fetch_assoc()) {
                        $item_names[$r['item_id']] = $r['name'];
                    }

                    // --- Generate synthetic usage rows ---
                    // Each item gets a random base daily usage (2–25 units).
                    // Each day we add ±20 % Gaussian-ish noise using an approximation.
                    // Some days are skipped randomly (items aren't used every single day).
                    $stmt_ins = $conn->prepare(
                        "INSERT INTO transactions (item_id, quantity_used, transaction_date) VALUES (?, ?, ?)"
                    );
                    $total_rows = 0;

                    foreach ($item_ids as $iid) {
                        // Seed the base usage for this item (consistent across days)
                        $base = mt_rand(3, 20);
                        $skip_chance = mt_rand(0, 30); // 0-30 % chance to skip any given day

                        for ($d = $days; $d >= 1; $d--) {
                            // Randomly skip some days to simulate non-daily usage
                            if (mt_rand(1, 100) <= $skip_chance) continue;

                            // ±20% noise: sum of 3 uniform randoms approximates Gaussian
                            $noise = (mt_rand(0, 100) + mt_rand(0, 100) + mt_rand(0, 100)) / 150.0 - 1.0; // range ~-1..1
                            $qty = (int)round($base * (1 + 0.20 * $noise));
                            $qty = max(1, $qty);

                            $date = date('Y-m-d', strtotime("-{$d} days"));
                            $stmt_ins->bind_param("iis", $iid, $qty, $date);
                            $stmt_ins->execute();
                            $total_rows++;
                        }
                    }
                    $stmt_ins->close();

                    // Decision log entry
                    $log_stmt = $conn->prepare(
                        "INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, ?, ?)"
                    );
                    $action_type = "Demo Data Reset";
                    $names_str   = implode(', ', array_intersect_key($item_names, array_flip($item_ids)));
                    $details     = "Generated $total_rows synthetic transaction rows over $days days for: $names_str. Previous transactions for these items were deleted.";
                    $log_stmt->bind_param("isss", $_SESSION['user_id'], $_SESSION['username'], $action_type, $details);
                    $log_stmt->execute();
                    $log_stmt->close();

                    $conn->commit();
                    $message = "✓ Generated <strong>$total_rows</strong> synthetic transactions over <strong>$days days</strong> for <strong>" . count($item_ids) . "</strong> item(s). Previous transactions for those items have been removed.";

                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error: " . $e->getMessage();
                }
            }

        } elseif ($action === 'clear_all') {
            // Nuclear option — wipe ALL transactions + DEMO-SEED batches
            $conn->begin_transaction();
            try {
                $conn->query("DELETE FROM transactions");
                $conn->query("DELETE FROM item_batches WHERE purchase_order_id = 'DEMO-SEED'");

                $log_stmt = $conn->prepare(
                    "INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, ?, ?)"
                );
                $action_type = "Demo Data Reset";
                $details     = "ALL transactions cleared by " . $_SESSION['username'] . " via Demo Data Manager.";
                $log_stmt->bind_param("isss", $_SESSION['user_id'], $_SESSION['username'], $action_type, $details);
                $log_stmt->execute();
                $log_stmt->close();

                $conn->commit();
                $message = "✓ All transaction records have been deleted.";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------------------
// Fetch items + current transaction counts for the UI
// -----------------------------------------------------------------------
$items_res = $conn->query("
    SELECT i.item_id, i.name, i.item_code,
           COUNT(t.transaction_id) AS tx_count
    FROM items i
    LEFT JOIN transactions t ON i.item_id = t.item_id
    GROUP BY i.item_id, i.name, i.item_code
    ORDER BY i.name ASC
");
$items_list = [];
$total_tx   = 0;
while ($r = $items_res->fetch_assoc()) {
    $items_list[] = $r;
    $total_tx += (int)$r['tx_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Data Manager — SmartMediStocks</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">

<div class="max-w-4xl mx-auto py-10 px-4">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Demo Data Manager</h1>
            <p class="text-sm text-gray-500 mt-1">Generate realistic synthetic transaction data for forecast simulation.</p>
        </div>
        <a href="admin_dashboard.php" class="text-sm text-blue-600 hover:underline">&larr; Dashboard</a>
    </div>

    <!-- Alerts -->
    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-300 text-green-900 rounded-lg p-4 mb-6 text-sm">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-300 text-red-900 rounded-lg p-4 mb-6 text-sm">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Info banner -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
        <p class="font-bold mb-1">How this works</p>
        <p>For each selected item, the generator creates daily usage records with a random base quantity and
           <strong>±20% natural variation</strong>. The data follows a stable pattern — ideal for the demand
           forecast to learn from and produce a <strong>low MAPE</strong>.</p>
        <p class="mt-1.5 text-blue-600">No real supply or patient data is affected. All actions are logged.</p>
    </div>

    <!-- Generate Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="font-semibold text-gray-700 mb-4">Generate Synthetic Usage Data</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="generate">

            <!-- Days range -->
            <div class="mb-5 flex flex-wrap items-center gap-4">
                <label class="text-sm font-medium text-gray-700 whitespace-nowrap">History length:</label>
                <div class="flex gap-2 flex-wrap">
                    <?php foreach ([30 => '30 days', 60 => '60 days', 90 => '90 days (recommended)', 120 => '120 days', 180 => '6 months'] as $v => $lbl): ?>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="days" value="<?php echo $v; ?>" <?php echo $v === 90 ? 'checked' : ''; ?> class="text-blue-600">
                            <span class="text-sm text-gray-700"><?php echo $lbl; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Item selector -->
            <div class="mb-5">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700">Select items to (re)generate:</label>
                    <div class="flex gap-2 text-xs">
                        <button type="button" onclick="toggleAll(true)" class="text-blue-600 hover:underline">Select all</button>
                        <span class="text-gray-300">|</span>
                        <button type="button" onclick="toggleAll(false)" class="text-blue-600 hover:underline">Deselect all</button>
                    </div>
                </div>
                <div class="border border-gray-200 rounded-lg overflow-y-auto max-h-64 divide-y divide-gray-100">
                    <?php foreach ($items_list as $item): ?>
                        <label class="flex items-center justify-between px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" name="items[]" value="<?php echo (int)$item['item_id']; ?>"
                                       class="item-checkbox w-4 h-4 text-blue-600 rounded">
                                <div>
                                    <span class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <span class="text-xs text-gray-400 ml-1"><?php echo htmlspecialchars($item['item_code']); ?></span>
                                </div>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full <?php echo $item['tx_count'] > 0 ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-400'; ?>">
                                <?php echo (int)$item['tx_count']; ?> transactions
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg shadow text-sm transition">
                ⚡ Generate Demo Transactions
            </button>
        </form>
    </div>

    <!-- Clear All -->
    <div class="bg-white rounded-xl shadow-sm border border-red-200 p-6">
        <h2 class="font-semibold text-red-700 mb-1">Clear All Transactions</h2>
        <p class="text-sm text-gray-500 mb-4">Permanently deletes every transaction record in the database. Use before importing or starting fresh.</p>
        <form method="POST" onsubmit="return confirm('Delete ALL transactions? This cannot be undone.');">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="clear_all">
            <button type="submit"
                class="bg-red-600 hover:bg-red-700 text-white font-semibold px-5 py-2 rounded-lg text-sm transition">
                🗑 Delete All <?php echo number_format($total_tx); ?> Transactions
            </button>
        </form>
    </div>

</div>

<script>
function toggleAll(state) {
    document.querySelectorAll('.item-checkbox').forEach(function(cb) { cb.checked = state; });
}
</script>
</body>
</html>
<?php $conn->close(); ?>
