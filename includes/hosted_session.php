<?php
require_once __DIR__ . '/hosted_database.php';
require_once __DIR__ . '/DatabaseSessionHandler.php';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '100');
ini_set('session.gc_maxlifetime', '1800');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => true,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_set_save_handler(new DatabaseSessionHandler(createHostedDatabaseConnection()), true);
// Each existing page starts its session after this handler has been registered.
