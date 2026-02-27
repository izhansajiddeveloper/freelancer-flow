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
    SELECT p.*, c.client_name, c.email as client_email, c.company_name as client_company, c.address as client_address, c.phone as client_phone,
           u.full_name as user_name, u.email as user_email, u.phone as user_phone, u.job_title, u.profile_image
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
$date = date('d M Y', strtotime($proposal['created_at']));
$valid_until = date('d M Y', strtotime($proposal['created_at'] . ' + 15 days'));
$proposal_no = "PR-" . str_pad($proposal['id'], 3, '0', STR_PAD_LEFT);

$hide_navbar = true;
include_once '../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">

<div class="proposal-viewer">
    <!-- Action Bar -->
    <div class="viewer-actions no-print">
        <div class="actions-left">
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Proposals</a>
            <span class="proposal-id"><?php echo $proposal_no; ?> - <?php echo htmlspecialchars($proposal['project_title']); ?></span>
        </div>
        <div class="actions-right">
            <button onclick="window.print()" class="btn-action"><i class="fas fa-print"></i> Print</button>
            <a href="download.php?id=<?php echo $proposal_id; ?>" class="btn-action primary"><i class="fas fa-file-pdf"></i> Download PDF</a>
        </div>
    </div>

    <!-- Multi-page Proposal Mockup -->
    <div class="proposal-document">
        
        <!-- PAGE 1: COVER -->
        <div class="proposal-page cover-page">
            <div class="blob blob-1"></div>
            <div class="blob blob-2"></div>
            <div class="blob blob-3"></div>
            
            <div class="page-content">
                <div class="cover-header">
                    <div class="brand-logo">
                        <i class="fas fa-rocket"></i>
                        <span><?php echo $_SESSION['company_name'] ?? 'FreelanceFlow'; ?></span>
                    </div>
                </div>

                <div class="cover-title-box">
                    <p class="pre-title">Prepared For <?php echo htmlspecialchars($proposal['client_name']); ?></p>
                    <h1 class="main-title">PROJECT<br>PROPOSAL</h1>
                    <h2 class="sub-title"><?php echo htmlspecialchars($proposal['project_title']); ?></h2>
                </div>

                <div class="cover-footer">
                    <div class="footer-info academic-font">
                        <div class="info-group">
                            <label>Prepared for</label>
                            <strong><?php echo htmlspecialchars($proposal['client_name']); ?></strong>
                            <p><?php echo htmlspecialchars($proposal['client_address'] ?: 'Client Location'); ?></p>
                            <p><?php echo htmlspecialchars($proposal['client_email']); ?></p>
                            <p><?php echo htmlspecialchars($proposal['client_phone'] ?: ''); ?></p>
                        </div>
                        <div class="info-meta">
                            <div class="meta-item">
                                <label>Proposal Issued</label>
                                <span><?php echo $date; ?></span>
                            </div>
                            <div class="meta-item">
                                <label>Proposal Valid to</label>
                                <span><?php echo $valid_until; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="footer-url">
                        www.yourportfolio.com
                    </div>
                </div>
            </div>
        </div>

        <!-- PAGE 2: INTRODUCTION -->
        <div class="proposal-page">
            <div class="blob-mini-top"></div>
            <div class="page-content padded">
                <div class="section-title-alt">
                    <span class="step-num">01.</span>
                    <h2 class="title-text">Project Description</h2>
                </div>
                
                <div class="intro-grid">
                    <div class="intro-main">
                        <p class="welcome-text">Some people dream of success while others wake up and work.</p>
                        <div class="content-body">
                            <h3>Overview</h3>
                            <p><?php echo nl2br(htmlspecialchars($proposal['project_overview'] ?: 'A professional engagement focused on delivering high-impact results through strategic execution and technical excellence.')); ?></p>
                        </div>
                    </div>
                    <div class="intro-side">
                        <div class="company-card">
                            <label>About The Company</label>
                            <h4>Freelancer Experts</h4>
                            <p>We provide top-tier digital solutions tailored to your unique business needs.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PAGE 3: SCOPE & SERVICES -->
        <div class="proposal-page">
            <div class="page-content padded">
                <div class="section-title-alt">
                    <span class="step-num">02.</span>
                    <h2 class="title-text">Scope of Services</h2>
                </div>

                <div class="services-container">
                    <div class="services-header-box">
                        <div class="icon-box">
                            <i class="fas fa-network-wired"></i>
                        </div>
                        <div class="text-box">
                            <p><?php echo nl2br(htmlspecialchars($proposal['project_scope'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="service-grid-boxes">
                        <div class="service-mini-card">
                            <i class="fas fa-pencil-ruler"></i>
                            <h5>Custom Design</h5>
                            <p>Tailored visual identity and UI/UX solutions.</p>
                        </div>
                        <div class="service-mini-card">
                            <i class="fas fa-code"></i>
                            <h5>Development</h5>
                            <p>Robust, scalable and clean code implementation.</p>
                        </div>
                        <div class="service-mini-card">
                            <i class="fas fa-bullhorn"></i>
                            <h5>Marketing</h5>
                            <p>Strategy for growth and digital presence.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PAGE 4: BUDGET & MILESTONES -->
        <div class="proposal-page">
            <div class="page-content padded">
                <div class="section-title-alt">
                    <span class="step-num">03.</span>
                    <h2 class="title-text">Budget Breakdown</h2>
                </div>

                <div class="budget-table-container">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Project Phases / Milestones</th>
                                <th class="text-right">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($proposal['milestone_breakdown']) {
                                $lines = explode("\n", $proposal['milestone_breakdown']);
                                foreach ($lines as $line) {
                                    if (trim($line)) {
                                        echo "<tr><td>" . htmlspecialchars($line) . "</td><td class='text-right'>Defined</td></tr>";
                                    }
                                }
                            } else {
                                echo "<tr><td>Core Development Phase</td><td class='text-right'>PKR " . number_format($proposal['price'], 2) . "</td></tr>";
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td>TOTAL INVESTMENT</td>
                                <td class="text-right">PKR <?php echo number_format($proposal['price'], 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="timeline-banner">
                    <div class="banner-item">
                        <label>Timeline</label>
                        <span><?php echo htmlspecialchars($proposal['timeline'] ?: 'To be agreed'); ?></span>
                    </div>
                    <div class="banner-item">
                        <label>Engagement</label>
                        <span>Fixed Price</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- PAGE 5: TERMS & ACCEPTANCE -->
        <div class="proposal-page">
            <div class="blob-mini-bottom"></div>
            <div class="page-content padded">
                <div class="section-title-alt">
                    <span class="step-num">04.</span>
                    <h2 class="title-text">Authorization</h2>
                </div>

                <div class="terms-acceptance-grid">
                    <div class="terms-box">
                        <h3>General Terms</h3>
                        <p><?php echo nl2br(htmlspecialchars($proposal['terms'] ?: "• Final scope as defined herein.\n• Additional revisions subject to billing.")); ?></p>
                        
                        <h3 style="margin-top: 30px;">Payment Terms</h3>
                        <p><?php echo nl2br(htmlspecialchars($proposal['payment_terms'] ?: "• 50% Upfront Retainer\n• 50% Final Project Delivery")); ?></p>
                    </div>
                    
                    <div class="acceptance-signature">
                        <div class="signature-box">
                            <div class="sig-line"></div>
                            <p>Client Signature / Date</p>
                        </div>
                        <div class="signature-box">
                            <div class="sig-line"></div>
                            <p>Provider Signature / Date</p>
                        </div>
                    </div>
                </div>

                <div class="thank-you-note">
                    <i class="fas fa-thumbs-up"></i>
                    <h2>THANK YOU</h2>
                    <p>We look forward to working with you!</p>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    :root {
        --proposal-orange: #DE6A26;
        --proposal-navy: #2C3E50;
        --proposal-grey: #64748B;
        --proposal-bg: #F4F7F9;
        --proposal-font: 'Outfit', sans-serif;
    }

    body {
        margin: 0;
        background-color: #f1f5f9;
        font-family: var(--proposal-font);
    }

    .proposal-viewer {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding-top: 80px;
        padding-bottom: 50px;
    }

    /* Action Bar */
    .viewer-actions {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 70px;
        background: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 40px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        z-index: 1000;
    }

    .back-link {
        text-decoration: none;
        color: var(--proposal-grey);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
    }

    .proposal-id {
        margin-left: 20px;
        font-weight: 800;
        color: var(--proposal-navy);
        border-left: 1px solid #e2e8f0;
        padding-left: 20px;
    }

    .btn-action {
        padding: 10px 20px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: white;
        color: var(--proposal-navy);
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        font-size: 0.85rem;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-action.primary {
        background: var(--proposal-navy);
        color: white;
        border: none;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    /* Document Container */
    .proposal-document {
        width: 850px; /* A4 Ratioish */
        box-shadow: 0 20px 50px rgba(0,0,0,0.1);
    }

    /* Page Setup */
    .proposal-page {
        width: 100%;
        min-height: 1100px;
        background: white;
        position: relative;
        overflow: hidden;
        margin-bottom: 40px;
        box-sizing: border-box;
    }

    .page-content {
        position: relative;
        z-index: 5;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .page-content.padded {
        padding: 80px 70px;
    }

    /* Blobs */
    .blob {
        position: absolute;
        z-index: 1;
        opacity: 0.15;
    }

    .blob-1 {
        width: 800px;
        height: 800px;
        background: #E5E7EB;
        border-radius: 50%;
        top: -150px;
        left: -200px;
        filter: blur(40px);
    }

    .blob-2 {
        width: 350px;
        height: 350px;
        background: var(--proposal-orange);
        border-radius: 50%;
        bottom: -50px;
        right: -80px;
        opacity: 0.8 !important;
    }

    .blob-3 {
        width: 300px;
        height: 600px;
        background: var(--proposal-navy);
        border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%;
        top: 50px;
        right: -100px;
        transform: rotate(15deg);
        opacity: 0.8 !important;
    }

    .blob-mini-top {
        position: absolute;
        width: 150px;
        height: 150px;
        background: var(--proposal-navy);
        border-radius: 50%;
        top: -75px;
        right: -75px;
        opacity: 0.9;
    }

    .blob-mini-bottom {
        position: absolute;
        width: 200px;
        height: 200px;
        background: var(--proposal-orange);
        border-radius: 50%;
        bottom: -100px;
        right: -100px;
        opacity: 1;
    }

    /* Cover Page Specific */
    .cover-page {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .cover-header {
        padding: 60px 80px;
    }

    .brand-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 800;
        font-size: 1.5rem;
        color: var(--proposal-orange);
    }

    .cover-title-box {
        padding: 0 80px;
        margin-top: 100px;
    }

    .pre-title {
        font-weight: 700;
        color: var(--proposal-orange);
        text-transform: uppercase;
        letter-spacing: 4px;
        font-size: 0.9rem;
        margin-bottom: 20px;
    }

    .main-title {
        font-size: 5rem;
        line-height: 0.9;
        font-weight: 800;
        color: var(--proposal-navy);
        margin: 0;
        letter-spacing: -3px;
    }

    .sub-title {
        font-size: 1.8rem;
        font-weight: 300;
        color: var(--proposal-grey);
        margin-top: 20px;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    .cover-footer {
        padding: 60px 80px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }

    .footer-info {
        display: flex;
        gap: 60px;
    }

    .info-group label, .info-meta label {
        display: block;
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--proposal-grey);
        letter-spacing: 1px;
        margin-bottom: 5px;
        font-weight: 600;
    }

    .info-group strong {
        font-size: 1.1rem;
        color: var(--proposal-navy);
        display: block;
        margin-bottom: 5px;
    }

    .info-group p {
        margin: 0;
        color: var(--proposal-grey);
        font-size: 0.85rem;
    }

    .info-meta {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .meta-item span {
        font-weight: 700;
        color: var(--proposal-navy);
        font-size: 0.9rem;
    }

    .footer-url {
        color: var(--proposal-grey);
        font-size: 0.75rem;
        letter-spacing: 1px;
    }

    /* Sub Pages Styles */
    .section-title-alt {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 60px;
    }

    .step-num {
        font-size: 1.2rem;
        font-weight: 800;
        color: var(--proposal-orange);
    }

    .title-text {
        font-size: 2rem;
        font-weight: 800;
        color: var(--proposal-navy);
        margin: 0;
        text-transform: uppercase;
        letter-spacing: -0.5px;
    }

    .intro-grid {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: 60px;
    }

    .welcome-text {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.2;
        color: var(--proposal-orange);
        margin-bottom: 40px;
    }

    .content-body h3 {
        font-size: 1.2rem;
        color: var(--proposal-navy);
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .content-body p {
        color: #4B5563;
        line-height: 1.7;
        font-size: 1rem;
    }

    .company-card {
        background: #F9FAFB;
        padding: 40px;
        border-radius: 20px;
        border-right: 6px solid var(--proposal-orange);
    }

    .company-card label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--proposal-grey);
        font-weight: 800;
        letter-spacing: 2px;
    }

    .company-card h4 {
        margin: 10px 0;
        color: var(--proposal-navy);
        font-weight: 800;
    }

    .company-card p {
        font-size: 0.85rem;
        color: var(--proposal-grey);
        margin: 0;
    }

    /* Services Styles */
    .services-header-box {
        display: flex;
        gap: 30px;
        background: #F4F7F9;
        padding: 40px;
        border-radius: 24px;
        margin-bottom: 50px;
    }

    .icon-box {
        width: 60px;
        height: 60px;
        background: var(--proposal-navy);
        color: white;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .text-box p {
        margin: 0;
        font-size: 1.1rem;
        line-height: 1.6;
        color: var(--proposal-navy);
    }

    .service-grid-boxes {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
    }

    .service-mini-card {
        padding: 30px;
        border: 1px solid #E5E7EB;
        border-radius: 20px;
    }

    .service-mini-card i {
        font-size: 1.5rem;
        color: var(--proposal-orange);
        margin-bottom: 20px;
    }

    .service-mini-card h5 {
        margin: 0 0 10px;
        font-size: 1rem;
        font-weight: 800;
        color: var(--proposal-navy);
    }

    .service-mini-card p {
        margin: 0;
        font-size: 0.85rem;
        color: var(--proposal-grey);
    }

    /* Table Styles */
    .budget-table-container {
        margin-bottom: 50px;
    }

    .premium-table {
        width: 100%;
        border-collapse: collapse;
    }

    .premium-table th {
        text-align: left;
        padding: 20px;
        background: var(--proposal-navy);
        color: white;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    .premium-table td {
        padding: 20px;
        border-bottom: 1px solid #E5E7EB;
        color: var(--proposal-navy);
        font-weight: 600;
    }

    .text-right { text-align: right; }

    .total-row td {
        background: #F9FAFB;
        font-size: 1.2rem;
        font-weight: 800;
        color: var(--proposal-orange);
        border-bottom: none;
        padding: 30px 20px;
    }

    .timeline-banner {
        display: flex;
        gap: 40px;
        background: #F4F7F9;
        padding: 30px 40px;
        border-radius: 20px;
    }

    .banner-item label {
        display: block;
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--proposal-grey);
        font-weight: 800;
    }

    .banner-item span {
        font-size: 1rem;
        font-weight: 700;
        color: var(--proposal-navy);
    }

    /* Terms & Acceptance */
    .terms-acceptance-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 80px;
    }

    .terms-box h3 {
        font-size: 1rem;
        font-weight: 800;
        color: var(--proposal-navy);
        margin-bottom: 15px;
    }

    .terms-box p {
        font-size: 0.9rem;
        color: #4B5563;
        line-height: 1.6;
    }

    .acceptance-signature {
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        gap: 60px;
    }

    .sig-line {
        border-bottom: 2px solid #E5E7EB;
        height: 40px;
        margin-bottom: 10px;
    }

    .signature-box p {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--proposal-grey);
        font-weight: 700;
        margin: 0;
    }

    .thank-you-note {
        margin-top: auto;
        padding: 60px 0 0;
        text-align: center;
        border-top: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .thank-you-note i {
        font-size: 3rem;
        color: var(--proposal-orange);
        margin-bottom: 20px;
    }

    .thank-you-note h2 {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--proposal-navy);
        margin: 0;
        letter-spacing: 4px;
    }

    .thank-you-note p {
        color: var(--proposal-grey);
        font-weight: 600;
        margin-top: 5px;
    }

    /* PRINT STYLES */
    @media print {
        .viewer-actions, .dashboard-wrapper { display: none !important; }
        body { background: white !important; margin: 0 !important; padding: 0 !important; }
        .proposal-viewer { padding: 0 !important; }
        .proposal-document { box-shadow: none !important; width: 100% !important; margin: 0 !important; }
        .proposal-page { margin: 0 !important; border: none !important; page-break-after: always; height: 100vh !important; }
        .blob-1, .blob-2, .blob-3 { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>


