<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';
require_once '../helpers/mail_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();

// Fetch pending email reminders that are due or overdue
$stmt = $pdo->prepare("
    SELECT r.*, 
           i.invoice_number, 
           p.project_title,
           u.email as user_email,
           u.full_name as user_name
    FROM reminders r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN invoices i ON r.invoice_id = i.id
    LEFT JOIN projects p ON r.project_id = p.id
    WHERE r.user_id = ? 
      AND r.status = 'pending' 
      AND r.medium = 'email' 
      AND r.reminder_date <= CURDATE()
");
$stmt->execute([$user_id]);
$due_reminders = $stmt->fetchAll();

$sent_count = 0;
foreach ($due_reminders as $rem) {
    $type_label = ucwords(str_replace('_', ' ', $rem['reminder_type']));
    $target = !empty($rem['invoice_number']) ? "Invoice #{$rem['invoice_number']}" : (!empty($rem['project_title']) ? "Project: {$rem['project_title']}" : "General");
    
    $subject = "Reminder: {$type_label} - " . date('M d, Y', strtotime($rem['reminder_date']));
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px;'>
        <h2 style='color: #4f46e5; margin-bottom: 20px;'>FreelanceFlow Reminder</h2>
        <p>Hi {$rem['user_name']},</p>
        <p>This is a reminder for the following task:</p>
        <div style='background: #f8fafc; padding: 20px; border-radius: 10px; margin: 20px 0;'>
            <p><strong>Type:</strong> {$type_label}</p>
            <p><strong>Linked To:</strong> {$target}</p>
            <p><strong>Date:</strong> " . date('M d, Y', strtotime($rem['reminder_date'])) . "</p>
        </div>
        <p>Stay on top of your schedule!</p>
        <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
        <p style='color: #94a3b8; font-size: 0.8rem; text-align: center;'>&copy; " . date('Y') . " FreelanceFlow. All rights reserved.</p>
    </div>
    ";

    if (sendEmail($rem['user_email'], $subject, $body)) {
        // Update status to 'sent'
        $update = $pdo->prepare("UPDATE reminders SET status = 'sent', updated_at = NOW() WHERE id = ?");
        $update->execute([$rem['id']]);
        $sent_count++;
    }
}

header("Location: index.php?success=emails_sent&count=" . $sent_count);
exit();
