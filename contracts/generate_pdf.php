<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';
require_once '../vendor/tcpdf/tcpdf.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$contract_id = $_GET['id'] ?? 0;

if (!$contract_id) {
    die("Invalid Contract ID");
}

// Fetch contract details
$stmt = $pdo->prepare("
    SELECT cont.*, c.client_name, c.email as client_email, c.company_name as client_company,
           p.project_title, u.full_name as user_name, u.email as user_email, u.phone as user_phone, u.job_title
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

// Custom PDF Class (same as download)
class ContractPDF extends TCPDF {
    public function Header() {
        $this->SetFillColor(79, 70, 230);
        $this->Rect(0, 0, 1.5, 297, 'F');
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 10, 'Agreement: FF-CTR-' . $this->getAliasNumPage() . ' | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Document Setup
$pdf = new ContractPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Freelance Flow');
$pdf->SetTitle('Contract Preview - ' . $c['project_title']);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetMargins(25, 25, 25);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();

// Load Template
$html = include 'assets/contract_template.php';
$pdf->writeHTML($html, true, false, true, false, '');

// File Response - 'I' means Inline to browser
$pdf->Output('Contract_' . $contract_id . '.pdf', 'I');
exit();
