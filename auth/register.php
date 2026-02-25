<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard/index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password']; // Plain password as requested
    $phone     = trim($_POST['phone']);
    $timezone  = $_POST['timezone'] ?: 'Asia/Karachi';

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email is already registered.';
        } else {
            // Handle Profile Image Upload
            $profile_image = '';
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['name'] !== '') {
                if ($_FILES['profile_image']['error'] === 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['profile_image']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    if (in_array($ext, $allowed)) {
                        $new_filename = uniqid('profile_') . '.' . $ext;
                        $upload_dir = ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR;
                        
                        // Check if directory is writable
                        if (!is_writable($upload_dir)) {
                            $error = 'Upload directory is not writable. Please check folder permissions.';
                        } else {
                            $upload_path = $upload_dir . $new_filename;

                            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                                $profile_image = $new_filename;
                            } else {
                                $error = 'Failed to move uploaded file. Check PHP temp folder or permissions.';
                            }
                        }
                    } else {
                        $error = 'Invalid file type. Only JPG, PNG, and GIF allowed.';
                    }
                } else {
                    switch ($_FILES['profile_image']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                            $error = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
                            break;
                        case UPLOAD_ERR_FORM_SIZE:
                            $error = 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.';
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error = 'The uploaded file was only partially uploaded.';
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            // No file was uploaded, which is fine since it's optional
                            break;
                        default:
                            $error = 'Unknown upload error.';
                            break;
                    }
                }
            }

            // ONLY proceed to Insert if there are no errors (like file upload errors)
            if (empty($error)) {
                // Insert user
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, timezone, profile_image) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$full_name, $email, $password, $phone, $timezone, $profile_image])) {
                    // Get newly created user ID
                    $user_id = $pdo->lastInsertId();
                    
                    // Log them in
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_name'] = $full_name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_image'] = $profile_image;
                    
                    header('Location: ' . BASE_URL . 'dashboard/index.php');
                    exit();
                } else {
                    $error = 'Something went wrong while saving your account. Please try again.';
                }
            }
        }
    }
}

include_once '../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h2>Create Account</h2>
        <p>Join FreelanceFlow and manage your business today.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" name="full_name" id="full_name" class="form-control" placeholder="John Doe" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="john@example.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" name="phone" id="phone" class="form-control" placeholder="+92 300 1234567">
            </div>

            <div class="form-group">
                <label for="profile_image">Profile Image</label>
                <input type="file" name="profile_image" id="profile_image" class="form-control" accept="image/*">
            </div>

            <div class="form-group">
                <label for="timezone">Timezone</label>
                <select name="timezone" id="timezone" class="form-control">
                    <option value="Asia/Karachi" selected>Asia/Karachi</option>
                    <option value="UTC">UTC</option>
                    <option value="America/New_York">America/New_York</option>
                    <option value="Europe/London">Europe/London</option>
                    <option value="Asia/Dubai">Asia/Dubai</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary auth-btn">Register Now</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
