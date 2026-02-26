<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$id = $_GET['id'] ?? 0;
$status = $_GET['status'] ?? '';
$ref = $_GET['ref'] ?? 'index';

$allowed_statuses = ['draft', 'sent', 'paid', 'cancelled'];

if ($id && in_array($status, $allowed_statuses)) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM invoices WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        
        if ($stmt->fetch()) {
            if ($status === 'paid') {
                $updateStmt = $pdo->prepare("UPDATE invoices SET status = ?, paid_date = NOW() WHERE id = ?");
                $updateStmt->execute([$status, $id]);
            } else {
                $updateStmt = $pdo->prepare("UPDATE invoices SET status = ?, paid_date = NULL WHERE id = ?");
                $updateStmt->execute([$status, $id]);
            }
        }
    } catch (PDOException $e) {
        // Log Error
    }
}

$redirect = ($ref === 'view') ? "view.php?id=$id" : "index.php?success=updated";
header("Location: $redirect");
exit();
