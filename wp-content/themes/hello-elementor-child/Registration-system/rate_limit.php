<?php
require_once 'config.php';

function isRateLimited($identifier, $action, $limit = 5, $cooldownSeconds = 60) {
    global $conn;

    $stmt = $conn->prepare("SELECT attempt_count, last_attempt_at FROM rate_limits WHERE identifier = ? AND action_type = ?");
    $stmt->bind_param("ss", $identifier, $action);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($attempts, $last_attempt_at);

    $now = new DateTime();
    if ($stmt->num_rows > 0) {
        $stmt->fetch();
        $lastAttemptTime = new DateTime($last_attempt_at);
        $interval = $now->getTimestamp() - $lastAttemptTime->getTimestamp();

        if ($interval < $cooldownSeconds && $attempts >= $limit) {
            return true;
        }

        $updateStmt = $conn->prepare(
            $interval >= $cooldownSeconds
                ? "UPDATE rate_limits SET attempt_count = 1, last_attempt_at = NOW() WHERE identifier = ? AND action_type = ?"
                : "UPDATE rate_limits SET attempt_count = attempt_count + 1, last_attempt_at = NOW() WHERE identifier = ? AND action_type = ?"
        );
        $updateStmt->bind_param("ss", $identifier, $action);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        $insertStmt = $conn->prepare("INSERT INTO rate_limits (identifier, action_type, attempt_count, last_attempt_at) VALUES (?, ?, 1, NOW())");
        $insertStmt->bind_param("ss", $identifier, $action);
        $insertStmt->execute();
        $insertStmt->close();
    }

    $stmt->close();
    return false;
}
?>
