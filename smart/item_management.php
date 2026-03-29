<?php
// Filename: item_management.php

require_once 'header.php';
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Pharmacist')) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

$sql = "SELECT i.*, s.name as supplier_name FROM items i LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id ORDER BY i.item_code ASC";
$result = $conn->query($sql);
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Item Master Management</h1>
        <a href="add_item.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            Add New Item
        </a>
    </div>

    <!-- User Feedback -->
    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert"><p>' . htmlspecialchars($_SESSION['message']) . '</p></div>';
        unset($_SESSION['message']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert"><p>' . htmlspecialchars($_SESSION['error']) . '</p></div>';
        unset($_SESSION['error']);
    }
    ?>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Code</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Name</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Category</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Brand</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Unit Cost (₱)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">UoM</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-center">Actions</th>
                </tr>
                </thead>
                <tbody class="text-gray-700">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="border-b hover:bg-gray-100">
                            <td class="py-3 px-4 font-mono"><?php echo htmlspecialchars($row['item_code'] ?? 'N/A'); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['category'] ?? 'N/A'); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['brand_name'] ?? 'N/A'); ?></td>
                            <td class="py-3 px-4"><?php echo number_format($row['unit_cost'], 2); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['unit_of_measure']); ?></td>
                            <td class="py-3 px-4 text-center">
                                <!-- This link now correctly points to the edit page -->
                                <a href="edit_item.php?id=<?php echo $row['item_id']; ?>" class="text-blue-500 hover:text-blue-700 font-semibold mr-4">Edit</a>

                                <!-- This form now correctly points to the delete handler -->
                                <form action="delete_item_handler.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this item? This action cannot be undone.');">
                                    <input type="hidden" name="item_id" value="<?php echo $row['item_id']; ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700 font-semibold">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-gray-500">No items found.</td>
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

