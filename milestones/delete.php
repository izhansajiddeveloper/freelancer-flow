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
    $stmt = $pdo->prepare("SELECT project_id FROM milestones WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $m = $stmt->fetch();

    if ($m) {
        $stmt = $pdo->prepare("DELETE FROM milestones WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        
        $project_id = $m['project_id'];
        // Redirect back to where they came from
        if (isset($_SERVER['HTTP_REFERER'])) {
            header("Location: " . $_SERVER['HTTP_REFERER']);
        } else {
            header("Location: ../projects/view.php?id=$project_id&milestone=deleted");
        }
        exit();
    }
}

header("Location: ../projects/index.php");
exit();
