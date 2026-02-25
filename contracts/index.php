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
    .action-btn-circle:hover {
        transform: translateY(-2px);
        filter: brightness(0.9);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
</style>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Contracts</h2>
            </div>
            <div class="topbar-actions">
                <a href="../projects/index.php" class="btn btn-primary" style="padding: 12px 24px; border-radius: 14px; font-weight: 800; background: var(--gradient-primary); border: none; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3); display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-file-contract"></i> Generate from Project
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
                                                $bg = '#f1f5f9'; $color = '#64748b';
                                                if ($status === 'signed') { $bg = '#ecfdf5'; $color = '#10b981'; }
                                                elseif ($status === 'sent') { $bg = '#eff6ff'; $color = '#3b82f6'; }
                                                elseif ($status === 'draft') { $bg = '#fefce8'; $color = '#ca8a04'; }
                                                ?>
                                                <span style="padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; text-transform: uppercase; letter-spacing: 0.5px;">
                                                    <?php echo $status; ?>
                                                </span>
                                            </td>
                                            <td style="padding: 20px; text-align: center;">
                                                <div style="display: flex; justify-content: center; gap: 10px;">
                                                    <a href="../projects/view.php?id=<?php echo $c['project_id']; ?>" class="action-btn-circle" style="width: 36px; height: 36px; background: #f1f5f9; color: #64748b; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none;" title="View Project">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="download.php?id=<?php echo $c['id']; ?>" class="action-btn-circle" style="width: 36px; height: 36px; background: #eef2ff; color: #4f46e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none;" title="Download PDF">
                                                        <i class="fas fa-download"></i>
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

<?php include_once '../includes/footer.php'; ?>
