<?php
// Filename: decision_log.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Ensure an Admin or Warehouse user is logged in.
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch all log entries from the database, newest first
$sql = "SELECT log_id, username, action_type, details, created_at FROM decision_log ORDER BY created_at DESC";
$result = $conn->query($sql);
?>

<div class="p-6">
    <div class="bg-white p-8 rounded-lg shadow-md w-full">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">System Decision Log</h1>
        <p class="text-gray-600 mb-8">This log provides a chronological record of all significant actions taken by users within the system, ensuring accountability and traceability.</p>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border" id="logTable">
                <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Timestamp</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">User</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Action</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Details</th>
                </tr>
                </thead>
                <tbody class="text-gray-700">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="border-b hover:bg-gray-100">
                            <td class="py-3 px-4"><?php echo date("F j, Y, g:i a", strtotime($row['created_at'])); ?></td>
                            <td class="py-3 px-4 font-semibold"><?php echo htmlspecialchars($row['username']); ?></td>
                            <td class="py-3 px-4">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo htmlspecialchars($row['action_type']); ?>
                                    </span>
                            </td>
                            <td class="py-3 px-4 text-sm"><?php echo htmlspecialchars($row['details']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center py-4 text-gray-500">No log entries found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        $('#logTable').DataTable({
            "order": [[ 0, "desc" ]]
        });
    });
</script>
<?php
$conn->close();
require_once 'footer.php';
?>
