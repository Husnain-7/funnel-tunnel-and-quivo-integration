<?php
// config.php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax', // or 'Strict'
    'use_strict_mode' => true
]);

// DB (use env vars in production)
$DB_DSN = 'mysql:host=127.0.0.1;dbname=u534957383_funnel_tunnel;charset=utf8mb4';
$DB_USER = 'u534957383_husnain7z';
$DB_PASS = '4876246@Hostinger';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($DB_DSN, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // log error securely; do not echo in prod
    die('DB connection failed');
}

// Encryption key: load from env or secure store. 32 bytes for AES-256.
define('ENCRYPTION_KEY', getenv('CREDENTIALS_KEY') ?: 'replace_with_32_byte_random_string_here');
