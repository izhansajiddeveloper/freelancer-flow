<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$proposal_id = $_GET['id'] ?? 0;

if (!$proposal_id) {
    header("Location: index.php");
    exit();
}

// Fetch proposal details
$stmt = $pdo->prepare("
    SELECT p.*, c.client_name, c.email as client_email, c.company_name as client_company, c.address as client_address,
           u.full_name as user_name, u.email as user_email, u.phone as user_phone, u.job_title
    FROM proposals p 
    JOIN clients c ON p.client_id = c.id 
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ? AND p.user_id = ?
");
$stmt->execute([$proposal_id, $user_id]);
$proposal = $stmt->fetch();

if (!$proposal) {
    header("Location: index.php");
    exit();
}

// Format the date
$date = date('d F Y', strtotime($proposal['created_at']));
$proposal_no = "FF-PR-" . str_pad($proposal['id'], 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal - <?php echo htmlspecialchars($proposal['project_title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --bg-page: #f1f5f9;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-page); 
            color: var(--text-main);
            line-height: 1.7;
        }
        .action-bar {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 15px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            padding: 12px 25px;
            border-radius: 50px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            z-index: 1000;
        }
        .btn-action {
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }
        .btn-back { background: #f1f5f9; color: #475569; }
        .btn-download { background: #eff6ff; color: #2563eb; }
        .btn-send { background: #f0fdf4; color: #10b981; }
        .btn-action:hover { transform: translateY(-2px); filter: brightness(0.95); }
        .preview-wrapper {
            padding: 60px 20px;
            min-height: 100vh;
        }
        .proposal-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 100px 100px;
            box-shadow: 0 40px 100px -20px rgba(0,0,0,0.1);
            border-radius: 8px;
            position: relative;
        }
        
        .divider-main {
            height: 2px;
            background: #000;
            margin: 40px 0;
            width: 100%;
        }
        .divider-sub {
            height: 1px;
            background: var(--border);
            margin: 30px 0;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 60px;
        }
        .header-top h1 {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: 5px;
            color: #000;
            text-transform: uppercase;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            margin-bottom: 60px;
        }
        .info-box h4 {
            font-weight: 800;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
            color: var(--text-muted);
        }
        .info-box p {
            font-size: 1rem;
            color: #334155;
            margin-bottom: 4px;
        }

        .content-section {
            margin-bottom: 50px;
        }
        .content-section h2 {
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 25px;
            color: #000;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .content-section h2::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        .content-body {
            font-size: 1.05rem;
            color: #334155;
            white-space: pre-line;
        }

        .total-banner {
            background: #000;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            margin: 40px 0;
            border-radius: 4px;
        }
        .total-label {
            font-weight: 700;
            font-size: 0.9rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            opacity: 0.7;
        }
        .total-amount {
            font-weight: 800;
            font-size: 1.8rem;
        }

        .actions-bar {
            max-width: 900px;
            margin: 0 auto 30px;
            display: flex;
            justify-content: space-between;
            background: white;
            padding: 15px 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            position: sticky;
            top: 20px;
            z-index: 100;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-primary { background: #000; color: white; }
        .btn-outline { border: 1px solid var(--border); color: var(--text-main); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        
        .status-pill {
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        @media print {
            .actions-bar { display: none; }
            body { background: white; padding: 0; }
            .preview-wrapper { padding: 0; }
            .proposal-container { box-shadow: none; max-width: 100%; border: none; padding: 40px; }
        }
    </style>
</head>
<body>
    <div class="action-bar">
        <a href="index.php" class="btn-action btn-back"><i class="fas fa-arrow-left"></i> Back</a>
        <a href="download.php?id=<?php echo $proposal_id; ?>" class="btn-action btn-download"><i class="fas fa-file-pdf"></i> Download PDF</a>
        <a href="send.php?id=<?php echo $proposal_id; ?>" class="btn-action btn-send"><i class="fas fa-paper-plane"></i> Send via Email</a>
    </div>

    <div class="preview-wrapper">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 20px; max-width: 900px; margin-left: auto; margin-right: auto;">
            <?php 
            $status_colors = [
                'draft' => ['bg' => '#f1f5f9', 'text' => '#64748b'],
                'sent' => ['bg' => '#eff6ff', 'text' => '#3b82f6'],
                'accepted' => ['bg' => '#f0fdf4', 'text' => '#10b981'],
                'rejected' => ['bg' => '#fef2f2', 'text' => '#ef4444']
            ];
            $sc = $status_colors[$proposal['status']] ?? $status_colors['draft'];
            ?>
            <span class="status-pill" style="background: <?php echo $sc['bg']; ?>; color: <?php echo $sc['text']; ?>; padding: 10px 20px; border-radius: 50px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;">
                ● <?php echo ucfirst($proposal['status']); ?>
            </span>
        </div>

        <div class="proposal-container" style="border-left: 8px solid var(--primary); padding-left: 60px;">
            <!-- Header Section -->
            <div class="header-top">
                <div>
                    <h1 style="color: #1e293b; font-size: 3rem; letter-spacing: -1px;">Project Proposal</h1>
                    <p style="letter-spacing: 3px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; margin-top: 5px;">STRATEGIC EXECUTION FRAMEWORK</p>
                </div>
                <div style="text-align: right;">
                    <p style="font-weight: 800; font-size: 1.1rem; color: #1e293b;">ID: <?php echo $proposal_no; ?></p>
                    <p style="font-weight: 600; color: var(--text-muted);">Issued on <?php echo $date; ?></p>
                </div>
            </div>

            <div class="divider-main"></div>

            <!-- Prepared info -->
            <div class="info-grid">
                <div class="info-box">
                    <h4>Drafted By</h4>
                    <p><strong><?php echo htmlspecialchars($proposal['user_name']); ?></strong></p>
                    <p style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars($proposal['job_title']); ?></p>
                    <p><?php echo htmlspecialchars($proposal['user_email']); ?></p>
                    <?php if ($proposal['user_phone']): ?>
                        <p><?php echo htmlspecialchars($proposal['user_phone']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="info-box" style="text-align: right;">
                    <h4>Prepared For</h4>
                    <p><strong><?php echo htmlspecialchars($proposal['client_name']); ?></strong></p>
                    <?php if ($proposal['client_company']): ?>
                        <p><strong><?php echo htmlspecialchars($proposal['client_company']); ?></strong></p>
                    <?php endif; ?>
                    <p><?php echo htmlspecialchars($proposal['client_email']); ?></p>
                    <?php if ($proposal['client_address']): ?>
                        <p><?php echo nl2br(htmlspecialchars($proposal['client_address'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 1. OVERVIEW -->
            <div class="content-section">
                <h2><span style="color: var(--primary); margin-right: 15px;">01</span> Overview</h2>
                <div class="content-body">
                    <p style="font-weight: 800; font-size: 1.25rem; color: #1e293b; margin-bottom: 20px;">Focus: <?php echo htmlspecialchars($proposal['project_title']); ?></p>
                    <?php echo nl2br(htmlspecialchars($proposal['project_overview'] ?: 'A professional engagement focused on delivering high-impact results through strategic execution and technical excellence.')); ?>
                </div>
            </div>

            <!-- 2. PROJECT SCOPE -->
            <div class="content-section">
                <h2><span style="color: var(--primary); margin-right: 15px;">02</span> Project Scope</h2>
                <div class="content-body">
                    <?php echo nl2br(htmlspecialchars($proposal['project_scope'])); ?>
                </div>
            </div>

            <!-- 3. DELIVERY SCHEDULE -->
            <?php if ($proposal['milestone_breakdown']): ?>
            <div class="content-section">
                <h2><span style="color: var(--primary); margin-right: 15px;">03</span> Delivery Schedule</h2>
                <div class="content-body">
                    <?php echo nl2br(htmlspecialchars($proposal['milestone_breakdown'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Total Investment Banner -->
            <div class="total-banner" style="background: #f8fafc; color: #1e293b; border: 1px solid #e2e8f0; height: auto; padding: 40px;">
                <div>
                    <div class="total-label" style="color: #64748b; font-size: 0.8rem; letter-spacing: 2px;">Proposed Investment</div>
                    <div class="total-amount" style="font-size: 2.5rem; letter-spacing: -1px;">PKR <?php echo number_format($proposal['price'], 0); ?><span style="font-size: 1rem; color: #94a3b8; font-weight: normal; margin-left: 10px;">Total Value</span></div>
                </div>
                <div style="text-align: right; color: #64748b; font-size: 0.9rem;">
                    <strong>Timeline:</strong> <?php echo htmlspecialchars($proposal['timeline'] ?: 'See Schedule'); ?><br>
                    <strong>Validity:</strong> 15 Days from Issue
                </div>
            </div>

            <!-- 4. COMMERCIAL TERMS -->
            <div class="content-section">
                <h2><span style="color: var(--primary); margin-right: 15px;">04</span> Commercial Terms</h2>
                <div class="content-body">
                    <p style="font-weight: 800; color: #1e293b; margin-bottom: 5px;">Payment Structure</p>
                    <?php echo nl2br(htmlspecialchars($proposal['payment_terms'] ?: "• Initial Retainer: 50%\n• Final Delivery: 50%")); ?>
                    <br><br>
                    <p style="font-weight: 800; color: #1e293b; margin-bottom: 5px;">Terms of Engagement</p>
                    <?php echo nl2br(htmlspecialchars($proposal['terms'] ?: "• Scope represents all included deliverables.\n• Additional requirements will be quoted separately.")); ?>
                </div>
            </div>

            <div class="divider-sub" style="margin-top: 80px; border-bottom: 2px solid #000;"></div>

            <!-- 5. ACCEPTANCE -->
            <div class="content-section" style="page-break-inside: avoid;">
                <h2><span style="color: var(--primary); margin-right: 15px;">05</span> Acceptance & Authorization</h2>
                <div class="content-body">
                    <p style="margin-bottom: 60px; font-size: 1rem;">By signing this document, the Client authorizes the commencement of the project as outlined within this framework.</p>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 60px;">
                        <div>
                            <div style="height: 60px; border-bottom: 1.5pt solid #cbd5e1; margin-bottom: 15px;"></div>
                            <p style="font-weight: 800; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Client Signature // Date</p>
                        </div>
                        <div>
                            <div style="height: 60px; border-bottom: 1.5pt solid #cbd5e1; margin-bottom: 15px;"></div>
                            <p style="font-weight: 800; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Provider Signature // Date</p>
                        </div>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 100px;">
                <p style="font-weight: 700; font-size: 0.9rem; letter-spacing: 1px; color: #94a3b8;">This document remains confidential and is intended solely for the recipient.</p>
            </div>
        </div>
    </div>
</body>
</html>
