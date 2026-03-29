<?php
// --- Application Secrets / API Keys ---
// Copy this file to config.php and replace the placeholder values with real credentials.
// config.php is gitignored and should NEVER be committed.

// Google Gemini API
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent');

// SMTP Mail Settings
define('SMTP_HOST',     'mail.yourdomain.com');
define('SMTP_USERNAME', 'alerts@yourdomain.com');
define('SMTP_PASSWORD', 'YOUR_SMTP_PASSWORD_HERE');
define('SMTP_PORT',     465);
define('SMTP_FROM',     'alerts@yourdomain.com');
define('SMTP_FROM_NAME','Smart Medi Stocks Alerts');
