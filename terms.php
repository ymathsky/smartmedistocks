<?php
// Filename: terms.php
// Public page - does not require login, but starts session for potential future use or consistency
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Note: We are NOT including the standard header.php because it forces login.
// We replicate the necessary HTML structure and styling here.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Use - Smart Medi Stocks</title>
    <link rel="shortcut icon" type="image/png" href="logo.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh; /* Ensure body takes full viewport height */
        }
        .main-content {
            flex-grow: 1; /* Allow content to grow and push footer down */
        }
        .terms-container h1, .terms-container h2, .terms-container h3 {
            margin-top: 1.5em;
            margin-bottom: 0.5em;
            font-weight: bold;
        }
        .terms-container h1 { font-size: 1.875rem; /* text-3xl */ }
        .terms-container h2 { font-size: 1.5rem; /* text-2xl */ }
        .terms-container h3 { font-size: 1.25rem; /* text-xl */ }
        .terms-container p { margin-bottom: 1em; }
        .terms-container ul { list-style: disc; margin-left: 2em; margin-bottom: 1em; }
        .terms-container li { margin-bottom: 0.5em; }
        .terms-container strong { font-weight: bold; }
        .terms-container a { color: #2563EB; text-decoration: underline; } /* blue-600 */
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

<!-- Basic Header -->
<header class="bg-gradient-to-r from-blue-600 to-blue-800 shadow-lg text-white">
    <div class="container mx-auto px-8 py-5 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold">SMART MEDI STOCKS</h1>
        </div>
        <div>
            <!-- Link back to login or dashboard if logged in -->
            <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                <a href="index.php" class="text-blue-100 hover:text-white">Back to Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="text-blue-100 hover:text-white">Login</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Main Content Area -->
<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 main-content">
    <div class="container mx-auto px-6 py-8">
        <div class="bg-white p-8 rounded-lg shadow-md max-w-4xl mx-auto terms-container">

            <h1>Terms of Use for <a href="https://smartmedistock.com/">https://smartmedistock.com/</a></h1>

            <p><strong>Last Updated: October 2025</strong></p>

            <p>Please read these Terms of Use ("Terms") carefully before using the <a href="https://smartmedistock.com/">https://smartmedistock.com/</a> website (the "Service") operated by SmartMediStocks ("us", "we", or "our").</p>

            <p>Your access to and use of the Service are conditioned on your acceptance of and compliance with these Terms. These Terms apply to all visitors, users, and others who access or use the Service.</p>

            <p>By accessing or using the Service, you agree to be bound by these Terms. If you disagree with any part of the terms, you may not access the Service.</p>

            <h3>1. Accounts and User Responsibilities</h3>
            <p>When creating an account, you must provide accurate and complete information. You are responsible for maintaining the confidentiality of your account credentials and for any activity under your account.</p>
            <p>You agree not to share your password or allow unauthorized access. Notify us immediately of any security breach or unauthorized use of your account.</p>
            <p>This system is intended for authorized hospital personnel, staff, and system administrators responsible for inventory management and operations.</p>

            <h3>2. Data Privacy and Confidentiality</h3>
            <p>SmartMediStocks ensures that all stored and processed data remain confidential and are used solely for operational and analytical purposes.</p>
            <p>Users agree not to disclose or misuse any data accessed through the Service. The system does not collect personal medical records or sensitive patient information.</p>
            <p>All user and transaction data are protected under applicable data privacy laws of the Republic of the Philippines, including the Data Privacy Act of 2012 (RA 10173).</p>

            <h3>3. Disclaimer</h3>
            <p>The Service provides AI-assisted insights and automation tools for inventory management. It is intended for decision support only and should not replace professional human judgment. SmartMediStocks is not liable for any direct or indirect loss resulting from reliance on AI-generated data or system recommendations.</p>

            <h3>4. Intellectual Property</h3>
            <p>All software, content, and design elements on this website are the intellectual property of SmartMediStock and are protected by copyright and applicable laws.</p>
            <p>Users may not copy, modify, distribute, or reuse any part of the Service without prior written consent.</p>

            <h3>5. Links to Other Websites</h3>
            <p>Our Service may contain links to third-party websites not operated by SmartMediStocks. We have no control over and assume no responsibility for the content, privacy policies, or practices of third-party sites or services.</p>

            <h3>6. Termination</h3>
            <p>We may suspend or terminate access to the Service without prior notice for violations of these Terms or misuse of the system.</p>
            <p>Upon termination, your right to use the Service will immediately cease.</p>

            <h3>7. Limitation of Liability</h3>
            <p>SmartMediStocks, its developers, and affiliates shall not be held liable for any indirect, incidental, or consequential damages arising from the use or inability to use the Service, including data loss, system errors, or operational disruptions.</p>

            <h3>8. Governing Law</h3>
            <p>These Terms shall be governed and construed in accordance with the laws of the Republic of the Philippines, without regard to its conflict of law provisions.</p>

            <h3>9. Changes</h3>
            <p>We reserve the right to modify or update these Terms at any time. Continued use of the Service after changes are made constitutes acceptance of the revised Terms.</p>

            <h3>10. Contact Us</h3>
            <p>If you have any questions about these Terms, please contact us at:</p>
            <p>
                <strong>SmartMediStocks Support Team</strong><br>
                Email: <a href="mailto:support@smartmedistock.com">support@smartmedistock.com</a><br>
                Website: <a href="https://smartmedistock.com/">https://smartmedistock.com/</a>
            </p>
            <p>Lucena City, Quezon Province, Philippines</p>

        </div>
    </div>
</main>

<!-- Footer (Matches login.php footer) -->
<footer class="text-center text-sm text-gray-600 py-6 mt-auto border-t border-gray-200 bg-gray-50">
    &copy; <?php echo date("Y"); ?> Smart Medi Stocks. All rights reserved. |
    <a href="terms.php" class="text-blue-600 hover:underline">Terms of Use</a> |
    <a href="privacy.php" class="text-blue-600 hover:underline">Privacy Policy</a>
</footer>

</body>
</html>
