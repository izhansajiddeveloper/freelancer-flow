<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$contract_id = $_GET['id'] ?? 0;

if (!$contract_id) {
    header("Location: index.php");
    exit();
}

// Fetch contract with all relationships
$stmt = $pdo->prepare("
    SELECT cont.*, c.client_name, c.email as client_email, c.company_name as client_company,
           p.project_title, p.id as project_id,
           u.full_name as user_name, u.email as user_email
    FROM contracts cont
    JOIN clients c ON cont.client_id = c.id
    JOIN projects p ON cont.project_id = p.id
    JOIN users u ON cont.user_id = u.id
    WHERE cont.id = ? AND cont.user_id = ?
");
$stmt->execute([$contract_id, $user_id]);
$c = $stmt->fetch();

if (!$c) {
    die("Contract not found.");
}

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Contract View</h2>
            </div>
            <div class="topbar-actions" style="display: flex; gap: 12px;">
                <a href="edit.php?id=<?php echo $contract_id; ?>" class="btn btn-outline" style="border-radius: 12px;"><i class="fas fa-edit"></i> Edit</a>
                <?php if ($c['status'] !== 'signed'): ?>
                    <a href="index.php?id=<?php echo $contract_id; ?>" class="btn btn-primary" style="background: #f59e0b; border: none; border-radius: 12px;"><i class="fas fa-file-signature"></i>Back to Contracts</a>
                   
                <?php else: ?>
                    <a href="../uploads/contracts/<?php echo $c['pdf_file']; ?>" target="_blank" class="btn btn-primary" style="background: #10b981; border: none; border-radius: 12px;"><i class="fas fa-check-circle"></i> View Signed Copy</a>
                <?php endif; ?>
                <a href="download_pdf.php?id=<?php echo $contract_id; ?>" class="btn btn-primary" style="border-radius: 12px;"><i class="fas fa-download"></i> Download PDF</a>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in" style="max-width: 900px; margin: 0 auto;">
                <div class="glass-card" style="padding: 60px; border-radius: 8px; background: white !important; box-shadow: 0 20px 50px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 50px; border-bottom: 2px solid #f1f5f9; padding-bottom: 30px;">
                        <div>
                            <h1 style="font-size: 2.2rem; font-weight: 900; color: #1e293b; text-transform: uppercase; letter-spacing: -1px;">Service Agreement</h1>
                            <p style="color: #64748b; font-weight: 600;">Contract ID: #FF-CTR-<?php echo str_pad($c['id'], 3, '0', STR_PAD_LEFT); ?></p>
                        </div>
                        <div style="text-align: right;">
                            <span style="padding: 8px 16px; border-radius: 50px; background: #eef2ff; color: #4f46e5; font-weight: 800; font-size: 0.75rem; text-transform: uppercase;">
                                <?php echo $c['status']; ?>
                            </span>
                            <p style="margin-top: 10px; font-size: 0.9rem; color: #64748b;">Issued: <?php echo date('M d, Y', strtotime($c['created_at'])); ?></p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 60px;">
                        <div>
                            <h4 style="font-size: 0.75rem; text-transform: uppercase; color: #4f46e5; letter-spacing: 1px; margin-bottom: 15px;">Service Provider</h4>
                            <p style="font-weight: 800; font-size: 1.1rem; color: #1e293b;"><?php echo htmlspecialchars($c['user_name']); ?></p>
                            <p style="color: #64748b; font-size: 0.9rem;"><?php echo htmlspecialchars($c['user_email']); ?></p>
                        </div>
                        <div style="text-align: right;">
                            <h4 style="font-size: 0.75rem; text-transform: uppercase; color: #4f46e5; letter-spacing: 1px; margin-bottom: 15px;">Prepared For</h4>
                            <p style="font-weight: 800; font-size: 1.1rem; color: #1e293b;"><?php echo htmlspecialchars($c['client_name']); ?></p>
                            <?php if ($c['client_company']): ?>
                                <p style="font-weight: 700; color: #475569; font-size: 0.9rem;"><?php echo htmlspecialchars($c['client_company']); ?></p>
                            <?php endif; ?>
                            <p style="color: #64748b; font-size: 0.9rem;"><?php echo htmlspecialchars($c['client_email']); ?></p>
                        </div>
                    </div>

                    <div style="margin-bottom: 50px;">
                        <h4 style="font-size: 0.75rem; text-transform: uppercase; color: #4f46e5; letter-spacing: 1px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">Agreement Details</h4>
                        <div style="font-family: 'Inter', sans-serif; line-height: 1.8; color: #334155; white-space: pre-wrap;"><?php echo htmlspecialchars($c['contract_details']); ?></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 80px;">
                        <div style="border-top: 1px solid #cbd5e1; padding-top: 20px;">
                            <p style="font-size: 0.7rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Provider Signature</p>
                        </div>
                        <div style="border-top: 1px solid #cbd5e1; padding-top: 20px; text-align: right;">
                            <p style="font-size: 0.7rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Client Signature</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>


