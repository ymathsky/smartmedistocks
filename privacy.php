<?php
// Filename: privacy.php
// Public page - does not require login, but starts session for consistency
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Note: We are NOT including the standard header.php because it forces login.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Smart Medi Stocks</title>
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
        /* Consistent styling for policy content */
        .policy-container h1, .policy-container h2, .policy-container h3 {
            margin-top: 1.5em;
            margin-bottom: 0.5em;
            font-weight: bold;
        }
        .policy-container h1 { font-size: 1.875rem; /* text-3xl */ }
        .policy-container h2 { font-size: 1.5rem; /* text-2xl */ }
        .policy-container h3 { font-size: 1.25rem; /* text-xl */ }
        .policy-container p { margin-bottom: 1em; }
        .policy-container ul { list-style: disc; margin-left: 2em; margin-bottom: 1em; }
        .policy-container li { margin-bottom: 0.5em; }
        .policy-container strong { font-weight: bold; }
        .policy-container a { color: #2563EB; text-decoration: underline; } /* blue-600 */
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
        <div class="bg-white p-8 rounded-lg shadow-md max-w-4xl mx-auto policy-container">

            <h1>Privacy Policy for <a href="https://smartmedistock.com/">https://smartmedistock.com/</a></h1>

            <p><strong>Last Updated: October 2025</strong></p>

            <p>SmartMediStocks ("us", "we", or "our") operates the <a href="https://smartmedistock.com/">https://smartmedistock.com/</a> website (the "Service").</p>

            <p>This page informs you of our policies regarding the collection, use, and disclosure of personal data when you use our Service and the choices you have associated with that data.</p>

            <p>We use your data to provide and improve the Service. By using the Service, you agree to the collection and use of information in accordance with this policy.</p>

            <h3>1. Information Collection and Use</h3>
            <p>We collect several types of information for various purposes to provide and improve our Service:</p>
            <ul>
                <li><strong>Account Information:</strong> When you register for an account, we collect information such as your name, username, contact number, and address. This information is used for account management and communication.</li>
                <li><strong>Operational Data:</strong> The Service primarily processes inventory data, transaction history, supplier information, and purchase orders. This data is essential for the functionality of the inventory management system.</li>
                <li><strong>Usage Data:</strong> We may collect information on how the Service is accessed and used ("Usage Data"). This Usage Data may include information such as your computer's Internet Protocol address (e.g., IP address), browser type, browser version, the pages of our Service that you visit, the time and date of your visit, the time spent on those pages, and other diagnostic data.</li>
            </ul>
            <p><strong>Note:</strong> We do not collect or store sensitive patient health information or personal medical records.</p>

            <h3>2. Use of Data</h3>
            <p>SmartMediStocks uses the collected data for various purposes:</p>
            <ul>
                <li>To provide and maintain the Service</li>
                <li>To manage user accounts and provide support</li>
                <li>To monitor the usage of the Service</li>
                <li>To detect, prevent, and address technical issues</li>
                <li>To generate anonymized analytical insights for system improvement</li>
                <li>To comply with legal obligations</li>
            </ul>

            <h3>3. Data Security</h3>
            <p>The security of your data is important to us. We implement appropriate technical and organizational measures to protect your data from unauthorized access, alteration, disclosure, or destruction. However, remember that no method of transmission over the Internet or method of electronic storage is 100% secure.</p>

            <h3>4. Data Retention</h3>
            <p>We will retain your Account Information for as long as your account is active or as needed to provide you services. Operational Data will be retained as necessary for system functionality, analysis, and regulatory compliance.</p>

            <h3>5. Disclosure of Data</h3>
            <p>We do not sell, trade, or otherwise transfer your identifiable information to outside parties except under the following circumstances:</p>
            <ul>
                <li><strong>Legal Requirements:</strong> We may disclose your data if required to do so by law or in response to valid requests by public authorities (e.g., a court or a government agency).</li>
                <li><strong>Service Providers:</strong> We may employ third-party companies and individuals to facilitate our Service ("Service Providers"), provide the Service on our behalf, or assist us in analyzing how our Service is used. These third parties have access to your data only to perform these tasks on our behalf and are obligated not to disclose or use it for any other purpose.</li>
            </ul>

            <h3>6. Your Data Protection Rights (Philippines Data Privacy Act of 2012)</h3>
            <p>As a user based in the Philippines, you have certain data protection rights under the Data Privacy Act of 2012 (RA 10173). SmartMediStocks aims to take reasonable steps to allow you to correct, amend, delete, or limit the use of your Personal Data associated with your account.</p>
            <p>You have the right:</p>
            <ul>
                <li>To access and receive a copy of the personal data we hold about you</li>
                <li>To rectify any personal data held about you that is inaccurate</li>
                <li>To request the deletion of personal data held about you</li>
                <li>To object to processing of your personal data</li>
            </ul>
            <p>Please note that operational data related to inventory and transactions may be subject to different retention and modification rules due to system integrity and audit requirements.</p>
            <p>To exercise these rights, please contact your system administrator or our support team.</p>

            <h3>7. Changes to This Privacy Policy</h3>
            <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page.</p>
            <p>You are advised to review this Privacy Policy periodically for any changes. Changes to this Privacy Policy are effective when they are posted on this page.</p>

            <h3>8. Contact Us</h3>
            <p>If you have any questions about this Privacy Policy, please contact us:</p>
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
