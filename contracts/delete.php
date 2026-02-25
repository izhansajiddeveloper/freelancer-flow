<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$contract_id = $_GET['id'] ?? 0;

if ($contract_id) {
    // Delete contract
    $stmt = $pdo->prepare("DELETE FROM contracts WHERE id = ? AND user_id = ?");
    $stmt->execute([$contract_id, $user_id]);

    // Also update project to remove contract link if applicable (optional depending on logic)
    // Note: The schema doesn't have a contract_id in projects yet, but if it did, we'd clear it.
}

header("Location: index.php?success=deleted");
exit();
