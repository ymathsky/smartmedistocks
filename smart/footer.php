</main> <!-- End Main Content -->

<!-- AI Assistant Chat Widget -->
<div id="ai-chat-widget" class="fixed bottom-5 right-5 z-50">
    <!-- Chat Bubble -->
    <div id="chat-bubble" class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center cursor-pointer shadow-lg hover:bg-blue-700 transition-transform transform hover:scale-110">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
    </div>

    <!-- Chat Window -->
    <div id="chat-window" class="hidden absolute bottom-20 right-0 w-80 sm:w-96 bg-white rounded-lg shadow-2xl border border-gray-200 flex flex-col" style="height: 500px;">
        <div class="bg-blue-600 text-white p-4 rounded-t-lg flex justify-between items-center">
            <h3 class="font-bold text-lg">AI Assistant</h3>
            <button id="close-chat" class="text-white">&times;</button>
        </div>
        <div id="chat-log" class="flex-1 p-4 overflow-y-auto bg-gray-50">
            <!-- Messages will appear here -->
            <div class="mb-2 text-sm text-gray-600 p-3 bg-gray-200 rounded-lg self-start max-w-xs">
                Hello! I'm the AI assistant. Ask me to explain concepts like "EOQ", "Safety Stock", or to find the "top items".
            </div>
        </div>
        <div class="p-4 border-t bg-white">
            <div class="flex">
                <input type="text" id="chat-input" placeholder="Ask a question..." class="flex-1 border rounded-l-lg p-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button id="send-chat" class="bg-blue-600 text-white px-4 rounded-r-lg hover:bg-blue-700">Send</button>
            </div>
        </div>
    </div>
</div>
</div>

<script>
    // --- AI Chat Widget Logic ---
    document.addEventListener('DOMContentLoaded', function() {
        const chatBubble = document.getElementById('chat-bubble');
        const chatWindow = document.getElementById('chat-window');
        const closeChat = document.getElementById('close-chat');
        const chatLog = document.getElementById('chat-log');
        const chatInput = document.getElementById('chat-input');
        const sendChat = document.getElementById('send-chat');

        chatBubble.addEventListener('click', () => {
            chatWindow.classList.toggle('hidden');
            chatBubble.classList.toggle('hidden');
        });

        closeChat.addEventListener('click', () => {
            chatWindow.classList.toggle('hidden');
            chatBubble.classList.toggle('hidden');
        });

        const sendMessage = () => {
            const message = chatInput.value.trim();
            if (message === '') return;

            // Display user's message
            const userMessageDiv = document.createElement('div');
            userMessageDiv.className = 'mb-2 text-sm text-white p-3 bg-blue-500 rounded-lg self-end max-w-xs ml-auto';
            userMessageDiv.textContent = message;
            chatLog.appendChild(userMessageDiv);

            chatInput.value = '';
            chatLog.scrollTop = chatLog.scrollHeight;

            // Add a "thinking" indicator
            const thinkingDiv = document.createElement('div');
            thinkingDiv.className = 'mb-2 text-sm text-gray-500 p-3 self-start max-w-xs';
            thinkingDiv.innerHTML = '<i>Typing...</i>';
            chatLog.appendChild(thinkingDiv);
            chatLog.scrollTop = chatLog.scrollHeight;

            // Send message to the backend
            fetch('ai_assistant_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message: message })
            })
                .then(response => response.json())
                .then(data => {
                    // Remove "thinking" indicator
                    chatLog.removeChild(thinkingDiv);

                    // Display AI's response
                    const aiMessageDiv = document.createElement('div');
                    aiMessageDiv.className = 'mb-2 text-sm text-gray-600 p-3 bg-gray-200 rounded-lg self-start max-w-xs';
                    aiMessageDiv.textContent = data.answer || 'Sorry, something went wrong.';
                    chatLog.appendChild(aiMessageDiv);
                    chatLog.scrollTop = chatLog.scrollHeight;
                })
                .catch(error => {
                    console.error('AI Assistant Error:', error);
                    // Remove "thinking" indicator
                    chatLog.removeChild(thinkingDiv);

                    // Display an error message
                    const errorMessageDiv = document.createElement('div');
                    errorMessageDiv.className = 'mb-2 text-sm text-red-600 p-3 bg-red-100 rounded-lg self-start max-w-xs';
                    errorMessageDiv.textContent = 'Error connecting to the assistant. Please try again later.';
                    chatLog.appendChild(errorMessageDiv);
                    chatLog.scrollTop = chatLog.scrollHeight;
                });
        };

        sendChat.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    });
</script>

</body>
</html>

