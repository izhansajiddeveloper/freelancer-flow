<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();

$success_msg = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created')  $success_msg = "Payment recorded successfully!";
    if ($_GET['success'] === 'updated')  $success_msg = "Payment status updated!";
    if ($_GET['success'] === 'deleted')  $success_msg = "Payment deleted.";
}

// Summary totals
$stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$total_received = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$total_pending = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'failed'");
$stmt->execute([$user_id]);
$total_failed = $stmt->fetchColumn() ?? 0;

// Fetch all payments
$stmt = $pdo->prepare("
    SELECT p.*, c.client_name, i.invoice_number
    FROM payments p
    JOIN clients c ON p.client_id = c.id
    JOIN invoices i ON p.invoice_id = i.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$user_id]);
$payments = $stmt->fetchAll();

$hide_navbar = true;
include_once '../includes/header.php';
?>

<style>
    .stat-card-pay { border-radius: 20px; padding: 25px 30px; display: flex; align-items: center; gap: 20px; }
    .stat-icon-pay { width: 55px; height: 55px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
    .action-btn-circle { transition: all 0.2s; text-decoration: none; }
    .action-btn-circle:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
</style>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Payments</h2>
            </div>
            <div class="topbar-actions">
                <a href="reports.php" class="btn btn-outline" style="padding: 10px 20px; border-radius: 12px; margin-right: 10px; font-weight: 700;">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="create.php" class="btn btn-primary" style="padding: 12px 24px; border-radius: 14px; font-weight: 800; background: var(--gradient-primary); border: none; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3); display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-plus"></i> Record Payment
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

                <!-- Summary Cards -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">
                    <div class="glass-card stat-card-pay">
                        <div class="stat-icon-pay" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Total Received</div>
                            <div style="font-size: 1.6rem; font-weight: 900; color: #10b981;">RS <?php echo number_format($total_received, 2); ?></div>
                        </div>
                    </div>
                    <div class="glass-card stat-card-pay">
                        <div class="stat-icon-pay" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Pending</div>
                            <div style="font-size: 1.6rem; font-weight: 900; color: #f59e0b;">RS <?php echo number_format($total_pending, 2); ?></div>
                        </div>
                    </div>
                    <div class="glass-card stat-card-pay">
                        <div class="stat-icon-pay" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Failed Payments</div>
                            <div style="font-size: 1.6rem; font-weight: 900; color: #ef4444;"><?php echo $total_failed; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Payments Table -->
                <div class="glass-card" style="padding: 0; overflow: hidden; border-radius: 20px;">
                    <div class="table-responsive">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Invoice & Client</th>
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Amount</th>
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Method</th>
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Date</th>
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Status</th>
                                    <th style="padding: 15px 20px; text-align: center; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="6" style="padding: 50px; text-align: center; color: #64748b;">
                                            <i class="fas fa-credit-card" style="font-size: 2.5rem; color: #cbd5e1; display: block; margin-bottom: 15px;"></i>
                                            <p style="font-size: 1rem; font-weight: 600;">No payments recorded yet.</p>
                                            <a href="create.php" style="display: inline-block; margin-top: 15px; padding: 10px 24px; background: var(--gradient-primary); color: white; border-radius: 12px; font-weight: 700; text-decoration: none;">Record First Payment</a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $pay): ?>
                                        <?php
                                            $s = $pay['status'];
                                            $sbg = '#f1f5f9'; $sc = '#64748b';
                                            if ($s === 'completed') { $sbg = '#ecfdf5'; $sc = '#10b981'; }
                                            elseif ($s === 'pending')   { $sbg = '#fffbeb'; $sc = '#f59e0b'; }
                                            elseif ($s === 'failed')    { $sbg = '#fef2f2'; $sc = '#ef4444'; }
                                        ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                                            <td style="padding: 18px 20px;">
                                                <div style="font-weight: 800; color: #1e293b;">#<?php echo htmlspecialchars($pay['invoice_number']); ?></div>
                                                <div style="font-size: 0.8rem; color: #64748b;"><?php echo htmlspecialchars($pay['client_name']); ?></div>
                                            </td>
                                            <td style="padding: 18px 20px;">
                                                <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($pay['currency'] ?? 'PKR'); ?> <?php echo number_format($pay['amount'], 2); ?></div>
                                            </td>
                                            <td style="padding: 18px 20px;">
                                                <div style="color: #64748b; font-size: 0.9rem;"><?php echo !empty($pay['payment_method']) ? htmlspecialchars($pay['payment_method']) : '—'; ?></div>
                                            </td>
                                            <td style="padding: 18px 20px;">
                                                <div style="color: #64748b; font-size: 0.9rem; font-weight: 600;">
                                                    <i class="far fa-calendar-alt" style="margin-right: 5px;"></i>
                                                    <?php echo date('M d, Y', strtotime($pay['payment_date'])); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 18px 20px;">
                                                <span style="padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: <?php echo $sbg; ?>; color: <?php echo $sc; ?>; text-transform: uppercase; letter-spacing: 0.5px;">
                                                    <?php echo $s; ?>
                                                </span>
                                            </td>
                                            <td style="padding: 18px 20px; text-align: center;">
                                                <div style="display: flex; justify-content: center; gap: 8px; align-items: center;">
                                                    <!-- Quick status dots -->
                                                    <div style="display: flex; background: #f1f5f9; padding: 4px; border-radius: 12px; gap: 6px; align-items: center; border: 1px solid #e2e8f0;">
                                                        <a href="update_status.php?id=<?php echo $pay['id']; ?>&status=pending" title="Mark Pending" style="background: #f59e0b; width: 14px; height: 14px; border-radius: 50%; opacity: <?php echo $pay['status'] === 'pending' ? '1' : '0.2'; ?>; display: block; <?php echo ($pay['status'] === 'completed') ? 'pointer-events: none; opacity: 0.1;' : ''; ?>"></a>
                                                        <a href="update_status.php?id=<?php echo $pay['id']; ?>&status=completed" title="Mark Completed" style="background: #10b981; width: 14px; height: 14px; border-radius: 50%; opacity: <?php echo $pay['status'] === 'completed' ? '1' : '0.2'; ?>; display: block; <?php echo ($pay['status'] === 'completed') ? 'pointer-events: none;' : ''; ?>" onclick="return confirm('Mark this payment as completed?')"></a>
                                                        <a href="update_status.php?id=<?php echo $pay['id']; ?>&status=failed" title="Mark Failed" style="background: #ef4444; width: 14px; height: 14px; border-radius: 50%; opacity: <?php echo $pay['status'] === 'failed' ? '1' : '0.2'; ?>; display: block; <?php echo ($pay['status'] === 'completed') ? 'pointer-events: none; opacity: 0.1;' : ''; ?>"></a>
                                                    </div>
                                                    <a href="view.php?id=<?php echo $pay['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: rgba(79,70,229,0.1); color: #4f46e5; border-radius: 10px; display: flex; align-items: center; justify-content: center;" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $pay['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: #f1f5f9; color: #475569; border-radius: 10px; display: flex; align-items: center; justify-content: center;" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?php echo $pay['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: rgba(239,68,68,0.1); color: #ef4444; border-radius: 10px; display: flex; align-items: center; justify-content: center;" title="Delete" onclick="return confirm('Delete this payment?')">
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


