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
        // Only delete if the project belongs to the current user
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        
        // Return to projects list
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        die("Error deleting project: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit();
}
?>
