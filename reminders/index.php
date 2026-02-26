<?php
/**
 * Reminders Index
 * Listed all reminders with automatic status updates
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();

// Handle success messages
$success_msg = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created')   $success_msg = "New reminder scheduled successfully!";
    if ($_GET['success'] === 'deleted')   $success_msg = "Reminder has been removed.";
    if ($_GET['success'] === 'updated')   $success_msg = "Reminder updated successfully!";
}

/**
 * Fetch All Reminders with Linked Data
 */
$stmt = $pdo->prepare("
    SELECT r.*,
           i.invoice_number,
           p.project_title
    FROM reminders r
    LEFT JOIN invoices i ON r.invoice_id = i.id
    LEFT JOIN projects p ON r.project_id = p.id
    WHERE r.user_id = ?
    ORDER BY r.reminder_date ASC
");
$stmt->execute([$user_id]);
$reminders = $stmt->fetchAll();

// Get count of pending (unprocessed) reminders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reminders WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_count = $stmt->fetchColumn();

// Design tokens
$hide_navbar = true;
include_once '../includes/header.php';
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        --card-shadow: 0 10px 25px rgba(0,0,0,0.05);
    }
    
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .status-pending { background: #fef3c7; color: #d97706; }
    .status-sent { background: #e0f2fe; color: #0369a1; }
    .status-completed { background: #dcfce7; color: #166534; }
    
    .type-chip {
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 700;
        background: #f1f5f9;
        color: #475569;
    }
    
    .action-btn { transition: all 0.2s; text-decoration: none; border-radius: 10px; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; }
    .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
</style>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-bell" style="color: #4f46e5;"></i>
                    Task Reminders
                    <?php if ($pending_count > 0): ?>
                        <span style="background: #ef4444; color: #fff; font-size: 0.7rem; padding: 2px 8px; border-radius: 10px;"><?php echo $pending_count; ?> Due</span>
                    <?php endif; ?>
                </h2>
            </div>
            <div class="topbar-actions">
                <a href="create.php" class="btn btn-primary" style="background: var(--primary-gradient); border: none; padding: 12px 24px; border-radius: 12px; font-weight: 800; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);">
                    <i class="fas fa-plus"></i> New Reminder
                </a>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in">
                
                <!-- Success Alert -->
                <?php if ($success_msg): ?>
                    <div style="background: #ecfdf5; color: #10b981; border: 1px solid #d1fae5; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 700;">
                        <i class="fas fa-check-circle" style="margin-right:8px;"></i> <?php echo $success_msg; ?>
                    </div>
                <?php endif; ?>

                <!-- Table Content -->
                <div class="glass-card" style="padding: 0; overflow: hidden; border-radius: 20px; box-shadow: var(--card-shadow);">
                    <div class="table-responsive">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                    <th style="padding: 20px; text-align: left; font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Topic & Goal</th>
                                    <th style="padding: 20px; text-align: left; font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Linked Reference</th>
                                    <th style="padding: 20px; text-align: center; font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Medium</th>
                                    <th style="padding: 20px; text-align: left; font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Scheduled At</th>
                                    <th style="padding: 20px; text-align: left; font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Processing Status</th>
                                    <th style="padding: 20px; text-align: center; font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reminders)): ?>
                                    <tr>
                                        <td colspan="6" style="padding: 60px; text-align: center; color: #94a3b8;">
                                            <div style="font-size: 3rem; margin-bottom: 20px; opacity: 0.3;"><i class="fas fa-calendar-alt"></i></div>
                                            <p style="font-size: 1.1rem; font-weight: 700; color: #64748b;">No schedule active yet.</p>
                                            <p style="font-size: 0.9rem; margin-top: 5px;">Configure reminders to automate your workflow.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reminders as $rem): ?>
                                        <?php
                                        // Dynamic Date Logic
                                        $today = strtotime(date('Y-m-d'));
                                        $remDate = strtotime($rem['reminder_date']);
                                        $isPast = ($remDate < $today) && $rem['status'] === 'pending';
                                        
                                        // Status Display Mapping
                                        $s = $rem['status'];
                                        $sClass = 'status-pending';
                                        $sIcon = '<i class="far fa-clock"></i>';
                                        $sLabel = 'Pending';
                                        
                                        if ($s === 'sent') {
                                            $sClass = 'status-sent';
                                            $sIcon = '<i class="fas fa-paper-plane"></i>';
                                            $sLabel = 'Sent Automatically';
                                        } elseif ($s === 'completed') {
                                            $sClass = 'status-completed';
                                            $sIcon = '<i class="fas fa-check-double"></i>';
                                            $sLabel = 'Marked Done';
                                        }
                                        ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                                            <td style="padding: 18px 20px;">
                                                <span class="type-chip"><?php echo ucwords(str_replace('_', ' ', $rem['reminder_type'])); ?></span>
                                            </td>
                                            <td style="padding: 18px 20px;">
                                                <?php if ($rem['invoice_number']): ?>
                                                    <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem;"><i class="fas fa-file-invoice-dollar" style="color:#f59e0b; margin-right:6px;"></i>Invoice #<?php echo $rem['invoice_number']; ?></div>
                                                <?php elseif ($rem['project_title']): ?>
                                                    <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem;"><i class="fas fa-tasks" style="color:#4f46e5; margin-right:6px;"></i><?php echo $rem['project_title']; ?></div>
                                                <?php else: ?>
                                                    <span style="color: #cbd5e1;">General Context</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 18px 20px; text-align: center;">
                                                <div style="color: #64748b; font-weight: 700; text-transform: capitalize;">
                                                    <i class="fas <?php echo $rem['medium']==='email' ? 'fa-envelope' : 'fa-desktop'; ?>" style="margin-right:5px; color: #4f46e5;"></i>
                                                    <?php echo $rem['medium']; ?>
                                                </div>
                                            </td>
                                            <td style="padding: 18px 20px;">
                                                <div style="font-weight: 700; color: <?php echo $isPast ? '#ef4444' : '#1e293b'; ?>;">
                                                    <?php echo date('M d, Y', $remDate); ?>
                                                </div>
                                                <?php if ($isPast): ?>
                                                    <div style="font-size: 0.7rem; color: #ef4444; font-weight: 800;">Missed Schedule</div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 18px 20px;">
                                                <span class="status-badge <?php echo $sClass; ?>">
                                                    <?php echo $sIcon; ?> <?php echo $sLabel; ?>
                                                </span>
                                            </td>
                                            <td style="padding: 18px 20px; text-align: center;">
                                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                                    <!-- Fast Status Logic -->
                                                    <div style="background: #f1f5f9; padding: 4px 8px; border-radius: 12px; display: flex; gap: 6px;">
                                                        <a href="update_status.php?id=<?php echo $rem['id']; ?>&status=pending" title="Pend" style="color:#f59e0b; opacity:<?php echo $s==='pending'?1:0.3;?>"><i class="fas fa-dot-circle"></i></a>
                                                        <a href="update_status.php?id=<?php echo $rem['id']; ?>&status=completed" title="Done" style="color:#10b981; opacity:<?php echo $s==='completed'?1:0.3;?>"><i class="fas fa-check-circle"></i></a>
                                                    </div>
                                                    <a href="edit.php?id=<?php echo $rem['id']; ?>" class="action-btn" style="background: #eef2ff; color: #4f46e5;"><i class="fas fa-edit"></i></a>
                                                    <a href="delete.php?id=<?php echo $rem['id']; ?>" onclick="return confirm('Remove reminder?')" class="action-btn" style="background: #fef2f2; color: #ef4444;"><i class="fas fa-trash-alt"></i></a>
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
