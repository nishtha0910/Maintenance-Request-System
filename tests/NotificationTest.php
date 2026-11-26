<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../includes/request_email_notifications.php";

final class NotificationTest extends TestCase
{
    private function connectionWithAdmins(array $admins): mysqli
    {
        $result = $this->createMock(mysqli_result::class);
        $result->expects(self::exactly(count($admins) + 1))
            ->method("fetch_assoc")->willReturnOnConsecutiveCalls(...[...$admins, null]);
        $result->expects(self::once())->method("free");

        $connection = $this->createMock(mysqli::class);
        $connection->expects(self::once())->method("query")
            ->with("SELECT user_id, email FROM users WHERE role = 'admin'")
            ->willReturn($result);
        return $connection;
    }

    public function testEveryAdministratorReceivesTheNewRequestDetails(): void
    {
        $connection = $this->connectionWithAdmins([
            ["user_id" => 1, "email" => "first@example.test"],
            ["user_id" => 2, "email" => "second@example.test"],
        ]);
        $sent = [];
        emailAdminsAboutNewRequest($connection, 42, "Broken sink", "High",
            function ($email, $subject, $body) use (&$sent): void {
                $sent[] = [$email, $subject, $body];
            });
        self::assertSame(["first@example.test", "second@example.test"], array_column($sent, 0));
        foreach ($sent as [$email, $subject, $body]) {
            self::assertSame("New maintenance request #42", $subject);
            self::assertStringContainsString("#42", $body);
            self::assertStringContainsString("Broken sink", $body);
            self::assertStringContainsString("High", $body);
        }
    }

    public function testNoAdministratorsMeansNoEmailIsSent(): void
    {
        $connection = $this->connectionWithAdmins([]);
        $calls = 0;
        emailAdminsAboutNewRequest($connection, 42, "Sink", "Low",
            function () use (&$calls): void { $calls++; });
        self::assertSame(0, $calls);
    }

    public function testRequestTextIsHtmlEncodedInEmail(): void
    {
        $connection = $this->connectionWithAdmins([["user_id" => 1, "email" => "admin@example.test"]]);
        $message = "";
        emailAdminsAboutNewRequest($connection, 42, '<script>alert("x")</script> & sink', "<High>",
            function ($email, $subject, $body) use (&$message): void { $message = $body; });
        self::assertStringNotContainsString("<script>", $message);
        self::assertStringContainsString("&lt;script&gt;", $message);
        self::assertStringContainsString("&amp; sink", $message);
        self::assertStringContainsString("&lt;High&gt;", $message);
    }

    public function testFailedEmailDoesNotPreventOtherAdminNotifications(): void
    {
        $connection = $this->connectionWithAdmins([
            ["user_id" => 1, "email" => "first@example.test"],
            ["user_id" => 2, "email" => "second@example.test"],
        ]);
        $attempts = [];
        $logFile = tempnam(sys_get_temp_dir(), "notification-test-");
        $previousLog = ini_get("error_log");
        ini_set("error_log", $logFile);
        try {
            emailAdminsAboutNewRequest($connection, 42, "Sink", "Low",
                function ($email) use (&$attempts): void {
                    $attempts[] = $email;
                    if ($email === "first@example.test") {
                        throw new RuntimeException("Simulated SMTP failure");
                    }
                });
            self::assertSame(["first@example.test", "second@example.test"], $attempts);
            self::assertStringContainsString("Notification email failed for admin #1 on request #42", file_get_contents($logFile));
        } finally {
            ini_set("error_log", $previousLog);
            unlink($logFile);
        }
    }
}