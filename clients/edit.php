<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();

if (!$user_id) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}
$id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Fetch existing client data
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$client = $stmt->fetch();

if (!$client) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = trim($_POST['client_name']);
    $company_name = trim($_POST['company_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $country = trim($_POST['country']);
    $address = trim($_POST['address']);
    $notes = trim($_POST['notes']);
    $status = $_POST['status'] ?? 'active';

    if (empty($client_name) || empty($email)) {
        $error = 'Client name and email are required.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE clients SET client_name = ?, company_name = ?, email = ?, phone = ?, country = ?, address = ?, notes = ?, status = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$client_name, $company_name, $email, $phone, $country, $address, $notes, $status, $id, $user_id]);
            $success = 'Client updated successfully!';
            header("Refresh: 2; url=index.php");
        } catch (PDOException $e) {
            $error = 'Error updating client: ' . $e->getMessage();
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
                <h2 style="font-weight: 800;">Edit Client: <?php echo htmlspecialchars($client['client_name']); ?></h2>
            </div>
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline" style="font-size: 0.85rem; padding: 10px 20px; border-radius: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-arrow-left" style="font-size: 0.8rem;"></i> Back to Clients
                </a>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in" style="max-width: 800px; margin: 0 auto;">
                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom: 25px;"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 25px;"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="glass-card" style="padding: 40px;">
                    <form action="" method="POST" class="auth-form">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                            <div class="form-group">
                                <label for="client_name">Full Name <span style="color: #ef4444;">*</span></label>
                                <input type="text" name="client_name" id="client_name" class="form-control" value="<?php echo htmlspecialchars($client['client_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="company_name">Company Name</label>
                                <input type="text" name="company_name" id="company_name" class="form-control" value="<?php echo htmlspecialchars($client['company_name']); ?>">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                            <div class="form-group">
                                <label for="email">Email Address <span style="color: #ef4444;">*</span></label>
                                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($client['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($client['phone']); ?>">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                            <div class="form-group">
                                <label for="country">Country</label>
                                <input type="text" name="country" id="country" class="form-control" value="<?php echo htmlspecialchars($client['country']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="status">Client Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="active" <?php echo $client['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $client['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="on_hold" <?php echo $client['status'] == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Mailing Address</label>
                            <textarea name="address" id="address" class="form-control" style="height: 80px;"><?php echo htmlspecialchars($client['address']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="notes">Internal Notes</label>
                            <textarea name="notes" id="notes" class="form-control" style="height: 100px;"><?php echo htmlspecialchars($client['notes']); ?></textarea>
                        </div>

                        <div style="margin-top: 30px; display: flex; gap: 15px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                <i class="fas fa-check"></i> Update Client
                            </button>
                            <a href="index.php" class="btn btn-outline" style="flex: 1; text-align: center;">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include_once '../includes/footer.php'; ?>
