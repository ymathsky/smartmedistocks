<?php
// Filename: global_settings.php
require_once 'header.php';
require_once 'db_connection.php';

// Security: Ensure only Admins can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch current settings from the database
$settings = [];
$result = $conn->query("SELECT setting_name, setting_value FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
}

// Assign to variables with defaults to prevent errors if they don't exist
$serviceLevel = $settings['service_level'] ?? '95';
$holdingCostRate = $settings['holding_cost_rate'] ?? '25';
$orderingCost = $settings['ordering_cost'] ?? '50';
?>

<div class="bg-white p-8 rounded-lg shadow-md w-full max-w-2xl mx-auto">
    <h2 class="text-3xl font-bold text-gray-800 mb-6">Global Inventory Settings</h2>
    <p class="text-gray-600 mb-8">These values are used across the application for calculating inventory policies like EOQ and Safety Stock. Changes here will affect all future calculations.</p>

    <!-- Display Success/Error Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['error']; unset($_SESSION['error']);?></span>
        </div>
    <?php endif; ?>

    <form action="update_settings_handler.php" method="POST" class="space-y-6">
        <div>
            <label for="service_level" class="block text-sm font-medium text-gray-700">Target Service Level (%)</label>
            <input type="number" name="service_level" id="service_level" min="0" max="100" step="0.1" value="<?php echo htmlspecialchars($serviceLevel); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            <p class="mt-2 text-sm text-gray-500">The probability of not having a stockout. Higher values result in more safety stock.</p>
        </div>

        <div>
            <label for="holding_cost_rate" class="block text-sm font-medium text-gray-700">Annual Holding Cost Rate (%)</label>
            <input type="number" name="holding_cost_rate" id="holding_cost_rate" min="0" step="0.1" value="<?php echo htmlspecialchars($holdingCostRate); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            <p class="mt-2 text-sm text-gray-500">The cost of holding inventory for a year as a percentage of the item's value (includes storage, insurance, spoilage).</p>
        </div>

        <div>
            <label for="ordering_cost" class="block text-sm font-medium text-gray-700">Default Ordering Cost (₱)</label>
            <input type="number" name="ordering_cost" id="ordering_cost" min="0" step="0.01" value="<?php echo htmlspecialchars($orderingCost); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            <p class="mt-2 text-sm text-gray-500">The fixed administrative and logistical cost associated with placing a single order.</p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Save Settings
            </button>
        </div>
    </form>
</div>

<?php
require_once 'footer.php';
?>

