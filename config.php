<?php

define('APP_NAME', 'HudumaLink');
define('APP_TAGLINE', 'Public Service Request System');
define('APP_SHORT', 'HL');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'hudumalink');
define('DB_USER', 'root'); 
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_UPLOAD_BYTES', 4 * 1024 * 1024);

define('MAIL_ENABLED', true);
// Mailtrap SMTP configuration for email testing
define('MAIL_HOST', 'sandbox.smtp.mailtrap.io');
define('MAIL_PORT', 2525);
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_USER', ''); // Your Mailtrap username
define('MAIL_PASS', ''); // Your Mailtrap password
define('MAIL_FROM', 'noreply@hudumalink.go.ke');
define('MAIL_FROM_NAME', APP_NAME);

define('SMS_ENABLED', true);
define('AT_SANDBOX', true);
define('AT_USERNAME', 'sandbox');
define('AT_API_KEY', ''); // Africa's Talking API key
define('AT_SENDER_ID', '');


$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if (preg_match('#/(admin)$#', $scriptDir)) {
    $scriptDir = dirname($scriptDir);
}
define('BASE_URL', rtrim($scriptDir, '/'));

date_default_timezone_set('Africa/Nairobi');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
