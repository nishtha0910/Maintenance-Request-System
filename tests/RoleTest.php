<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../includes/auth.php";

class RoleTest extends TestCase
{
    public function testTenantCannotAccessAdminPage(): void
    {
        $session = [
            "user_id" => 1,
            "role" => "tenant"
        ];

        $this->assertFalse(canAccessRole($session, "admin"));
    }

    public function testAdminCanAccessAdminPage(): void
    {
        $session = [
            "user_id" => 2,
            "role" => "admin"
        ];

        $this->assertTrue(canAccessRole($session, "admin"));
    }

    public function testTenantCanAccessTenantPage(): void
    {
        $session = [
            "user_id" => 1,
            "role" => "tenant"
        ];

        $this->assertTrue(canAccessRole($session, "tenant"));
    }

    public function testLoggedOutUserCannotAccessAdminPage(): void
    {
        $this->assertFalse(canAccessRole([], "admin"));
    }

    public function testRoleWithoutLoginCannotGrantAccess(): void
    {
        $session = ["role" => "admin"];

        $this->assertFalse(canAccessRole($session, "admin"));
    }

    public function testUnknownRoleCannotAccessAdminPage(): void
    {
        $session = [
            "user_id" => 3,
            "role" => "unknown"
        ];

        $this->assertFalse(canAccessRole($session, "admin"));
    }
}