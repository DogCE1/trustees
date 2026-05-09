<?php
//Rata limit class
//Returns true if user isn't over the limit

function rate_limit_exceeded(mysqli $conn, string $action, string $key_value, int $max, int $window_sec): bool {
    $stmt = $conn-> prepare("SELECT COUNT(*) AS c FROM rate_events WHERE action = ? AND key_value = ? AND created_at > (NOW() - INTERVAL ? SECOND)");
    $stmt->bind_param("ssi", $action, $key_value, $window_sec);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $count >= $max;
}

function rate_limit_log(mysqli $conn, string $action, string $key_value): void {
    $stmt = $conn->prepare("INSERT INTO rate_events (action, key_value) VALUES (?, ?)");
    if($stmt){
        $stmt->bind_param("ss", $action, $key_value);
        try{ $stmt ->execute();} catch (mysqli_sql_exception $e) {
            // Handle exception if needed
            error_log("rate_events insert failed" . $e->getMessage());
        }
        $stmt->close();
    }

    // Opportunistic cleanup: ~1% of the time, drop rows older than 1 day.
    if (random_int(1, 100) === 1) {
        try {
            $conn->query("DELETE FROM rate_events WHERE created_at < (NOW() - INTERVAL 1 DAY)");
        } catch (mysqli_sql_exception $e) {
            error_log("rate_events cleanup failed: " . $e->getMessage());
        }
    }
}
?>