<?php

function validateMaintenanceRequest(
    mixed $title,
    mixed $description,
    mixed $priority
): array {
    $errors = [];

    if (
        !is_string($title) ||
        trim($title) === "" ||
        mb_strlen(trim($title), "UTF-8") > 150
    ) {
        $errors["title"] = "Enter a title of 1–150 characters.";
    }

    if (
        !is_string($description) ||
        trim($description) === "" ||
        mb_strlen(trim($description), "UTF-8") > 5000
    ) {
        $errors["description"] =
            "Enter a description of 1–5,000 characters.";
    }

    if (
        !is_string($priority) ||
        !in_array($priority, ["Low", "Medium", "High"], true)
    ) {
        $errors["priority"] = "Select Low, Medium, or High priority.";
    }

    return $errors;
}