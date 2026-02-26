<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$contract_id = $_GET['id'] ?? $_POST['contract_id'] ?? 0;
$error = '';

if (!$contract_id) {
    header("Location: index.php");
    exit();
}

// Fetch contract details
$stmt = $pdo->prepare("SELECT * FROM contracts WHERE id = ? AND user_id = ?");
$stmt->execute([$contract_id, $user_id]);
$contract = $stmt->fetch();

if (!$contract) {
    die("Contract not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['signed_contract'])) {
    $file = $_FILES['signed_contract'];
    $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
    $filename = $file['name'];
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_exts)) {
        $error = "Only PDF and Images (JPG, PNG) are allowed.";
    } elseif ($file['size'] > 5000000) { // 5MB limit
        $error = "File size exceeds 5MB limit.";
    } else {
        $upload_dir = '../uploads/contracts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $new_filename = "Signed_Contract_" . $contract_id . "_" . time() . "." . $file_ext;
        $target_path = $upload_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Update database
            $stmt = $pdo->prepare("UPDATE contracts SET pdf_file = ?, status = 'signed' WHERE id = ? AND user_id = ?");
            $stmt->execute([$new_filename, $contract_id, $user_id]);

            header("Location: index.php?success=signed");
            exit();
        } else {
            $error = "Failed to move uploaded file.";
        }
    }
}

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Upload Signed Contract</h2>
            </div>
        </div>

        <div class="dashboard-container" style="max-width: 600px; margin: 0 auto;">
            <div class="animate-fade-in">
                <?php if ($error): ?>
                    <div style="background: #fef2f2; color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 25px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <?php if ($contract['status'] === 'signed' && $contract['pdf_file']): ?>
                        <div style="text-align: center; padding: 40px;">
                            <div style="width: 80px; height: 80px; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 20px;">
                                <i class="fas fa-check-double"></i>
                            </div>
                            <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 10px;">Already Uploaded</h3>
                            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 30px;">The signed copy of this contract has already been uploaded and processed.</p>
                            
                            <div style="display: flex; gap: 15px; justify-content: center;">
                                <a href="index.php" class="btn btn-outline" style="padding: 12px 25px; border-radius: 12px;">Back to List</a>
                                <a href="../uploads/contracts/<?php echo $contract['pdf_file']; ?>" target="_blank" class="btn btn-primary" style="padding: 12px 25px; border-radius: 12px; background: #10b981; border: none;">
                                    <i class="fas fa-eye"></i> View Signed Copy
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
                            
                            <div style="text-align: center; margin-bottom: 30px;">
                                <div style="width: 80px; height: 80px; background: #eef2ff; color: #4f46e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 20px;">
                                    <i class="fas fa-file-upload"></i>
                                </div>
                                <h3 style="font-weight: 800; color: #1e293b;">Select Signed Document</h3>
                                <p style="color: #64748b; font-size: 0.9rem;">Upload the PDF or Image of the signed agreement.</p>
                            </div>

                            <div class="form-group" style="margin-bottom: 30px;">
                                <input type="file" name="signed_contract" accept=".pdf,image/*" style="width: 100%; padding: 15px; background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 16px; cursor: pointer;" required>
                                <p style="margin-top: 10px; font-size: 0.75rem; color: #94a3b8;">Supported formats: PDF, JPG, PNG (Max 5MB)</p>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 16px; border-radius: 16px; font-weight: 800; background: var(--gradient-primary); border: none;">
                                <i class="fas fa-check-double"></i> Confirm & Mark as Signed
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>


