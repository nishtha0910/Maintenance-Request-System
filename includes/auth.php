<?php

function hashUserPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT);
}
function canAccessRole(array $session, string $requiredRole): bool
{
    return isset($session["user_id"])
        && ($session["role"] ?? "") === $requiredRole;
}