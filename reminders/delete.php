<?php
/**
 * Delete Reminder
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();

$id = intval($_GET['id'] ?? 0);

if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM reminders WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        
        header("Location: index.php?success=deleted");
        exit();
    } catch (Exception $e) {
        header("Location: index.php?error=delete_failed");
        exit();
    }
}

header("Location: index.php");
exit();
