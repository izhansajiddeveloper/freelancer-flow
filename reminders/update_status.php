<?php
/**
 * Update Reminder Status
 * Toggles reminders between pending, sent, and completed
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();

$id = intval($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';

// Allowed statuses for security
$allowed = ['pending', 'sent', 'completed'];

if ($id && in_array($status, $allowed)) {
    try {
        $stmt = $pdo->prepare("UPDATE reminders SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, $id, $user_id]);
        
        header("Location: index.php?success=updated");
        exit();
    } catch (Exception $e) {
        error_log("FreelanceFlow Error: " . $e->getMessage());
        header("Location: index.php?error=update_failed");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
