<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$error = '';
$success = '';

// Generate a default invoice number
$stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ?");
$stmt->execute([$user_id]);
$invoice_count = $stmt->fetchColumn() + 1;
$default_invoice_number = "INV-" . date('Ymd') . "-" . str_pad($invoice_count, 3, '0', STR_PAD_LEFT);

// Fetch all clients
$stmt = $pdo->prepare("SELECT id, client_name FROM clients WHERE user_id = ? ORDER BY client_name ASC");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all projects for the user with their client ID to link dropdowns
$stmt = $pdo->prepare("SELECT id, project_title, client_id FROM projects WHERE user_id = ? ORDER BY project_title ASC");
$stmt->execute([$user_id]);
$projects_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$projects_json = json_encode($projects_data);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'] ?? 0;
    $project_id = $_POST['project_id'] !== '' ? $_POST['project_id'] : null;
    $invoice_number = trim($_POST['invoice_number'] ?? '');
    $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
    $due_date = $_POST['due_date'] ?? null;
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $tax = floatval($_POST['tax'] ?? 0);
    $total_amount = floatval($_POST['total_amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'draft'; // draft, sent, paid

    if (empty($client_id) || empty($invoice_number) || empty($issue_date) || empty($total_amount)) {
        $error = "Client, Invoice Number, Issue Date, and Total Amount are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO invoices (user_id, client_id, project_id, invoice_number, issue_date, due_date, subtotal, tax, total_amount, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $client_id,
                $project_id,
                $invoice_number,
                $issue_date,
                $due_date,
                $subtotal,
                $tax,
                $total_amount,
                $status,
                $notes
            ]);

            header("Location: index.php?success=created");
            exit();
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Create Invoice</h2>
            </div>
        </div>

        <div class="dashboard-container" style="max-width: 1000px; margin: 0 auto;">
            <div class="animate-fade-in">
                <?php if ($error): ?>
                    <div style="background: #fef2f2; color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; border: 1px solid #fee2e2;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 25px;">
                            
                            <!-- Invoice Meta Section -->
                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Client</label>
                                <select id="client_selector" name="client_id" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; background: white;" required>
                                    <option value="">-- Select Client --</option>
                                    <?php foreach ($clients as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['client_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Related Project (Optional)</label>
                                <select id="project_selector" name="project_id" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; background: white;" disabled>
                                    <option value="">-- Select Project --</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Invoice Number</label>
                                <input type="text" name="invoice_number" value="<?php echo htmlspecialchars($default_invoice_number); ?>" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; font-family: monospace;" required>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Status</label>
                                <select name="status" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; background: white;">
                                    <option value="draft">Draft</option>
                                    <option value="sent">Sent</option>
                                    <option value="paid">Paid</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Issue Date</label>
                                <input type="date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none;" required>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Due Date</label>
                                <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none;" required>
                            </div>
                        </div>

                        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">

                        <!-- Financials -->
                        <h4 style="font-weight: 800; font-size: 1.25rem; color: #1e293b; margin-bottom: 20px;">Financial Details</h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #64748b;">Subtotal</label>
                                <div style="display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: white;">
                                    <span style="padding: 14px 20px; background: #f8fafc; font-weight: 700; color: #94a3b8; border-right: 1px solid #e2e8f0;">$</span>
                                    <input type="number" step="0.01" id="subtotal" name="subtotal" style="width: 100%; padding: 14px 20px; border: none; outline: none; font-weight: 600;" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #64748b;">Tax (%)</label>
                                <div style="display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: white;">
                                    <input type="number" step="0.01" id="tax" name="tax" value="0.00" style="width: 100%; padding: 14px 20px; border: none; outline: none; font-weight: 600;" required>
                                    <span style="padding: 14px 20px; background: #f8fafc; font-weight: 700; color: #94a3b8; border-left: 1px solid #e2e8f0;">%</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 800; margin-bottom: 10px; color: var(--primary-color);">Total Amount</label>
                                <div style="display: flex; align-items: center; border: 2px solid var(--primary-color); border-radius: 12px; overflow: hidden; background: white; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.1);">
                                    <span style="padding: 14px 20px; background: rgba(79, 70, 229, 0.1); font-weight: 800; color: var(--primary-color);">PKR/USD</span>
                                    <input type="number" step="0.01" id="total_amount" name="total_amount" style="width: 100%; padding: 14px 20px; border: none; outline: none; font-weight: 800; color: #0f172a; font-size: 1.1rem;" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Notes & Terms -->
                        <div class="form-group" style="margin-bottom: 30px;">
                            <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Additional Notes / Payment Instructions</label>
                            <textarea name="notes" rows="6" style="width: 100%; padding: 16px 20px; border: 1px solid #e2e8f0; border-radius: 16px; outline: none; background: #f8fafc; resize: vertical;" placeholder="Thank you for your business! Payment is due within 30 days. Please pay via Bank Transfer to Account #123456..."></textarea>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 40px;">
                            <button type="submit" class="btn btn-primary" style="flex: 2; padding: 16px; border-radius: 16px; font-weight: 800; background: var(--gradient-primary); border: none; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);">
                                <i class="fas fa-file-invoice" style="margin-right: 8px;"></i> Save Invoice
                            </button>
                            <a href="index.php" class="btn btn-outline" style="flex: 1; padding: 16px; border-radius: 16px; font-weight: 700; text-align: center; border: 2px solid #e2e8f0; color: #475569; display: flex; align-items: center; justify-content: center;">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    const projects = <?php echo $projects_json; ?>;
    const clientSelector = document.getElementById('client_selector');
    const projectSelector = document.getElementById('project_selector');
    
    // Financial calculation
    const subtotalInput = document.getElementById('subtotal');
    const taxInput = document.getElementById('tax');
    const totalInput = document.getElementById('total_amount');

    clientSelector.addEventListener('change', function() {
        const clientId = this.value;
        projectSelector.innerHTML = '<option value="">-- Select Project --</option>';
        if (clientId) {
            projectSelector.disabled = false;
            const filteredProjects = projects.filter(p => p.client_id == clientId);
            filteredProjects.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.project_title;
                projectSelector.appendChild(opt);
            });
        } else {
            projectSelector.disabled = true;
        }
    });

    function calculateTotal() {
        const sub = parseFloat(subtotalInput.value) || 0;
        const taxRate = parseFloat(taxInput.value) || 0;
        const taxAmount = sub * (taxRate / 100);
        const total = sub + taxAmount;
        totalInput.value = total.toFixed(2);
    }

    subtotalInput.addEventListener('input', calculateTotal);
    taxInput.addEventListener('input', calculateTotal);
</script>


