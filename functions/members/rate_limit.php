<?php
// Sliding-window rate limits stored in MySQL (rate_limit_events).
// Keys are hashed so the table does not collect IPs, emails or phone numbers.

/**
 * True if $action for $key has already happened $max times in the last $windowSeconds.
 */
function rate_limited($action, $key, $max, $windowSeconds)
{
    $row = db_one(
        "SELECT COUNT(*) AS n FROM rate_limit_events
         WHERE action = ? AND key_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
        [$action, hash('sha256', $action . '|' . $key), (int) $windowSeconds]
    );
    return (int) $row['n'] >= $max;
}

function rate_hit($action, $key)
{
    executeQuery(
        "INSERT INTO rate_limit_events (action, key_hash) VALUES (?, ?)",
        [$action, hash('sha256', $action . '|' . $key)]
    );
    // Occasional clean-up of old events.
    if (random_int(1, 200) === 1) {
        executeQuery("DELETE FROM rate_limit_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
    }
}
