<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../includes/login_security.php";

final class AccountLockoutTest extends TestCase
{
    public function testNoFailuresDoesNotLockAccount(): void
    {
        self::assertFalse(shouldLockAccount([], 1000));
    }

    public function testFourRecentFailuresDoNotLockAccount(): void
    {
        self::assertFalse(
            shouldLockAccount([700, 800, 900, 1000], 1000)
        );
    }

    public function testFiveRecentFailuresLockAccount(): void
    {
        self::assertTrue(
            shouldLockAccount([600, 700, 800, 900, 1000], 1000)
        );
    }

    public function testMoreThanFiveRecentFailuresLockAccount(): void
    {
        self::assertTrue(
            shouldLockAccount([500, 600, 700, 800, 900, 1000], 1000)
        );
    }

    public function testOlderFailuresDoNotCount(): void
    {
        self::assertFalse(
            shouldLockAccount([100, 200, 300, 900, 1000], 1000)
        );
    }

    public function testFailureExactlyTenMinutesOldDoesNotCount(): void
    {
        self::assertFalse(
            shouldLockAccount([400, 700, 800, 900, 1000], 1000)
        );
    }

    public function testFailureJustInsideWindowCounts(): void
    {
        self::assertTrue(
            shouldLockAccount([401, 700, 800, 900, 1000], 1000)
        );
    }

    public function testFutureTimestampsDoNotCount(): void
    {
        self::assertFalse(
            shouldLockAccount([700, 800, 900, 1000, 1001], 1000)
        );
    }
}