<?php

function requestPagination(int $total, mixed $requestedPage = 1): array
{
    $perPage = 10;
    $pages = max(1, (int) ceil($total / $perPage));
    $validatedPage = filter_var($requestedPage, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    $page = min($validatedPage ?: 1, $pages);

    return [
        "perPage" => $perPage,
        "pages" => $pages,
        "page" => $page,
        "offset" => ($page - 1) * $perPage,
    ];
}