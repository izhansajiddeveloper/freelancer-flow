<?php
/**
 * Automated Reminder Engine
 * Runs silently on Dashboard visit
 */

// Basic check - skip if already processed in this request
if (defined('REMINDERS_PROCESSED')) return;
define('REMINDERS_PROCESSED', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/mail_helper.php';

// Ensure user_id is available
if (!isset($user_id)) return;

// 1. SELECT pending reminders that are due today or overdue
$stmt = $pdo->prepare("
    SELECT r.*, 
           u.email as user_email,
           u.full_name as user_name,
           i.invoice_number,
           p.project_title
    FROM reminders r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN invoices i ON r.invoice_id = i.id
    LEFT JOIN projects p ON r.project_id = p.id
    WHERE r.user_id = ? 
      AND r.status = 'pending' 
      AND r.medium = 'email'

");
$stmt->execute([$user_id]);
$pending_items = $stmt->fetchAll();

if (empty($pending_items)) return;

$sent_count = 0;
$failed_count = 0;

foreach ($pending_items as $item) {
    try {
        $type_label = ucwords(str_replace('_', ' ', $item['reminder_type']));
        $ref = !empty($item['invoice_number']) ? "Invoice #{$item['invoice_number']}" : (!empty($item['project_title']) ? "Project: {$item['project_title']}" : 'General');
        
        $subject = "🔔 Action Required: {$type_label} Reminder";
        
        // Premium Email Template
        $body = "
        <div style='font-family: \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;'>
            <div style='background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 40px 20px; text-align: center;'>
                <div style='background: rgba(255, 255, 255, 0.2); width: 60px; height: 60px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;'>
                     <span style='font-size: 30px;'>🔔</span>
                </div>
                <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;'>Reminder Notification</h1>
            </div>
            
            <div style='padding: 40px 30px;'>
                <p style='color: #475569; font-size: 16px; line-height: 1.6;'>Hi <strong>{$item['user_name']}</strong>,</p>
                <p style='color: #475569; font-size: 16px; line-height: 1.6; margin-bottom: 30px;'>This is a scheduled reminder for an important task in your <strong>FreelanceFlow</strong> workspace.</p>
                
                <div style='background-color: #f8fafc; border-radius: 12px; padding: 25px; border-left: 4px solid #4f46e5;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding-bottom: 12px; color: #64748b; font-size: 13px; font-weight: 700; text-transform: uppercase;'>Task Type</td>
                            <td style='padding-bottom: 12px; color: #1e293b; font-size: 15px; font-weight: 700; text-align: right;'>{$type_label}</td>
                        </tr>
                        <tr>
                            <td style='padding-bottom: 12px; color: #64748b; font-size: 13px; font-weight: 700; text-transform: uppercase;'>Reference</td>
                            <td style='padding-bottom: 12px; color: #1e293b; font-size: 15px; font-weight: 600; text-align: right;'>{$ref}</td>
                        </tr>
                        <tr>
                            <td style='color: #64748b; font-size: 13px; font-weight: 700; text-transform: uppercase;'>Due Date</td>
                            <td style='color: #ef4444; font-size: 15px; font-weight: 700; text-align: right;'>" . date('F d, Y', strtotime($item['reminder_date'])) . "</td>
                        </tr>
                    </table>
                </div>
                
                <div style='margin-top: 40px; text-align: center;'>
                    <a href='" . BASE_URL . "dashboard/index.php' style='background-color: #4f46e5; color: #ffffff; padding: 16px 32px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 15px; display: inline-block; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);'>Go to Dashboard</a>
                </div>
            </div>
            
            <div style='background-color: #f8fafc; padding: 30px; text-align: center;'>
                <p style='color: #94a3b8; font-size: 12px; margin: 0;'>Stay organized, stay productive.</p>
                <p style='color: #94a3b8; font-size: 12px; margin: 10px 0 0 0;'>&copy; " . date('Y') . " FreelanceFlow Terminal. All rights reserved.</p>
            </div>
        </div>
        ";

        // 2. Dispatch Email
        if (sendEmail($item['user_email'], $subject, $body)) {
            // 3. IMPORTANT: Update status from 'pending' to 'sent'
            $update = $pdo->prepare("UPDATE reminders SET status = 'sent', updated_at = NOW() WHERE id = ?");
            $update->execute([$item['id']]);
            $sent_count++;
        } else {
            $failed_count++;
        }
    } catch (Exception $e) {
        $failed_count++;
        error_log("FreelanceFlow Mail Error (ID {$item['id']}): " . $e->getMessage());
    }
}
// echo "DEBUG: Sent: $sent_count, Failed: $failed_count";

?>
