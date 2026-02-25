<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: ' . BASE_URL . 'auth/forgot_password.php');
    exit();
}

// Verify token
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
$stmt->execute([$token]);
$reset_request = $stmt->fetch();

if (!$reset_request) {
    $error = 'This reset link is invalid or has expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset_request) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password)) {
        $error = 'Please enter a new password.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Update password in users table
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        if ($stmt->execute([$new_password, $reset_request['email']])) {
            
            // Delete the token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$reset_request['email']]);
            
            header('Location: ' . BASE_URL . 'auth/login.php?reset=success');
            exit();
        } else {
            $error = 'Failed to update password.';
        }
    }
}

include_once '../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h2>Set New Password</h2>
        <p>Choose a secure password for your account.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($reset_request): ?>
        <form action="" method="POST">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary auth-btn">Update Password</button>
        </form>
        <?php else: ?>
            <a href="forgot_password.php" class="btn btn-outline auth-btn">Request New Link</a>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
