<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuzzy Search Demo | Smart Medi Stocks</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .test-card { transition: all 0.2s; }
        .test-card:hover { transform: translateY(-2px); }
        .result-good { background: #dcfce7; border-left: 4px solid #16a34a; }
        .result-warning { background: #fef3c7; border-left: 4px solid #ca8a04; }
        .result-error { background: #fee2e2; border-left: 4px solid #dc2626; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-cyan-50 min-h-screen">

<!-- Navigation -->
<nav class="bg-white border-b border-gray-200 shadow-sm">
    <div class="max-w-6xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">Fuzzy Search Enhancement</h1>
        <a href="public_chat.php" class="text-blue-600 hover:text-blue-700 font-medium">← Back to Chat</a>
    </div>
</nav>

<!-- Main Content -->
<div class="max-w-6xl mx-auto px-4 py-8">

    <!-- Overview -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Enhancement Overview</h2>
        <div class="space-y-3 text-gray-700">
            <p><strong>✓ What's New:</strong> The system now intelligently suggests medicine names when users misspell them.</p>
            <p><strong>✓ Technology:</strong> Uses Levenshtein distance algorithm to find the closest matching medicine names.</p>
            <p><strong>✓ Where It Works:</strong> Public chat, internal AI assistant, and EOQ calculator.</p>
            <p><strong>✓ User Experience:</strong> If no exact match is found, users see "Did you mean..." suggestions instead of "Not found" errors.</p>
        </div>
    </div>

    <!-- Test Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-6">Test the Fuzzy Search</h2>
        
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Enter a medicine name (misspell it intentionally):</label>
            <div class="flex gap-2">
                <input type="text" id="searchInput" placeholder="e.g., 'paracetamol' or 'paracetamol' misspelled..." 
                    class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button onclick="testSearch()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-medium">
                    Search
                </button>
            </div>
        </div>

        <div id="testResults" class="space-y-3"></div>
    </div>

    <!-- Examples Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Example Misspellings</h3>
            <div class="space-y-2 text-sm text-gray-700">
                <p><strong>Try searching for:</strong></p>
                <ul class="list-disc list-inside space-y-1 ml-2">
                    <li><code class="bg-gray-100 px-2 py-1 rounded">paracetamol</code> (correct)</li>
                    <li><code class="bg-gray-100 px-2 py-1 rounded">paracetamol</code> (typo: extra 'l')</li>
                    <li><code class="bg-gray-100 px-2 py-1 rounded">amoxicilan</code> (typo: 'n' instead of 'm')</li>
                    <li><code class="bg-gray-100 px-2 py-1 rounded">ibuprofen</code> (correct)</li>
                    <li><code class="bg-gray-100 px-2 py-1 rounded">ibuproben</code> (typo: 'n' instead of 'n')</li>
                </ul>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">How It Works</h3>
            <div class="space-y-2 text-sm text-gray-700">
                <p><strong>Matching Process:</strong></p>
                <ol class="list-decimal list-inside space-y-1 ml-2">
                    <li>First, tries exact/partial match (LIKE query)</li>
                    <li>If no exact match, uses Levenshtein distance</li>
                    <li>Calculates character-level similarity</li>
                    <li>Shows top 3 closest matches</li>
                    <li>Distance threshold adapts to query length</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Technical Details -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Technical Implementation</h3>
        <div class="space-y-3 text-sm text-gray-700 bg-gray-50 p-4 rounded">
            <p><strong>Files Modified:</strong></p>
            <ul class="list-disc list-inside ml-2 space-y-1">
                <li><code class="text-gray-600">fuzzy_search_helper.php</code> - New helper with reusable functions</li>
                <li><code class="text-gray-600">public_chat_handler.php</code> - Enhanced with fuzzy matching</li>
                <li><code class="text-gray-600">ai_assistant_handler.php</code> - EOQ and other queries now use fuzzy search</li>
            </ul>
            
            <p class="mt-4"><strong>Key Functions:</strong></p>
            <ul class="list-disc list-inside ml-2 space-y-1">
                <li><code class="text-gray-600">fuzzy_search_items()</code> - Main fuzzy search function</li>
                <li><code class="text-gray-600">format_item_for_display()</code> - Format items for UI</li>
                <li><code class="text-gray-600">generate_suggestion_html()</code> - Generate suggestion cards</li>
            </ul>

            <p class="mt-4"><strong>Algorithm:</strong></p>
            <p>Levenshtein distance measures the minimum number of edits (insertions, deletions, substitutions) needed to transform one string into another. Lower scores = better matches.</p>
        </div>
    </div>

</div>

<script>
    function testSearch() {
        const query = document.getElementById('searchInput').value.trim();
        const resultsDiv = document.getElementById('testResults');
        
        if (!query || query.length < 2) {
            resultsDiv.innerHTML = '<div class="result-error p-4 rounded">Please enter at least 2 characters.</div>';
            return;
        }

        fetch('public_chat_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: query })
        })
        .then(r => r.json())
        .then(data => {
            const html = `
                <div class="result-good p-4 rounded">
                    <div class="font-medium mb-2">Search Query: <code class="bg-white px-2 py-1 rounded">${escapeHtml(query)}</code></div>
                    <div class="text-sm"><strong>Result:</strong></div>
                    <div class="mt-2 text-sm">${data.reply || 'No response'}</div>
                </div>
            `;
            resultsDiv.innerHTML = html;
        })
        .catch(err => {
            resultsDiv.innerHTML = `<div class="result-error p-4 rounded">Error: ${err.message}</div>`;
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Allow Enter key to submit
    document.getElementById('searchInput').addEventListener('keypress', e => {
        if (e.key === 'Enter') testSearch();
    });
</script>

</body>
</html>
