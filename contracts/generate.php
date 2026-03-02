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

// Fetch contract details
$stmt = $pdo->prepare("
    SELECT cont.*, c.client_name, c.email as client_email, c.company_name as client_company, c.address as client_address, c.phone as client_phone,
           p.project_title, p.id as project_id,
           u.full_name as user_name, u.email as user_email, u.phone as user_phone, u.job_title as user_job
    FROM contracts cont
    JOIN clients c ON cont.client_id = c.id
    JOIN projects p ON cont.project_id = p.id
    JOIN users u ON cont.user_id = u.id
    WHERE cont.id = ? AND cont.user_id = ?
");
$stmt->execute([$contract_id, $user_id]);
$c = $stmt->fetch();

if (!$c) {
    header("Location: index.php");
    exit();
}

$date = date('F d, Y', strtotime($c['created_at']));
$contract_no = "CON-" . str_pad($c['id'], 3, '0', STR_PAD_LEFT);
$brand_name = $_SESSION['company_name'] ?? 'FREELANCE FLOW';

// Enhanced Parser with Cleaner Formatting
function parseContractSections($text) {
    if (empty(trim($text))) return ['PROJECT DETAILS' => 'No details provided.'];
    
    $sections = [];
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $currentSection = null;
    $introText = "";
    
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        
        // Detect sections with or without ###
        if (preg_match('/^(?:###\s+)?(?:\d+\.|\d+)?\s*([a-zA-Z\s,&\/]+)$/', $trimmedLine, $matches)) {
            $potentialTitle = trim($matches[1]);
            if (strlen($potentialTitle) > 3 && strlen($potentialTitle) < 60 && !preg_match('/[.,;?!]/', $potentialTitle)) {
                $currentSection = strtoupper($potentialTitle);
                $sections[$currentSection] = "";
                continue;
            }
        }
        
        if ($currentSection) {
            $sections[$currentSection] .= $line . "\n";
        } else {
            if ($trimmedLine !== "") {
                $introText .= $line . "\n";
            }
        }
    }

    // Fallback if no sections
    if (empty($sections)) return ['PROJECT DETAILS' => trim($text)];
    
    // Clean each section: remove leading/trailing whitespace and excessive newlines
    foreach ($sections as $key => $val) {
        $sections[$key] = trim(preg_replace('/\n{3,}/', "\n\n", $val));
    }
    
    $sections['INTRO'] = trim($introText);
    return $sections;
}

$secs = parseContractSections($c['contract_details']);

function getSectionData($secs, $variants) {
    foreach ($variants as $v) {
        $v = strtoupper($v);
        foreach ($secs as $key => $content) {
            if (strpos($key, $v) !== false || strpos($v, $key) !== false) {
                return $content;
            }
        }
    }
    return null;
}

$hide_navbar = true;
include_once '../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">

<style>
    :root {
        --contract-teal: #004351;
        --contract-gold: #da9100;
        --contract-cream: #fff7e6;
        --contract-grey: #64748b;
        --font-serif: 'Playfair Display', serif;
        --font-sans: 'Outfit', sans-serif;
    }

    body {
        margin: 0;
        font-family: var(--font-sans);
        color: #1e293b;
        background: #f1f5f9;
        -webkit-print-color-adjust: exact;
    }

    .contract-viewer {
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
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        z-index: 1000;
    }

    .btn-action { padding: 10px 20px; text-decoration: none; font-weight: 700; font-size: 0.85rem; border-radius: 10px; border: 1px solid #e2e8f0; color: #1e293b; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
    .btn-action.primary { background: var(--contract-teal); color: white; border: none; }
    .btn-action:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

    /* Page Setup */
    .contract-page {
        width: 850px;
        min-height: 1100px;
        background: white;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        margin-bottom: 40px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* Page 1: Cover */
    .cover-header {
        background: var(--contract-teal);
        height: 380px;
        padding: 60px 80px;
        color: white;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .company-name { font-weight: 800; letter-spacing: 2px; text-transform: uppercase; font-size: 1.1rem; }
    .contract-title { font-family: var(--font-serif); font-size: 4rem; color: var(--contract-cream); line-height: 1; text-transform: uppercase; font-weight: 900; }

    .cover-body { padding: 60px 80px; flex: 1; }
    .intro-text { font-size: 1.1rem; line-height: 1.6; color: #475569; margin-bottom: 40px; }

    .party-title { font-family: var(--font-serif); font-size: 1.8rem; color: var(--contract-teal); margin-bottom: 15px; }
    .info-row { display: grid; grid-template-columns: 180px 1fr; border-bottom: 1px solid #f1f5f9; }
    .info-label { background: var(--contract-teal); color: white; padding: 12px 20px; font-weight: 600; font-size: 0.85rem; }
    .info-value { background: var(--contract-cream); color: #1e293b; padding: 12px 20px; font-weight: 500; }

    /* Standard Pages */
    .std-header { height: 100px; background: var(--contract-teal); color: white; padding: 0 80px; display: flex; align-items: center; justify-content: space-between; }
    .std-header span { font-weight: 800; text-transform: lowercase; letter-spacing: 1px; }

    .std-body { padding: 60px 80px; flex: 1; }
    .section-title { font-family: var(--font-serif); font-size: 2rem; color: var(--contract-teal); border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px; text-transform: uppercase; font-weight: 800; }
    .section-content { font-size: 1rem; line-height: 1.7; color: #334155; white-space: pre-wrap; padding-left: 5px; }

    /* Footer */
    .page-footer { height: 80px; background: var(--contract-teal); color: white; padding: 0 80px; display: flex; align-items: center; position: relative; margin-top: auto; }
    .footer-id { font-size: 0.8rem; font-weight: 600; opacity: 0.7; }
    .page-num { position: absolute; bottom: 0; right: 0; background: var(--contract-gold); height: 80px; width: 60px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.2rem; color: var(--contract-teal); }

    /* Signatures: USER WANTED TO EMPTY THE SIGNATURE PLACE */
    .sig-area { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; margin-top: 50px; }
    .sig-box { text-align: left; }
    .sig-label { font-family: var(--font-serif); font-size: 1.2rem; color: var(--contract-teal); margin-bottom: 60px; display: block; }
    .sig-line { height: 1px; background: #94a3b8; width: 100%; margin-bottom: 10px; }
    .sig-name { font-weight: 800; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }

    @media print {
        .viewer-actions, .dashboard-wrapper { display: none !important; }
        .contract-page { box-shadow: none !important; margin: 0 !important; page-break-after: always; }
    }
</style>

<div class="contract-viewer">
    <div class="viewer-actions no-print">
        <div class="actions-left">
            <a href="index.php" class="btn-action"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <span style="margin-left: 20px; font-weight: 800; color: var(--contract-teal); border-left: 1px solid #e2e8f0; padding-left: 20px;"><?php echo $contract_no; ?></span>
        </div>
        <div class="actions-right">
            <?php if ($c['status'] !== 'signed'): ?>
                <a href="update_status.php?id=<?php echo $contract_id; ?>&status=signed&ref=generate" class="btn-action"><i class="fas fa-check-circle"></i> Mark Signed</a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-action"><i class="fas fa-print"></i> Print</button>
            <a href="download.php?id=<?php echo $contract_id; ?>" class="btn-action primary"><i class="fas fa-file-pdf"></i> Download PDF</a>
        </div>
    </div>

    <!-- Page 1: Cover -->
    <div class="contract-page">
        <div class="cover-header">
            <div class="company-name"><?php echo $brand_name; ?></div>
            <div class="contract-title">Service<br>Agreement</div>
        </div>
        <div class="cover-body">
            <p class="intro-text"><?php echo nl2br(htmlspecialchars($secs['INTRO'] ?? "This formal agreement establishes the professional relationship between the Designer and the Client for the project: " . $c['project_title'])); ?></p>
            
            <div style="margin-top: 50px;">
                <h3 class="party-title">THE DESIGNER</h3>
                <div class="info-row"><div class="info-label">Name</div><div class="info-value"><?php echo htmlspecialchars($c['user_name']); ?></div></div>
                <div class="info-row"><div class="info-label">Email</div><div class="info-value"><?php echo htmlspecialchars($c['user_email']); ?></div></div>
                <div class="info-row"><div class="info-label">Phone</div><div class="info-value"><?php echo htmlspecialchars($c['user_phone']); ?></div></div>
            </div>

            <div style="margin-top: 40px;">
                <h3 class="party-title">THE CLIENT</h3>
                <div class="info-row"><div class="info-label">Name</div><div class="info-value"><?php echo htmlspecialchars($c['client_name']); ?></div></div>
                <div class="info-row"><div class="info-label">Address</div><div class="info-value"><?php echo htmlspecialchars($c['client_address']); ?></div></div>
                <div class="info-row"><div class="info-label">Email</div><div class="info-value"><?php echo htmlspecialchars($c['client_email']); ?></div></div>
                <div class="info-row"><div class="info-label">Phone</div><div class="info-value"><?php echo htmlspecialchars($c['client_phone'] ?? '--'); ?></div></div>
            </div>
        </div>
        <div class="page-footer">
            <div class="footer-id">Official Contract Document</div>
            <div class="page-num">1</div>
        </div>
    </div>

    <!-- Page 2: Project Scope -->
    <div class="contract-page">
        <div class="std-header"><span><?php echo strtolower(str_replace(' ', '', $brand_name)); ?></span><span>service agreement</span></div>
        <div class="std-body">
            <div class="contract-section">
                <h2 class="section-title">1. Project Details</h2>
                <div class="section-content"><?php echo nl2br(htmlspecialchars(getSectionData($secs, ['PROJECT DETAILS', 'DETAILS']) ?? "Details provided in main text.")); ?></div>
            </div>
            <div class="contract-section">
                <h2 class="section-title">2. Scope of Work</h2>
                <div class="section-content"><?php echo nl2br(htmlspecialchars(getSectionData($secs, ['SCOPE OF WORK', 'DELIVERABLES']) ?? "Scope as per initial project brief.")); ?></div>
            </div>
        </div>
        <div class="page-footer">
            <div class="page-num">2</div>
        </div>
    </div>

    <!-- Page 3: Terms -->
    <div class="contract-page">
        <div class="std-header"><span><?php echo strtolower(str_replace(' ', '', $brand_name)); ?></span><span>service agreement</span></div>
        <div class="std-body">
            <div class="contract-section">
                <h2 class="section-title">3. Payment Terms</h2>
                <div class="section-content"><?php echo nl2br(htmlspecialchars(getSectionData($secs, ['PAYMENT TERMS', 'PAYMENT', 'FEES']) ?? "Payment terms as discussed.")); ?></div>
            </div>
            <div class="contract-section">
                <h2 class="section-title">4. Timeline</h2>
                <div class="section-content"><?php echo nl2br(htmlspecialchars(getSectionData($secs, ['TIMELINE', 'DURATION']) ?? "Timeline as per project milestones.")); ?></div>
            </div>
        </div>
        <div class="page-footer">
            <div class="page-num">3</div>
        </div>
    </div>

    <!-- Page 4: Signatures -->
    <div class="contract-page">
        <div class="std-header"><span><?php echo strtolower(str_replace(' ', '', $brand_name)); ?></span><span>service agreement</span></div>
        <div class="std-body">
            <div class="contract-section">
                <h2 class="section-title">Final Acceptance</h2>
                <div class="section-content"><?php echo nl2br(htmlspecialchars(getSectionData($secs, ['ACCEPTANCE', 'SIGNATURES']) ?? "By signing below, both parties acknowledge and agree to the terms listed above.")); ?></div>
                
                <div class="sig-area">
                    <div class="sig-box">
                        <span class="sig-label">Signature of Designer</span>
                        <div class="sig-line"></div>
                        <div class="sig-name"><?php echo htmlspecialchars($c['user_name']); ?></div>
                    </div>
                    <div class="sig-box">
                        <span class="sig-label">Signature of Client</span>
                        <div class="sig-line"></div>
                        <div class="sig-name"><?php echo htmlspecialchars($c['client_name']); ?></div>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 100px; padding: 25px; background: #f8fafc; border-left: 4px solid var(--contract-gold); color: #64748b; font-size: 0.9rem; line-height: 1.6;">
                <strong>LEGAL NOTICE:</strong> This agreement is a legally binding document. Both parties should retain a copy for their records.
            </div>
        </div>
        <div class="page-footer">
            <div class="page-num">4</div>
        </div>
    </div>
</div>
