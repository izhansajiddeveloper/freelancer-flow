<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();
$id = intval($_GET['id'] ?? 0);

if (!$id) { header("Location: index.php"); exit(); }

$stmt = $pdo->prepare("
    SELECT p.*, c.client_name, c.email as client_email, i.invoice_number, i.total_amount as invoice_total
    FROM payments p
    JOIN clients c ON p.client_id = c.id
    JOIN invoices i ON p.invoice_id = i.id
    WHERE p.id = ? AND p.user_id = ?
");
$stmt->execute([$id, $user_id]);
$pay = $stmt->fetch();
if (!$pay) { die("Payment not found."); }

$s = $pay['status'];
$sbg = '#f1f5f9'; $sc = '#64748b';
if ($s === 'completed') { $sbg = '#ecfdf5'; $sc = '#10b981'; }
elseif ($s === 'pending')  { $sbg = '#fffbeb'; $sc = '#f59e0b'; }
elseif ($s === 'failed')   { $sbg = '#fef2f2'; $sc = '#ef4444'; }

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Payment Details</h2>
            </div>
            <div class="topbar-actions">
                <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-outline" style="margin-right: 10px; border-radius: 12px;"><i class="fas fa-edit"></i> Edit</a>
                <a href="index.php" class="btn btn-outline" style="border-radius: 12px;"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>

        <div class="dashboard-container" style="max-width: 800px; margin: 0 auto;">
            <div class="animate-fade-in">
                <div class="glass-card" style="padding: 40px; border-radius: 24px;">

                    <!-- Header row -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 35px; padding-bottom: 25px; border-bottom: 2px solid #f1f5f9;">
                        <div>
                            <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Invoice</div>
                            <div style="font-size: 1.5rem; font-weight: 900; color: #1e293b;">#<?php echo htmlspecialchars($pay['invoice_number']); ?></div>
                            <div style="color: #64748b; margin-top: 4px;"><?php echo htmlspecialchars($pay['client_name']); ?></div>
                        </div>
                        <div style="text-align: right;">
                            <span style="padding: 8px 18px; border-radius: 25px; font-size: 0.8rem; font-weight: 800; background: <?php echo $sbg; ?>; color: <?php echo $sc; ?>; text-transform: uppercase; letter-spacing: 0.5px;">
                                <?php echo $s; ?>
                            </span>
                            <div style="margin-top: 15px; font-size: 2rem; font-weight: 900; color: #1e293b;">
                                <?php echo htmlspecialchars($pay['currency'] ?? 'PKR'); ?> <?php echo number_format($pay['amount'], 2); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Details Grid -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Payment Date</div>
                            <div style="font-weight: 600; color: #1e293b;"><?php echo date('d F Y', strtotime($pay['payment_date'])); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Payment Method</div>
                            <div style="font-weight: 600; color: #1e293b;"><?php echo !empty($pay['payment_method']) ? htmlspecialchars($pay['payment_method']) : '—'; ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Transaction Reference</div>
                            <div style="font-weight: 600; color: #1e293b; font-family: monospace;"><?php echo !empty($pay['transaction_reference']) ? htmlspecialchars($pay['transaction_reference']) : '—'; ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Invoice Total</div>
                            <div style="font-weight: 600; color: #1e293b;">RS <?php echo number_format($pay['invoice_total'], 2); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Client Email</div>
                            <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($pay['client_email']); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Recorded On</div>
                            <div style="font-weight: 600; color: #1e293b;"><?php echo date('d F Y, h:i A', strtotime($pay['created_at'])); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($pay['notes'])): ?>
                        <div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 14px; border: 1px solid #e2e8f0;">
                            <div style="font-weight: 700; color: #64748b; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">Notes</div>
                            <p style="color: #475569; line-height: 1.7; margin: 0;"><?php echo nl2br(htmlspecialchars($pay['notes'])); ?></p>
                        </div>
                    <?php endif; ?>


                </div>
            </div>
        </div>
    </main>
</div>

