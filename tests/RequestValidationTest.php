<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../includes/request_validation.php";

final class RequestValidationTest extends TestCase
{
    public function testValidRequestAcceptsEachAllowedPriority(): void
    {
        foreach (["Low", "Medium", "High"] as $priority) {
            $errors = validateMaintenanceRequest(
                "Broken sink",
                "Water is leaking from the kitchen sink.",
                $priority
            );

            self::assertSame([], $errors);
        }
    }

    public function testBlankTitleIsRejected(): void
    {
        $errors = validateMaintenanceRequest(
            "",
            "Water is leaking.",
            "Medium"
        );

        self::assertArrayHasKey("title", $errors);
    }

    public function testWhitespaceOnlyTitleIsRejected(): void
    {
        $errors = validateMaintenanceRequest(
            "   ",
            "Water is leaking.",
            "Medium"
        );

        self::assertArrayHasKey("title", $errors);
    }

    public function testTitleOver150CharactersIsRejected(): void
    {
        $errors = validateMaintenanceRequest(
            str_repeat("A", 151),
            "Water is leaking.",
            "Medium"
        );

        self::assertArrayHasKey("title", $errors);
    }

    public function testBlankDescriptionIsRejected(): void
    {
        $errors = validateMaintenanceRequest(
            "Broken sink",
            "",
            "Medium"
        );

        self::assertArrayHasKey("description", $errors);
    }

    public function testDescriptionOver5000CharactersIsRejected(): void
    {
        $errors = validateMaintenanceRequest(
            "Broken sink",
            str_repeat("A", 5001),
            "Medium"
        );

        self::assertArrayHasKey("description", $errors);
    }

    public function testMaximumAllowedLengthsAreAccepted(): void
    {
        $errors = validateMaintenanceRequest(
            str_repeat("A", 150),
            str_repeat("B", 5000),
            "High"
        );

        self::assertSame([], $errors);
    }

    public function testUnsupportedPriorityIsRejected(): void
    {
        $errors = validateMaintenanceRequest(
            "Broken sink",
            "Water is leaking.",
            "Urgent"
        );

        self::assertArrayHasKey("priority", $errors);
    }

    public function testNonStringInputsAreRejected(): void
    {
        $errors = validateMaintenanceRequest(
            ["Unexpected array"],
            123,
            null
        );

        self::assertArrayHasKey("title", $errors);
        self::assertArrayHasKey("description", $errors);
        self::assertArrayHasKey("priority", $errors);
    }
}