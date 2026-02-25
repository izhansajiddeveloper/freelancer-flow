<?php
/**
 * Mail Helper - Integrated with PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Manual include since we cloned without composer
require_once dirname(__DIR__) . '/vendor/phpmailer/src/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/SMTP.php';

/**
 * Sends a password reset email
 * @param string $toEmail
 * @param string $token
 * @return bool
 */
function sendResetEmail($toEmail, $token) {
    if (empty($toEmail) || empty($token)) return false;

    $subject = "Password Reset Request - FreelanceFlow";
    $resetLink = BASE_URL . "auth/reset_password.php?token=" . $token;
    
    $message = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 10px; padding: 30px;'>
        <h2 style='color: #4f46e5; text-align: center;'>FreelanceFlow</h2>
        <p>Hi there,</p>
        <p>You requested a password reset for your account. Click the button below to set a new password:</p>
        <div style='text-align: center; margin: 30px 0;'>
            <a href='{$resetLink}' style='background: #4f46e5; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Reset Password</a>
        </div>
        <p style='color: #64748b; font-size: 0.9rem;'>This link will expire in 1 hour for security reasons.</p>
        <p style='color: #64748b; font-size: 0.9rem;'>If you didn't request this, you can safely ignore this email.</p>
        <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
        <p style='color: #94a3b8; font-size: 0.8rem; text-align: center;'>&copy; " . date('Y') . " FreelanceFlow. All rights reserved.</p>
    </div>
    ";

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error if needed: $e->getMessage()
        return false;
    }
}
/**
 * Sends a contact form email to the admin
 */
function sendContactEmail($name, $email, $subject, $message) {
    if (empty($name) || empty($email) || empty($message)) return false;

    $mail_subject = "New Contact Inquiry: " . $subject;
    
    $mail_message = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 10px; padding: 30px;'>
        <h2 style='color: #4f46e5;'>New Inquiry from FreelanceFlow</h2>
        <p><strong>Name:</strong> {$name}</p>
        <p><strong>Email:</strong> {$email}</p>
        <p><strong>Subject:</strong> {$subject}</p>
        <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
        <p><strong>Message:</strong></p>
        <p style='white-space: pre-wrap;'>{$message}</p>
    </div>
    ";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress(MAIL_FROM); // Send to admin email
        $mail->addReplyTo($email, $name); // Allow replying to the user

        $mail->isHTML(true);
        $mail->Subject = $mail_subject;
        $mail->Body    = $mail_message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
/**
 * Generic Email Sender
 * @param string $toEmail
 * @param string $subject
 * @param string $message
 * @param array $attachments Array of file paths [ 'path' => 'file.pdf', 'name' => 'Name.pdf' ]
 * @return bool
 */
function sendEmail($toEmail, $subject, $message, $attachments = []) {
    if (empty($toEmail) || empty($subject) || empty($message)) return false;

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail);

        // Attachments
        if (!empty($attachments)) {
            foreach ($attachments as $file) {
                if (isset($file['path']) && file_exists($file['path'])) {
                    $name = $file['name'] ?? basename($file['path']);
                    $mail->addAttachment($file['path'], $name);
                }
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
