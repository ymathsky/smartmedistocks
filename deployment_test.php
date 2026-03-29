<?php
// Deployment verification file - safe to delete after testing
$checks = [];

// 1. DB connection
require_once 'db_connection.php';
$checks['Database Connection'] = isset($conn) && !$conn->connect_error ? 'PASS' : 'FAIL: ' . ($conn->connect_error ?? 'Unknown');

// 2. Warehouse overview feature in admin_dashboard.php
$admin_dashboard = file_get_contents(__DIR__ . '/admin_dashboard.php');
$checks['Warehouse Overview (admin_dashboard.php)'] = strpos($admin_dashboard, 'Warehouse Overview') !== false ? 'PASS' : 'FAIL: Section not found';

// 3. AI asterisk fix
$ai_handler = file_get_contents(__DIR__ . '/ai_assistant_handler.php');
$checks['AI No-Asterisk Fix'] = strpos($ai_handler, "str_replace('*', ''") !== false ? 'PASS' : 'FAIL: Fix not found';

// 4. ROP fix in get_order_suggestions.php
$rop_file = file_get_contents(__DIR__ . '/get_order_suggestions.php');
$checks['ROP Threshold Fix (>= 1)'] = strpos($rop_file, 'transaction_days_90 >= 1') !== false ? 'PASS' : 'FAIL: Fix not found';

// 5. .cpanel.yml exists
$checks['.cpanel.yml Exists'] = file_exists(__DIR__ . '/.cpanel.yml') ? 'PASS' : 'FAIL: File missing';

// 6. .gitignore excludes db_connection.php
$gitignore = file_get_contents(__DIR__ . '/.gitignore');
$checks['db_connection.php in .gitignore'] = strpos($gitignore, 'db_connection.php') !== false ? 'PASS' : 'FAIL: Not ignored';

// Output
$all_pass = true;
foreach ($checks as $v) {
    if (substr($v, 0, 4) !== 'PASS') { $all_pass = false; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deployment Test</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-10">
    <div class="max-w-xl mx-auto bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold mb-2">Deployment Verification</h1>
        <p class="text-gray-500 text-sm mb-6">Run date: <?php echo date('M j, Y H:i:s'); ?></p>

        <div class="space-y-3">
            <?php foreach ($checks as $label => $result): ?>
                    <div class="flex items-center justify-between p-3 rounded-lg <?php echo substr($result, 0, 4) === 'PASS' ? 'bg-green-50' : 'bg-red-50'; ?>">
                    <span class="font-medium text-gray-700"><?php echo htmlspecialchars($label); ?></span>
                    <span class="font-bold <?php echo substr($result, 0, 4) === 'PASS' ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo htmlspecialchars($result); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 p-4 rounded-lg text-center font-bold text-lg <?php echo $all_pass ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo $all_pass ? 'All checks passed. Deployment successful!' : 'Some checks failed. Review above.'; ?>
        </div>

        <p class="mt-4 text-xs text-gray-400 text-center">Delete this file after testing.</p>
    </div>
</body>
</html>
