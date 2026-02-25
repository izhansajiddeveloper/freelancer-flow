<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';
require_once '../helpers/mail_helper.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = 'Please enter your email.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));
            
            // Delete any existing tokens for this email
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            // Save token to database
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            if ($stmt->execute([$email, $token, $expires_at])) {
                
                // Send email
                if (sendResetEmail($email, $token)) {
                    $success = 'A password reset link has been sent to your email.';
                } else {
                    $error = 'Failed to send the email. Please check your SMTP settings.';
                }
            } else {
                $error = 'Failed to generate reset link.';
            }
        } else {
            $error = 'No account found with that email.';
        }
    }
}

include_once '../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h2>Forgot Password</h2>
        <p>Enter your email and we'll send you a reset link.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="john@example.com" required>
            </div>

            <button type="submit" class="btn btn-primary auth-btn">Send Reset Link</button>
        </form>

        <div class="auth-footer">
            Remembered? <a href="login.php">Back to Login</a>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
