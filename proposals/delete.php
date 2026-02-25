<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$id = $_GET['id'] ?? 0;

if ($id > 0) {
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM proposals WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM proposals WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        header("Location: index.php?success=deleted");
        exit();
    }
}

header("Location: index.php");
exit();
