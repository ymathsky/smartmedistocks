<?php
// Filename: smart/edit_transaction.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

$transaction_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$transaction_id) {
    $_SESSION['error'] = "Invalid transaction ID.";
    header("Location: transaction_history.php");
    exit();
}

// Fetch transaction data
$stmt = $conn->prepare("SELECT t.item_id, t.quantity_used, t.transaction_date, i.name as item_name 
                        FROM transactions t 
                        JOIN items i ON t.item_id = i.item_id 
                        WHERE t.transaction_id = ?");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['error'] = "Transaction not found.";
    header("Location: transaction_history.php");
    exit();
}
$transaction = $result->fetch_assoc();
$stmt->close();
?>

<div class="p-6">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg mx-auto">
        <h1 class="text-3xl font-bold mb-2 text-gray-800">Edit Transaction</h1>
        <p class="text-sm text-gray-500 mb-6">Only correct genuine data-entry mistakes. All edits are audit-logged.</p>

        <form action="edit_transaction_handler.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="transaction_id" value="<?php echo $transaction_id; ?>">

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Item:</label>
                <p class="bg-gray-200 p-2 rounded"><?php echo htmlspecialchars($transaction['item_name']); ?></p>
            </div>

            <div class="mb-4">
                <label for="quantity_used" class="block text-gray-700 text-sm font-bold mb-2">Quantity Used:</label>
                <input type="number" id="quantity_used" name="quantity_used" min="1" value="<?php echo htmlspecialchars($transaction['quantity_used']); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="mb-6">
                <label for="transaction_date" class="block text-gray-700 text-sm font-bold mb-2">Date of Usage:</label>
                <input type="date" id="transaction_date" name="transaction_date" value="<?php echo htmlspecialchars($transaction['transaction_date']); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="mb-6">
                <label for="correction_reason" class="block text-gray-700 text-sm font-bold mb-2">
                    Reason for Correction <span class="text-red-500">*</span>
                </label>
                <textarea id="correction_reason" name="correction_reason" rows="3" required
                    placeholder="e.g. Typo — entered 1000 instead of 100; confirmed with dispensing logbook."
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                <p class="text-xs text-gray-400 mt-1">This explanation will be saved to the audit log.</p>
            </div>

            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Save Changes
                </button>
                <a href="transaction_history.php" class="inline-block align-baseline font-bold text-sm text-blue-600 hover:text-blue-800">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php
$conn->close();
require_once 'footer.php';
?>
