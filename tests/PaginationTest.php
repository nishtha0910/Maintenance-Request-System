<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../includes/request_pagination.php";

final class PaginationTest extends TestCase
{
    public function testSecondPageReturnsTheNextTenRequests(): void
    {
        $requests = range(1, 25);
        $pagination = requestPagination(count($requests), "2");
        $page = array_slice($requests, $pagination["offset"], $pagination["perPage"]);

        self::assertSame(range(11, 20), $page);
        self::assertSame(3, $pagination["pages"]);
    }

    public function testLastPageContainsRemainingRequests(): void
    {
        $pagination = requestPagination(25, 3);
        self::assertSame(range(21, 25), array_slice(range(1, 25), $pagination["offset"], $pagination["perPage"]));
    }

    public function testExactlyTenRequestsHaveOnePage(): void
    {
        self::assertSame(["perPage" => 10, "pages" => 1, "page" => 1, "offset" => 0], requestPagination(10));
    }

    public function testEmptyListHasAValidFirstPage(): void
    {
        self::assertSame(["perPage" => 10, "pages" => 1, "page" => 1, "offset" => 0], requestPagination(0, 2));
    }

    public function testElevenRequestsHaveTwoPages(): void
    {
        $pagination = requestPagination(11, 2);
        self::assertSame(2, $pagination["pages"]);
        self::assertSame([11], array_slice(range(1, 11), $pagination["offset"], $pagination["perPage"]));
    }

    public function testInvalidPageInputsUseTheFirstPage(): void
    {
        foreach ([null, "", "abc", "1.5", "0", "-1", ["2"], "999999999999999999999999"] as $input) {
            $pagination = requestPagination(25, $input);
            self::assertSame(1, $pagination["page"]);
            self::assertSame(0, $pagination["offset"]);
        }
    }

    public function testPageBeyondTheEndUsesTheLastPage(): void
    {
        $pagination = requestPagination(25, "99");
        self::assertSame(3, $pagination["page"]);
        self::assertSame(20, $pagination["offset"]);
    }
}