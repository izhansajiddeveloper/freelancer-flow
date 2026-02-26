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

// Fetch proposal details with client info and user info
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

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Proposal Document</h2>
            </div>
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline" style="margin-right: 10px; font-size: 0.85rem; padding: 10px 20px; border-radius: 12px; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-arrow-left" style="font-size: 0.8rem;"></i> All Proposals
                </a>
                <a href="download.php?id=<?php echo $proposal_id; ?>" class="btn btn-primary" style="padding: 10px 20px; border-radius: 12px; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </a>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in" style="max-width: 1000px; margin: 0 auto;">
                <!-- Status & Identification Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <div>
                        <span class="status-badge <?php echo strtolower($proposal['status']); ?>" style="padding: 8px 16px; font-size: 0.8rem; border-radius: 10px;">
                            ● <?php echo strtoupper($proposal['status']); ?>
                        </span>
                    </div>
                </div>

                <!-- Main Proposal Content -->
                <div class="glass-card" style="padding: 60px 80px; border-radius: 30px; position: relative; border-left: 10px solid var(--primary-color);">
                    <!-- Header Section -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 60px;">
                        <div>
                            <h1 style="color: #1e293b; font-size: 3rem; font-weight: 800; letter-spacing: -1.5px; margin-bottom: 5px;">Project Proposal</h1>
                            <p style="letter-spacing: 4px; font-weight: 700; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Strategic Execution Framework</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-weight: 800; font-size: 1.25rem; color: #1e293b;"><?php echo $proposal_no; ?></p>
                            <p style="font-weight: 600; color: #64748b;">Issued on <?php echo $date; ?></p>
                        </div>
                    </div>

                    <hr style="border: 0; border-top: 2px solid #1e293b; margin-bottom: 60px;">

                    <!-- Participant Grid -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 80px; margin-bottom: 80px;">
                        <div>
                            <h4 style="font-weight: 800; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px; color: #64748b;">Expert Consultation By</h4>
                            <p style="font-size: 1.15rem; font-weight: 800; color: #1e293b; margin-bottom: 2px;"><?php echo htmlspecialchars($proposal['user_name']); ?></p>
                            <p style="color: var(--primary-color); font-weight: 700; font-size: 0.95rem; margin-bottom: 10px;"><?php echo htmlspecialchars($proposal['job_title']); ?></p>
                            <p style="color: #475569;"><?php echo htmlspecialchars($proposal['user_email']); ?></p>
                            <?php if ($proposal['user_phone']): ?>
                                <p style="color: #475569;"><?php echo htmlspecialchars($proposal['user_phone']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: right;">
                            <h4 style="font-weight: 800; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px; color: #64748b;">Prepared For</h4>
                            <p style="font-size: 1.15rem; font-weight: 800; color: #1e293b; margin-bottom: 2px;"><?php echo htmlspecialchars($proposal['client_name']); ?></p>
                            <?php if ($proposal['client_company']): ?>
                                <p style="font-weight: 700; color: #334155; margin-bottom: 10px;"><?php echo htmlspecialchars($proposal['client_company']); ?></p>
                            <?php endif; ?>
                            <p style="color: #475569;"><?php echo htmlspecialchars($proposal['client_email']); ?></p>
                            <?php if ($proposal['client_address']): ?>
                                <div style="color: #475569; margin-top: 5px;"><?php echo nl2br(htmlspecialchars($proposal['client_address'])); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 01. OVERVIEW -->
                    <div style="margin-bottom: 60px;">
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 30px; display: flex; align-items: center; gap: 20px;">
                            <span style="color: var(--primary-color);">01</span> Project Overview
                            <span style="flex: 1; height: 1px; background: #e2e8f0;"></span>
                        </h2>
                        <div style="color: #334155; line-height: 1.8; font-size: 1.1rem;">
                            <p style="font-weight: 800; font-size: 1.3rem; color: #1e293b; margin-bottom: 20px; border-left: 4px solid var(--primary-color); padding-left: 20px;">Focus: <?php echo htmlspecialchars($proposal['project_title']); ?></p>
                            <?php echo nl2br(htmlspecialchars($proposal['project_overview'] ?: 'A professional engagement focused on delivering high-impact results through strategic execution and technical excellence.')); ?>
                        </div>
                    </div>

                    <!-- 02. SCOPE -->
                    <div style="margin-bottom: 60px;">
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 30px; display: flex; align-items: center; gap: 20px;">
                            <span style="color: var(--primary-color);">02</span> Implementation Scope
                            <span style="flex: 1; height: 1px; background: #e2e8f0;"></span>
                        </h2>
                        <div style="color: #334155; line-height: 1.8; font-size: 1.1rem;">
                            <?php echo nl2br(htmlspecialchars($proposal['project_scope'])); ?>
                        </div>
                    </div>

                    <!-- 03. MILESTONES -->
                    <?php if ($proposal['milestone_breakdown']): ?>
                    <div style="margin-bottom: 60px;">
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 30px; display: flex; align-items: center; gap: 20px;">
                            <span style="color: var(--primary-color);">03</span> Delivery Schedule
                            <span style="flex: 1; height: 1px; background: #e2e8f0;"></span>
                        </h2>
                        <div style="color: #334155; line-height: 1.8; font-size: 1.1rem; background: #f8fafc; padding: 40px; border-radius: 20px; border: 1px dashed #cbd5e1;">
                            <?php echo nl2br(htmlspecialchars($proposal['milestone_breakdown'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Investment Banner -->
                    <div style="background: #1e293b; color: white; padding: 50px 60px; border-radius: 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 60px; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
                        <div>
                            <div style="text-transform: uppercase; letter-spacing: 3px; font-size: 0.8rem; font-weight: 700; color: #94a3b8; margin-bottom: 10px;">Proposed Investment Value</div>
                            <div style="font-size: 3rem; font-weight: 800; letter-spacing: -2px;">PKR <?php echo number_format($proposal['price'], 0); ?><span style="font-size: 1.1rem; font-weight: 500; color: #94a3b8; margin-left: 15px; letter-spacing: 0;">Net Value</span></div>
                        </div>
                        <div style="text-align: right;">
                            <div style="margin-bottom: 10px;"><strong style="color: #94a3b8;">Engagement Duration:</strong> <?php echo htmlspecialchars($proposal['timeline'] ?: 'Variable'); ?></div>
                            <div style="color: #94a3b8;"><i class="fas fa-info-circle"></i> Proposal Valid for 15 Days</div>
                        </div>
                    </div>

                    <!-- 04. TERMS -->
                    <div style="margin-bottom: 80px;">
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 30px; display: flex; align-items: center; gap: 20px;">
                            <span style="color: var(--primary-color);">04</span> Commercial Terms
                            <span style="flex: 1; height: 1px; background: #e2e8f0;"></span>
                        </h2>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                            <div>
                                <h5 style="font-weight: 800; color: #1e293b; margin-bottom: 15px; font-size: 0.95rem; text-transform: uppercase;">Payment Structure</h5>
                                <div style="color: #475569; font-size: 1rem; line-height: 1.7;">
                                    <?php echo nl2br(htmlspecialchars($proposal['payment_terms'] ?: "• 50% Upfront Retainer\n• 50% Final Project Delivery")); ?>
                                </div>
                            </div>
                            <div>
                                <h5 style="font-weight: 800; color: #1e293b; margin-bottom: 15px; font-size: 0.95rem; text-transform: uppercase;">Engagement Policy</h5>
                                <div style="color: #475569; font-size: 1rem; line-height: 1.7;">
                                    <?php echo nl2br(htmlspecialchars($proposal['terms'] ?: "• Final scope as defined herein.\n• Additional revisions subject to billing.")); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 05. AUTHORIZATION -->
                    <div style="padding-top: 60px; border-top: 2px solid #1e293b;">
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 40px;">05 Authorization & Acceptance</h2>
                        <p style="color: #475569; margin-bottom: 60px; font-size: 1.1rem; max-width: 600px;">Project initiation signifies acceptance of the strategic framework and commercial terms detailed above.</p>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 100px;">
                            <div>
                                <div style="height: 1px; background: #cbd5e1; margin-bottom: 20px;"></div>
                                <p style="font-weight: 800; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px;">Client Signature & Date</p>
                            </div>
                            <div>
                                <div style="height: 1px; background: #cbd5e1; margin-bottom: 20px;"></div>
                                <p style="font-weight: 800; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px;">Provider Signature & Date</p>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 100px; padding-top: 40px; border-top: 1px solid #f1f5f9;">
                        <p style="font-weight: 700; font-size: 0.8rem; letter-spacing: 2px; color: #cbd5e1; text-transform: uppercase;">Confidential Business Document</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
    .status-badge {
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 800;
        font-size: 0.7rem;
        letter-spacing: 1px;
    }
    .status-badge.accepted { background: rgba(16, 185, 129, 0.1); color: #059669; }
    .status-badge.sent { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
    .status-badge.draft { background: rgba(100, 116, 139, 0.1); color: #475569; }
    .status-badge.rejected { background: rgba(239, 68, 68, 0.1); color: #dc2626; }

    @media print {
        .dashboard-wrapper .sidebar, .dashboard-topbar { display: none !important; }
        .main-content { padding: 0 !important; margin: 0 !important; }
        .glass-card { box-shadow: none !important; border: 1px solid #eee !important; padding: 40px !important; }
        body { background: white !important; }
        .dashboard-container { padding: 0 !important; }
    }
</style>


