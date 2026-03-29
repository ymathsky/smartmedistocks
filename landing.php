<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Medi Stocks - AI Inventory Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-text {
            background: linear-gradient(to right, #2563eb, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .medical-bg {
            background-color: #f0f9ff;
            background-image: radial-gradient(#bae6fd 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

<!-- Navigation -->
<nav class="fixed w-full bg-white/90 backdrop-blur-sm z-50 border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <div class="flex-shrink-0 flex items-center gap-2">
                <div class="bg-blue-600 p-1.5 rounded-lg">
                    <i data-lucide="activity" class="h-6 w-6 text-white"></i>
                </div>
                <span class="font-bold text-xl tracking-tight text-gray-900">Smart Medi Stocks</span>
            </div>
            <div class="hidden md:flex space-x-8">
                <a href="#analytics" class="text-gray-600 hover:text-blue-600 transition-colors">Analytics</a>
                <a href="#features" class="text-gray-600 hover:text-blue-600 transition-colors">Features</a>
                <a href="#roles" class="text-gray-600 hover:text-blue-600 transition-colors">Roles</a>
                <a href="#tech" class="text-gray-600 hover:text-blue-600 transition-colors">Tech Stack</a>
            </div>
            <div>
                <a href="login.php" class="hidden md:inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-all">
                    System Login
                </a>
                <button onclick="toggleMobileMenu()" class="md:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none">
                    <i data-lucide="menu" class="h-6 w-6"></i>
                </button>
            </div>
        </div>
    </div>
    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-100">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <a href="#analytics" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50">Analytics</a>
            <a href="#features" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50">Features</a>
            <a href="#roles" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50">Roles</a>
            <a href="login.php" class="block px-3 py-2 rounded-md text-base font-medium text-blue-600 bg-blue-50">System Login</a>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="pt-32 pb-20 lg:pt-40 lg:pb-28 overflow-hidden medical-bg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        <div class="text-center max-w-4xl mx-auto">
            <div class="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-800 text-xs font-medium mb-6">
                <span class="flex h-2 w-2 rounded-full bg-blue-600 mr-2 animate-pulse"></span>
                v6.10.0 Live System
            </div>
            <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 sm:text-5xl md:text-6xl mb-6">
                Optimize Healthcare Inventory with <span class="gradient-text">AI Precision</span>
            </h1>
            <p class="mt-3 max-w-2xl mx-auto text-base text-gray-500 sm:text-lg md:mt-5 md:text-xl">
                Prevents stockouts and minimizes expiry wastage. Smart Medi Stocks uses <strong>Holt-Winters forecasting</strong> and <strong>ABC Analysis</strong> to automate pharmacy supply chains.
            </p>
            <div class="mt-10 sm:flex sm:justify-center gap-4">
                <div class="rounded-md shadow">
                    <a href="#features" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 md:py-4 md:text-lg transition-all">
                        Explore Capabilities
                    </a>
                </div>
                <div class="mt-3 sm:mt-0 sm:ml-3">
                    <a href="public_chat.php" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 md:py-4 md:text-lg transition-all">
                        <i data-lucide="pill" class="w-5 h-5 mr-2"></i> Check Medicine Availability
                    </a>
                </div>
            </div>
        </div>

        <!-- Hero Visual: Dashboard Preview -->
        <div class="mt-16 relative max-w-5xl mx-auto">
            <div class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <div class="bg-gradient-to-tr from-blue-300 to-cyan-300 opacity-30 w-full h-full blur-3xl rounded-full transform scale-75"></div>
            </div>
            <div class="relative rounded-xl bg-gray-900/5 backdrop-blur-sm border border-white/20 shadow-2xl p-2 md:p-4">
                <div class="bg-white rounded-lg shadow-inner overflow-hidden relative group">
                    <!-- Header Mockup -->
                    <div class="h-12 bg-gray-800 flex items-center px-4 justify-between">
                        <div class="flex gap-2">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                            <div class="w-3 h-3 rounded-full bg-green-500"></div>
                        </div>
                        <div class="text-gray-400 text-xs font-mono">admin_dashboard.php</div>
                    </div>
                    <!-- Content Mockup -->
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50">
                        <div class="bg-white p-4 rounded shadow border-l-4 border-blue-500">
                            <div class="text-xs text-gray-500 uppercase font-bold">Total Inventory Value</div>
                            <div class="text-2xl font-bold text-gray-800">₱1,240,500</div>
                        </div>
                        <div class="bg-white p-4 rounded shadow border-l-4 border-red-500">
                            <div class="text-xs text-gray-500 uppercase font-bold">Below Reorder Point</div>
                            <div class="text-2xl font-bold text-red-600">12 Items</div>
                        </div>
                        <div class="bg-white p-4 rounded shadow border-l-4 border-yellow-500">
                            <div class="text-xs text-gray-500 uppercase font-bold">Expiring < 60 Days</div>
                            <div class="text-2xl font-bold text-yellow-600">8 Batches</div>
                        </div>
                        <div class="col-span-3 bg-white p-4 rounded shadow h-64 flex items-center justify-center border border-gray-200">
                            <div class="text-center">
                                <i data-lucide="bar-chart-2" class="w-12 h-12 text-blue-300 mx-auto mb-2"></i>
                                <p class="text-gray-400 text-sm">Demand Forecasting Visualization</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Core Analytics Section -->
<section id="analytics" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-base text-blue-600 font-semibold tracking-wide uppercase">Intelligence</h2>
            <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                Data-Driven Decisions
            </p>
            <p class="mt-4 max-w-2xl text-xl text-gray-500 mx-auto">
                We move beyond simple spreadsheets. Smart Medi Stocks uses advanced algorithms to suggest exactly what to order and when.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
            <!-- Feature 1 -->
            <div class="relative p-6 bg-gray-50 rounded-2xl border border-gray-100">
                <div class="absolute -top-6 left-6 w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i data-lucide="trending-up" class="w-6 h-6 text-white"></i>
                </div>
                <h3 class="mt-8 text-xl font-bold text-gray-900">Demand Forecasting</h3>
                <p class="mt-4 text-gray-500 text-sm leading-relaxed">
                    Utilizes <strong>Holt-Winters</strong> for seasonal items and <strong>Croston's Method</strong> for intermittent demand items. Predicts future consumption based on 180 days of history.
                </p>
            </div>

            <!-- Feature 2 -->
            <div class="relative p-6 bg-gray-50 rounded-2xl border border-gray-100">
                <div class="absolute -top-6 left-6 w-12 h-12 bg-green-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i data-lucide="calculator" class="w-6 h-6 text-white"></i>
                </div>
                <h3 class="mt-8 text-xl font-bold text-gray-900">Inventory Policy</h3>
                <p class="mt-4 text-gray-500 text-sm leading-relaxed">
                    Automatically calculates <strong>Economic Order Quantity (EOQ)</strong> and <strong>Reorder Points (ROP)</strong>. Ensures you balance holding costs against ordering costs.
                </p>
            </div>

            <!-- Feature 3 -->
            <div class="relative p-6 bg-gray-50 rounded-2xl border border-gray-100">
                <div class="absolute -top-6 left-6 w-12 h-12 bg-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i data-lucide="pie-chart" class="w-6 h-6 text-white"></i>
                </div>
                <h3 class="mt-8 text-xl font-bold text-gray-900">ABC Analysis</h3>
                <p class="mt-4 text-gray-500 text-sm leading-relaxed">
                    Classifies inventory into <strong>A (Critical)</strong>, <strong>B</strong>, and <strong>C</strong> categories based on Annual Consumption Value (ACV), helping you prioritize management efforts.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Detailed Features Section -->
<section id="features" class="py-20 bg-gray-50 border-t border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
            <div>
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl mb-6">
                    Comprehensive Warehouse Operations
                </h2>
                <div class="space-y-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                <i data-lucide="alert-triangle" class="h-6 w-6"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Expiry & FEFO Tracking</h3>
                            <p class="mt-2 text-base text-gray-500">
                                First-Expired, First-Out logic ensures oldest batches are used first. Automated alerts for items expiring in < 60 days.
                            </p>
                        </div>
                    </div>

                    <div class="flex">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                <i data-lucide="shopping-cart" class="h-6 w-6"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Automated Procurement</h3>
                            <p class="mt-2 text-base text-gray-500">
                                Purchase Orders (PO) are generated based on system suggestions. Track PO status from 'Placed' to 'Shipped' to 'Received'.
                            </p>
                        </div>
                    </div>

                    <div class="flex">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                <i data-lucide="message-square" class="h-6 w-6"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">"Stocksy" AI Assistant</h3>
                            <p class="mt-2 text-base text-gray-500">
                                Built-in chatbot to query stock data naturally. Ask "What is the EOQ for Paracetamol?" or "Show slow-moving items."
                            </p>
                        </div>
                    </div>

                    <div class="flex">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                <i data-lucide="sliders" class="h-6 w-6"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">What-If Simulator</h3>
                            <p class="mt-2 text-base text-gray-500">
                                Scenario planning tool. Adjust service levels or holding costs to see how they impact your Reorder Points and Safety Stock.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-10 lg:mt-0">
                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
                    <!-- Mockup of the alerts cron output or dashboard list -->
                    <div class="flex justify-between items-center mb-4 pb-2 border-b">
                        <h4 class="font-bold text-gray-700">System Alerts</h4>
                        <span class="bg-red-100 text-red-800 text-xs font-bold px-2 py-1 rounded">3 Critical</span>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-start p-3 bg-red-50 rounded border border-red-100">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mt-0.5 mr-3"></i>
                            <div>
                                <p class="text-sm font-bold text-gray-800">Low Stock: Amoxicillin 500mg</p>
                                <p class="text-xs text-gray-600">Current: 45 units | ROP: 120 units</p>
                            </div>
                        </div>
                        <div class="flex items-start p-3 bg-yellow-50 rounded border border-yellow-100">
                            <i data-lucide="clock" class="w-5 h-5 text-yellow-500 mt-0.5 mr-3"></i>
                            <div>
                                <p class="text-sm font-bold text-gray-800">Expiry Warning: Insulin (Human)</p>
                                <p class="text-xs text-gray-600">Batch #B992 expires in 28 days.</p>
                            </div>
                        </div>
                        <div class="flex items-start p-3 bg-blue-50 rounded border border-blue-100">
                            <i data-lucide="truck" class="w-5 h-5 text-blue-500 mt-0.5 mr-3"></i>
                            <div>
                                <p class="text-sm font-bold text-gray-800">PO #10243 Shipped</p>
                                <p class="text-xs text-gray-600">Supplier: PharmaDistributors Inc.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Role Based Section -->
<section id="roles" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-extrabold text-gray-900">Built for the Whole Team</h2>
            <p class="mt-4 text-gray-500">Specific dashboards tailored to hospital workflows.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- Role 1 -->
            <div class="text-center p-6 border rounded-xl hover:shadow-lg transition-shadow bg-gray-50">
                <div class="w-16 h-16 mx-auto bg-red-100 rounded-full flex items-center justify-center text-red-600 mb-4">
                    <i data-lucide="shield" class="w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Admin</h3>
                <p class="text-sm text-gray-500 mt-2">User management, global settings configuration, and system-wide audit logs.</p>
            </div>
            <!-- Role 2 -->
            <div class="text-center p-6 border rounded-xl hover:shadow-lg transition-shadow bg-gray-50">
                <div class="w-16 h-16 mx-auto bg-green-100 rounded-full flex items-center justify-center text-green-600 mb-4">
                    <i data-lucide="cross" class="w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Pharmacist</h3>
                <p class="text-sm text-gray-500 mt-2">Record usage transactions, view critical stock alerts, and manage expiry.</p>
            </div>
            <!-- Role 3 -->
            <div class="text-center p-6 border rounded-xl hover:shadow-lg transition-shadow bg-gray-50">
                <div class="w-16 h-16 mx-auto bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mb-4">
                    <i data-lucide="shopping-bag" class="w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Procurement</h3>
                <p class="text-sm text-gray-500 mt-2">Manage suppliers, approve purchase orders, and analyze lead time performance.</p>
            </div>
            <!-- Role 4 -->
            <div class="text-center p-6 border rounded-xl hover:shadow-lg transition-shadow bg-gray-50">
                <div class="w-16 h-16 mx-auto bg-yellow-100 rounded-full flex items-center justify-center text-yellow-600 mb-4">
                    <i data-lucide="package" class="w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Warehouse</h3>
                <p class="text-sm text-gray-500 mt-2">Receive stock, manage batch locations, and perform physical stock adjustments.</p>
            </div>
        </div>
    </div>
</section>

<!-- Tech Stack Section -->
<section id="tech" class="py-16 bg-gray-900 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
            <h2 class="text-2xl font-bold">Technical Architecture</h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-8 text-center items-center opacity-70">
            <div>
                <span class="text-4xl font-bold block mb-1">PHP</span>
                <span class="text-xs">Core Backend</span>
            </div>
            <div>
                <span class="text-4xl font-bold block mb-1">MySQL</span>
                <span class="text-xs">Database</span>
            </div>
            <div>
                <span class="text-2xl font-bold block mb-1">Tailwind</span>
                <span class="text-xs">CSS Framework</span>
            </div>
            <div>
                <span class="text-2xl font-bold block mb-1">Chart.js</span>
                <span class="text-xs">Visualization</span>
            </div>
            <div>
                <span class="text-2xl font-bold block mb-1">PHPMailer</span>
                <span class="text-xs">Notifications</span>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 pt-12 pb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
            <div class="col-span-1 md:col-span-2">
                <div class="flex items-center gap-2 mb-4">
                    <div class="bg-blue-600 p-1 rounded">
                        <i data-lucide="activity" class="h-5 w-5 text-white"></i>
                    </div>
                    <span class="font-bold text-xl text-gray-900">Smart Medi Stocks</span>
                </div>
                <p class="text-gray-500 text-sm max-w-xs">
                    Empowering healthcare providers with intelligent inventory solutions. minimizing waste, maximizing availability.
                </p>
            </div>
            <div>
                <h4 class="font-bold mb-4 text-gray-900">System</h4>
                <ul class="space-y-2 text-sm text-gray-500">
                    <li><a href="login.php" class="hover:text-blue-600">Login</a></li>
                    <li><a href="register.php" class="hover:text-blue-600">Register</a></li>
                    <li><a href="privacy.php" class="hover:text-blue-600">Privacy Policy</a></li>
                    <li><a href="terms.php" class="hover:text-blue-600">Terms of Use</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold mb-4 text-gray-900">Contact</h4>
                <ul class="space-y-2 text-sm text-gray-500">
                    <li>Lucena City, Quezon Province</li>
                    <li>Philippines</li>
                    <li>support@smartmedistock.com</li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-100 pt-8 flex flex-col md:flex-row justify-between items-center">
            <p class="text-xs text-gray-400">&copy; 2025 Smart Medi Stocks. All rights reserved.</p>
            <div class="flex space-x-4 mt-4 md:mt-0">
                <i data-lucide="twitter" class="w-4 h-4 text-gray-400 cursor-pointer hover:text-blue-500"></i>
                <i data-lucide="linkedin" class="w-4 h-4 text-gray-400 cursor-pointer hover:text-blue-700"></i>
                <i data-lucide="github" class="w-4 h-4 text-gray-400 cursor-pointer hover:text-gray-900"></i>
            </div>
        </div>
    </div>
</footer>

<script src="https://unpkg.com/lucide@latest" defer></script>
<script>
    // Mobile Menu Toggle
    function toggleMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        menu.classList.toggle('hidden');
    }

    // Initialize Icons after page load
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
</script>
</body>
</html>