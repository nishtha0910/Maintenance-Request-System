<?php
// Only explicitly listed application pages can be reached over HTTP.
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$pages = [
    'login.php', 'register.php', 'logout.php', 'profile.php', 'EditProfile.php',
    'change_password.php', 'forgot_password.php', 'reset_password.php',
    'tenant_dashboard.php', 'my_requests.php', 'create_request.php',
    'request_details.php', 'admin_dashboard.php', 'admin_request.php',
    'api/login.php', 'api/register.php', 'api/profile.php',
    'api/create_requests.php', 'api/my_requests.php',
    'api/admin_requests.php', 'api/update_request.php'
];
$page = ltrim(is_string($path) ? $path : '', '/');
if ($page === '' || $page === 'index.php') {
    header('Location: /login.php', true, 302);
    exit;
}
if ($page === 'api/profile' || $page === 'api/profile/') {
    $page = 'api/profile.php';
}
if (!in_array($page, $pages, true)) {
    http_response_code(404);
    exit('Page not found.');
}
ini_set('display_errors', '0');
set_exception_handler(static function (Throwable $error): void {
    error_log('Application error: ' . $error->getMessage());
    http_response_code(503);
    header('Cache-Control: no-store');
    if (str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'The service is temporarily unavailable. Please try again later.']);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>Temporarily unavailable</h1><p>Please try again later.</p>';
    }
});
$_SERVER['SCRIPT_NAME'] = '/' . $page;
$_SERVER['PHP_SELF'] = '/' . $page;
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require $root . '/includes/hosted_session.php';
chdir(dirname($root . '/' . $page));
require $root . '/' . $page;
