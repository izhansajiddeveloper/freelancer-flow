<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';
require_once '../helpers/mail_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$contract_id = $_GET['id'] ?? $_POST['contract_id'] ?? 0;

if (!$contract_id) {
    header("Location: index.php");
    exit();
}

// Fetch contract and client details
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

require_once '../vendor/tcpdf/tcpdf.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_email = $_POST['recipient_email'] ?? '';
    $subject  = $_POST['email_subject'] ?? '';
    $message_body = $_POST['email_body'] ?? '';

    if (empty($to_email) || empty($subject) || empty($message_body)) {
        $error = "Recipient, Subject, and Message are required.";
    } else {
        // --- PDF Generation Logic ---
        class ContractPDF extends TCPDF {
            public function Header() {
                $this->SetFillColor(79, 70, 230); // #4f46e5
                $this->Rect(0, 0, 1.5, 297, 'F');
            }
            public function Footer() {
                $this->SetY(-15);
                $this->SetFont('helvetica', 'I', 8);
                $this->SetTextColor(148, 163, 184);
                $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
            }
        }

        $pdf = new ContractPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Freelance Flow');
        $pdf->SetAuthor($c['user_name']);
        $pdf->SetTitle('Contract - ' . $c['project_title']);
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(25, 25, 25);
        $pdf->SetAutoPageBreak(TRUE, 25);
        $pdf->AddPage();

        // Load Template (captured from output buffer to avoid direct include echo if it has any)
        $html = include 'assets/contract_template.php';
        $pdf->writeHTML($html, true, false, true, false, '');

        // Save PDF to temp file
        $temp_dir = sys_get_temp_dir();
        $attachment_name = 'Contract_' . str_replace(' ', '_', $c['client_name']) . '.pdf';
        $temp_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_name;
        $pdf->Output($temp_path, 'F');

        // Prepare HTML for email
        $email_html = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; color: #334155;'>
            <h2 style='color: #4f46e5;'>Service Agreement</h2>
            <p>" . nl2br(htmlspecialchars($message_body)) . "</p>
            <div style='margin-top: 30px; background: #f8fafc; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0;'>
                <p style='margin: 0; font-weight: 700; color: #1e293b;'>Agreement for Project:</p>
                <p style='margin: 5px 0; color: #4f46e5;'>" . htmlspecialchars($c['project_title']) . "</p>
            </div>
            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
            <p style='font-size: 0.75rem; color: #94a3b8; text-align: center;'>Sent via FreelanceFlow CRM</p>
        </div>";

        $attachments = [['path' => $temp_path, 'name' => $attachment_name]];
        $sent = sendEmail($to_email, $subject, $email_html, $attachments);

        // Clean up
        if (file_exists($temp_path)) unlink($temp_path);

        if ($sent) {
            $pdo->prepare("UPDATE contracts SET status = 'sent' WHERE id = ?")->execute([$contract_id]);
            header("Location: index.php?success=sent");
            exit();
        } else {
            $error = "Failed to send email. Check SMTP settings.";
        }
    }
}

$default_subject = "Action Required: Project Contract Review - " . $c['project_title'];
$default_body = "Dear " . $c['client_name'] . ",\n\nI hope you are having a productive week.\n\nFollowing our proposal acceptance, I have prepared the legal service agreement for the '" . $c['project_title'] . "' project. Please review the terms and let me know if you have any questions.\n\nYou can sign the agreement digitally or scan and send back a signed copy.\n\nBest regards,\n" . $c['user_name'];

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Send Contract</h2>
            </div>
        </div>

        <div class="dashboard-container" style="max-width: 800px; margin: 0 auto;">
            <div class="animate-fade-in">
                <?php if ($error): ?>
                    <div style="background: #fef2f2; color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 25px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <form method="POST">
                        <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
                        
                        <div style="margin-bottom: 25px;">
                            <label style="font-weight: 700; color: #1e293b; margin-bottom: 10px; display: block;">Recipient Email</label>
                            <input type="email" name="recipient_email" value="<?php echo htmlspecialchars($c['client_email']); ?>" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px;" required>
                        </div>

                        <div style="margin-bottom: 25px;">
                            <label style="font-weight: 700; color: #1e293b; margin-bottom: 10px; display: block;">Subject</label>
                            <input type="text" name="email_subject" value="<?php echo htmlspecialchars($default_subject); ?>" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px;" required>
                        </div>

                        <div style="margin-bottom: 30px;">
                            <label style="font-weight: 700; color: #1e293b; margin-bottom: 10px; display: block;">Message</label>
                            <textarea name="email_body" rows="8" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; line-height: 1.6;" required><?php echo htmlspecialchars($default_body); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 16px; border-radius: 16px; font-weight: 800; background: var(--gradient-primary); border: none; display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <i class="fas fa-paper-plane"></i> Send Contract for Review
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include_once '../includes/footer.php'; ?>
