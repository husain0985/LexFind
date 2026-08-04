<?php
/**
 * LexFind - Database connection
 * from the defaults (root user, no password, port 3306).
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'lexfind_db');
define('DB_USER', 'root');
define('DB_PASS', '468751');
date_default_timezone_set('Asia/Dhaka');
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            // Make MySQL's own clock agree with PHP's (Asia/Dhaka, UTC+6) above.
            // Without this, InfinityFree's MySQL server (often defaulted to UTC
            // or another timezone) and PHP can disagree, making every displayed
            // date('...', strtotime(...)) call across the app off by a fixed
            // number of hours — this is what caused the "booking expired" bug
            // and the contact-message timestamps being off by 1 hour.
            $pdo->exec("SET time_zone = '+06:00'");
        } catch (PDOException $e) {
            error_log('[LexFind DB] Connection failed: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
               . '<title>Something went wrong</title>'
               . '<style>body{font-family:system-ui,sans-serif;background:#f7f5f0;color:#2a2a2a;'
               . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px}'
               . '.box{max-width:420px}h1{font-size:1.4rem;margin-bottom:8px}p{color:#666;line-height:1.5}</style>'
               . '</head><body><div class="box">'
               . '<h1>Something went wrong</h1>'
               . '<p>We\'re having trouble connecting right now. Please try again in a few minutes.</p>'
               . '</div></body></html>';
            exit;
        }
    }
    return $pdo;
}