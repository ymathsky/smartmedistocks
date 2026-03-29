<?php
// Filename: smart/location_management.php
session_start(); // Start session immediately

// The POST handling logic MUST be here, before ANY output.
require_once 'db_connection.php';

// Security check: Only Admins and Warehouse staff can manage locations
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Handler for adding/editing/deactivating locations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $location_id = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active = (isset($_POST['is_active']) && $_POST['is_active'] == '1') ? 1 : 0;
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Basic CSRF check
    if ($csrf_token !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security error: Invalid request token.";
        header("Location: location_management.php");
        exit();
    }

    try {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO locations (name, description, is_active) VALUES (?, ?, 1)");
            $stmt->bind_param("ss", $name, $description);
            if (!$stmt->execute()) {
                if ($conn->errno == 1062) throw new Exception("Location name '$name' already exists.");
                throw new Exception("Error adding location: " . $stmt->error);
            }
            $_SESSION['message'] = "Location '$name' added successfully.";

        } elseif ($action === 'edit' && $location_id) {
            $stmt = $conn->prepare("UPDATE locations SET name = ?, description = ?, is_active = ? WHERE location_id = ?");
            $stmt->bind_param("ssii", $name, $description, $is_active, $location_id);

            if (!$stmt->execute()) {
                if ($conn->errno == 1062) throw new Exception("Location name '$name' already exists.");
                throw new Exception("Error updating location: " . $stmt->error);
            }

            // Added check for affected rows for better user feedback
            if ($stmt->affected_rows > 0) {
                $_SESSION['message'] = "Location updated successfully.";
            } else {
                $_SESSION['message'] = "Location details saved, but no changes were detected.";
            }

        } elseif ($action === 'toggle_active' && $location_id) {
            // Refactored to use prepared statement for consistency and safety
            $status_stmt = $conn->prepare("SELECT is_active FROM locations WHERE location_id = ?");
            $status_stmt->bind_param("i", $location_id);
            $status_stmt->execute();
            $current_status = $status_stmt->get_result()->fetch_assoc()['is_active'];
            $status_stmt->close();

            $new_status = $current_status ? 0 : 1;
            $status_word = $new_status ? 'activated' : 'deactivated';

            $stmt = $conn->prepare("UPDATE locations SET is_active = ? WHERE location_id = ?");
            $stmt->bind_param("ii", $new_status, $location_id);
            if (!$stmt->execute()) throw new Exception("Error toggling status: " . $stmt->error);
            $_SESSION['message'] = "Location successfully $status_word.";
        }

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    // Perform redirect immediately after setting the session message/error
    $conn->close();
    header("Location: location_management.php");
    exit();
}

// --- END OF POST HANDLING ---

// Start HTML output by including the header
require_once 'header.php';

// Fetch all locations (Only runs if not a POST request or after successful redirect)
$locations = [];
$sql = "SELECT location_id, name, description, is_active FROM locations ORDER BY name ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
}
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Inventory Location Management</h1>

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- 1. Add New Location Form -->
        <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Add New Location</h2>
            <form action="location_management.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div>
                    <label for="new_name" class="block text-sm font-medium text-gray-700">Location Name (e.g., Aisle 1, Bin 3)</label>
                    <input type="text" id="new_name" name="name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" required>
                </div>
                <div>
                    <label for="new_description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="new_description" name="description" rows="2" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"></textarea>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
                    Add Location
                </button>
            </form>
        </div>

        <!-- 2. Locations Table -->
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Existing Locations</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border" id="locationTable">
                    <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Name</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm text-left hidden sm:table-cell">Description</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm text-center">Status</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="text-gray-700">
                    <?php foreach ($locations as $loc): ?>
                        <tr class="border-b hover:bg-gray-100">
                            <td class="py-3 px-4 font-semibold"><?php echo htmlspecialchars($loc['name']); ?></td>
                            <td class="py-3 px-4 text-sm hidden sm:table-cell"><?php echo htmlspecialchars($loc['description']); ?></td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    <?php echo $loc['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $loc['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($loc)); ?>)"
                                        class="text-blue-600 hover:text-blue-800 font-semibold mr-3">Edit</button>
                                <form action="location_management.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to change the status? Stock may still be assigned here.');">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="location_id" value="<?php echo $loc['location_id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800 font-semibold">
                                        <?php echo $loc['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Location Modal (Hidden by Default) -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-xl font-bold mb-4">Edit Location</h3>
        <form action="location_management.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="location_id" id="edit_location_id">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div>
                <label for="edit_name" class="block text-sm font-medium text-gray-700">Location Name</label>
                <input type="text" id="edit_name" name="name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" required>
            </div>
            <div>
                <label for="edit_description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea id="edit_description" name="description" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"></textarea>
            </div>
            <div class="flex items-center">
                <input type="checkbox" id="edit_is_active" name="is_active" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                <label for="edit_is_active" class="ml-2 block text-sm text-gray-900">Is Active?</label>
            </div>

            <div class="flex justify-end space-x-4 pt-4">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const editModal = document.getElementById('editModal');
    const editLocationId = document.getElementById('edit_location_id');
    const editName = document.getElementById('edit_name');
    const editDescription = document.getElementById('edit_description');
    const editIsActive = document.getElementById('edit_is_active');

    function openEditModal(locationData) {
        editLocationId.value = locationData.location_id;
        editName.value = locationData.name;
        editDescription.value = locationData.description;
        editIsActive.checked = (locationData.is_active == 1);
        editModal.classList.remove('hidden');
    }

    function closeEditModal() {
        editModal.classList.add('hidden');
    }

    editModal.addEventListener('click', function(event) {
        if (event.target === editModal) { closeEditModal(); }
    });
</script>

<?php
// The database connection is closed here if it was opened outside the POST handler,
// and if it was not closed inside the successful POST handler logic.
// However, since the POST handler redirects and exits, the connection would be closed there.

// We must explicitly ensure $conn is closed only if it's open, but in a multi-file
// structure like this, the responsibility is usually managed by the overall page structure (footer.php).
// To prevent issues, we remove $conn->close() here as it is intended to be called in footer.php.
// If the POST handler redirects and exits, this part is never reached anyway.
?>

<?php
// Since the header file is included above, we only need the footer here.
require_once 'footer.php';
?>
