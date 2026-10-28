<?php
// Uses only a connection-local TEMPORARY table; no real accounts or tables change.
require_once __DIR__ . '/../includes/DatabaseSessionHandler.php';
require __DIR__ . '/../config/database.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->query('CREATE TEMPORARY TABLE app_sessions (
    session_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    session_data MEDIUMBLOB NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    INDEX idx_session_expiry (expires_at)
) ENGINE=InnoDB');
$handler = new DatabaseSessionHandler($conn);
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
try {
    check(!$handler->validateId('missing'), 'Unknown session was accepted.');
    check($handler->write('first', 'user_id|i:17;'), 'Write failed.');
    $handler->close();
    $nextRequest = new DatabaseSessionHandler($conn);
    check($nextRequest->read('first') === 'user_id|i:17;', 'Session did not persist across handlers.');
    check($nextRequest->validateId('first'), 'Existing session was rejected.');
    check($nextRequest->updateTimestamp('first', 'user_id|i:17;role|s:5:"admin";'), 'Refresh failed.');
    check($nextRequest->destroy('first'), 'Destroy failed.');
    check(!$nextRequest->validateId('first'), 'Destroyed session remained valid.');
    $nextRequest->close();
    check($handler->write('regenerated', 'user_id|i:17;'), 'New session ID write failed.');
    $handler->close();
    $conn->query('UPDATE app_sessions SET expires_at = 1');
    check(!$handler->validateId('regenerated'), 'Expired session was accepted.');
    check($handler->gc(1800) === 1, 'Expired session was not collected.');
    echo "Session lifecycle checks passed: $checks\n";
} finally {
    $handler->close();
    if (isset($nextRequest)) { $nextRequest->close(); }
    $conn->close(); // Also discards the temporary table.
}
