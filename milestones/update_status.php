<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$id = $_GET['id'] ?? 0;
$status = $_GET['status'] ?? '';

// Allowed statuses
$allowed_statuses = ['pending', 'in_progress', 'completed'];

if (in_array($status, $allowed_statuses) && $id > 0) {
    // First, fetch the project_id to redirect back correctly
    $stmt = $pdo->prepare("SELECT project_id FROM milestones WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $milestone = $stmt->fetch();

    if ($milestone) {
        $stmt = $pdo->prepare("UPDATE milestones SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, $id, $user_id]);
        
        $project_id = $milestone['project_id'];
        header("Location: ../projects/view.php?id=$project_id&milestone=updated");
        exit();
    }
}

header("Location: ../projects/index.php");
exit();
