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

$error = '';
$success = '';

// Fetch clients for the dropdown
$stmt = $pdo->prepare("SELECT id, client_name FROM clients WHERE user_id = ? ORDER BY client_name ASC");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll();

// Get pre-selected client if coming from view.php
$selected_client_id = $_GET['client_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_title = trim($_POST['project_title']);
    $client_id = $_POST['client_id'];
    $project_type = trim($_POST['project_type']);
    $description = trim($_POST['description']);
    $total_budget = $_POST['total_budget'];
    $currency = $_POST['currency'] ?? 'PKR';
    $start_date = $_POST['start_date'];
    $deadline = $_POST['deadline'];
    $status = $_POST['status'] ?? 'active';

    if (empty($project_title) || empty($client_id)) {
        $error = 'Project title and client are required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO projects (user_id, client_id, project_title, project_type, description, total_budget, currency, start_date, deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $client_id, $project_title, $project_type, $description, $total_budget, $currency, $start_date, $deadline, $status]);
            $success = 'Project launched successfully!';
            header("Refresh: 2; url=index.php");
        } catch (PDOException $e) {
            $error = 'Error launching project: ' . $e->getMessage();
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
                <h2 style="font-weight: 800;">Launch New Project</h2>
            </div>
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline" style="font-size: 0.85rem; padding: 10px 20px; border-radius: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-arrow-left" style="font-size: 0.8rem;"></i> Back to Projects
                </a>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in" style="max-width: 900px; margin: 0 auto;">
                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom: 25px;"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 25px;"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <form action="" method="POST" class="auth-form">
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                            <div class="form-group">
                                <label for="project_title">Project Title <span style="color: #ef4444;">*</span></label>
                                <input type="text" name="project_title" id="project_title" class="form-control" placeholder="e.g. Website Redesign 2024" required>
                            </div>
                            <div class="form-group">
                                <label for="client_id">Client <span style="color: #ef4444;">*</span></label>
                                <select name="client_id" id="client_id" class="form-control" required>
                                    <option value="">Select a client</option>
                                    <?php foreach($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>" <?php echo $selected_client_id == $client['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client['client_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px;">
                            <div class="form-group">
                                <label for="project_type">Project Type</label>
                                <input type="text" name="project_type" id="project_type" class="form-control" placeholder="e.g. Web Dev, Branding">
                            </div>
                            <div class="form-group">
                                <label for="total_budget">Budget Amount</label>
                                <input type="number" step="0.01" name="total_budget" id="total_budget" class="form-control" placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label for="currency">Currency</label>
                                <select name="currency" id="currency" class="form-control">
                                    <option value="PKR">PKR</option>
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="GBP">GBP</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px;">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="deadline">Deadline</label>
                                <input type="date" name="deadline" id="deadline" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="status">Initial Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="pending" selected>Pending (Needs Contract)</option>
                                    <option value="in_progress">In Progress (Started)</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Project Description & Scope</label>
                            <textarea name="description" id="description" class="form-control" placeholder="Describe the project goals and major deliverables..." style="height: 150px;"></textarea>
                        </div>

                        <div style="margin-top: 40px; display: flex; gap: 20px;">
                            <button type="submit" class="btn btn-primary" style="flex: 2; padding: 15px; font-weight: 700; border-radius: 12px; font-size: 1rem;">
                                <i class="fas fa-check-circle" style="margin-right: 8px;"></i> Create Project Portfolio
                            </button>
                            <a href="index.php" class="btn btn-outline" style="flex: 1; text-align: center; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
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
