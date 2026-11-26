<?php

use PHPUnit\Framework\TestCase;

final class RequestUpdateAccessTest extends TestCase
{
    private function callUpdateEndpoint(array $session): array
    {
        $endpoint = realpath(__DIR__ . "/../api/update_request.php");
        $script = 'ini_set("session.use_cookies", "0");'
            . 'session_cache_limiter("");'
            . 'session_save_path(sys_get_temp_dir());'
            . 'session_id("phpunit-" . bin2hex(random_bytes(16)));'
            . 'session_start();'
            . '$_SESSION = ' . var_export($session, true) . ';'
            . 'session_write_close();'
            . '$_SERVER["REQUEST_METHOD"] = "PUT";'
            . '$_SERVER["CONTENT_TYPE"] = "application/json";'
            . '$_GET["id"] = "1";'
            . 'register_shutdown_function(function () {'
            . 'echo "\nHTTP_STATUS=" . http_response_code();'
            . 'if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }'
            . '});'
            . 'require ' . var_export($endpoint, true) . ';';

        $process = proc_open([PHP_BINARY, "-r", $script], [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ], $pipes);
        self::assertIsResource($process);
        fwrite($pipes[0], '{"status":"Completed"}');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        self::assertSame("", $errors);

        [$body, $status] = explode("\nHTTP_STATUS=", $output);
        return [(int) $status, json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
    }

    public function testTenantStatusUpdateIsRejectedByTheEndpoint(): void
    {
        [$status, $body] = $this->callUpdateEndpoint(["user_id" => 1, "role" => "tenant"]);
        self::assertSame(403, $status);
        self::assertSame(["error" => "Access denied."], $body);
    }

    public function testLoggedOutStatusUpdateRequiresLogin(): void
    {
        [$status, $body] = $this->callUpdateEndpoint([]);
        self::assertSame(401, $status);
        self::assertSame(["error" => "Please log in first."], $body);
    }

    public function testUnknownRoleCannotUpdateStatus(): void
    {
        [$status, $body] = $this->callUpdateEndpoint(["user_id" => 1, "role" => "unknown"]);
        self::assertSame(403, $status);
        self::assertSame(["error" => "Access denied."], $body);
    }
}