<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$id = $_GET['id'] ?? 0;

if ($id) {
    try {
        // Verify ownership before deleting
        $stmt = $pdo->prepare("SELECT id FROM invoices WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        
        if ($stmt->fetch()) {
            $deleteStmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
            $deleteStmt->execute([$id]);
        }
    } catch (PDOException $e) {
        // Handle error silently or log
    }
}

header("Location: index.php?success=deleted");
exit();
