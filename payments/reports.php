<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();

// Monthly breakdown
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(payment_date, '%Y-%m') as month,
           SUM(CASE WHEN status='completed' THEN amount ELSE 0 END) as received,
           SUM(CASE WHEN status='pending'   THEN amount ELSE 0 END) as pending,
           COUNT(*) as count
    FROM payments
    WHERE user_id = ?
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute([$user_id]);
$monthly = $stmt->fetchAll();

// Top clients by payment
$stmt = $pdo->prepare("
    SELECT c.client_name, SUM(p.amount) as total, COUNT(p.id) as payments
    FROM payments p
    JOIN clients c ON p.client_id = c.id
    WHERE p.user_id = ? AND p.status = 'completed'
    GROUP BY p.client_id
    ORDER BY total DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$top_clients = $stmt->fetchAll();

// Overall totals
$stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$grand_total = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_count = $stmt->fetchColumn();

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left"><h2 style="font-weight: 800;">Payment Reports</h2></div>
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline" style="border-radius: 12px;"><i class="fas fa-arrow-left"></i> Back to Payments</a>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in">

                <!-- Summary -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
                    <div class="glass-card" style="padding: 30px; border-radius: 20px;">
                        <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Total Revenue Collected</div>
                        <div style="font-size: 2.2rem; font-weight: 900; color: #10b981;">RS <?php echo number_format($grand_total, 2); ?></div>
                    </div>
                    <div class="glass-card" style="padding: 30px; border-radius: 20px;">
                        <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Total Payments Recorded</div>
                        <div style="font-size: 2.2rem; font-weight: 900; color: #4f46e5;"><?php echo $total_count; ?></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1.6fr 1fr; gap: 25px;">

                    <!-- Monthly Table -->
                    <div class="glass-card" style="padding: 0; overflow: hidden; border-radius: 20px;">
                        <div style="padding: 20px 25px; border-bottom: 1px solid #f1f5f9;">
                            <h3 style="font-weight: 800; color: #1e293b; margin: 0;">Monthly Breakdown</h3>
                        </div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8fafc;">
                                    <th style="padding: 12px 20px; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Month</th>
                                    <th style="padding: 12px 20px; text-align: right; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Received</th>
                                    <th style="padding: 12px 20px; text-align: right; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Pending</th>
                                    <th style="padding: 12px 20px; text-align: center; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;">#</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($monthly)): ?>
                                    <tr><td colspan="4" style="padding: 30px; text-align: center; color: #64748b;">No data yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($monthly as $m): ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 14px 20px; font-weight: 700; color: #1e293b;"><?php echo date('F Y', strtotime($m['month'] . '-01')); ?></td>
                                            <td style="padding: 14px 20px; text-align: right; font-weight: 700; color: #10b981;">RS <?php echo number_format($m['received'], 2); ?></td>
                                            <td style="padding: 14px 20px; text-align: right; color: #f59e0b; font-weight: 600;">RS <?php echo number_format($m['pending'], 2); ?></td>
                                            <td style="padding: 14px 20px; text-align: center; color: #64748b;"><?php echo $m['count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Top Clients -->
                    <div class="glass-card" style="padding: 0; overflow: hidden; border-radius: 20px;">
                        <div style="padding: 20px 25px; border-bottom: 1px solid #f1f5f9;">
                            <h3 style="font-weight: 800; color: #1e293b; margin: 0;">Top Clients</h3>
                        </div>
                        <div style="padding: 20px;">
                            <?php if (empty($top_clients)): ?>
                                <p style="color: #64748b; text-align: center; padding: 20px 0;">No data yet.</p>
                            <?php else: ?>
                                <?php $max = $top_clients[0]['total'] ?? 1; ?>
                                <?php foreach ($top_clients as $tc): ?>
                                    <div style="margin-bottom: 18px;">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                            <span style="font-weight: 700; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($tc['client_name']); ?></span>
                                            <span style="font-weight: 700; color: #10b981; font-size: 0.9rem;">RS <?php echo number_format($tc['total'], 2); ?></span>
                                        </div>
                                        <div style="background: #f1f5f9; height: 8px; border-radius: 99px; overflow: hidden;">
                                            <div style="background: var(--gradient-primary); height: 100%; border-radius: 99px; width: <?php echo min(100, round($tc['total'] / $max * 100)); ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>
</div>


