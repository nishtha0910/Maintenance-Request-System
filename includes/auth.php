<?php

function hashUserPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT);
}