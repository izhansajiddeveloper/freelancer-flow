<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';
require_once '../helpers/mail_helper.php';
require_once '../vendor/tcpdf/tcpdf.php';

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
$p = $stmt->fetch();

if (!$p) {
    die("Proposal not found or access denied.");
}

// --- PDF Generation Logic (Reused from download.php) ---

class ProfessionalProposal extends TCPDF {
    public $proposal_no;
    public $user_name;
    public function Header() {
        $this->SetFillColor(79, 70, 230); // #4f46e5
        $this->Rect(0, 0, 2, 297, 'F');
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(148, 163, 184); // #94a3b8
        $this->Cell(0, 10, 'Proposal ID: ' . $this->proposal_no . ' | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new ProfessionalProposal(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->proposal_no = "FF-PR-" . str_pad($p['id'], 3, '0', STR_PAD_LEFT);
$pdf->user_name = $p['user_name'];

$pdf->SetCreator('Freelance Flow');
$pdf->SetAuthor($p['user_name']);
$pdf->SetTitle('Proposal - ' . $p['project_title']);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetMargins(25, 25, 25);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();

$date = date('d F Y', strtotime($p['created_at']));
$proposal_no = "FF-PR-" . str_pad($p['id'], 3, '0', STR_PAD_LEFT);

$html = '
<style>
    .page-container { font-family: "Helvetica", sans-serif; }
    .header-table { margin-bottom: 40px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; }
    .proposal-label { font-size: 24pt; font-weight: bold; color: #1e293b; letter-spacing: -1px; }
    .proposal-sub { font-size: 9pt; color: #64748b; text-transform: uppercase; letter-spacing: 2px; margin-top: 5px; }
    .info-table { margin-bottom: 60px; }
    .label-header { font-size: 7.5pt; font-weight: bold; color: #4f46e5; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
    .info-name { font-size: 11pt; font-weight: bold; color: #1e293b; }
    .info-detail { font-size: 9pt; color: #64748b; line-height: 1.4; }
    .section-header { margin-top: 30px; margin-bottom: 15px; border-bottom: 0.5pt solid #cbd5e1; padding-bottom: 8px; }
    .section-num { font-size: 8pt; font-weight: bold; color: #4f46e5; margin-right: 10px; }
    .section-title { font-size: 10pt; font-weight: bold; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; }
    .body-text { font-size: 9.5pt; color: #334155; line-height: 1.6; text-align: justify; }
    .body-text b { color: #1e293b; }
    .investment-container { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin: 40px 0; padding: 25px; }
    .investment-label { font-size: 8pt; font-weight: bold; color: #64748b; text-transform: uppercase; }
    .investment-amount { font-size: 18pt; font-weight: bold; color: #1e293b; }
    .investment-currency { font-size: 9pt; color: #94a3b8; font-weight: normal; margin-left: 5px; }
    .sig-label { font-size: 7pt; font-weight: bold; color: #94a3b8; text-transform: uppercase; margin-bottom: 40px; }
    .sig-line { border-bottom: 1px solid #cbd5e1; height: 50px; width: 220px; }
    .footer-note { font-size: 8pt; color: #94a3b8; text-align: center; margin-top: 80px; }
</style>

<div class="page-container">
    <table class="header-table" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="70%">
                <div class="proposal-label">Project Proposal</div>
                <div class="proposal-sub">Strategic Execution Framework</div>
            </td>
            <td width="30%" align="right">
                <div style="font-size: 10pt; font-weight: bold; color: #1e293b;">ID: '.$proposal_no.'</div>
                <div style="font-size: 9pt; color: #64748b;">Issued on '.$date.'</div>
            </td>
        </tr>
    </table>

    <table class="info-table" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="50%" valign="top">
                <div class="label-header">Drafted By</div>
                <div class="info-name">'.htmlspecialchars($p['user_name']).'</div>
                <div style="font-size: 9pt; font-weight: bold; color: #4f46e5; margin: 3px 0;">'.htmlspecialchars($p['job_title']).'</div>
                <div class="info-detail">'.htmlspecialchars($p['user_email']).'</div>'.
                ($p['user_phone'] ? '<div class="info-detail">'.htmlspecialchars($p['user_phone']).'</div>' : '').'
            </td>
            <td width="50%" valign="top" align="right">
                <div class="label-header">Prepared For</div>
                <div class="info-name">'.htmlspecialchars($p['client_name']).'</div>
                '.($p['client_company'] ? '<div class="info-detail" style="font-weight: bold;">'.htmlspecialchars($p['client_company']).'</div>' : '').'
                <div class="info-detail">'.htmlspecialchars($p['client_email']).'</div>
                '.($p['client_address'] ? '<div class="info-detail">'.nl2br(htmlspecialchars($p['client_address'])).'</div>' : '').'
            </td>
        </tr>
    </table>

    <div class="section-header">
        <span class="section-num">01</span><span class="section-title">Overview</span>
    </div>
    <div class="body-text">
        <b>Focus: '.htmlspecialchars($p['project_title']).'</b><br><br>
        '.nl2br(htmlspecialchars($p['project_overview'] ?: 'A professional engagement focused on delivering high-impact results through strategic execution and technical excellence.')).'
    </div>

    <div class="section-header">
        <span class="section-num">02</span><span class="section-title">Project Scope</span>
    </div>
    <div class="body-text">
        '.nl2br(htmlspecialchars($p['project_scope'])).'
    </div>

    '.($p['milestone_breakdown'] ? '
    <div class="section-header">
        <span class="section-num">03</span><span class="section-title">Delivery Schedule</span>
    </div>
    <div class="body-text">
        '.nl2br(htmlspecialchars($p['milestone_breakdown'])).'
    </div>
    ' : '').'

    <div class="investment-container">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="55%">
                    <div class="investment-label">Proposed Investment</div>
                    <div class="investment-amount">PKR '.number_format($p['price'], 0).'<span class="investment-currency">Total Value</span></div>
                </td>
                <td width="45%" align="right" valign="middle">
                    <div style="font-size: 8.5pt; color: #64748b; line-height: 1.4;">
                        <b>Timeline:</b> '.htmlspecialchars($p['timeline'] ?: 'See Schedule').'<br>
                        <b>Validity:</b> 15 Days from Issue
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-header">
        <span class="section-num">04</span><span class="section-title">Commercial Terms</span>
    </div>
    <div class="body-text">
        <b>Payment Structure:</b><br>
        '.nl2br(htmlspecialchars($p['payment_terms'] ?: "• Initial Retainer: 50%\n• Final Delivery: 50%")).'
        <br><br>
        <b>Terms of Engagement:</b><br>
        '.nl2br(htmlspecialchars($p['terms'] ?: "• Scope represents all included deliverables.\n• Additional requirements will be quoted separately.")).'
    </div>

    <br><br><br>
</div>
';

$pdf->writeHTML($html, true, false, true, false, '');

// Save PDF to temporary file
$temp_dir = sys_get_temp_dir();
$filename = 'Proposal_' . str_replace(' ', '_', $p['client_name']) . '_' . $proposal_id . '.pdf';
$temp_path = $temp_dir . DIRECTORY_SEPARATOR . $filename;
$pdf->Output($temp_path, 'F');

// --- Email Sending Logic ---

$toEmail = $p['client_email'];
$subject = "Project Proposal: " . $p['project_title'];
$user_first_name = explode(' ', $p['user_name'])[0];

$email_body = "
<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; color: #334155;'>
    <h2 style='color: #4f46e5;'>Project Proposal</h2>
    <p>Dear " . htmlspecialchars($p['client_name']) . ",</p>
    <p>I hope this email finds you well.</p>
    <p>It was a pleasure discussing the <strong>" . htmlspecialchars($p['project_title']) . "</strong> project with you. Based on our conversation, I have drafted a comprehensive proposal outlining the project scope, timeline, and investment required.</p>
    <p>Please find the formal proposal attached to this email in PDF format.</p>
    <div style='background: #f8fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #4f46e5; margin: 25px 0;'>
        <p style='margin: 0; font-weight: bold;'>Drafted By:</p>
        <p style='margin: 5px 0; color: #4f46e5;'>" . htmlspecialchars($p['user_name']) . "</p>
        <p style='margin: 0; font-size: 0.9rem; color: #64748b;'>" . htmlspecialchars($p['job_title']) . "</p>
    </div>
    <p>If you have any questions or would like to discuss any part of the proposal further, please feel free to reach out.</p>
    <p>Best regards,</p>
    <p><strong>" . htmlspecialchars($p['user_name']) . "</strong></p>
    <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
    <p style='font-size: 0.75rem; color: #94a3b8; text-align: center;'>Sent via FreelanceFlow CRM</p>
</div>
";

$attachments = [
    [
        'path' => $temp_path,
        'name' => $filename
    ]
];

$sent = sendEmail($toEmail, $subject, $email_body, $attachments);

// Clean up: delete the temp file
if (file_exists($temp_path)) {
    unlink($temp_path);
}

if ($sent) {
    // Update proposal status if it was draft
    if ($p['status'] === 'draft') {
        $update_stmt = $pdo->prepare("UPDATE proposals SET status = 'sent' WHERE id = ?");
        $update_stmt->execute([$proposal_id]);
    }
    header("Location: index.php?success=sent");
} else {
    die("Failed to send email. Please check your SMTP configuration.");
}
exit();
