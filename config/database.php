<?php
// Hosted database settings come from the deployment environment.
// Without DB_HOST, retain the existing local XAMPP connection below.
if (getenv('DB_HOST')) {
    require_once __DIR__ . '/../includes/hosted_database.php';
    $conn = createHostedDatabaseConnection();
    return;
}

// A deployment must never silently fall back to local database credentials.
if (getenv('VERCEL') || getenv('APP_ENV') === 'production') {
    throw new mysqli_sql_exception('Hosted database configuration is missing.');
}

$host = 'localhost';
$user= 'root';
$dbpassword = '';
$database = 'maintenance_request_system';

$conn=mysqli_connect($host, $user, $dbpassword, $database);
if(!$conn){
    die("Connection failed: " . mysqli_connect_error());
}
?>