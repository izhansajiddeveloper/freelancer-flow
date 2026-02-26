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
        // Verify ownership and then update status to 'paid' and set paid_date to now
        $stmt = $pdo->prepare("SELECT id FROM invoices WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        
        if ($stmt->fetch()) {
            $updateStmt = $pdo->prepare("UPDATE invoices SET status = 'paid', paid_date = NOW() WHERE id = ?");
            $updateStmt->execute([$id]);
        }
    } catch (PDOException $e) {
        // Log Error
    }
}

header("Location: index.php?success=paid");
exit();
