<?php
/**
 * LexFind - one-time setup script.
 *
 * Run this ONCE in your browser (e.g. http://localhost/lexfind/setup.php)
 * right after importing database/schema.sql. It (re)hashes the demo
 * password "Password123" for every seeded account using PHP's own
 * password_hash(), so the demo logins work regardless of PHP version.
 *
 * You can delete this file after running it once.
 */
require_once __DIR__ . '/config/db.php';

$demoEmails = [
    'admin@lexfind.com.bd',
    'john.doe@email.com',
    'rahim@law.com',
    'sara.ahmed@law.com',
    'nisha@law.com',
    'karim@law.com',
    'ali@law.com',
    'maya@law.com',
];

try {
    $db = getDB();
    $hash = password_hash('Password123', PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
    $count = 0;
    foreach ($demoEmails as $email) {
        $stmt->execute([$hash, $email]);
        if ($stmt->rowCount() > 0) $count++;
    }
    echo "<h2>LexFind setup complete</h2>";
    echo "<p>$count demo account(s) updated. You can now log in with:</p>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin@lexfind.com.bd / Password123</li>";
    echo "<li><strong>Client:</strong> john.doe@email.com / Password123</li>";
    echo "<li><strong>Advisor:</strong> rahim@law.com / Password123 (and 5 others - see database/schema.sql)</li>";
    echo "</ul>";
    echo "<p>You can now delete <code>setup.php</code>. <a href='index.php'>Go to the site &rarr;</a></p>";
} catch (Exception $e) {
    echo '<h2>Setup failed</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Make sure you have imported <code>database/schema.sql</code> and that config/db.php has the right credentials.</p>';
}
