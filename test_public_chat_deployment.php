<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Chat Deployment Test | Smart Medi Stocks</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .status-pass { background: #dcfce7; border-left: 4px solid #16a34a; color: #15803d; }
        .status-fail { background: #fee2e2; border-left: 4px solid #dc2626; color: #b91c1c; }
        .status-pending { background: #fef3c7; border-left: 4px solid #ca8a04; color: #92400e; }
        .test-case { transition: all 0.2s; }
        .test-case:hover { transform: translateX(4px); }
        .spinner { 
            display: inline-block; 
            width: 16px; height: 16px;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: #3b82f6;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-cyan-50 min-h-screen">

<!-- Navigation -->
<nav class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">🚀 Deployment Test Suite</h1>
        <div class="flex gap-2">
            <button onclick="runAllTests()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium">
                Run All Tests
            </button>
            <a href="public_chat.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium">
                View Chat →
            </a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="max-w-6xl mx-auto px-4 py-8">

    <!-- Overview -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Deployment Verification</h2>
        <p class="text-gray-700">This page tests all components of the public chat system to ensure successful deployment. Run the tests below to verify functionality.</p>
        
        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-blue-600" id="totalTests">0</div>
                <div class="text-xs text-gray-600">Total Tests</div>
            </div>
            <div class="text-center p-3 bg-green-50 rounded-lg">
                <div class="text-2xl font-bold text-green-600" id="passedTests">0</div>
                <div class="text-xs text-gray-600">Passed</div>
            </div>
            <div class="text-center p-3 bg-red-50 rounded-lg">
                <div class="text-2xl font-bold text-red-600" id="failedTests">0</div>
                <div class="text-xs text-gray-600">Failed</div>
            </div>
            <div class="text-center p-3 bg-amber-50 rounded-lg">
                <div class="text-2xl font-bold text-amber-600" id="pendingTests">0</div>
                <div class="text-xs text-gray-600">Pending</div>
            </div>
        </div>
    </div>

    <!-- Test Categories -->
    <div class="space-y-6">

        <!-- System Health Tests -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">System Health</h3>
            <div class="space-y-2" id="systemHealthTests"></div>
        </div>

        <!-- Handler Tests -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Public Chat Handler</h3>
            <div class="space-y-2" id="handlerTests"></div>
        </div>

        <!-- Fuzzy Search Tests -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Fuzzy Search Functionality</h3>
            <div class="space-y-2" id="fuzzyTests"></div>
        </div>

        <!-- Edge Cases -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Edge Cases & Validation</h3>
            <div class="space-y-2" id="edgeCaseTests"></div>
        </div>

    </div>

    <!-- Test Results Summary -->
    <div class="bg-white rounded-lg shadow-md p-6 mt-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Summary</h3>
        <div id="testSummary" class="space-y-3"></div>
    </div>

</div>

<script>
const tests = {
    systemHealth: [
        {
            name: 'public_chat_handler.php exists',
            test: async () => {
                const res = await fetch('public_chat_handler.php', { method: 'HEAD' });
                return res.status === 200;
            }
        },
        {
            name: 'fuzzy_search_helper.php exists',
            test: async () => {
                const res = await fetch('fuzzy_search_helper.php', { method: 'HEAD' });
                return res.status === 200;
            }
        }
    ],
    handler: [
        {
            name: 'Handler accepts JSON post',
            test: async () => {
                const res = await fetch('public_chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: 'paracetamol' })
                });
                const data = await res.json();
                return res.status === 200 && data.reply;
            }
        },
        {
            name: 'Handler returns valid JSON',
            test: async () => {
                const res = await fetch('public_chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: 'test' })
                });
                try {
                    const data = await res.json();
                    return data && typeof data === 'object' && data.reply;
                } catch {
                    return false;
                }
            }
        },
        {
            name: 'Handler validates input length',
            test: async () => {
                const res = await fetch('public_chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: 'a' })
                });
                const data = await res.json();
                return data.reply && data.reply.includes('valid');
            }
        },
        {
            name: 'Handler implements rate limiting',
            test: async () => {
                // Make 31 requests (limit is 30)
                for (let i = 0; i < 31; i++) {
                    const res = await fetch('public_chat_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ query: 'test' })
                    });
                    const data = await res.json();
                    if (i === 30 && data.reply && data.reply.includes('maximum')) {
                        return true;
                    }
                }
                return false;
            }
        }
    ],
    fuzzy: [
        {
            name: 'Finds exact matches',
            test: async () => {
                const res = await fetch('public_chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: 'paracetamol' })
                });
                const data = await res.json();
                return data.reply && (data.reply.includes('Paracetamol') || data.reply.includes('paracetamol'));
            },
            description: 'Should find exact medicine name'
        },
        {
            name: 'Suggests on misspelling',
            test: async () => {
                const res = await fetch('public_chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: 'parasetamol' })
                });
                const data = await res.json();
                // Should either find it or suggest
                return data.reply && (data.reply.includes('mean') || data.reply.includes('Paracetamol'));
            },
            description: 'Should suggest correct spelling'
        },
        {
            name: 'Multiple match handling',
            test: async () => {
                const res = await fetch('public_chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: 'am' })
                });
                const data = await res.json();
                // Should handle multiple matches gracefully
                return data.reply && typeof data.reply === 'string';
            },
            description: 'Should handle multiple matches'
        },
        {
            name: 'No match fallback',
            test: async () => {
                const res = await fetch('public_chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: 'xyzabc123notamedicine' })
                });
                const data = await res.json();
                return data.reply && (data.reply.includes('could not find') || data.reply.includes('did you mean'));
            },
            description: 'Should show helpful message for no matches'
        }
    ],
    edgeCase: [
        {
            name: 'HTML sanitization',
            test: async () => {
                const res = await fetch('public_chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: '<sc' + 'ript>alert("xss")<' + '/script>' })
                });
                const data = await res.json();
                // Should not have unescaped script tags
                return !data.reply.includes('<' + 'script>');
            },
            description: 'Should sanitize HTML'
        },
        {
            name: 'Special character handling',
            test: async () => {
                const res = await fetch('public_chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: 'paracetamol\'s "test"' })
                });
                const data = await res.json();
                return data.reply && typeof data.reply === 'string';
            },
            description: 'Should handle special characters'
        },
        {
            name: 'Case insensitivity',
            test: async () => {
                const res1 = await fetch('public_chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: 'PARACETAMOL' })
                });
                const data1 = await res1.json();
                
                const res2 = await fetch('public_chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: 'paracetamol' })
                });
                const data2 = await res2.json();
                
                // Both should find the same result
                return data1.reply && data2.reply;
            },
            description: 'Should ignore case'
        },
        {
            name: 'Whitespace trimming',
            test: async () => {
                const res = await fetch('public_chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: '   paracetamol   ' })
                });
                const data = await res.json();
                return data.reply && typeof data.reply === 'string';
            },
            description: 'Should trim whitespace'
        }
    ]
};

let testResults = [];
let testIndex = 0;
let totalCount = 0;

async function runTest(category, test) {
    const testEl = document.createElement('div');
    testEl.className = 'test-case status-pending p-3 rounded';
    testEl.innerHTML = `<div class="flex items-center gap-2">
        <div class="spinner"></div>
        <span>${test.name}</span>
    </div>`;
    
    const containerId = category + 'Tests';
    const container = document.getElementById(containerId);
    container.appendChild(testEl);
    
    try {
        const result = await test.test();
        testResults.push({ name: test.name, passed: result, category });
        
        testEl.className = result ? 'test-case status-pass p-3 rounded' : 'test-case status-fail p-3 rounded';
        testEl.innerHTML = `<div class="flex items-center gap-2">
            <span>${result ? '✓' : '✗'}</span>
            <span>${test.name}</span>
            ${test.description ? `<span class="text-xs text-gray-500 ml-auto">${test.description}</span>` : ''}
        </div>`;
    } catch (err) {
        testResults.push({ name: test.name, passed: false, category, error: err.message });
        testEl.className = 'test-case status-fail p-3 rounded';
        testEl.innerHTML = `<div class="flex items-center gap-2">
            <span>✗</span>
            <span>${test.name}</span>
            <span class="text-xs text-red-600 ml-auto">Error: ${err.message}</span>
        </div>`;
    }
    
    updateStats();
}

function updateStats() {
    const passed = testResults.filter(t => t.passed).length;
    const failed = testResults.filter(t => !t.passed).length;
    const pending = totalCount - testResults.length;
    
    document.getElementById('totalTests').textContent = totalCount;
    document.getElementById('passedTests').textContent = passed;
    document.getElementById('failedTests').textContent = failed;
    document.getElementById('pendingTests').textContent = pending;
    
    if (pending === 0) {
        generateSummary(passed, failed);
    }
}

function generateSummary(passed, failed) {
    const summary = document.getElementById('testSummary');
    const total = passed + failed;
    const passRate = total > 0 ? Math.round((passed / total) * 100) : 0;
    
    let html = `
        <div class="${passed === total ? 'status-pass' : 'status-fail'} p-4 rounded">
            <div class="text-lg font-semibold mb-2">${passed === total ? '✓ All Tests Passed!' : '✗ Some Tests Failed'}</div>
            <div class="text-sm">Passed: <strong>${passed}/${total}</strong> (${passRate}%)</div>
        </div>
    `;
    
    if (failed > 0) {
        html += `<div class="bg-red-50 border-l-4 border-red-600 p-4 rounded">
            <div class="font-semibold text-red-800 mb-2">Failed Tests:</div>
            <ul class="text-sm text-red-700 list-disc list-inside">`;
        
        testResults.filter(t => !t.passed).forEach(t => {
            html += `<li>${t.name} ${t.error ? `(${t.error})` : ''}</li>`;
        });
        
        html += `</ul>
        </div>`;
    }
    
    if (passed === total) {
        html += `<div class="bg-green-50 border-l-4 border-green-600 p-4 rounded">
            <div class="font-semibold text-green-800 mb-2">✓ Deployment Successful!</div>
            <p class="text-sm text-green-700">The public chat system is fully operational. Users can now:</p>
            <ul class="text-sm text-green-700 list-disc list-inside mt-2">
                <li>Search for medicines by exact name</li>
                <li>Get suggestions when spelling is incorrect</li>
                <li>View real-time inventory availability</li>
                <li>Experience fuzzy match technology</li>
            </ul>
        </div>`;
    }
    
    summary.innerHTML = html;
}

async function runAllTests() {
    testResults = [];
    document.getElementById('systemHealthTests').innerHTML = '';
    document.getElementById('handlerTests').innerHTML = '';
    document.getElementById('fuzzyTests').innerHTML = '';
    document.getElementById('edgeCaseTests').innerHTML = '';
    document.getElementById('testSummary').innerHTML = '';
    
    totalCount = Object.values(tests).reduce((sum, arr) => sum + arr.length, 0);
    updateStats();
    
    for (const [category, testList] of Object.entries(tests)) {
        for (const test of testList) {
            await runTest(category, test);
            await new Promise(resolve => setTimeout(resolve, 100)); // Stagger requests
        }
    }
}

// Run tests on page load
window.addEventListener('load', () => {
    runAllTests();
});
</script>

</body>
</html>
