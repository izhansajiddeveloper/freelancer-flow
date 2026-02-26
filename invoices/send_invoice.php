<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';
require_once '../helpers/mail_helper.php';

// Disable warnings for PDF library output
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once '../vendor/tcpdf/tcpdf.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$invoice_id = $_GET['id'] ?? $_POST['invoice_id'] ?? 0;

if (!$invoice_id) {
    header("Location: index.php");
    exit();
}

// Fetch invoice and client details
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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_email = $_POST['recipient_email'] ?? '';
    $subject  = $_POST['email_subject'] ?? '';
    $message_body = $_POST['email_body'] ?? '';

    if (empty($to_email) || empty($subject) || empty($message_body)) {
        $error = "Recipient, Subject, and Message are required.";
    } else {
        // --- PDF Generation Logic ---
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
                $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
            }
        }

        $pdf = new InvoicePDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Freelance Flow');
        $pdf->SetAuthor($inv['user_name']);
        $pdf->SetTitle('Invoice - ' . $inv['invoice_number']);
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(15, 52, 15);
        $pdf->SetAutoPageBreak(TRUE, 25);
        $pdf->AddPage();

        // Capture Template HTML
        include 'assets/invoice_template.php';

        $pdf->writeHTML($html, true, false, true, false, '');

        // Save PDF to temp file
        $temp_dir = sys_get_temp_dir();
        $attachment_name = 'Invoice_' . str_replace('-', '', $inv['invoice_number']) . '.pdf';
        $temp_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_name;
        $pdf->Output($temp_path, 'F');

        // Prepare HTML for email payload
        $email_html = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; color: #334155;'>
            <h2 style='color: #4f46e5;'>Invoice #" . htmlspecialchars($inv['invoice_number']) . "</h2>
            <p>" . nl2br(htmlspecialchars($message_body)) . "</p>
            <div style='margin-top: 30px; background: #f8fafc; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; display:flex; justify-content:space-between;'>
                <div>
                    <p style='margin: 0; font-weight: 700; color: #1e293b;'>Total Due:</p>
                    <p style='margin: 5px 0; color: #4f46e5; font-size:1.5rem;'>$" . number_format($inv['total_amount'], 2) . "</p>
                </div>
                <div>
                    <p style='margin: 0; font-weight: 700; color: #1e293b;'>Due Date:</p>
                    <p style='margin: 5px 0; color: #ef4444;'>" . date('M d, Y', strtotime($inv['due_date'])) . "</p>
                </div>
            </div>
            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
            <p style='font-size: 0.75rem; color: #94a3b8; text-align: center;'>Sent via FreelanceFlow Billing</p>
        </div>";

        $attachments = [['path' => $temp_path, 'name' => $attachment_name]];
        $sent = sendEmail($to_email, $subject, $email_html, $attachments);

        // Clean up
        if (file_exists($temp_path)) unlink($temp_path);

        if ($sent) {
            if ($inv['status'] !== 'paid') {
                $pdo->prepare("UPDATE invoices SET status = 'sent' WHERE id = ?")->execute([$invoice_id]);
            }
            header("Location: index.php?success=sent");
            exit();
        } else {
            $error = "Failed to send email. Check SMTP settings.";
        }
    }
}

$default_subject = "Invoice #" . $inv['invoice_number'] . " from " . $inv['user_name'];
$default_body = "Dear " . $inv['client_name'] . ",\n\nI hope this email finds you well.\n\nPlease find attached the invoice for my recent services. The total amount due is $" . number_format($inv['total_amount'], 2) . " by " . date('M d, Y', strtotime($inv['due_date'])) . ".\n\nThank you for your business. Please let me know if you have any questions.\n\nBest regards,\n" . $inv['user_name'];

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Send Invoice: <?php echo htmlspecialchars($inv['invoice_number']); ?></h2>
            </div>
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline" style="border-radius: 12px;"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>

        <div class="dashboard-container" style="max-width: 800px; margin: 0 auto;">
            <div class="animate-fade-in">
                <?php if ($error): ?>
                    <div style="background: #fef2f2; color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 25px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <form method="POST">
                        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                        
                        <div style="margin-bottom: 25px;">
                            <label style="font-weight: 700; color: #1e293b; margin-bottom: 10px; display: block;">Recipient Email</label>
                            <input type="email" name="recipient_email" value="<?php echo htmlspecialchars($inv['client_email']); ?>" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px;" required>
                        </div>

                        <div style="margin-bottom: 25px;">
                            <label style="font-weight: 700; color: #1e293b; margin-bottom: 10px; display: block;">Subject</label>
                            <input type="text" name="email_subject" value="<?php echo htmlspecialchars($default_subject); ?>" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px;" required>
                        </div>

                        <div style="margin-bottom: 30px;">
                            <label style="font-weight: 700; color: #1e293b; margin-bottom: 10px; display: block;">Message Body</label>
                            <textarea name="email_body" rows="8" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; line-height: 1.6;" required><?php echo htmlspecialchars($default_body); ?></textarea>
                            <p style="font-size: 0.8rem; color: #64748b; margin-top: 10px;"><i class="fas fa-paperclip"></i> A PDF copy of Invoice #<?php echo htmlspecialchars($inv['invoice_number']); ?> will be automatically attached.</p>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 16px; border-radius: 16px; font-weight: 800; background: var(--gradient-primary); border: none; display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <i class="fas fa-paper-plane"></i> Send Invoice & Request Payment
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>


