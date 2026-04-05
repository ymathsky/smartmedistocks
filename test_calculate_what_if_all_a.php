<?php
// Simple deployment test page for calculate_what_if_all_a.php
// Use this page while logged in as an authorized user to verify the handler response.

ob_start();
include 'calculate_what_if_all_a.php';
$response = ob_get_clean();

header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>What-If API Test</title>
    <style>
        body { font-family: Inter, system-ui, sans-serif; background:#f4f7fb; color:#111827; margin:0; padding:30px; }
        .card { max-width:960px; margin:0 auto; background:#fff; border:1px solid #e5e7eb; border-radius:18px; box-shadow:0 15px 50px rgba(15,23,42,.08); padding:28px; }
        pre { white-space: pre-wrap; word-break: break-word; font-size:0.95rem; line-height:1.5; background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:18px; overflow:auto; }
        .badge { display:inline-flex; align-items:center; gap:0.5rem; background:#eef2ff; color:#3730a3; border-radius:999px; padding:0.5rem 0.85rem; font-weight:600; font-size:0.95rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">What-If API Deployment Test</div>
        <h1 style="margin-top:20px;">calculate_what_if_all_a.php response</h1>
        <p>This page includes the handler directly and prints the raw output so you can confirm whether the deployed file is returning valid JSON or an error.</p>
        <p><strong>Note:</strong> If you are not logged in as Admin/Pharmacist/Procurement/Warehouse, the handler will return an Unauthorized error.</p>
        <h2 style="margin-top:24px;">Raw Output</h2>
        <pre><?php echo htmlspecialchars($response); ?></pre>
    </div>
</body>
</html>
