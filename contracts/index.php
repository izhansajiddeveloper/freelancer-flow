<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$success_msg = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') $success_msg = "Contract generated successfully!";
    if ($_GET['success'] === 'deleted') $success_msg = "Contract removed.";
    if ($_GET['success'] === 'signed') $success_msg = "Contract marked as signed!";
    if ($_GET['success'] === 'updated') $success_msg = "Contract updated successfully!";
    if ($_GET['success'] === 'sent') $success_msg = "Contract sent to client!";
}

// Fetch all contracts with client and project names
$stmt = $pdo->prepare("
    SELECT cont.*, c.client_name, p.project_title, p.id as project_id
    FROM contracts cont
    JOIN clients c ON cont.client_id = c.id 
    JOIN projects p ON cont.project_id = p.id
    WHERE cont.user_id = ? 
    ORDER BY cont.created_at DESC
");
$stmt->execute([$user_id]);
$contracts = $stmt->fetchAll();

$hide_navbar = true;
include_once '../includes/header.php';
?>

<style>
  
    .dashboard-wrapper {
        background: transparent !important;
    }

    /* Make sure main content area is transparent */
    .main-content {
        background: transparent !important;
    }

    /* Keep dashboard container with subtle gradient overlay but transparent */
    .dashboard-container {
        background: radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.05) 0, transparent 50%),
            radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.05) 0, transparent 50%) !important;
        min-height: 100vh;
    }

    .action-btn-circle:hover {
        transform: translateY(-2px);
        filter: brightness(0.9);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    /* Add a slight white overlay to the glass card for better readability */
    .glass-card {
        background: rgba(255, 255, 255, 0.85) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.3) !important;
    }

    /* Dark mode support */
    body.dark-mode .glass-card {
        background: rgba(30, 41, 59, 0.85) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
</style>

<body>
    <div class="dashboard-wrapper">
        <?php include_once '../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="dashboard-topbar">
                <div class="topbar-left">
                    <h2 style="font-weight: 800; letter-spacing: -0.5px;">Contracts</h2>
                </div>
                <div class="topbar-actions">
                    <a href="create.php" class="btn btn-primary" style="padding: 12px 24px; border-radius: 14px; font-weight: 800; background: var(--gradient-primary); border: none; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3); display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-file-contract"></i> Draft New Agreement
                    </a>
                </div>
            </div>

            <div class="dashboard-container">
                <div class="animate-fade-in">
                    <?php if ($success_msg): ?>
                        <div style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; border: 1px solid rgba(16, 185, 129, 0.2);">
                            <i class="fas fa-check-circle" style="margin-right: 8px;"></i> <?php echo $success_msg; ?>
                        </div>
                    <?php endif; ?>

                    <div class="glass-card" style="padding: 0; overflow: hidden; border-radius: 20px;">
                        <div class="table-responsive">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                        <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Project & Client</th>
                                        <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Date Created</th>
                                        <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Status</th>
                                        <th style="padding: 15px 20px; text-align: center; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($contracts)): ?>
                                        <tr>
                                            <td colspan="4" style="padding: 40px; text-align: center; color: #64748b;">
                                                <i class="fas fa-file-signature" style="font-size: 2rem; color: #cbd5e1; display: block; margin-bottom: 10px;"></i>
                                                <p>No contracts generated yet. Start from a project to create an agreement.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($contracts as $c): ?>
                                            <tr style="border-bottom: 1px solid #f1f5f9; transition: all 0.2s;">
                                                <td style="padding: 20px;">
                                                    <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($c['project_title']); ?></div>
                                                    <div style="font-size: 0.8rem; color: #64748b;"><?php echo htmlspecialchars($c['client_name']); ?></div>
                                                </td>
                                                <td style="padding: 20px;">
                                                    <div style="color: #64748b; font-size: 0.9rem; font-weight: 600;">
                                                        <i class="far fa-calendar-alt" style="margin-right: 5px;"></i>
                                                        <?php echo date('M d, Y', strtotime($c['created_at'])); ?>
                                                    </div>
                                                </td>
                                                <td style="padding: 20px;">
                                                    <?php
                                                    $status = $c['status'];
                                                    $bg = '#f1f5f9';
                                                    $color = '#64748b';
                                                    if ($status === 'signed') {
                                                        $bg = '#ecfdf5';
                                                        $color = '#10b981';
                                                    } elseif ($status === 'sent') {
                                                        $bg = '#eff6ff';
                                                        $color = '#3b82f6';
                                                    } elseif ($status === 'draft') {
                                                        $bg = '#fefce8';
                                                        $color = '#ca8a04';
                                                    }
                                                    ?>
                                                    <span style="padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; text-transform: uppercase; letter-spacing: 0.5px;">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 20px; text-align: center;">
                                                    <div style="display: flex; justify-content: center; gap: 8px; align-items: center; min-width: 320px;">
                                                        <!-- Quick Status -->
                                                        <div style="display: flex; background: #f1f5f9; padding: 4px; border-radius: 12px; gap: 6px; align-items: center; border: 1px solid #e2e8f0; <?php echo $c['status'] === 'signed' ? 'pointer-events: none; opacity: 0.5;' : ''; ?>">
                                                            <a href="update_status.php?id=<?php echo $c['id']; ?>&status=sent&ref=index" class="status-dot" title="Mark as Sent" style="background: #3b82f6; width: 14px; height: 14px; border-radius: 50%; opacity: <?php echo $c['status'] == 'sent' ? '1' : '0.2'; ?>; display: block;"></a>
                                                            <a href="update_status.php?id=<?php echo $c['id']; ?>&status=signed&ref=index" class="status-dot" title="Mark as Signed" style="background: #10b981; width: 14px; height: 14px; border-radius: 50%; opacity: <?php echo $c['status'] == 'signed' ? '1' : '0.2'; ?>; display: block;"></a>
                                                            <a href="update_status.php?id=<?php echo $c['id']; ?>&status=cancelled&ref=index" class="status-dot" title="Mark as Cancelled" style="background: #ef4444; width: 14px; height: 14px; border-radius: 50%; opacity: <?php echo $c['status'] == 'cancelled' ? '1' : '0.2'; ?>; display: block;"></a>
                                                        </div>

                                                        <a href="generate.php?id=<?php echo $c['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: rgba(79, 70, 229, 0.1); color: var(--primary-color); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;" title="View Agreement">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <?php if ($c['status'] !== 'signed'): ?>
                                                            <a href="edit.php?id=<?php echo $c['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: #f1f5f9; color: #475569; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;" title="Edit Terms">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <div class="action-btn-circle" title="Signed Contracts cannot be edited" style="background: rgba(16, 185, 129, 0.1); color: #10b981; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; cursor: default;">
                                                                <i class="fas fa-check-double"></i>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if ($c['status'] === 'draft'): ?>
                                                            <a href="send_contract.php?id=<?php echo $c['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;" title="Send via Email">
                                                                <i class="fas fa-paper-plane"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <div class="action-btn-circle" title="<?php echo $c['status'] === 'signed' ? 'Contract Signed' : 'Already Sent'; ?>" style="background: rgba(16, 185, 129, 0.05); color: #94a3b8; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; cursor: not-allowed;">
                                                                <i class="fas fa-check-double"></i>
                                                            </div>
                                                        <?php endif; ?>

                                                        <a href="download.php?id=<?php echo $c['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: rgba(37, 99, 235, 0.1); color: #2563eb; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;" title="Download PDF">
                                                            <i class="fas fa-file-pdf"></i>
                                                        </a>

                                                        <?php if (!empty($c['pdf_file'])): ?>
                                                            <a href="../assets/uploads/contracts/<?php echo $c['pdf_file']; ?>" target="_blank" class="action-btn-circle" style="width: 34px; height: 34px; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;" title="View Already Uploaded Signed Copy">
                                                                <i class="fas fa-check-circle"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="upload_signed.php?id=<?php echo $c['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;" title="Upload Signed Copy">
                                                                <i class="fas fa-file-signature"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <a href="delete.php?id=<?php echo $c['id']; ?>" class="action-btn-circle" onclick="return confirm('Archive this contract?')" style="width: 34px; height: 34px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s; <?php echo $c['status'] === 'signed' ? 'pointer-events: none; opacity: 0.3;' : ''; ?>" title="Delete">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>