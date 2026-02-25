<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$success_msg = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') $success_msg = "Proposal drafted successfully!";
    if ($_GET['success'] === 'deleted') $success_msg = "Proposal deleted.";
}

// Fetch all proposals with client names
$stmt = $pdo->prepare("
    SELECT p.*, c.client_name 
    FROM proposals p 
    JOIN clients c ON p.client_id = c.id 
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$user_id]);
$proposals = $stmt->fetchAll();

$hide_navbar = true;
include_once '../includes/header.php';
?>

<style>
    .action-btn-circle:hover {
        transform: translateY(-2px);
        filter: brightness(0.9);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .status-dot {
        transition: all 0.2s ease;
    }
    .status-dot:hover {
        transform: scale(1.3);
        opacity: 1 !important;
    }
</style>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Proposals</h2>
            </div>
            <div class="topbar-actions">
                <a href="create.php" class="btn btn-primary" style="padding: 12px 24px; border-radius: 14px; font-weight: 800; background: var(--gradient-primary); border: none; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3); display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-plus-circle"></i> Create New Proposal
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
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Client & Project</th>
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Value</th>
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Timeline</th>
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Status</th>
                                    <th style="padding: 15px 20px; text-align: center; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($proposals)): ?>
                                    <tr>
                                        <td colspan="5" style="padding: 40px; text-align: center; color: #64748b;">
                                            <i class="fas fa-file-contract" style="font-size: 2rem; color: #cbd5e1; display: block; margin-bottom: 10px;"></i>
                                            <p>No proposals yet. Create your first winning proposal today!</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($proposals as $p): ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9; transition: all 0.2s;">
                                            <td style="padding: 20px;">
                                                <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($p['project_title']); ?></div>
                                                <div style="font-size: 0.8rem; color: #64748b;"><?php echo htmlspecialchars($p['client_name']); ?></div>
                                            </td>
                                            <td style="padding: 20px;">
                                                <div style="font-weight: 700; color: var(--primary-color);">PKR <?php echo number_format($p['price'], 2); ?></div>
                                            </td>
                                            <td style="padding: 20px;">
                                                <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;"><?php echo htmlspecialchars($p['timeline']); ?></div>
                                            </td>
                                            <td style="padding: 20px;">
                                                <?php
                                                $status_colors = [
                                                    'draft' => 'background: #f1f5f9; color: #475569;',
                                                    'sent' => 'background: #eff6ff; color: #2563eb;',
                                                    'accepted' => 'background: #f0fdf4; color: #10b981;',
                                                    'rejected' => 'background: #fef2f2; color: #ef4444;'
                                                ];
                                                ?>
                                                <span style="padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; <?php echo $status_colors[$p['status']] ?? ''; ?>">
                                                    <?php echo $p['status']; ?>
                                                </span>
                                            </td>
                                            <td style="padding: 20px;">
                                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center; min-width: 250px;">
                                                    <!-- Quick Status -->
                                                    <div style="display: flex; background: #f1f5f9; padding: 4px; border-radius: 12px; gap: 6px; align-items: center; border: 1px solid #e2e8f0;">
                                                        <a href="update_status.php?id=<?php echo $p['id']; ?>&status=sent&ref=index" class="status-dot" title="Mark as Sent" style="background: #3b82f6; width: 14px; height: 14px; border-radius: 50%; opacity: <?php echo $p['status'] == 'sent' ? '1' : '0.2'; ?>; display: block;"></a>
                                                        <a href="update_status.php?id=<?php echo $p['id']; ?>&status=accepted&ref=index" class="status-dot" title="Mark as Accepted" style="background: #10b981; width: 14px; height: 14px; border-radius: 50%; opacity: <?php echo $p['status'] == 'accepted' ? '1' : '0.2'; ?>; display: block;"></a>
                                                        <a href="update_status.php?id=<?php echo $p['id']; ?>&status=rejected&ref=index" class="status-dot" title="Mark as Rejected" style="background: #ef4444; width: 14px; height: 14px; border-radius: 50%; opacity: <?php echo $p['status'] == 'rejected' ? '1' : '0.2'; ?>; display: block;"></a>
                                                    </div>

                                                    <a href="generate.php?id=<?php echo $p['id']; ?>" class="action-btn-circle" title="View Proposal" style="background: rgba(79, 70, 229, 0.1); color: var(--primary-color); width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="send.php?id=<?php echo $p['id']; ?>" class="action-btn-circle" title="Send via Email" style="background: rgba(16, 185, 129, 0.1); color: #10b981; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;">
                                                        <i class="fas fa-paper-plane"></i>
                                                    </a>
                                                    <a href="download.php?id=<?php echo $p['id']; ?>" class="action-btn-circle" title="Download PDF" style="background: rgba(37, 99, 235, 0.1); color: #2563eb; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                    <?php if ($p['status'] !== 'accepted'): ?>
                                                        <a href="edit.php?id=<?php echo $p['id']; ?>" class="action-btn-circle" title="Edit" style="background: #f1f5f9; color: #475569; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;"><i class="fas fa-edit"></i></a>
                                                    <?php else: ?>
                                                        <div class="action-btn-circle" title="Already Accepted" style="background: rgba(16, 185, 129, 0.1); color: #10b981; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; cursor: default;"><i class="fas fa-check-double"></i></div>
                                                    <?php endif; ?>
                                                    <a href="delete.php?id=<?php echo $p['id']; ?>" class="action-btn-circle" title="Delete" onclick="return confirm('Delete this proposal?')" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;"><i class="fas fa-trash-alt"></i></a>
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

<style>
    .action-btn {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        font-size: 0.85rem;
    }
    .action-btn:hover {
        transform: scale(1.1);
        filter: brightness(0.9);
    }
    tr:hover {
        background: rgba(248, 250, 252, 0.8);
    }
</style>

<?php include_once '../includes/footer.php'; ?>
