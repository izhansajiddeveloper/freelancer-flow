<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();

if (!$user_id) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}
$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
        // Ensure the client belongs to the user before deleting
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        
        // We could add a flash message here if we had a helper for it
    } catch (PDOException $e) {
        // Handle error (e.g., if client has foreign key constraints like projects)
    }
}

header("Location: index.php");
exit();
?>
