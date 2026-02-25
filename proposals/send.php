<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';
require_once '../helpers/mail_helper.php';
require_once '../vendor/tcpdf/tcpdf.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$proposal_id = $_GET['id'] ?? $_POST['proposal_id'] ?? 0;

if (!$proposal_id) {
    header("Location: index.php");
    exit();
}

// Fetch proposal and related details
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

$error = '';
$success = '';

// Handle Email Sending (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_email = $_POST['recipient_email'] ?? '';
    $subject  = $_POST['email_subject'] ?? '';
    $message_body = $_POST['email_body'] ?? '';

    if (empty($to_email) || empty($subject) || empty($message_body)) {
        $error = "Recipient, Subject, and Message are required.";
    } else {
        // --- PDF Generation Logic ---
        class ProfessionalProposal extends TCPDF {
            public $proposal_no;
            public function Header() {
                $this->SetFillColor(79, 70, 230); // #4f46e5
                $this->Rect(0, 0, 2, 297, 'F');
            }
            public function Footer() {
                $this->SetY(-15);
                $this->SetFont('helvetica', 'I', 8);
                $this->SetTextColor(148, 163, 184);
                $this->Cell(0, 10, 'Proposal ID: ' . $this->proposal_no . ' | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
            }
        }

        $pdf = new ProfessionalProposal(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->proposal_no = "FF-PR-" . str_pad($p['id'], 3, '0', STR_PAD_LEFT);
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

        // Standard PDF Template (simplified for sending)
        $html = '
        <style>
            .page-container { font-family: "Helvetica", sans-serif; }
            .header-table { margin-bottom: 40px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; }
            .proposal-label { font-size: 24pt; font-weight: bold; color: #1e293b; letter-spacing: -1px; }
            .info-table { margin-bottom: 60px; }
            .label-header { font-size: 7.5pt; font-weight: bold; color: #4f46e5; text-transform: uppercase; margin-bottom: 10px; }
            .section-header { margin-top: 30px; margin-bottom: 15px; border-bottom: 0.5pt solid #cbd5e1; padding-bottom: 8px; }
            .body-text { font-size: 9.5pt; color: #334155; line-height: 1.6; }
            .investment-container { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin: 40px 0; padding: 25px; }
        </style>
        <div class="page-container">
            <table class="header-table" width="100%">
                <tr>
                    <td><div class="proposal-label">Project Proposal</div></td>
                    <td align="right"><div>ID: '.$proposal_no.'</div></td>
                </tr>
            </table>
            <table class="info-table" width="100%">
                <tr>
                    <td><div class="label-header">Drafted By</div><div>'.htmlspecialchars($p['user_name']).'</div></td>
                    <td align="right"><div class="label-header">Prepared For</div><div>'.htmlspecialchars($p['client_name']).'</div></td>
                </tr>
            </table>
            <div class="section-header"><b>OVERVIEW</b></div>
            <div class="body-text">'.nl2br(htmlspecialchars($p['project_overview'])).'</div>
            <div class="section-header"><b>SCOPE</b></div>
            <div class="body-text">'.nl2br(htmlspecialchars($p['project_scope'])).'</div>
            <div class="investment-container"><b>Investment: PKR '.number_format($p['price'], 0).'</b></div>
        </div>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $temp_dir = sys_get_temp_dir();
        $attachment_name = 'Proposal_' . str_replace(' ', '_', $p['client_name']) . '.pdf';
        $temp_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_name;
        $pdf->Output($temp_path, 'F');

        // --- Email Sending Logic ---
        $email_html = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; color: #334155;'>
            <h2 style='color: #4f46e5;'>Project Proposal</h2>
            " . nl2br(htmlspecialchars($message_body)) . "
            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
            <p style='font-size: 0.75rem; color: #94a3b8; text-align: center;'>Sent via FreelanceFlow CRM</p>
        </div>";

        $attachments = [['path' => $temp_path, 'name' => $attachment_name]];
        $sent = sendEmail($to_email, $subject, $email_html, $attachments);

        if (file_exists($temp_path)) unlink($temp_path);

        if ($sent) {
            if ($p['status'] === 'draft') {
                $pdo->prepare("UPDATE proposals SET status = 'sent' WHERE id = ?")->execute([$proposal_id]);
            }
            header("Location: index.php?success=sent");
            exit();
        } else {
            $error = "Failed to send email. Check SMTP settings.";
        }
    }
}

// Prefill data for GET
$default_subject = "Project Proposal: " . $p['project_title'];
$default_body = "Dear " . $p['client_name'] . ",\n\nI hope this email finds you well.\n\nIt was a pleasure discussing the " . $p['project_title'] . " project with you. Based on our conversation, I have drafted a comprehensive proposal outlining the project scope, timeline, and investment required.\n\nPlease find the formal proposal attached to this email in PDF format.\n\nBest regards,\n" . $p['user_name'];

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Send Proposal</h2>
            </div>
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline" style="border-radius: 12px;">Cancel</a>
            </div>
        </div>

        <div class="dashboard-container" style="max-width: 800px; margin: 0 auto;">
            <div class="animate-fade-in">
                <?php if ($error): ?>
                    <div style="background: #fef2f2; border: 1px solid #fee2e2; color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600;">
                        <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <form method="POST">
                        <input type="hidden" name="proposal_id" value="<?php echo $proposal_id; ?>">
                        
                        <div style="margin-bottom: 25px;">
                            <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 10px; font-size: 0.9rem;">Recipient Email</label>
                            <input type="email" name="recipient_email" value="<?php echo htmlspecialchars($p['client_email']); ?>" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem; color: #1e293b; outline: none; transition: border 0.2s;" required>
                        </div>

                        <div style="margin-bottom: 25px;">
                            <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 10px; font-size: 0.9rem;">Email Subject</label>
                            <input type="text" name="email_subject" value="<?php echo htmlspecialchars($default_subject); ?>" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem; color: #1e293b; outline: none; transition: border 0.2s;" required>
                        </div>

                        <div style="margin-bottom: 30px;">
                            <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 10px; font-size: 0.9rem;">Message</label>
                            <textarea name="email_body" rows="8" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem; color: #334155; outline: none; line-height: 1.6; transition: border 0.2s;" required><?php echo htmlspecialchars($default_body); ?></textarea>
                        </div>

                        <!-- Attachment Preview -->
                        <div style="background: #f8fafc; border: 1px dashed #cbd5e1; padding: 20px; border-radius: 16px; display: flex; align-items: center; gap: 15px; margin-bottom: 35px;">
                            <div style="width: 44px; height: 44px; background: #eef2ff; color: #4f46e5; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div>
                                <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem;">Proposal Attachment</div>
                                <div style="font-size: 0.8rem; color: #64748b;">PDF document will be generated and attached automatically.</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 16px; border-radius: 16px; font-size: 1.1rem; font-weight: 800; background: var(--gradient-primary); border: none; box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4); display: flex; align-items: center; justify-content: center; gap: 12px; cursor: pointer; transition: all 0.2s;">
                            <i class="fas fa-paper-plane"></i> Send Proposal Now
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include_once '../includes/footer.php'; ?>
