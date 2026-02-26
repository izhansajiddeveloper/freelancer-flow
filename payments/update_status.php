<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();

$id     = intval($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';
$allowed = ['pending', 'completed', 'failed'];

if ($id && in_array($status, $allowed)) {
    // Fetch payment
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $pay = $stmt->fetch();

    if ($pay) {
        // Update payment status
        $pdo->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$status, $id]);

        // Sync invoice status
        if ($status === 'completed') {
            $pdo->prepare("UPDATE invoices SET status = 'paid', paid_date = NOW() WHERE id = ? AND user_id = ?")
                ->execute([$pay['invoice_id'], $user_id]);
        } elseif ($status === 'failed') {
            $pdo->prepare("UPDATE invoices SET status = 'overdue' WHERE id = ? AND user_id = ?")
                ->execute([$pay['invoice_id'], $user_id]);
        } elseif ($status === 'pending') {
            $pdo->prepare("UPDATE invoices SET status = 'sent', paid_date = NULL WHERE id = ? AND user_id = ?")
                ->execute([$pay['invoice_id'], $user_id]);
        }
    }
}

header("Location: index.php?success=updated");
exit();
