<?php
ob_start();
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Include TCPDF
require_once '../vendor/tcpdf/tcpdf.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$contract_id = $_GET['id'] ?? 0;

if (!$contract_id) die("Invalid ID");

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

if (!$c) die("Contract not found");

$brand_name = $_SESSION['company_name'] ?? 'FREELANCE FLOW';
$date = date('F d, Y', strtotime($c['created_at']));

// Robust Cleaner Parser
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
    foreach ($sections as $k => $v) $sections[$k] = trim(preg_replace('/\n{3,}/', "\n\n", $v));
    return array_merge(['INTRO' => trim($introText)], $sections);
}

$secs = parseContractSections($c['contract_details']);

function getSectionData($secs, $variants) {
    foreach ($variants as $v) {
        $v = strtoupper($v);
        foreach ($secs as $key => $content) {
            if (strpos($key, $v) !== false || strpos($v, $key) !== false) return $content;
        }
    }
    return null;
}

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
        $this->SetFont('helvetica', '', 7);
        $this->SetX(20);
        $this->Cell(0, 5, 'SERVICE AGREEMENT', 0, 1, 'L');
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
$pdf->SetTitle('Agreement - ' . $c['project_title']);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->setMargins(20, 40, 20);
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

// SECTIONS: ALIGNED AND CLEANED
$sections_to_show = [
    ['1. PROJECT DETAILS', ['PROJECT DETAILS', 'DETAILS']],
    ['2. SCOPE OF WORK', ['SCOPE OF WORK', 'DELIVERABLES', 'SCOPE']],
    ['3. PAYMENT TERMS', ['PAYMENT TERMS', 'PAYMENT', 'FEES']],
    ['4. TIMELINE', ['TIMELINE', 'DURATION']]
];

$pdf->AddPage();
foreach($sections_to_show as $i => $conf) {
    if($i == 2) $pdf->AddPage();
    $data = getSectionData($secs, $conf[1]);
    $html = '<style>.h { font-family: times; font-size: 18pt; color: #004351; border-bottom: 2px solid #004351; }.c { font-size: 10pt; line-height: 1.6; color: #334155; }</style><div class="h">'.$conf[0].'</div><div class="c"><br>'.nl2br(htmlspecialchars($data ?? "Details as discussed.")).'</div><br><br>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

// SIGNATURES: EMPTY SPACE
$pdf->AddPage();
$sig_intro = getSectionData($secs, ['ACCEPTANCE', 'SIGNATURES']) ?? "By signing, both parties agree to terms.";
$h_sig = '<style>.h { font-family: times; font-size: 18pt; color: #004351; border-bottom: 2px solid #004351; }</style><div class="h">FINAL ACCEPTANCE</div><div style="font-size: 10pt; color: #334155;"><br>'.nl2br(htmlspecialchars($sig_intro)).'</div><br><br><br><br><br><table width="100%"><tr><td width="48%"><b>Signature of Designer</b><br><br><br><div style="border-bottom: 0.5px solid #94a3b8;"></div><br><b>'.$c['user_name'].'</b></td><td width="4%"></td><td width="48%"><b>Signature of Client</b><br><br><br><div style="border-bottom: 0.5px solid #94a3b8;"></div><br><b>'.$c['client_name'].'</b></td></tr></table><br><br><br><div style="background-color: #f8fafc; padding: 10px; font-size: 8pt; color: #64748b; border-left: 3px solid #da9100;"><b>Legal Note:</b> This document is a legally binding agreement between the two parties named on page 1.</div>';
$pdf->writeHTML($h_sig, true, false, true, false, '');

if (ob_get_length()) ob_end_clean();
$pdf->Output('Agreement_' . str_replace(' ', '_', $c['client_name']) . '.pdf', 'D');
exit();
?>
