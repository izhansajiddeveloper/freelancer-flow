<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$contract_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

if (!$contract_id) {
    header("Location: index.php");
    exit();
}

// Fetch existing contract details
$stmt = $pdo->prepare("
    SELECT cont.*, p.project_title 
    FROM contracts cont
    JOIN projects p ON cont.project_id = p.id
    WHERE cont.id = ? AND cont.user_id = ?
");
$stmt->execute([$contract_id, $user_id]);
$contract = $stmt->fetch();

if (!$contract) {
    die("Contract not found or access denied.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contract_details = trim($_POST['contract_details'] ?? '');
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $status = $_POST['status'] ?? 'draft';

    if (empty($contract_details)) {
        $error = "Contract Details are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE contracts 
                SET contract_details = ?, start_date = ?, end_date = ?, status = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $contract_details,
                $start_date,
                $end_date,
                $status,
                $contract_id,
                $user_id
            ]);

            header("Location: index.php?success=updated");
            exit();
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
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
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Edit Contract</h2>
            </div>
        </div>

        <div class="dashboard-container" style="max-width: 1000px; margin: 0 auto;">
            <div class="animate-fade-in">
                <?php if ($error): ?>
                    <div style="background: #fef2f2; color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; border: 1px solid #fee2e2;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Project (Locked)</label>
                                <div style="padding: 12px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; color: #64748b;">
                                    <?php echo htmlspecialchars($contract['project_title']); ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Start Date</label>
                                <input type="date" name="start_date" value="<?php echo $contract['start_date']; ?>" style="width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px;">
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">End Date</label>
                                <input type="date" name="end_date" value="<?php echo $contract['end_date']; ?>" style="width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px;">
                            </div>
                        </div>

                        <div style="margin-bottom: 30px;">
                            <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Status</label>
                            <select name="status" style="width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px;">
                                <option value="draft" <?php echo $contract['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="sent" <?php echo $contract['status'] == 'sent' ? 'selected' : ''; ?>>Sent</option>
                                <option value="signed" <?php echo $contract['status'] == 'signed' ? 'selected' : ''; ?>>Signed</option>
                                <option value="cancelled" <?php echo $contract['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>

                        <div style="margin-bottom: 30px;">
                            <label style="display: block; font-weight: 700; margin-bottom: 15px; color: #1e293b;">Contract Terms & Conditions (Markdown Supported)</label>
                            <textarea name="contract_details" rows="25" style="width: 100%; padding: 20px; border: 1px solid #e2e8f0; border-radius: 16px; background: #f8fafc; line-height: 1.6; outline: none; font-family: 'Courier New', Courier, monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($contract['contract_details']); ?></textarea>
                        </div>

                        <div style="display: flex; gap: 15px;">
                            <button type="submit" class="btn btn-primary" style="flex: 2; padding: 16px; border-radius: 16px; font-weight: 800; background: var(--gradient-primary); border: none;">
                                <i class="fas fa-save"></i> Update Agreement
                            </button>
                            <a href="index.php" class="btn btn-outline" style="flex: 1; padding: 16px; border-radius: 16px; font-weight: 700; text-align: center;">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>


