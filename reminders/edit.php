<?php
/**
 * Edit Reminder
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();
$error = '';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header("Location: index.php");
    exit();
}

/**
 * Fetch Current Reminder Data
 */
$rem = $pdo->prepare("SELECT * FROM reminders WHERE id = ? AND user_id = ?");
$rem->execute([$id, $user_id]);
$current = $rem->fetch();

if (!$current) {
    die("Resource Not Found.");
}

/**
 * Handle POST Update
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rType   = $_POST['reminder_type'] ?? '';
    $medium  = $_POST['medium'] ?? 'email';
    $rDate   = $_POST['reminder_date'] ?? '';
    $invID   = intval($_POST['invoice_id'] ?? 0) ?: null;
    $projID  = intval($_POST['project_id'] ?? 0) ?: null;
    $status  = $_POST['status'] ?? 'pending';

    if (empty($rType) || empty($rDate)) {
        $error = "Subject and Scheduled Date are mandatory.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE reminders 
                SET invoice_id=?, project_id=?, reminder_type=?, medium=?, reminder_date=?, status=?, updated_at=NOW()
                WHERE id=? AND user_id=?
            ");
            $stmt->execute([$invID, $projID, $rType, $medium, $rDate, $status, $id, $user_id]);
            
            header("Location: index.php?success=updated");
            exit();
        } catch (Exception $e) {
            $error = "System Error: " . $e->getMessage();
        }
    }
}

/**
 * Fetch References
 */
$invoices = $pdo->prepare("SELECT id, invoice_number FROM invoices WHERE user_id = ? ORDER BY created_at DESC");
$invoices->execute([$user_id]);
$all_invoices = $invoices->fetchAll();

$projects = $pdo->prepare("SELECT id, project_title FROM projects WHERE user_id = ? ORDER BY created_at DESC");
$projects->execute([$user_id]);
$all_projects = $projects->fetchAll();

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-edit" style="color: #4f46e5;"></i>
                    Modify Schedule
                </h2>
            </div>
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline" style="border-radius: 12px; font-weight: 700;">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
            </div>
        </div>

        <div class="dashboard-container" style="max-width: 800px; margin: 0 auto;">
            <div class="animate-fade-in">
                
                <?php if ($error): ?>
                    <div style="background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 700;">
                        <i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px;">
                            
                            <div style="grid-column: span 2;">
                                <label style="display:block; font-weight:800; margin-bottom:10px; color:#1e293b; font-size: 0.85rem; text-transform: uppercase;">Goal of the Reminder</label>
                                <select name="reminder_type" style="width:100%; padding:14px; border:1px solid #e2e8f0; border-radius:14px; background:white; font-size: 1rem;" required>
                                    <?php 
                                    $types = [
                                        'invoice_due' => 'Invoices: Follow-up on Payments',
                                        'proposal_followup' => 'Proposals: Track Client Decision',
                                        'contract_signing' => 'Contracts: Request Signatures',
                                        'milestone_deadline' => 'Strategy: Milestone Approaching',
                                        'general' => 'Advisory: Internal Review / General'
                                    ];
                                    foreach($types as $val => $lbl): ?>
                                        <option value="<?php echo $val; ?>" <?php echo $current['reminder_type']===$val?'selected':''; ?>><?php echo $lbl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label style="display:block; font-weight:800; margin-bottom:10px; color:#1e293b; font-size: 0.85rem; text-transform: uppercase;">Run Date</label>
                                <input type="date" name="reminder_date" value="<?php echo $current['reminder_date']; ?>" style="width:100%; padding:14px; border:1px solid #e2e8f0; border-radius:14px; font-size: 1rem;" required>
                            </div>

                            <div>
                                <label style="display:block; font-weight:800; margin-bottom:10px; color:#1e293b; font-size: 0.85rem; text-transform: uppercase;">Delivery Channel</label>
                                <select name="medium" style="width:100%; padding:14px; border:1px solid #e2e8f0; border-radius:14px; background:white; font-size: 1rem;">
                                    <option value="email" <?php echo $current['medium']==='email'?'selected':''; ?>>Automated Email (Primary)</option>
                                    <option value="dashboard" <?php echo $current['medium']==='dashboard'?'selected':''; ?>>Local Dashboard Log Only</option>
                                </select>
                            </div>

                            <div>
                                <label style="display:block; font-weight:800; margin-bottom:10px; color:#1e293b; font-size: 0.85rem; text-transform: uppercase;">Current Status</label>
                                <select name="status" style="width:100%; padding:14px; border:1px solid #e2e8f0; border-radius:14px; background:white; font-size: 1rem;">
                                    <option value="pending" <?php echo $current['status']==='pending'?'selected':''; ?>>Pending Process</option>
                                    <option value="sent" <?php echo $current['status']==='sent'?'selected':''; ?>>Sent/Dispatched</option>
                                    <option value="completed" <?php echo $current['status']==='completed'?'selected':''; ?>>Marked Done</option>
                                </select>
                            </div>

                            <div>
                                <label style="display:block; font-weight:800; margin-bottom:10px; color:#1e293b; font-size: 0.85rem; text-transform: uppercase;">Linked Invoice</label>
                                <select name="invoice_id" style="width:100%; padding:14px; border:1px solid #e2e8f0; border-radius:14px; background:white; font-size: 1rem;">
                                    <option value="">-- No Link --</option>
                                    <?php foreach ($all_invoices as $inv): ?>
                                        <option value="<?php echo $inv['id']; ?>" <?php echo $current['invoice_id']==$inv['id']?'selected':''; ?>>#<?php echo $inv['invoice_number']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                        </div>

                        <div style="display: flex; gap: 15px;">
                            <button type="submit" class="btn btn-primary" style="flex: 2; padding: 18px; border-radius: 16px; font-weight: 800; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); border: none;">
                                <i class="fas fa-save" style="margin-right:10px;"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include_once '../includes/footer.php'; ?>
