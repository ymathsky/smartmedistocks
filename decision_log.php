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

// Fetch distinct users and action types for filter dropdowns
$users_result  = $conn->query("SELECT DISTINCT username FROM decision_log ORDER BY username ASC");
$actions_result = $conn->query("SELECT DISTINCT action_type FROM decision_log ORDER BY action_type ASC");

// Fetch all log entries
$result = $conn->query("SELECT log_id, username, action_type, details, created_at FROM decision_log ORDER BY created_at DESC");
?>

<!-- DataTables Buttons plugin for CSV Export -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">

<div class="p-6">
    <div class="bg-white p-8 rounded-lg shadow-md w-full">
        <div class="flex flex-wrap justify-between items-center mb-2 gap-3">
            <h1 class="text-3xl font-bold text-gray-800">System Decision Log</h1>
        </div>
        <p class="text-gray-600 mb-6">A chronological record of all significant actions for accountability and compliance auditing.</p>

        <!-- Filters Row -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6 p-4 bg-gray-50 rounded-lg border">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">From Date</label>
                <input type="date" id="filter_from" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">To Date</label>
                <input type="date" id="filter_to" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">User</label>
                <select id="filter_user" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm">
                    <option value="">All Users</option>
                    <?php if ($users_result): while ($u = $users_result->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($u['username']) ?>"><?= htmlspecialchars($u['username']) ?></option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Action Type</label>
                <select id="filter_action" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm">
                    <option value="">All Actions</option>
                    <?php if ($actions_result): while ($a = $actions_result->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($a['action_type']) ?>"><?= htmlspecialchars($a['action_type']) ?></option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
        </div>

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
                        <tr class="border-b hover:bg-gray-100"
                            data-date="<?= date('Y-m-d', strtotime($row['created_at'])) ?>"
                            data-user="<?= htmlspecialchars($row['username']) ?>"
                            data-action="<?= htmlspecialchars($row['action_type']) ?>">
                            <td class="py-3 px-4"><?php echo date("Y-m-d H:i", strtotime($row['created_at'])); ?></td>
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
                    <tr><td colspan="4" class="text-center py-4 text-gray-500">No log entries found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script>
$(document).ready(function() {
    // Custom DataTables search plug-in for date range
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'logTable') return true;
        const from   = document.getElementById('filter_from').value;
        const to     = document.getElementById('filter_to').value;
        const rowDate = $(settings.nTable).find('tbody tr').eq(dataIndex).data('date') || '';
        if (from && rowDate < from) return false;
        if (to   && rowDate > to)   return false;
        return true;
    });

    const table = $('#logTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'csvHtml5',
                text: '&#x2B07; Export CSV',
                title: 'Decision_Log_' + new Date().toISOString().slice(0,10),
                className: 'bg-green-600 hover:bg-green-700 text-white text-xs font-bold py-1.5 px-3 rounded mr-2',
                exportOptions: { columns: [0,1,2,3] }
            }
        ]
    });

    // Wire up filter controls
    ['filter_user', 'filter_action'].forEach(id => {
        document.getElementById(id).addEventListener('change', function() {
            const col = id === 'filter_user' ? 1 : 2;
            table.column(col).search(this.value).draw();
        });
    });

    ['filter_from', 'filter_to'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => table.draw());
    });
});
</script>
<?php
$conn->close();
require_once 'footer.php';
?>
