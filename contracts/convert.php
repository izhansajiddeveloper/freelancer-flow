<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$contract_id = $_GET['id'] ?? 0;

if (!$contract_id) {
    header("Location: index.php");
    exit();
}

// Fetch contract details to verify ownership
$stmt = $pdo->prepare("SELECT * FROM contracts WHERE id = ? AND user_id = ?");
$stmt->execute([$contract_id, $user_id]);
$contract = $stmt->fetch();

if (!$contract) {
    header("Location: index.php?error=not_found");
    exit();
}

try {
    // Mark as signed
    $stmt = $pdo->prepare("UPDATE contracts SET status = 'signed' WHERE id = ?");
    $stmt->execute([$contract_id]);
    
    // Redirect back to the high-fidelity view with success
    header("Location: generate.php?id=$contract_id&success=signed");
    exit();
} catch (PDOException $e) {
    die("Error marking contract as signed: " . $e->getMessage());
}
?>
