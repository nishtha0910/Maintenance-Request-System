<?php
function createHostedDatabaseConnection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $dbHost = getenv('DB_HOST');
    $dbUser = getenv('DB_USER');
    $dbSecret = getenv('DB_PASSWORD');
    $dbName = getenv('DB_NAME');
    $dbPort = filter_var(getenv('DB_PORT') ?: '3306', FILTER_VALIDATE_INT);

    if (!$dbUser || !$dbSecret || !$dbName || !$dbPort || $dbPort > 65535 || $dbPort < 1) {
        throw new mysqli_sql_exception('Hosted database configuration is incomplete.');
    }

    $caPath = getenv('DB_SSL_CA_PATH') ?: dirname(__DIR__) . '/config/aiven-ca.pem';
    if (!is_readable($caPath)) {
        throw new mysqli_sql_exception('Database CA certificate is unavailable.');
    }

    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    $conn->ssl_set(null, null, $caPath, null, null);
    $conn->real_connect($dbHost, $dbUser, $dbSecret, $dbName, $dbPort, null, MYSQLI_CLIENT_SSL);
    $conn->set_charset('utf8mb4');
    return $conn;
}
