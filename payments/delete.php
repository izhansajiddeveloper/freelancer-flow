<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();
$id = intval($_GET['id'] ?? 0);

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $pay = $stmt->fetch();

    if ($pay) {
        // Delete payment record
        $pdo->prepare("DELETE FROM payments WHERE id = ?")->execute([$id]);
    }
}

header("Location: index.php?success=deleted");
exit();
