<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Availability | Smart Medi Stocks</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .chat-bubble-bot  { background: #f1f5f9; color: #1e293b; border-radius: 0 16px 16px 16px; }
        .chat-bubble-user { background: #2563eb; color: #fff;     border-radius: 16px 0 16px 16px; }
        #chat-log { scroll-behavior: smooth; }
        .typing-dot { width: 7px; height: 7px; border-radius: 50%; background: #94a3b8; display: inline-block; animation: blink 1.2s infinite; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes blink { 0%,80%,100%{opacity:.2;transform:scale(1)} 40%{opacity:1;transform:scale(1.3)} }
        .suggestion-chip { cursor: pointer; transition: background 0.15s; }
        .suggestion-chip:hover { background: #dbeafe; color: #1d4ed8; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-cyan-50 min-h-screen flex flex-col">

<!-- Navigation -->
<nav class="bg-white border-b border-gray-100 shadow-sm sticky top-0 z-40">
    <div class="max-w-5xl mx-auto px-4 py-3 flex justify-between items-center">
        <a href="landing.php" class="flex items-center gap-2 text-gray-800 hover:text-blue-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l-7 7 7 7"/>
            </svg>
            <span class="text-sm font-medium">Back to Home</span>
        </a>
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2h-4l-2-2H9L7 5H3a2 2 0 00-2 2v13a2 2 0 002 2z"/>
                </svg>
            </div>
            <span class="font-semibold text-gray-800 text-sm">Smart Medi Stocks</span>
        </div>
        <a href="login.php" class="text-sm font-medium text-blue-600 hover:underline">Staff Login &rarr;</a>
    </div>
</nav>

<!-- Hero -->
<div class="text-center pt-10 pb-4 px-4">
    <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full mb-4">
        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse inline-block"></span>
        Live Inventory Check
    </div>
    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-2">Is your medicine available?</h1>
    <p class="text-gray-500 text-sm max-w-md mx-auto">Type any medicine name or code below to instantly check availability in our pharmacy stock.</p>
</div>

<!-- Chat Window -->
<div class="flex-1 max-w-2xl w-full mx-auto px-4 pb-6 flex flex-col">

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 flex flex-col overflow-hidden" style="min-height:460px;">

        <!-- Chat Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-4 flex items-center gap-3">
            <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3-3-3z"/>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-white text-sm">Medicine Availability Assistant</p>
                <p class="text-blue-200 text-xs">Ask about any medicine in our stock</p>
            </div>
            <div class="ml-auto flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-green-400"></span>
                <span class="text-blue-200 text-xs">Online</span>
            </div>
        </div>

        <!-- Chat Log -->
        <div id="chat-log" class="flex-1 p-4 overflow-y-auto space-y-4" style="max-height:360px;">
            <!-- Welcome message -->
            <div class="flex items-end gap-2">
                <div class="w-7 h-7 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 mb-0.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2h-4l-2-2H9L7 5H3a2 2 0 00-2 2v13a2 2 0 002 2z"/></svg>
                </div>
                <div class="chat-bubble-bot px-4 py-3 text-sm max-w-xs shadow-sm">
                    Hello! I can check if a medicine is available in our stock. Just type the medicine name or item code below.
                </div>
            </div>
        </div>

        <!-- Suggestion chips -->
        <div id="chips-area" class="px-4 pb-2 flex flex-wrap gap-2">
            <span class="suggestion-chip text-xs bg-gray-100 text-gray-600 px-3 py-1.5 rounded-full font-medium">Paracetamol</span>
            <span class="suggestion-chip text-xs bg-gray-100 text-gray-600 px-3 py-1.5 rounded-full font-medium">Amoxicillin</span>
            <span class="suggestion-chip text-xs bg-gray-100 text-gray-600 px-3 py-1.5 rounded-full font-medium">Ibuprofen</span>
            <span class="suggestion-chip text-xs bg-gray-100 text-gray-600 px-3 py-1.5 rounded-full font-medium">Cetirizine</span>
            <span class="suggestion-chip text-xs bg-gray-100 text-gray-600 px-3 py-1.5 rounded-full font-medium">Metformin</span>
        </div>

        <!-- Input -->
        <div class="p-3 border-t border-gray-100 bg-gray-50">
            <form id="chat-form" class="flex gap-2">
                <input type="text" id="chat-input" placeholder="e.g. Paracetamol, Amoxicillin 500mg…"
                    class="flex-1 text-sm border border-gray-300 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                    autocomplete="off" maxlength="200">
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition flex items-center gap-1.5 flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Ask
                </button>
            </form>
            <p class="text-xs text-gray-400 mt-1.5 text-center">This tool only checks medicine availability. For prescriptions, please consult a licensed pharmacist.</p>
        </div>

    </div>

    <!-- Disclaimer -->
    <p class="text-center text-xs text-gray-400 mt-4">
        &copy; <?php echo date('Y'); ?> Smart Medi Stocks &mdash;
        <a href="terms.php" class="hover:underline">Terms</a> &middot;
        <a href="privacy.php" class="hover:underline">Privacy</a>
    </p>
</div>

<script>
(function () {
    var log    = document.getElementById('chat-log');
    var form   = document.getElementById('chat-form');
    var input  = document.getElementById('chat-input');
    var chips  = document.querySelectorAll('.suggestion-chip');

    function scrollBottom() {
        log.scrollTop = log.scrollHeight;
    }

    function addBubble(html, isUser) {
        var wrap = document.createElement('div');
        wrap.className = 'flex items-end gap-2' + (isUser ? ' justify-end' : '');

        if (!isUser) {
            var avatar = document.createElement('div');
            avatar.className = 'w-7 h-7 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 mb-0.5';
            avatar.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2h-4l-2-2H9L7 5H3a2 2 0 00-2 2v13a2 2 0 002 2z"/></svg>';
            wrap.appendChild(avatar);
        }

        var bubble = document.createElement('div');
        bubble.className = (isUser ? 'chat-bubble-user' : 'chat-bubble-bot') + ' px-4 py-3 text-sm max-w-xs shadow-sm';
        bubble.innerHTML = html;
        wrap.appendChild(bubble);
        log.appendChild(wrap);
        scrollBottom();
        return bubble;
    }

    function addTyping() {
        var wrap = document.createElement('div');
        wrap.className = 'flex items-end gap-2';
        wrap.id = 'typing-indicator';

        var avatar = document.createElement('div');
        avatar.className = 'w-7 h-7 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 mb-0.5';
        avatar.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2h-4l-2-2H9L7 5H3a2 2 0 00-2 2v13a2 2 0 002 2z"/></svg>';
        wrap.appendChild(avatar);

        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble-bot px-4 py-3 text-sm max-w-xs shadow-sm flex gap-1 items-center';
        bubble.innerHTML = '<span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>';
        wrap.appendChild(bubble);
        log.appendChild(wrap);
        scrollBottom();
    }

    function removeTyping() {
        var t = document.getElementById('typing-indicator');
        if (t) t.remove();
    }

    function sendQuery(q) {
        if (!q || q.length < 2) return;
        addBubble(escapeHtml(q), true);
        input.value = '';

        addTyping();

        fetch('public_chat_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: q })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            removeTyping();
            addBubble(data.reply || 'Sorry, something went wrong.', false);
        })
        .catch(function() {
            removeTyping();
            addBubble('Could not reach the server. Please try again.', false);
        });
    }

    function escapeHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        sendQuery(input.value.trim());
    });

    chips.forEach(function(chip) {
        chip.addEventListener('click', function() {
            document.getElementById('chips-area').style.display = 'none';
            sendQuery(chip.textContent.trim());
        });
    });

    input.focus();
})();
</script>

</body>
</html>
