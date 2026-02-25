<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$contract_id = $_GET['id'] ?? 0;
$status = $_GET['status'] ?? '';
$ref = $_GET['ref'] ?? 'index';

if ($contract_id && $status) {
    // Validate status
    $allowed_statuses = ['draft', 'sent', 'signed', 'cancelled'];
    if (in_array($status, $allowed_statuses)) {
        $stmt = $pdo->prepare("UPDATE contracts SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, $contract_id, $user_id]);
    }
}

if ($ref === 'view') {
    header("Location: view.php?id=$contract_id");
} else {
    header("Location: index.php?success=$status");
}
exit();
