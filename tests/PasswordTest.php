<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../includes/auth.php";

class PasswordTest extends TestCase
{
    public function testPasswordHashing(): void
    {
        // Arrange: choose a test password.
        $password = "Password123";

        // Act: call the function used by registration.
        $hash = hashUserPassword($password);

        // Assert: check the expected results.
        $this->assertNotSame($password, $hash);

        $this->assertTrue(
            password_verify($password, $hash)
        );

        $this->assertFalse(
            password_verify("WrongPassword999", $hash)
        );
    }
}