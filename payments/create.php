<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();
$error = '';

// Fetch clients
$stmt = $pdo->prepare("SELECT id, client_name FROM clients WHERE user_id = ? ORDER BY client_name");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll();

// Fetch unpaid/sent invoices
$stmt = $pdo->prepare("
    SELECT i.id, i.invoice_number, i.total_amount, i.client_id
    FROM invoices i
    WHERE i.user_id = ? AND i.status IN ('sent', 'pending', 'overdue')
    ORDER BY i.created_at DESC
");
$stmt->execute([$user_id]);
$invoices = $stmt->fetchAll();
$invoices_json = json_encode($invoices);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id       = intval($_POST['invoice_id'] ?? 0);
    $client_id        = intval($_POST['client_id'] ?? 0);
    $amount           = floatval($_POST['amount'] ?? 0);
    $currency         = trim($_POST['currency'] ?? 'PKR');
    $payment_date     = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_method   = trim($_POST['payment_method'] ?? '');
    $transaction_ref  = trim($_POST['transaction_reference'] ?? '');
    $status           = $_POST['status'] ?? 'pending';
    $notes            = trim($_POST['notes'] ?? '');

    if (!$invoice_id || !$client_id || $amount <= 0) {
        $error = "Invoice, Client, and Amount are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO payments (user_id, client_id, invoice_id, amount, currency, payment_date, payment_method, transaction_reference, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $client_id, $invoice_id, $amount, $currency, $payment_date, $payment_method, $transaction_ref, $status, $notes]);

            // Sync invoice status based on payment status
            if ($status === 'completed') {
                $pdo->prepare("UPDATE invoices SET status = 'paid', paid_date = ? WHERE id = ? AND user_id = ?")
                    ->execute([$payment_date, $invoice_id, $user_id]);
            } elseif ($status === 'failed') {
                $pdo->prepare("UPDATE invoices SET status = 'overdue' WHERE id = ? AND user_id = ?")
                    ->execute([$invoice_id, $user_id]);
            }

            header("Location: index.php?success=created");
            exit();
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
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
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Record Payment</h2>
            </div>
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline" style="border-radius: 12px;"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>

        <div class="dashboard-container" style="max-width: 800px; margin: 0 auto;">
            <div class="animate-fade-in">
                <?php if ($error): ?>
                    <div style="background: #fef2f2; color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; border: 1px solid #fee2e2;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <form method="POST">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Invoice</label>
                                <select id="invoice_sel" name="invoice_id" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; background: white;" required onchange="autoFill()">
                                    <option value="">-- Select Invoice --</option>
                                    <?php foreach ($invoices as $inv): ?>
                                        <option value="<?php echo $inv['id']; ?>" data-amount="<?php echo $inv['total_amount']; ?>" data-client="<?php echo $inv['client_id']; ?>">
                                            #<?php echo htmlspecialchars($inv['invoice_number']); ?> — RS <?php echo number_format($inv['total_amount'], 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Client</label>
                                <select id="client_sel" name="client_id" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; background: white;" required>
                                    <option value="">-- Select Client --</option>
                                    <?php foreach ($clients as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['client_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Amount</label>
                                <div style="display: flex; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                                    <select name="currency" style="padding: 14px 12px; border: none; border-right: 1px solid #e2e8f0; background: #f8fafc; font-weight: 700; outline: none;">
                                        <option value="PKR">PKR</option>
                                        <option value="USD">USD</option>
                                    </select>
                                    <input type="number" step="0.01" id="amount_inp" name="amount" style="flex: 1; padding: 14px 20px; border: none; outline: none; font-weight: 700;" placeholder="0.00" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Payment Date</label>
                                <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none;" required>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Payment Method</label>
                                <select name="payment_method" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; background: white;">
                                    <option value="">-- Select Method --</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="JazzCash">JazzCash</option>
                                    <option value="EasyPaisa">EasyPaisa</option>
                                    <option value="PayPal">PayPal</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Transaction Reference</label>
                                <input type="text" name="transaction_reference" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none;" placeholder="TXN-123456">
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Status</label>
                                <select name="status" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; background: white;">
                                    <option value="pending">Pending</option>
                                    <option value="completed" selected>Completed</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 30px;">
                            <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Notes (Optional)</label>
                            <textarea name="notes" rows="3" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; resize: vertical;"></textarea>
                        </div>

                        <div style="display: flex; gap: 15px;">
                            <button type="submit" class="btn btn-primary" style="flex: 2; padding: 16px; border-radius: 16px; font-weight: 800; background: var(--gradient-primary); border: none; font-size: 1rem;">
                                <i class="fas fa-save" style="margin-right: 8px;"></i> Save Payment
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
function autoFill() {
    const sel = document.getElementById('invoice_sel');
    const opt = sel.options[sel.selectedIndex];
    if (opt.value) {
        document.getElementById('amount_inp').value = parseFloat(opt.dataset.amount).toFixed(2);
        const clientSel = document.getElementById('client_sel');
        for (let o of clientSel.options) {
            if (o.value == opt.dataset.client) { o.selected = true; break; }
        }
    }
}
</script>


