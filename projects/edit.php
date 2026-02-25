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

// Fetch existing project data
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: index.php");
    exit();
}

// Fetch clients for the dropdown
$stmt = $pdo->prepare("SELECT id, client_name FROM clients WHERE user_id = ? ORDER BY client_name ASC");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_title = trim($_POST['project_title']);
    $client_id = $_POST['client_id'];
    $project_type = trim($_POST['project_type']);
    $description = trim($_POST['description']);
    $total_budget = $_POST['total_budget'];
    $currency = $_POST['currency'];
    $start_date = $_POST['start_date'];
    $deadline = $_POST['deadline'];
    $status = $_POST['status'];

    if (empty($project_title) || empty($client_id)) {
        $error = 'Project title and client are required.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE projects SET client_id = ?, project_title = ?, project_type = ?, description = ?, total_budget = ?, currency = ?, start_date = ?, deadline = ?, status = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$client_id, $project_title, $project_type, $description, $total_budget, $currency, $start_date, $deadline, $status, $id, $user_id]);
            $success = 'Project updated successfully!';
            header("Refresh: 2; url=view.php?id=" . $id);
        } catch (PDOException $e) {
            $error = 'Error updating project: ' . $e->getMessage();
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
                <h2 style="font-weight: 800;">Edit Project</h2>
            </div>
            <div class="topbar-actions">
                <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline" style="font-size: 0.85rem; padding: 10px 20px; border-radius: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-times" style="font-size: 0.8rem;"></i> Cancel Editing
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
                                <input type="text" name="project_title" id="project_title" class="form-control" value="<?php echo htmlspecialchars($project['project_title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="client_id">Client <span style="color: #ef4444;">*</span></label>
                                <select name="client_id" id="client_id" class="form-control" required>
                                    <?php foreach($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>" <?php echo $project['client_id'] == $client['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client['client_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px;">
                            <div class="form-group">
                                <label for="project_type">Project Type</label>
                                <input type="text" name="project_type" id="project_type" class="form-control" value="<?php echo htmlspecialchars($project['project_type']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="total_budget">Budget Amount</label>
                                <input type="number" step="0.01" name="total_budget" id="total_budget" class="form-control" value="<?php echo $project['total_budget']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="currency">Currency</label>
                                <select name="currency" id="currency" class="form-control">
                                    <option value="PKR" <?php echo $project['currency'] == 'PKR' ? 'selected' : ''; ?>>PKR</option>
                                    <option value="USD" <?php echo $project['currency'] == 'USD' ? 'selected' : ''; ?>>USD</option>
                                    <option value="EUR" <?php echo $project['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                    <option value="GBP" <?php echo $project['currency'] == 'GBP' ? 'selected' : ''; ?>>GBP</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px;">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $project['start_date']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="deadline">Deadline</label>
                                <input type="date" name="deadline" id="deadline" class="form-control" value="<?php echo $project['deadline']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="status">Project Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="pending" <?php echo $project['status'] == 'pending' ? 'selected' : ''; ?>>Pending (Needs Contract)</option>
                                    <option value="in_progress" <?php echo $project['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress (Started)</option>
                                    <option value="completed" <?php echo $project['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="on_hold" <?php echo $project['status'] == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                    <option value="cancelled" <?php echo $project['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Project Description & Scope</label>
                            <textarea name="description" id="description" class="form-control" style="height: 150px;"><?php echo htmlspecialchars($project['description']); ?></textarea>
                        </div>

                        <div style="margin-top: 40px; display: flex; gap: 20px;">
                            <button type="submit" class="btn btn-primary" style="flex: 2; padding: 15px; font-weight: 700; border-radius: 12px; font-size: 1rem;">
                                <i class="fas fa-save" style="margin-right: 8px;"></i> Save Changes
                            </button>
                            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline" style="flex: 1; text-align: center; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
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
