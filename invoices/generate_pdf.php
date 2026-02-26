<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Disable warnings for PDF library output
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once '../vendor/tcpdf/tcpdf.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$invoice_id = $_GET['id'] ?? 0;

if (!$invoice_id) {
    die("Invalid Invoice ID");
}

// Fetch invoice details
$stmt = $pdo->prepare("
    SELECT i.*, c.client_name, c.email as client_email, c.company_name as client_company, c.address as client_address, c.phone as client_phone,
           p.project_title, u.full_name as user_name, u.email as user_email, u.phone as user_phone
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN projects p ON i.project_id = p.id
    JOIN users u ON i.user_id = u.id
    WHERE i.id = ? AND i.user_id = ?
");
$stmt->execute([$invoice_id, $user_id]);
$inv = $stmt->fetch();

if (!$inv) {
    die("Invoice not found.");
}

class InvoicePDF extends TCPDF {
    public function Header() {
        // Draw the curved background vector graphic
        $svg = '<svg width="210" height="55" xmlns="http://www.w3.org/2000/svg"><path d="M 0,0 L 210,0 L 210,22 C 140,15 70,55 0,38 Z" fill="#0B428A" /></svg>';
        $this->ImageSVG('@' . $svg, 0, 0, 210, 55);
        // Overlay the invoice title on top of the blue curve
        global $inv;
        $this->SetFont('helvetica', 'B', 28);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(10, 5);
        $this->Cell(90, 20, 'INVOICE', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 12);
        $this->SetXY(110, 8);
        $this->Cell(90, 14, 'NO: ' . ($inv['invoice_number'] ?? ''), 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 10, 'Invoice | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Document Setup
$pdf = new InvoicePDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Freelance Flow');
$pdf->SetTitle('Invoice - ' . $inv['invoice_number']);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 52, 15);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();

// Capture Template HTML
// Capture Template HTML
include 'assets/invoice_template.php';

$pdf->writeHTML($html, true, false, true, false, '');

// File Response - 'I' means Inline to browser
$pdf->Output('Invoice_' . $inv['invoice_number'] . '.pdf', 'I');
exit();
