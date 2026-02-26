<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();
$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit(); }

$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$pay = $stmt->fetch();
if (!$pay) { die("Not found"); }

$error = '';

$stmt = $pdo->prepare("SELECT id, client_name FROM clients WHERE user_id = ? ORDER BY client_name");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, invoice_number, total_amount, client_id FROM invoices WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$invoices = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id      = intval($_POST['invoice_id'] ?? 0);
    $client_id       = intval($_POST['client_id'] ?? 0);
    $amount          = floatval($_POST['amount'] ?? 0);
    $currency        = trim($_POST['currency'] ?? 'PKR');
    $payment_date    = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_method  = trim($_POST['payment_method'] ?? '');
    $transaction_ref = trim($_POST['transaction_reference'] ?? '');
    $status          = $_POST['status'] ?? 'pending';
    $notes           = trim($_POST['notes'] ?? '');

    if (!$invoice_id || $amount <= 0) {
        $error = "Invoice and Amount are required.";
    } else {
        $pdo->prepare("
            UPDATE payments SET invoice_id=?, client_id=?, amount=?, currency=?, payment_date=?, payment_method=?, transaction_reference=?, status=?, notes=?, updated_at=NOW()
            WHERE id = ? AND user_id = ?
        ")->execute([$invoice_id, $client_id, $amount, $currency, $payment_date, $payment_method, $transaction_ref, $status, $notes, $id, $user_id]);

        // Sync invoice status
        if ($status === 'completed') {
            $pdo->prepare("UPDATE invoices SET status = 'paid', paid_date = ? WHERE id = ? AND user_id = ?")
                ->execute([$payment_date, $invoice_id, $user_id]);
        } elseif ($status === 'failed') {
            $pdo->prepare("UPDATE invoices SET status = 'overdue' WHERE id = ? AND user_id = ?")
                ->execute([$invoice_id, $user_id]);
        }

        header("Location: index.php?success=updated");
        exit();
    }
}

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left"><h2 style="font-weight: 800;">Edit Payment</h2></div>
            <div class="topbar-actions"><a href="index.php" class="btn btn-outline" style="border-radius: 12px;"><i class="fas fa-arrow-left"></i> Back</a></div>
        </div>
        <div class="dashboard-container" style="max-width: 800px; margin: 0 auto;">
            <div class="animate-fade-in">
                <?php if ($error): ?>
                    <div style="background:#fef2f2;color:#ef4444;padding:15px;border-radius:12px;margin-bottom:25px;font-weight:600;"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:10px;color:#1e293b;">Invoice</label>
                                <select name="invoice_id" style="width:100%;padding:14px 20px;border:1px solid #e2e8f0;border-radius:12px;background:white;" required>
                                    <?php foreach ($invoices as $inv): ?>
                                        <option value="<?php echo $inv['id']; ?>" <?php echo $inv['id'] == $pay['invoice_id'] ? 'selected' : ''; ?>>
                                            #<?php echo htmlspecialchars($inv['invoice_number']); ?> — RS <?php echo number_format($inv['total_amount'], 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:10px;color:#1e293b;">Client</label>
                                <select name="client_id" style="width:100%;padding:14px 20px;border:1px solid #e2e8f0;border-radius:12px;background:white;" required>
                                    <?php foreach ($clients as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $pay['client_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['client_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:10px;color:#1e293b;">Amount</label>
                                <div style="display:flex;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                                    <select name="currency" style="padding:14px 12px;border:none;border-right:1px solid #e2e8f0;background:#f8fafc;font-weight:700;">
                                        <option value="PKR" <?php echo ($pay['currency'] ?? 'PKR') === 'PKR' ? 'selected' : ''; ?>>PKR</option>
                                        <option value="USD" <?php echo ($pay['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD</option>
                                    </select>
                                    <input type="number" step="0.01" name="amount" value="<?php echo $pay['amount']; ?>" style="flex:1;padding:14px 20px;border:none;font-weight:700;" required>
                                </div>
                            </div>
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:10px;color:#1e293b;">Payment Date</label>
                                <input type="date" name="payment_date" value="<?php echo $pay['payment_date']; ?>" style="width:100%;padding:14px 20px;border:1px solid #e2e8f0;border-radius:12px;" required>
                            </div>
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:10px;color:#1e293b;">Payment Method</label>
                                <select name="payment_method" style="width:100%;padding:14px 20px;border:1px solid #e2e8f0;border-radius:12px;background:white;">
                                    <?php foreach (['Bank Transfer','JazzCash','EasyPaisa','PayPal','Cash','Cheque','Other'] as $m): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $pay['payment_method'] === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:10px;color:#1e293b;">Transaction Reference</label>
                                <input type="text" name="transaction_reference" value="<?php echo htmlspecialchars($pay['transaction_reference'] ?? ''); ?>" style="width:100%;padding:14px 20px;border:1px solid #e2e8f0;border-radius:12px;">
                            </div>
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:10px;color:#1e293b;">Status</label>
                                <select name="status" style="width:100%;padding:14px 20px;border:1px solid #e2e8f0;border-radius:12px;background:white;">
                                    <?php foreach (['pending','completed','failed'] as $st): ?>
                                        <option value="<?php echo $st; ?>" <?php echo $pay['status'] === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div style="margin-bottom:30px;">
                            <label style="display:block;font-weight:700;margin-bottom:10px;color:#1e293b;">Notes</label>
                            <textarea name="notes" rows="3" style="width:100%;padding:14px 20px;border:1px solid #e2e8f0;border-radius:12px;resize:vertical;"><?php echo htmlspecialchars($pay['notes'] ?? ''); ?></textarea>
                        </div>
                        <div style="display:flex;gap:15px;">
                            <button type="submit" class="btn btn-primary" style="flex:2;padding:16px;border-radius:16px;font-weight:800;background:var(--gradient-primary);border:none;">
                                <i class="fas fa-save" style="margin-right:8px;"></i> Update Payment
                            </button>
                            <a href="index.php" class="btn btn-outline" style="flex:1;padding:16px;border-radius:16px;font-weight:700;text-align:center;display:flex;align-items:center;justify-content:center;">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>


