<?php

function shouldLockAccount(array $failedAttemptTimes, int $now): bool
{
    $recentFailures = 0;

    foreach ($failedAttemptTimes as $attemptTime) {
        // Count failures within the previous 10 minutes.
        if ($attemptTime > $now - 600 && $attemptTime <= $now) {
            $recentFailures++;
        }
    }

    return $recentFailures >= 5;
}