<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$invoice_id = $_GET['id'] ?? 0;

if (!$invoice_id) {
    header("Location: index.php");
    exit();
}

// Fetch invoice with all relationships
$stmt = $pdo->prepare("
    SELECT i.*, c.client_name, c.email as client_email, c.company_name as client_company, c.address as client_address, c.phone as client_phone,
           p.project_title, p.id as project_id,
           u.full_name as user_name, u.email as user_email, u.phone as user_phone
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN projects p ON i.project_id = p.id
    JOIN users u ON i.user_id = u.id
    WHERE i.id = ? AND i.user_id = ?
");
$stmt->execute([$invoice_id, $user_id]);
$inv = $stmt->fetch();

if (!$inv) {
    die("Invoice not found.");
}

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">View Invoice: <?php echo htmlspecialchars($inv['invoice_number']); ?></h2>
            </div>
            <div class="topbar-actions" style="display: flex; gap: 12px;">
                <?php if ($inv['status'] === 'draft'): ?>
                    <a href="edit.php?id=<?php echo $invoice_id; ?>" class="btn btn-outline" style="border-radius: 12px;"><i class="fas fa-edit"></i> Edit</a>
                <?php endif; ?>
                
                <?php if ($inv['status'] !== 'paid'): ?>
                    <a href="mark_paid.php?id=<?php echo $invoice_id; ?>" class="btn btn-primary" style="background: #10b981; border: none; border-radius: 12px;" onclick="return confirm('Mark this invoice as paid?')"><i class="fas fa-check-circle"></i> Mark Paid</a>
                <?php endif; ?>
                
                <a href="download_pdf.php?id=<?php echo $invoice_id; ?>" class="btn btn-primary" style="border-radius: 12px;"><i class="fas fa-download"></i> Download PDF</a>
                <a href="index.php" class="btn btn-outline" style="border-radius: 12px;"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in" style="max-width: 900px; margin: 0 auto; padding-bottom: 50px;">
                
                <!-- The Invoice Paper -->
                <div class="glass-card" style="padding: 60px; border-radius: 8px; background: white !important; box-shadow: 0 20px 50px rgba(0,0,0,0.05); color: #334155;">
                    
                    <!-- Header -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 50px; border-bottom: 2px solid #f1f5f9; padding-bottom: 30px;">
                        <div>
                            <h1 style="font-size: 2.5rem; font-weight: 900; color: #1e293b; text-transform: uppercase; letter-spacing: -1px; margin-bottom: 5px;">INVOICE</h1>
                            <p style="color: #64748b; font-weight: 700; font-size: 1.1rem; line-height: 1.2;">#<?php echo htmlspecialchars($inv['invoice_number']); ?></p>
                        </div>
                        <div style="text-align: right;">
                            <?php 
                                $status = $inv['status'];
                                $bg = '#f1f5f9'; $color = '#64748b';
                                if ($status === 'paid') { $bg = '#ecfdf5'; $color = '#10b981'; }
                                elseif ($status === 'sent') { $bg = '#eff6ff'; $color = '#3b82f6'; }
                                elseif ($status === 'draft') { $bg = '#fefce8'; $color = '#ca8a04'; }
                                elseif ($status === 'overdue' || (strtotime($inv['due_date']) < time() && $status !== 'paid' && $status !== 'cancelled')) { 
                                    $bg = '#fef2f2'; $color = '#ef4444'; 
                                    $status = 'overdue';
                                }
                            ?>
                            <span style="padding: 8px 16px; border-radius: 50px; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; font-weight: 800; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; display: inline-block; margin-bottom: 15px;">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                            <div style="font-size: 0.95rem;">
                                <p style="margin-bottom: 5px;"><strong style="color: #1e293b;">Issue Date:</strong> <?php echo date('M d, Y', strtotime($inv['issue_date'])); ?></p>
                                <p><strong style="color: #1e293b;">Due Date:</strong> <?php echo date('M d, Y', strtotime($inv['due_date'])); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Addresses -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 50px;">
                        <div>
                            <h4 style="font-size: 0.75rem; text-transform: uppercase; color: #4f46e5; letter-spacing: 1px; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 5px; display: inline-block;">From</h4>
                            <p style="font-weight: 800; font-size: 1.1rem; color: #1e293b; margin-bottom: 5px;"><?php echo htmlspecialchars($inv['user_name']); ?></p>
                            <p style="color: #64748b; font-size: 0.95rem; line-height: 1.6;"><?php echo htmlspecialchars($inv['user_email']); ?></p>
                            <?php if ($inv['user_phone']): ?><p style="color: #64748b; font-size: 0.95rem;"><?php echo htmlspecialchars($inv['user_phone']); ?></p><?php endif; ?>
                        </div>
                        <div style="text-align: right;">
                            <h4 style="font-size: 0.75rem; text-transform: uppercase; color: #4f46e5; letter-spacing: 1px; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 5px; display: inline-block;">Bill To</h4>
                            <p style="font-weight: 800; font-size: 1.1rem; color: #1e293b; margin-bottom: 5px;"><?php echo htmlspecialchars($inv['client_name']); ?></p>
                            <?php if ($inv['client_company']): ?>
                                <p style="font-weight: 700; color: #475569; font-size: 0.95rem;"><?php echo htmlspecialchars($inv['client_company']); ?></p>
                            <?php endif; ?>
                            <?php if ($inv['client_address']): ?>
                                <p style="color: #64748b; font-size: 0.95rem; line-height: 1.6; white-space: pre-wrap;"><?php echo htmlspecialchars($inv['client_address']); ?></p>
                            <?php endif; ?>
                            <p style="color: #64748b; font-size: 0.95rem;"><?php echo htmlspecialchars($inv['client_email']); ?></p>
                            <?php if ($inv['client_phone']): ?><p style="color: #64748b; font-size: 0.95rem;"><?php echo htmlspecialchars($inv['client_phone']); ?></p><?php endif; ?>
                        </div>
                    </div>

                    <!-- Line Items / Summary -->
                    <div style="margin-bottom: 40px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 15px 10px; border-bottom: 2px solid #e2e8f0; color: #1e293b; font-weight: 800; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px;">Description</th>
                                    <th style="text-align: right; padding: 15px 10px; border-bottom: 2px solid #e2e8f0; color: #1e293b; font-weight: 800; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 20px 10px; border-bottom: 1px solid #f1f5f9;">
                                        <div style="font-weight: 700; color: #1e293b; font-size: 1.05rem; margin-bottom: 5px;">
                                            <?php echo htmlspecialchars($inv['project_title'] ? 'Services for: ' . $inv['project_title'] : 'Consulting / Freelance Services'); ?>
                                        </div>
                                    </td>
                                    <td style="padding: 20px 10px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: 600; font-size: 1.05rem;">
                                        RS<?php echo number_format($inv['subtotal'], 2); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div style="display: flex; justify-content: flex-end; margin-bottom: 50px;">
                        <div style="width: 350px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                                <span style="font-weight: 600; color: #64748b;">Subtotal:</span>
                                <span style="font-weight: 700;">RS<?php echo number_format($inv['subtotal'], 2); ?></span>
                            </div>
                            <?php if ($inv['tax'] > 0): ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                                <span style="font-weight: 600; color: #64748b;">Tax (<?php echo number_format($inv['tax'], 2); ?>%):</span>
                                <span style="font-weight: 700;">RS<?php echo number_format($inv['subtotal'] * ($inv['tax'] / 100), 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; padding: 15px 0; border-top: 2px solid #1e293b; margin-top: 10px;">
                                <span style="font-weight: 900; color: #1e293b; font-size: 1.2rem; text-transform: uppercase;">Total Due:</span>
                                <span style="font-weight: 900; color: var(--primary-color); font-size: 1.4rem;">RS<?php echo number_format($inv['total_amount'], 2); ?></span>
                            </div>
                            
                            <?php if ($inv['status'] === 'paid' && $inv['paid_date']): ?>
                            <div style="display: flex; justify-content: space-between; margin-top: 15px; color: #10b981; font-weight: 800; padding: 10px; background: #ecfdf5; border-radius: 8px;">
                                <span>Amount Paid:</span>
                                <span>RS<?php echo number_format($inv['total_amount'], 2); ?></span>
                            </div>
                            <div style="text-align: right; font-size: 0.8rem; color: #10b981; margin-top: 5px;">
                                Paid on <?php echo date('M d, Y', strtotime($inv['paid_date'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Notes -->
                    <?php if ($inv['notes']): ?>
                    <div style="background: #f8fafc; padding: 25px; border-radius: 12px; border-left: 4px solid #4f46e5;">
                        <h4 style="font-size: 0.85rem; text-transform: uppercase; color: #4f46e5; font-weight: 800; margin-bottom: 10px;">Notes / Payment Instructions</h4>
                        <div style="font-size: 0.95rem; line-height: 1.6; white-space: pre-wrap; color: #475569;"><?php echo htmlspecialchars($inv['notes']); ?></div>
                    </div>
                    <?php endif; ?>

                    <!-- Footer Message -->
                    <div style="text-align: center; margin-top: 60px; padding-top: 30px; border-top: 1px dashed #cbd5e1; color: #94a3b8; font-size: 0.9rem;">
                        <p>Thank you for your business. It's a pleasure working with you!</p>
                    </div>

                </div>
            </div>
        </div>
    </main>
</div>


