<?php
// Filename: supplier_management.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Ensure an Admin is logged in.
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch all suppliers from the database
$sql = "SELECT supplier_id, name, contact_info, average_lead_time_days, created_at FROM suppliers ORDER BY name ASC";
$result = $conn->query($sql);
?>

<!-- Main Content -->
<div class="flex-1 p-6 bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-md w-full">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Supplier Management</h1>
            <a href="add_supplier.php" class="bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg hover:bg-blue-700">
                + Add New Supplier
            </a>
        </div>
        <p class="mb-6 text-gray-600">This table displays all registered suppliers. You can add, edit, or remove suppliers from the system.</p>

        <?php
        // Display success or error messages
        if (isset($_SESSION['message'])) {
            echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">' . $_SESSION['message'] . '</div>';
            unset($_SESSION['message']);
        }
        if (isset($_SESSION['error'])) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">' . $_SESSION['error'] . '</div>';
            unset($_SESSION['error']);
        }
        ?>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border">
                <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Supplier Name</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Contact Info</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-center">Avg. Lead Time (Days)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Date Added</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-center">Actions</th>
                </tr>
                </thead>
                <tbody class="text-gray-700">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-100">
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['contact_info']); ?></td>
                            <td class="py-3 px-4 text-center"><?php echo htmlspecialchars($row['average_lead_time_days']); ?></td>
                            <td class="py-3 px-4"><?php echo date("F j, Y", strtotime($row['created_at'])); ?></td>
                            <td class="py-3 px-4 text-center">
                                <a href="edit_supplier.php?id=<?php echo $row['supplier_id']; ?>" class="text-blue-600 hover:underline">Edit</a>
                                <form action="delete_supplier_handler.php" method="POST" class="inline-block ml-4" onsubmit="return confirm('Are you sure you want to delete this supplier? This cannot be undone.');">
                                    <input type="hidden" name="supplier_id" value="<?php echo $row['supplier_id']; ?>">
                                    <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">No suppliers found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'footer.php';
?>
