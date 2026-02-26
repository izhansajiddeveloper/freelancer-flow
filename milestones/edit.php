<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$id = $_GET['id'] ?? 0;

// Fetch milestone and verify ownership
$stmt = $pdo->prepare("
    SELECT m.*, p.project_title 
    FROM milestones m 
    JOIN projects p ON m.project_id = p.id 
    WHERE m.id = ? AND m.user_id = ?
");
$stmt->execute([$id, $user_id]);
$milestone = $stmt->fetch();

if (!$milestone) {
    header("Location: ../projects/index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $amount = $_POST['amount'] ?: 0;
    $due_date = $_POST['due_date'];
    $status = $_POST['status'];

    if (empty($title)) {
        $error = 'Milestone title is required.';
    } else {
        $stmt = $pdo->prepare("UPDATE milestones SET title = ?, description = ?, amount = ?, due_date = ?, status = ? WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$title, $description, $amount, $due_date, $status, $id, $user_id])) {
            header("Location: ../projects/view.php?id=" . $milestone['project_id'] . "&milestone=updated");
            exit();
        } else {
            $error = 'Failed to update milestone. Please try again.';
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
                <h2 style="font-weight: 800;">Edit Milestone</h2>
            </div>
            <div class="topbar-actions">
                <a href="../projects/view.php?id=<?php echo $milestone['project_id']; ?>" class="btn btn-outline" style="border-radius: 12px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in" style="max-width: 800px; margin: 0 auto;">
                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <h2 style="font-weight: 800; margin-bottom: 5px;">Update Milestone</h2>
                    <p style="color: var(--text-muted); margin-bottom: 30px;">For: <strong style="color: var(--primary-color);"><?php echo htmlspecialchars($milestone['project_title']); ?></strong></p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                            <div class="form-group" style="grid-column: span 2;">
                                <label for="title">Milestone Title *</label>
                                <input type="text" name="title" id="title" class="form-control" value="<?php echo htmlspecialchars($milestone['title']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="amount">Milestone Value (Rs.)</label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-weight: 600;">Rs.</span>
                                    <input type="number" step="0.01" name="amount" id="amount" class="form-control" style="padding-left: 45px;" value="<?php echo $milestone['amount']; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="due_date">Target Due Date</label>
                                <input type="date" name="due_date" id="due_date" class="form-control" value="<?php echo $milestone['due_date']; ?>">
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label for="status">Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="pending" <?php echo $milestone['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_progress" <?php echo $milestone['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $milestone['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label for="description">Detailed Description</label>
                                <textarea name="description" id="description" class="form-control" rows="4"><?php echo htmlspecialchars($milestone['description']); ?></textarea>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 40px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; padding: 15px; border-radius: 12px; font-size: 1rem;">
                                <i class="fas fa-save" style="margin-right: 8px;"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>


