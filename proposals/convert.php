<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$proposal_id = $_GET['id'] ?? 0;

if (!$proposal_id) {
    header("Location: index.php");
    exit();
}

// Fetch proposal details
$stmt = $pdo->prepare("SELECT * FROM proposals WHERE id = ? AND user_id = ? AND status = 'accepted'");
$stmt->execute([$proposal_id, $user_id]);
$proposal = $stmt->fetch();

if (!$proposal) {
    // Cannot convert if not accepted or not yours
    header("Location: index.php?error=not_accepted");
    exit();
}

// Check if already converted (optional, but good)
$stmt = $pdo->prepare("SELECT id FROM projects WHERE client_id = ? AND project_title = ? AND user_id = ?");
$stmt->execute([$proposal['client_id'], $proposal['project_title'], $user_id]);
if ($stmt->fetch()) {
    header("Location: ../projects/index.php?msg=already_converted");
    exit();
}

try {
    // Insert into projects
    $stmt = $pdo->prepare("INSERT INTO projects (user_id, client_id, proposal_id, project_title, description, total_budget, status, start_date) VALUES (?, ?, ?, ?, ?, ?, 'active', CURDATE())");
    $stmt->execute([
        $user_id,
        $proposal['client_id'],
        $proposal_id,
        $proposal['project_title'],
        $proposal['project_scope'],
        $proposal['price']
    ]);
    
    $project_id = $pdo->lastInsertId();
    
    header("Location: ../projects/view.php?id=$project_id&from=proposal");
    exit();
} catch (PDOException $e) {
    die("Conversion Error: " . $e->getMessage());
}
