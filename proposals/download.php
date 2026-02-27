<?php
ob_start();
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Include TCPDF from vendor
require_once '../vendor/tcpdf/tcpdf.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$proposal_id = $_GET['id'] ?? 0;

if (!$proposal_id) {
    die("Invalid Proposal ID");
}

// Fetch proposal details
$stmt = $pdo->prepare("
    SELECT p.*, c.client_name, c.email as client_email, c.company_name as client_company, c.address as client_address, c.phone as client_phone,
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

// Custom PDF Class for Premium Formatting
class PremiumProposal extends TCPDF {
    public function Header() {
        if ($this->page == 1) {
            // COVER PAGE DESIGN
            // Large Navy Shape (Top Right)
            $this->SetFillColor(44, 62, 80); // #2C3E50
            $this->StartTransform();
            $this->Rotate(15, 180, 50);
            $this->Rect(150, -50, 100, 200, 'F');
            $this->StopTransform();

            // Orange Shape (Bottom Right)
            $this->SetFillColor(222, 106, 38); // #DE6A26
            $this->Circle(210, 290, 60, 0, 360, 'F');
            
            // Light Grey Blob (Background)
            $this->SetFillColor(244, 247, 249); 
            $this->Circle(0, 50, 120, 0, 360, 'F');
        } else {
            // SUBSEQUENT PAGES
            $this->SetFillColor(44, 62, 80);
            $this->Rect(190, 0, 20, 10, 'F'); // Small accent
        }
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 10, 'Confidential - Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'C');
    }
}

// Document Setup
$pdf = new PremiumProposal(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator('Freelance Flow');
$pdf->SetAuthor($p['user_name']);
$pdf->SetTitle('Proposal - ' . $p['project_title']);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetMargins(20, 30, 20);
$pdf->SetAutoPageBreak(TRUE, 25);

// --- PAGE 1: COVER ---
$pdf->AddPage();
$proposal_no = "PR-" . str_pad($p['id'], 3, '0', STR_PAD_LEFT);
$date = date('d M Y', strtotime($p['created_at']));
$valid_until = date('d M Y', strtotime($p['created_at'] . ' + 15 days'));

$cover_html = '
<style>
    .cover-box { font-family: helvetica; padding-top: 50px; }
    .pre-title { color: #DE6A26; font-size: 10pt; font-weight: bold; letter-spacing: 2px; }
    .main-title { color: #2C3E50; font-size: 48pt; font-weight: bold; line-height: 0.8; margin: 0; }
    .sub-title { color: #64748B; font-size: 18pt; letter-spacing: 1px; }
    .info-table { margin-top: 80px; }
    .label { color: #64748B; font-size: 8pt; text-transform: uppercase; font-weight: bold; }
    .val { color: #2C3E50; font-size: 11pt; font-weight: bold; }
    .detail { color: #64748B; font-size: 9pt; }
</style>
<div class="cover-box">
    <div style="margin-bottom: 80px;">
        <span style="color:#DE6A26; font-size:20pt; font-weight:bold;">🚀 FreelanceFlow</span>
    </div>

    <div class="pre-title">PREPARED FOR '.htmlspecialchars($p['client_name']).'</div>
    <div class="main-title">PROJECT<br>PROPOSAL</div>
    <div class="sub-title">'.htmlspecialchars($p['project_title']).'</div>

    <table class="info-table" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="35%">
                <div class="label">Prepared For</div>
                <div class="val">'.htmlspecialchars($p['client_name']).'</div>
                <div class="detail">'.htmlspecialchars($p['client_company'] ?: '').'</div>
                <div class="detail">'.htmlspecialchars($p['client_email']).'</div>
                <div class="detail">'.htmlspecialchars($p['client_phone'] ?: '').'</div>
            </td>
            <td width="35%">
                <div class="label">Proposal Issued</div>
                <div class="val">'.$date.'</div>
                <br>
                <div class="label">Proposal Valid to</div>
                <div class="val">'.$valid_until.'</div>
            </td>
            <td width="30%" align="right">
                <div class="detail" style="margin-top:20px;">www.freelanceflow.pro</div>
            </td>
        </tr>
    </table>
</div>
';
$pdf->writeHTML($cover_html, true, false, true, false, '');

// --- PAGE 2: CONTENT ---
$pdf->AddPage();
$content_html = '
<style>
    h2 { color: #2C3E50; font-size: 18pt; font-weight: bold; border-bottom: 1px solid #E5E7EB; padding-bottom: 5px; }
    .num { color: #DE6A26; }
    .body { font-size: 10pt; color: #334155; line-height: 1.6; }
    .welcome { color: #DE6A26; font-size: 16pt; font-weight: bold; }
    .investment-box { background-color: #2C3E50; color: white; padding: 20px; border-radius: 10px; }
    .price { font-size: 24pt; font-weight: bold; color: white; }
    .sig-label { font-size: 8pt; color: #64748B; text-transform: uppercase; border-top: 1px solid #E5E7EB; padding-top: 10px; }
</style>

<div class="body">
    <h2><span class="num">01.</span> Project Overview</h2>
    <div class="welcome">Some people dream of success while others wake up and work.</div>
    <br>
    <p>'.nl2br(htmlspecialchars($p['project_overview'])).'</p>

    <br>
    <h2><span class="num">02.</span> Scope of Services</h2>
    <p>'.nl2br(htmlspecialchars($p['project_scope'])).'</p>

    <br>
    <h2><span class="num">03.</span> Investment Breakdown</h2>
    <table width="100%" cellpadding="10" border="0">
        <tr style="background-color: #2C3E50; color: white; font-weight: bold;">
            <td width="70%">Description</td>
            <td width="30%" align="right">Amount</td>
        </tr>';

if ($p['milestone_breakdown']) {
    $lines = explode("\n", $p['milestone_breakdown']);
    foreach ($lines as $line) {
        if (trim($line)) {
            $content_html .= '<tr><td style="border-bottom: 1px solid #E5E7EB;">'.htmlspecialchars($line).'</td><td align="right" style="border-bottom: 1px solid #E5E7EB;">Defined</td></tr>';
        }
    }
} else {
    $content_html .= '<tr><td style="border-bottom: 1px solid #E5E7EB;">Project Execution & Delivery</td><td align="right" style="border-bottom: 1px solid #E5E7EB;">PKR '.number_format($p['price'], 2).'</td></tr>';
}

$content_html .= '
        <tr style="background-color: #F8FAFB;">
            <td style="font-size: 12pt; font-weight: bold; color: #DE6A26;">TOTAL INVESTMENT</td>
            <td align="right" style="font-size: 12pt; font-weight: bold; color: #DE6A26;">PKR '.number_format($p['price'], 2).'</td>
        </tr>
    </table>

    <br><br>
    <div class="investment-box">
        <table width="100%">
            <tr>
                <td>
                    <span style="font-size: 8pt; color: #CBD5E1; letter-spacing: 1px;">TOTAL PROJECT VALUE</span><br>
                    <span class="price">PKR '.number_format($p['price'], 0).'</span>
                </td>
                <td align="right">
                    <span style="font-size: 9pt;">Timeline: <b>'.htmlspecialchars($p['timeline'] ?: 'To be agreed').'</b></span><br>
                    <span style="font-size: 9pt; color: #CBD5E1;">Engagement: Fixed Price</span>
                </td>
            </tr>
        </table>
    </div>

    <br><br>
    <table width="100%">
        <tr>
            <td width="45%">
                <h2><span class="num">04.</span> Authorization</h2>
                <p><b>Payment Terms:</b><br>'.nl2br(htmlspecialchars($p['payment_terms'] ?: "50% Upfront, 50% on Completion")).'</p>
                <p><b>General Terms:</b><br>'.nl2br(htmlspecialchars($p['terms'] ?: "Final scope as defined herein.")).'</p>
            </td>
            <td width="10%"></td>
            <td width="45%" valign="bottom">
                <div style="height: 50px;"></div>
                <div class="sig-label">Client Signature // Date</div>
                <div style="height: 40px;"></div>
                <div class="sig-label">Provider Signature // Date</div>
            </td>
        </tr>
    </table>
</div>
';

$pdf->writeHTML($content_html, true, false, true, false, '');

// File Response
$filename = 'Proposal_' . str_replace(' ', '_', $p['client_name']) . '.pdf';

// Clean the output buffer to prevent TCPDF "Some data has already been output" error
if (ob_get_length()) ob_end_clean();

$pdf->Output($filename, 'D');
