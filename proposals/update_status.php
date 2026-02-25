<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$id = $_GET['id'] ?? 0;
$status = $_GET['status'] ?? '';

$allowed_statuses = ['draft', 'sent', 'accepted', 'rejected'];

if (in_array($status, $allowed_statuses) && $id > 0) {
    $stmt = $pdo->prepare("UPDATE proposals SET status = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$status, $id, $user_id]);
    
    // Auto-activate project if proposal is accepted
    if ($status === 'accepted') {
        $stmt = $pdo->prepare("UPDATE projects SET status = 'in_progress' WHERE proposal_id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
    }

    $ref = $_GET['ref'] ?? 'generate';
    if ($ref === 'index') {
        header("Location: index.php?success=status_updated");
    } else {
        header("Location: generate.php?id=$id&status_updated=true");
    }
    exit();
}

header("Location: index.php");
exit();
