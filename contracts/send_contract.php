<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';
require_once '../helpers/mail_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$contract_id = $_GET['id'] ?? $_POST['contract_id'] ?? 0;

if (!$contract_id) { header("Location: index.php"); exit(); }

// Fetch contract and client details
$stmt = $pdo->prepare("
    SELECT cont.*, c.client_name, c.email as client_email, c.company_name as client_company, c.address as client_address, c.phone as client_phone,
           p.project_title, u.full_name as user_name, u.email as user_email, u.phone as user_phone, u.job_title as user_job
    FROM contracts cont
    JOIN clients c ON cont.client_id = c.id
    JOIN projects p ON cont.project_id = p.id
    JOIN users u ON cont.user_id = u.id
    WHERE cont.id = ? AND cont.user_id = ?
");
$stmt->execute([$contract_id, $user_id]);
$c = $stmt->fetch();

if (!$c) die("Contract not found.");

require_once '../vendor/tcpdf/tcpdf.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_email = $_POST['recipient_email'] ?? '';
    $subject  = $_POST['email_subject'] ?? '';
    $message_body = $_POST['email_body'] ?? '';

    if (empty($to_email) || empty($subject) || empty($message_body)) {
        $error = "Recipient, Subject, and Message are required.";
    } else {
        // --- Shared Robust Parser ---
        function parseContractSections($text) {
            if (empty(trim($text))) return ['PROJECT DETAILS' => 'No details provided.'];
            $sections = [];
            $text = str_replace(["\r\n", "\r"], "\n", $text);
            $lines = explode("\n", $text);
            $currentSection = null;
            $introText = "";
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if (preg_match('/^(?:###\s+)?(?:\d+\.|\d+)?\s*([a-zA-Z\s,&\/]+)$/', $trimmedLine, $matches)) {
                    $potentialTitle = trim($matches[1]);
                    if (strlen($potentialTitle) > 3 && strlen($potentialTitle) < 60 && !preg_match('/[.,;?!]/', $potentialTitle)) {
                        $currentSection = strtoupper($potentialTitle);
                        $sections[$currentSection] = "";
                        continue;
                    }
                }
                if ($currentSection) $sections[$currentSection] .= $line . "\n";
                else if ($trimmedLine !== "") $introText .= $line . "\n";
            }
            if (empty($sections)) return ['PROJECT DETAILS' => trim($text)];
            foreach($sections as $k => $v) $sections[$k] = trim(preg_replace('/\n{3,}/', "\n\n", $v));
            return array_merge(['INTRO' => trim($introText)], $sections);
        }

        $secs = parseContractSections($c['contract_details']);
        $date = date('F d, Y', strtotime($c['created_at']));
        $brand_name = $_SESSION['company_name'] ?? 'FREELANCE FLOW';

        class ContractPDF extends TCPDF {
            public $isCover = false;
            public $brand_name = '';
            public function Header() {
                if ($this->isCover) return;
                $this->SetFillColor(0, 67, 81);
                $this->Rect(0, 0, 210, 30, 'F');
                $this->SetTextColor(255, 255, 255);
                $this->SetFont('helvetica', 'B', 10);
                $this->SetXY(20, 10);
                $this->Cell(0, 5, strtoupper($this->brand_name), 0, 1, 'L');
            }
            public function Footer() {
                $this->SetY(-15);
                $this->SetFillColor(0, 67, 81);
                $this->Rect(0, 282, 210, 15, 'F');
                $this->SetFillColor(218, 145, 0);
                $this->Rect(190, 282, 20, 15, 'F');
                $this->SetTextColor(255, 255, 255);
                $this->SetFont('helvetica', 'B', 12);
                $this->SetXY(190, -10);
                $this->Cell(20, 5, $this->PageNo(), 0, 0, 'C');
            }
        }

        $pdf = new ContractPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->brand_name = $brand_name;
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(20, 40, 20);
        $pdf->SetAutoPageBreak(TRUE, 25);

        // COVER
        $pdf->isCover = true;
        $pdf->AddPage();
        $pdf->SetFillColor(0, 67, 81);
        $pdf->Rect(0, 0, 210, 100, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(20, 20);
        $pdf->Cell(0, 10, strtoupper($brand_name), 0, 1, 'L');
        $pdf->SetFont('times', 'B', 40);
        $pdf->SetXY(20, 40);
        $pdf->MultiCell(0, 40, "SERVICE\nAGREEMENT", 0, 'L', false);

        $pdf->isCover = false;
        $pdf->SetY(105);
        $pdf->SetTextColor(51, 65, 85);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->MultiCell(0, 10, $secs['INTRO'] ?? "Service Agreement for " . $c['project_title'], 0, 'L', false);
        
        $html_parties = '<style>.st { font-family: times; font-size: 18pt; color: #004351; font-weight: bold; }.lb { background-color: #004351; color: white; font-weight: bold; }.vl { background-color: #fff7e6; color: #1e293b; }</style><br><div class="st">THE DESIGNER</div><table cellpadding="6"><tr><td width="30%" class="lb">Name</td><td width="70%" class="vl">'.$c['user_name'].'</td></tr><tr><td class="lb">Email</td><td class="vl">'.$c['user_email'].'</td></tr><tr><td class="lb">Phone</td><td class="vl">'.$c['user_phone'].'</td></tr></table><br><div class="st">THE CLIENT</div><table cellpadding="6"><tr><td width="30%" class="lb">Name</td><td width="70%" class="vl">'.$c['client_name'].'</td></tr><tr><td class="lb">Address</td><td class="vl">'.$c['client_address'].'</td></tr><tr><td class="lb">Email</td><td class="vl">'.$c['client_email'].'</td></tr><tr><td class="lb">Phone</td><td class="vl">'.($c['client_phone'] ?? '--').'</td></tr></table>';
        $pdf->writeHTML($html_parties, true, false, true, false, '');

        function getSD($secs, $v) { foreach($v as $x){if(isset($secs[strtoupper($x)])) return $secs[strtoupper($x)];} return null; }
        
        $p_secs = [['1. PROJECT DETAILS', ['PROJECT DETAILS', 'DETAILS']], ['2. SCOPE OF WORK', ['SCOPE OF WORK', 'DELIVERABLES', 'SCOPE']], ['3. PAYMENT TERMS', ['PAYMENT TERMS', 'PAYMENT', 'FEES']], ['4. TIMELINE', ['TIMELINE', 'DURATION']]];
        $pdf->AddPage();
        foreach($p_secs as $i => $v) {
            if($i==2) $pdf->AddPage();
            $d = getSD($secs, $v[1]);
            $h = '<style>.h { font-family: times; font-size: 18pt; color: #004351; border-bottom: 2px solid #004351; }.c { font-size: 10pt; line-height: 1.6; color: #334155; }</style><div class="h">'.$v[0].'</div><div class="c"><br>'.nl2br(htmlspecialchars($d ?? "TBD")).'</div><br><br>';
            $pdf->writeHTML($h, true, false, true, false, '');
        }

        // Acceptance EMPTY SIGS
        $pdf->AddPage();
        $sig_intro = getSD($secs, ['ACCEPTANCE', 'SIGNATURES']) ?? "By signing, both parties agree to terms.";
        $h_sig = '<style>.h { font-family: times; font-size: 18pt; color: #004351; border-bottom: 2px solid #004351; }</style><div class="h">FINAL ACCEPTANCE</div><div style="font-size: 10pt; color: #334155;"><br>'.nl2br(htmlspecialchars($sig_intro)).'</div><br><br><br><br><br><table width="100%"><tr><td width="48%"><b>Signature of Designer</b><br><br><br><div style="border-bottom: 0.5px solid #94a3b8;"></div><br><b>'.$c['user_name'].'</b></td><td width="4%"></td><td width="48%"><b>Signature of Client</b><br><br><br><div style="border-bottom: 0.5px solid #94a3b8;"></div><br><b>'.$c['client_name'].'</b></td></tr></table><br><br><br><div style="background-color: #f8fafc; padding: 10px; font-size: 8pt; color: #64748b; border-left: 3px solid #da9100;"><b>Legal Note:</b> This document is a legally binding agreement.</div>';
        $pdf->writeHTML($h_sig, true, false, true, false, '');

        // Save and Send
        $temp_dir = sys_get_temp_dir();
        $attachment_name = 'Agreement_' . str_replace(' ', '_', $c['client_name']) . '.pdf';
        $temp_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_name;
        $pdf->Output($temp_path, 'F');

        $email_html = "<div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 20px; padding: 0; color: #334155; overflow: hidden;'><div style='background: #004351; padding: 30px; color: white;'><h2 style='margin: 0;'>Agreement for Your Review</h2><p style='margin: 5px 0 0 0; opacity: 0.8;'>Project: " . htmlspecialchars($c['project_title']) . "</p></div><div style='padding: 30px;'><p style='font-size: 16px; line-height: 1.6;'>" . nl2br(htmlspecialchars($message_body)) . "</p><div style='margin-top: 30px; background: #fff7e6; padding: 25px; border-radius: 12px; border: 1px solid #ffd8a8;'><p style='margin: 0; font-weight: 700; color: #004351;'>Action Required:</p><p style='font-size: 14px;'>Please review the attached Service Agreement PDF.</p></div></div><div style='background: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'><p style='font-size: 12px; color: #94a3b8; margin: 0;'>Sent via Freight Flow CRM</p></div></div>";

        $attachments = [['path' => $temp_path, 'name' => $attachment_name]];
        $sent = sendEmail($to_email, $subject, $email_html, $attachments);
        if (file_exists($temp_path)) unlink($temp_path);
        if ($sent) { $pdo->prepare("UPDATE contracts SET status = 'sent' WHERE id = ?")->execute([$contract_id]); header("Location: index.php?success=sent"); exit(); }
        else { $error = "Failed to send email."; }
    }
}

$default_subject = "Action Required: Agreement for " . $c['project_title'];
$default_body = "Dear " . $c['client_name'] . ",\n\nI hope you're having a great day.\n\nI've finalized the Service Agreement for our " . $c['project_title'] . " project. Please find the PDF attached for your review and signature.\n\nIf all looks good, please sign and return a copy so we can get started.\n\nBest regards,\n" . $c['user_name'];

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dashboard-topbar"><div class="topbar-left"><h2 style="font-weight: 800; letter-spacing: -0.5px;">Send Agreement</h2></div></div>
        <div class="dashboard-container" style="max-width: 800px; margin: 0 auto;">
            <div class="animate-fade-in">
                <?php if ($error): ?><div style="background: #fef2f2; color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 25px;"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>
                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <form method="POST">
                        <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
                        <div style="margin-bottom: 25px;"><label style="font-weight: 700; color: #1e293b; margin-bottom: 10px; display: block;">Recipient Email</label><input type="email" name="recipient_email" value="<?php echo htmlspecialchars($c['client_email']); ?>" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px;" required></div>
                        <div style="margin-bottom: 25px;"><label style="font-weight: 700; color: #1e293b; margin-bottom: 10px; display: block;">Subject</label><input type="text" name="email_subject" value="<?php echo htmlspecialchars($default_subject); ?>" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px;" required></div>
                        <div style="margin-bottom: 30px;"><label style="font-weight: 700; color: #1e293b; margin-bottom: 10px; display: block;">Message</label><textarea name="email_body" rows="8" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; line-height: 1.6;" required><?php echo htmlspecialchars($default_body); ?></textarea></div>
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 16px; border-radius: 16px; font-weight: 800; background: #004351; color: white; border: none; display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer;"><i class="fas fa-paper-plane"></i> Send Official Agreement</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
