<?php
// Filename: smart/footer.php
// This file contains the closing HTML tags for the main content area,
// as well as the complete HTML and JavaScript for the AI chat widget,
// the notification system, and DataTables library includes.
?>
        </main><!-- closes <main> from header.php -->
        <!-- Application Footer -->
        <footer class="flex-shrink-0 text-center text-sm text-slate-400 py-3 border-t border-slate-200 bg-white">
    &copy; <?php echo date("Y"); ?> Smart Medi Stocks. All rights reserved. |
    <a href="terms.php" class="text-blue-600 hover:underline" target="_blank">Terms of Use</a> |
    <a href="privacy.php" class="text-blue-600 hover:underline" target="_blank">Privacy Policy</a>
        </footer>
</div><!-- closes <div class="md:ml-64 flex flex-col"> from header.php -->

<!-- AI Chat Widget -->
<div id="ai-chat-widget" class="fixed bottom-5 right-5 z-50">
    <!-- Chat Bubble Icon -->
    <div id="chat-bubble" class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center cursor-pointer shadow-lg hover:bg-blue-700 transition-transform transform hover:scale-110">
        <!-- robot.png as an inline SVG with an outline -->
        <img src="robot.png" alt="Robot Icon" class="h-9 w-9" style="filter: drop-shadow(0 1px 0 #fff) drop-shadow(1px 0 0 #fff) drop-shadow(-1px 0 0 #fff) drop-shadow(0 -1px 0 #fff);" />
    </div>

    <!-- Chat Window -->
    <div id="chat-window" class="hidden absolute bottom-20 right-0 w-96 sm:w-[450px] bg-white rounded-lg shadow-2xl border border-gray-200 flex flex-col" style="height: 600px;">
        <div class="bg-blue-600 text-white p-4 rounded-t-lg flex justify-between items-center">
            <h3 class="font-bold text-lg">AI Assistant</h3>
            <button id="close-chat" class="text-white">&times;</button>
        </div>
        <div id="chat-log" class="flex-1 p-4 overflow-y-auto bg-gray-50">
            <!-- Chat history is loaded here dynamically -->
        </div>
        <div class="p-4 border-t bg-white">
            <!-- Updated Chat Prompts Section with Toggle -->
            <div id="chat-prompts-toggle-container" class="mb-3">
                <div class="flex justify-center items-center mb-2">
                    <p class="text-xs text-gray-500 text-center mr-2">Or try one of these prompts:</p>
                    <button id="toggle-prompts-btn" class="text-xs text-blue-500 hover:text-blue-700 focus:outline-none">
                        <svg id="prompts-arrow-down" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                        <svg id="prompts-arrow-up" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                    </button>
                </div>
                <div id="prompt-buttons-container" class="flex flex-wrap gap-2 justify-center">
                    <button class="prompt-btn text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 py-1 px-2 rounded-full transition">What is the total stock value?</button>
                    <button class="prompt-btn text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 py-1 px-2 rounded-full transition">Show slow-moving items</button>
                    <button class="prompt-btn text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 py-1 px-2 rounded-full transition">Items below reorder point?</button>
                    <button class="prompt-btn text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 py-1 px-2 rounded-full transition">Any items expiring soon?</button>
                    <button class="prompt-btn text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 py-1 px-2 rounded-full transition">What is EOQ?</button>
                    <button class="prompt-btn text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 py-1 px-2 rounded-full transition">Predict stockout risks</button>
                </div>
            </div>
            <!-- End Updated Chat Prompts Section -->
            <div class="flex">
                <input type="text" id="chat-input" placeholder="Ask a question..." class="flex-1 border rounded-l-lg p-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button id="send-chat" class="bg-blue-600 text-white px-4 rounded-r-lg hover:bg-blue-700">Send</button>
            </div>
        </div>
    </div>
</div><!-- End AI Chat Widget -->

</div> <!-- This closes the flex-1 div from header.php -->
</div> <!-- This closes the flex h-screen div from header.php -->

<!-- JavaScript Includes -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.js"></script>
<!-- Chart.js (If needed on specific pages that include footer.php) -->
<!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->
<!-- <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.4.0/dist/chartjs-plugin-annotation.min.js"></script> -->

<!-- CENTRALIZED DATATABLES INIT SCRIPT & TRANSACTION HISTORY FILTER LOGIC -->
<script>
    jQuery(document).ready(function($) { // Use jQuery() and pass $
        const commonDataTablesOptions = {
            "pagingType": "full_numbers",
            "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
            "responsive": true // Optional: Add responsiveness
        };

        // Initialize common tables if they exist
        if ($('#itemTable').length && !$.fn.DataTable.isDataTable('#itemTable')) {
            $('#itemTable').DataTable({ ...commonDataTablesOptions, "order": [[ 0, "asc" ]] }); // Order by ID
        }
        if ($('#userTable').length && !$.fn.DataTable.isDataTable('#userTable')) {
            $('#userTable').DataTable(commonDataTablesOptions);
        }
        if ($('#supplierTable').length && !$.fn.DataTable.isDataTable('#supplierTable')) {
            $('#supplierTable').DataTable(commonDataTablesOptions);
        }
        // REMOVED transactionTable initialization from here
        if ($('#poTable').length && !$.fn.DataTable.isDataTable('#poTable')) {
            $('#poTable').DataTable({ ...commonDataTablesOptions, "order": [[ 5, "desc" ]] }); // Order by Expected Delivery Desc
        }
        if ($('#inventoryTable').length && !$.fn.DataTable.isDataTable('#inventoryTable')) {
            $('#inventoryTable').DataTable(commonDataTablesOptions);
        }
        if ($('#logTable').length && !$.fn.DataTable.isDataTable('#logTable')) {
            $('#logTable').DataTable({ ...commonDataTablesOptions, "order": [[ 0, "desc" ]] }); // Order by Timestamp Desc
        }
        if ($('#locationTable').length && !$.fn.DataTable.isDataTable('#locationTable')) {
            $('#locationTable').DataTable({
                ...commonDataTablesOptions,
                "columnDefs": [ { "orderable": false, "targets": 3 } ] // Disable sort on Actions
            });
        }
        if ($('#batchesTable').length && !$.fn.DataTable.isDataTable('#batchesTable')) {
            $('#batchesTable').DataTable(commonDataTablesOptions); // Default order likely fine
        }
        if ($('#itemSelectionTable').length && !$.fn.DataTable.isDataTable('#itemSelectionTable')) {
            $('#itemSelectionTable').DataTable({ ...commonDataTablesOptions, "order": [[ 0, "asc" ]] }); // Order by Name Asc
        }

        // NOTE: Tables initialized after AJAX calls (like abcTable, policy_table, performanceTable, suggestionTable)
        // should KEEP their initialization script within their respective PHP files' AJAX success handlers.

        // --- START: Transaction History Specific Initialization ---
        if ($('#transactionTable').length && !$.fn.DataTable.isDataTable('#transactionTable')) {
            let currentRange = 'all'; // Default range for transactions
            const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>'; // Pass CSRF token for delete forms

            const transactionTable = $('#transactionTable').DataTable({
                "processing": true, // Show processing indicator
                "serverSide": false, // Keep client-side for faster filtering with current setup
                "ajax": {
                    "url": "get_transaction_data.php",
                    "type": "GET",
                    "data": function ( d ) {
                        d.range = currentRange; // Send the current date range filter
                    }
                },
                "columns": [
                    { "data": "date" },
                    { "data": "item_code" },
                    { "data": "item_name" },
                    { "data": "quantity_used", "className": "text-right" },
                    {
                        "data": "transaction_type",
                        "render": function(data) {
                            if (data === 'Wastage Write-off') {
                                return '<span class="inline-block bg-red-100 text-red-700 text-xs font-semibold px-2 py-0.5 rounded-full">' + data + '</span>';
                            }
                            return '<span class="inline-block bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full">' + data + '</span>';
                        }
                    },
                    {
                        "data": "actions", // Placeholder column
                        "orderable": false,
                        "searchable": false,
                        "className": "text-center whitespace-nowrap"
                    }
                ],
                "order": [[ 0, "desc" ]], // Default order by date descending
                "pagingType": "full_numbers",
                "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
                "createdRow": function( row, data, dataIndex ) {
                    // Inject real action buttons/forms after row creation using AJAX data
                    let actionsCell = $('td', row).eq(5); // Get the 6th cell (index 5) — after type column
                    if (data.transaction_id) { // Check if transaction_id exists in the data
                        actionsCell.html(`
                            <a href="edit_transaction.php?id=${data.transaction_id}" class="text-indigo-600 hover:text-indigo-900 font-semibold mr-4">Edit</a>
                            <form action="delete_transaction_handler.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this transaction? This will add the used stock back to inventory and cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="${csrfToken}">
                                <input type="hidden" name="transaction_id" value="${data.transaction_id}">
                                <button type="submit" class="text-red-600 hover:text-red-900 font-semibold">Delete</button>
                            </form>
                        `);
                    } else {
                        actionsCell.html('<span class="text-gray-400">N/A</span>');
                    }
                }
            });

            // Handle filter button clicks for the transaction table specifically
            $('.filter-btn').on('click', function() {
                // Check if the clicked button is related to the transaction table page
                // This check assumes the filter buttons ONLY appear on transaction_history.php
                if ($('#transactionTable').length) {
                    currentRange = $(this).data('range');

                    // Update button styles
                    $('.filter-btn').removeClass('active-filter bg-gray-500').addClass('bg-blue-500');
                    $(this).removeClass('bg-blue-500').addClass('active-filter bg-gray-500');

                    // Reload only the transaction table's data
                    transactionTable.ajax.reload();
                }
            });
        }
        // --- END: Transaction History Specific Initialization ---

    });
</script>

<script>
    // --- AI Chat Widget Logic ---
    // Ensure this runs after jQuery is loaded by being inside the main $(document).ready() or jQuery(document).ready()
    jQuery(document).ready(function($) { // Use jQuery() and pass $
        // ... (rest of the AI chat and notification script remains unchanged) ...
        const chatBubble = $('#chat-bubble'); // Use jQuery selectors
        const chatWindow = $('#chat-window');
        const closeChat = $('#close-chat');
        const chatLog = $('#chat-log');
        const chatInput = $('#chat-input');
        const sendChat = $('#send-chat');
        const promptButtonsContainer = $('#prompt-buttons-container');
        const togglePromptsBtn = $('#toggle-prompts-btn');
        const arrowDown = $('#prompts-arrow-down');
        const arrowUp = $('#prompts-arrow-up');


        const maxRetries = 3;
        const initialDelayMs = 1000;

        const fetchWithRetry = (url, options, retries = 0) => {
            return fetch(url, options)
                .then(response => {
                    if (!response.ok) {
                        if (retries < maxRetries && [500, 503, 429].includes(response.status)) {
                            const delay = initialDelayMs * Math.pow(2, retries);
                            console.warn(`[AI Assistant] Request failed with status ${response.status}. Retrying in ${delay / 1000}s...`);
                            return new Promise(resolve => setTimeout(resolve, delay))
                                .then(() => fetchWithRetry(url, options, retries + 1));
                        }
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                });
        };

        const loadChatHistory = () => {
            if (!chatLog.length) return; // Check if element exists using jQuery's length property
            chatLog.html('<div class="text-center text-gray-500 p-4"><i>Loading history...</i></div>'); // Use .html()

            fetchWithRetry('ai_assistant_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_history' })
            })
                .then(data => {
                    if (!chatLog.length) return;
                    chatLog.html(''); // Clear loading message

                    // Add the default welcome message first
                    const welcomeDiv = $('<div></div>') // Use jQuery to create elements
                        .addClass('mb-2 text-sm text-gray-600 p-3 bg-gray-200 rounded-lg self-start max-w-xs')
                        .html('Hello! I’m Stocksy, the AI assistant for SmartMediStocks. I am here to help you with tasks like analyzing inventory data, predicting stockouts, and calculating optimal order quantities. Go ahead and ask me anything about your inventory!');
                    chatLog.append(welcomeDiv); // Use .append()

                    if (data.history && data.history.length > 0) {
                        data.history.forEach(item => {
                            const messageDiv = $('<div></div>'); // Use jQuery
                            if (item.sender === 'user') {
                                messageDiv.addClass('mb-2 text-sm text-white p-3 bg-blue-500 rounded-lg self-end max-w-xs ml-auto')
                                    .text(item.message); // Use .text() for user messages
                            } else {
                                messageDiv.addClass('mb-2 text-sm text-gray-600 p-3 bg-gray-200 rounded-lg self-start max-w-xs')
                                    .html(item.message); // Use .html() for AI messages
                            }
                            chatLog.append(messageDiv);
                        });
                    }
                    if (chatLog.length) chatLog.scrollTop(chatLog[0].scrollHeight); // Scroll using jQuery
                })
                .catch(error => {
                    console.error('Chat History Error:', error);
                    if (chatLog.length) chatLog.html('<div class="text-center text-red-500 p-4"><i>Could not load chat history.</i></div>');
                });
        };

        if (chatBubble.length) {
            chatBubble.on('click', () => { // Use .on() for events
                if(chatWindow.length) chatWindow.removeClass('hidden');
                chatBubble.addClass('hidden');
                loadChatHistory();
            });
        }

        if (closeChat.length) {
            closeChat.on('click', () => {
                if(chatWindow.length) chatWindow.addClass('hidden');
                if(chatBubble.length) chatBubble.removeClass('hidden');
            });
        }


        const sendMessage = () => {
            if (!chatInput.length || !chatLog.length || !sendChat.length) return;

            const message = chatInput.val().trim(); // Use .val() to get input value
            if (message === '') return;

            const userMessageDiv = $('<div></div>')
                .addClass('mb-2 text-sm text-white p-3 bg-blue-500 rounded-lg self-end max-w-xs ml-auto')
                .text(message);
            chatLog.append(userMessageDiv);

            chatInput.val(''); // Use .val() to clear input
            chatLog.scrollTop(chatLog[0].scrollHeight);

            const thinkingDiv = $('<div></div>')
                .addClass('mb-2 text-sm text-gray-500 p-3 self-start max-w-xs')
                .html('<i>Typing...</i>');
            chatLog.append(thinkingDiv);
            chatLog.scrollTop(chatLog[0].scrollHeight);

            const fetchOptions = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'send_message', message: message })
            };

            fetchWithRetry('ai_assistant_handler.php', fetchOptions)
                .then(data => {
                    if (!chatLog.length) return;
                    thinkingDiv.remove(); // Remove thinking message using jQuery
                    const aiMessageDiv = $('<div></div>')
                        .addClass('mb-2 text-sm text-gray-600 p-3 bg-gray-200 rounded-lg self-start max-w-xs')
                        .html(data.answer || 'Sorry, something went wrong.'); // Use .html()
                    chatLog.append(aiMessageDiv);
                    chatLog.scrollTop(chatLog[0].scrollHeight);
                })
                .catch(error => {
                    console.error('AI Assistant Error:', error);
                    if (!chatLog.length) return;
                    thinkingDiv.remove();
                    const errorMessageDiv = $('<div></div>')
                        .addClass('mb-2 text-sm text-red-600 p-3 bg-red-100 rounded-lg self-start max-w-xs')
                        .text('Error connecting to the assistant. Please try again later.');
                    chatLog.append(errorMessageDiv);
                    chatLog.scrollTop(chatLog[0].scrollHeight);
                });
        };

        // Event listener for prompt buttons using event delegation with jQuery
        if (promptButtonsContainer.length) {
            promptButtonsContainer.on('click', '.prompt-btn', function() { // Delegate event
                const promptText = $(this).text(); // Use $(this) to refer to the clicked button
                if (chatInput.length) {
                    chatInput.val(promptText);
                    sendMessage();
                }
            });
        }

        // Event listener for the toggle button
        if (togglePromptsBtn.length) {
            togglePromptsBtn.on('click', () => {
                if (promptButtonsContainer.length) {
                    promptButtonsContainer.toggleClass('hidden');
                    if(arrowDown.length) arrowDown.toggleClass('hidden');
                    if(arrowUp.length) arrowUp.toggleClass('hidden');
                }
            });
        }


        if (sendChat.length) sendChat.on('click', sendMessage);
        if (chatInput.length) {
            chatInput.on('keypress', function(e) { // Use .on()
                if (e.key === 'Enter') {
                    sendMessage();
                }
            });
        }
        // --- Notification System Logic ---
        const notificationIcon = $('#notification-bubble'); // Use jQuery selectors
        const notificationDropdown = $('#notification-dropdown');
        const notificationBadge = $('#notification-badge');
        const notificationList = $('#notification-list');
        const markAllRead = $('#mark-all-read');
        const POLLING_INTERVAL = 30000; // 30 seconds

        if (notificationIcon.length) {
            notificationIcon.on('click', (e) => {
                e.stopPropagation();
                if (notificationDropdown.length) {
                    notificationDropdown.toggleClass('hidden');
                    if (!notificationDropdown.hasClass('hidden')) { // Use .hasClass()
                        fetchNotifications(false); // Fetch immediately when opened
                    }
                }
            });
        }

        $(document).on('click', (e) => { // Use $(document) for global click listener
            if (notificationDropdown.length && !notificationDropdown.hasClass('hidden') &&
                notificationIcon.length && !notificationDropdown.is(e.target) && notificationDropdown.has(e.target).length === 0 && !notificationIcon.is(e.target)) {
                notificationDropdown.addClass('hidden');
            }
        });

        const renderNotifications = (data) => {
            if (!notificationList.length || !notificationBadge.length) return;

            notificationList.html(''); // Use .html('') to clear

            if (data.count > 0) {
                notificationBadge.removeClass('hidden').text(data.count); // Chain methods
            } else {
                notificationBadge.addClass('hidden');
            }

            if (data.notifications.length === 0) {
                notificationList.html('<li class="p-4 text-center text-sm text-gray-500">No recent alerts.</li>');
                return;
            }

            data.notifications.forEach(notif => {
                const li = $('<li></li>'); // Create element with jQuery
                let textClass = notif.is_read ? 'text-gray-600' : 'font-semibold text-red-800';
                let backgroundClass = notif.is_read ? 'bg-gray-100' : 'bg-red-50 border-l-4 border-red-400';
                li.addClass(`p-3 text-sm hover:bg-gray-200 cursor-pointer ${textClass} ${backgroundClass}`) // Use addClass
                    .html(`<p>${notif.message}</p><span class="text-xs text-gray-400 block mt-1">${notif.time}</span>`) // Use html()
                    .attr('data-id', notif.id); // Use attr()

                if (!notif.is_read) {
                    li.on('click', () => markNotificationAsRead(notif.id)); // Attach click handler
                }
                notificationList.append(li); // Use append()
            });
        };

        const fetchNotifications = (silent = true) => {
            if (!notificationList.length) return;
            fetch('get_notifications.php?action=fetch')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderNotifications(data);
                    } else if (!silent) {
                        notificationList.html(`<li class="p-4 text-center text-sm text-red-500">${data.error || 'Error fetching alerts.'}</li>`);
                    }
                })
                .catch(error => {
                    console.error('Notification fetch error:', error);
                    if (!silent && notificationList.length) {
                        notificationList.html('<li class="p-4 text-center text-sm text-red-500">Network error.</li>');
                    }
                });
        };

        const markNotificationAsRead = (id) => {
            fetch(`get_notifications.php?action=mark_read&notification_id=${id}`)
                .then(response => response.json())
                .then(data => { if (data.success) fetchNotifications(false); });
        };

        if (markAllRead.length) {
            markAllRead.on('click', (e) => {
                e.preventDefault();
                fetch('get_notifications.php?action=mark_all_read')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (notificationBadge.length) notificationBadge.addClass('hidden');
                            fetchNotifications(false);
                        }
                    })
                    .catch(console.error);
            });
        }

        // Initial and periodic fetching of notifications
        if (notificationIcon.length) {
            fetchNotifications(true);
            setInterval(fetchNotifications, POLLING_INTERVAL);
        }
    });
</script>

<script>
// Topbar dropdown toggles
document.addEventListener('DOMContentLoaded', function () {
    function makeToggle(btnId, dropId) {
        var btn  = document.getElementById(btnId);
        var drop = document.getElementById(dropId);
        if (!btn || !drop) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            drop.classList.toggle('hidden');
        });
    }
    makeToggle('notification-bubble', 'notification-dropdown');
    makeToggle('user-menu-btn', 'user-menu-dropdown');

    document.addEventListener('click', function () {
        var ids = ['notification-dropdown', 'user-menu-dropdown'];
        ids.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        });
    });
});
</script>

</body>
</html>

