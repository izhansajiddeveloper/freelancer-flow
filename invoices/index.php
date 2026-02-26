<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$success_msg = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') $success_msg = "Invoice generated successfully!";
    if ($_GET['success'] === 'paid') $success_msg = "Payment recorded successfully!";
}

// Fetch all invoices with client and project names
$stmt = $pdo->prepare("
    SELECT i.*, c.client_name, c.country, p.project_title
    FROM invoices i
    JOIN clients c ON i.client_id = c.id 
    LEFT JOIN projects p ON i.project_id = p.id
    WHERE i.user_id = ? 
    ORDER BY i.created_at DESC
");
$stmt->execute([$user_id]);
$invoices = $stmt->fetchAll();

$hide_navbar = true;
include_once '../includes/header.php';
?>

<style>
    .action-btn-circle:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
</style>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Financial Invoices</h2>
            </div>
            <div class="topbar-actions">
                <a href="create.php" class="btn btn-primary" style="padding: 12px 24px; border-radius: 14px; font-weight: 800; background: var(--gradient-primary); border: none; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3); display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-file-invoice-dollar"></i> Create New Invoice
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
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Inv # & Client</th>
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Amount</th>
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Due Date</th>
                                    <th style="padding: 15px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Status</th>
                                    <th style="padding: 15px 20px; text-align: center; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($invoices)): ?>
                                    <tr>
                                        <td colspan="5" style="padding: 40px; text-align: center; color: #64748b;">
                                            <i class="fas fa-receipt" style="font-size: 2rem; color: #cbd5e1; display: block; margin-bottom: 10px;"></i>
                                            <p>No invoices found. Bill your clients to see them here.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($invoices as $inv): ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9; transition: all 0.2s;">
                                            <td style="padding: 20px;">
                                                <div style="font-weight: 800; color: #1e293b;">#<?php echo htmlspecialchars($inv['invoice_number'] ?? $inv['id']); ?></div>
                                                <div style="font-size: 0.8rem; color: #64748b;"><?php echo htmlspecialchars($inv['client_name']); ?></div>
                                            </td>
                                            <td style="padding: 20px;">
                                                <?php
                                                    $amount = $inv['total_amount'] ?? 0;
                                                    $is_foreign = (!empty($inv['country']) && strtolower(trim($inv['country'])) !== 'pakistan');
                                                    if ($is_foreign) {
                                                        $exchange_rate = 280; // Approx PKR to USD rate
                                                        $usd_amount = $amount / $exchange_rate;
                                                        $amount_display = '$' . number_format($usd_amount, 2) . ' <small style="color: #64748b; font-weight: normal;">(USD)</small>';
                                                    } else {
                                                        $amount_display = 'Rs. ' . number_format($amount, 2);
                                                    }
                                                ?>
                                                <div style="font-weight: 700; color: #1e293b;"><?php echo $amount_display; ?></div>
                                            </td>
                                            <td style="padding: 20px;">
                                                <div style="color: <?php echo (strtotime($inv['due_date']) < time() && $inv['status'] != 'paid') ? '#ef4444' : '#64748b'; ?>; font-size: 0.9rem; font-weight: 600;">
                                                    <i class="far fa-calendar-alt" style="margin-right: 5px;"></i>
                                                    <?php echo date('M d, Y', strtotime($inv['due_date'])); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 20px;">
                                                <?php 
                                                $status = $inv['status'];
                                                $bg = '#f1f5f9'; $color = '#64748b';
                                                if ($status === 'paid') { $bg = '#ecfdf5'; $color = '#10b981'; }
                                                elseif ($status === 'pending') { $bg = '#fffbeb'; $color = '#f59e0b'; }
                                                elseif ($status === 'overdue') { $bg = '#fef2f2'; $color = '#ef4444'; }
                                                ?>
                                                <span style="padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; text-transform: uppercase; letter-spacing: 0.5px;">
                                                    <?php echo $status; ?>
                                                </span>
                                            </td>
                                            <td style="padding: 20px; text-align: center;">
                                                <div style="display: flex; justify-content: center; gap: 8px; align-items: center; min-width: 320px;">
                                                    <!-- Quick Status -->
                                                    <div style="display: flex; background: #f1f5f9; padding: 4px; border-radius: 12px; gap: 6px; align-items: center; border: 1px solid #e2e8f0;">
                                                        <a href="update_status.php?id=<?php echo $inv['id']; ?>&status=sent&ref=index" class="status-dot" title="Mark as Sent" style="background: #3b82f6; width: 14px; height: 14px; border-radius: 50%; opacity: <?php echo $inv['status'] == 'sent' ? '1' : '0.2'; ?>; display: block;"></a>
                                                        <a href="update_status.php?id=<?php echo $inv['id']; ?>&status=paid&ref=index" class="status-dot" title="Mark as Paid" style="background: #10b981; width: 14px; height: 14px; border-radius: 50%; opacity: <?php echo $inv['status'] == 'paid' ? '1' : '0.2'; ?>; display: block;" onclick="return confirm('Mark this invoice as paid?')"></a>
                                                        <a href="update_status.php?id=<?php echo $inv['id']; ?>&status=cancelled&ref=index" class="status-dot" title="Mark as Cancelled" style="background: #ef4444; width: 14px; height: 14px; border-radius: 50%; opacity: <?php echo $inv['status'] == 'cancelled' ? '1' : '0.2'; ?>; display: block;"></a>
                                                    </div>

                                                    <a href="view.php?id=<?php echo $inv['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: rgba(79, 70, 229, 0.1); color: var(--primary-color); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;" title="View Invoice">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $inv['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: #f1f5f9; color: #475569; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;" title="Edit Invoice" <?php if($inv['status'] !== 'draft' && $inv['status'] !== 'pending') echo 'style="opacity: 0.5; cursor: not-allowed; pointer-events: none;"'; else echo 'onclick="return true;"'; ?>>
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="download_pdf.php?id=<?php echo $inv['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: rgba(37, 99, 235, 0.1); color: #2563eb; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;" title="Download PDF">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                    <a href="send_invoice.php?id=<?php echo $inv['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;" title="Send via Email" <?php if($inv['status'] === 'sent') echo 'onclick="if(!confirm(\'This email is already sent. Do you want to send it again?\')) return false;"'; ?>>
                                                        <i class="fas fa-paper-plane"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?php echo $inv['id']; ?>" class="action-btn-circle" style="width: 34px; height: 34px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s;" title="Delete Invoice" onclick="return confirm('Are you sure you want to delete this invoice?');">
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


