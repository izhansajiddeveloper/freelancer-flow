<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$project_id = $_GET['project_id'] ?? 0;

// Fetch project data with client name and proposal status
$stmt = $pdo->prepare("
    SELECT p.project_title, prop.status as proposal_status
    FROM projects p
    LEFT JOIN proposals prop ON p.proposal_id = prop.id
    WHERE p.id = ? AND p.user_id = ?
");
$stmt->execute([$project_id, $user_id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: ../projects/index.php");
    exit();
}

// RESTRICTION: Cannot add milestones if linked proposal is not 'accepted'
if ($project['proposal_status'] !== 'accepted') {
    die("<div style='padding: 50px; text-align: center; font-family: sans-serif;'>
            <h2 style='color: #ef4444;'>Action Blocked</h2>
            <p>You cannot add milestones to this project because the linked proposal is still in <strong>" . strtoupper($project['proposal_status'] ?: 'Draft') . "</strong> status.</p>
            <p>Please go to the proposal and mark it as <strong>Accepted</strong> first.</p>
            <br>
            <a href='../projects/view.php?id=$project_id' style='color: #4f46e5;'>Back to Project</a>
         </div>");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $amount = $_POST['amount'] ?: 0;
    $due_date = $_POST['due_date'];
    $status = $_POST['status'] ?: 'pending';

    if (empty($title)) {
        $error = 'Milestone title is required.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO milestones (user_id, project_id, title, description, amount, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $project_id, $title, $description, $amount, $due_date, $status])) {
            header("Location: ../projects/view.php?id=$project_id&milestone=added");
            exit();
        } else {
            $error = 'Failed to add milestone. Please try again.';
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
                <h2 style="font-weight: 800;">Add Milestone</h2>
            </div>
            <div class="topbar-actions">
                <a href="../projects/view.php?id=<?php echo $project_id; ?>" class="btn btn-outline" style="border-radius: 12px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in" style="max-width: 800px; margin: 0 auto;">
                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <h2 style="font-weight: 800; margin-bottom: 5px;">New Milestone</h2>
                    <p style="color: var(--text-muted); margin-bottom: 30px;">For: <strong style="color: var(--primary-color);"><?php echo htmlspecialchars($project['project_title']); ?></strong></p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                            <div class="form-group" style="grid-column: span 2;">
                                <label for="title">Milestone Title *</label>
                                <input type="text" name="title" id="title" class="form-control" placeholder="e.g., Initial Design Mockups" required>
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;">What deliverable is this milestone for?</p>
                            </div>

                            <div class="form-group">
                                <label for="amount">Milestone Value (Rs.)</label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-weight: 600;">Rs.</span>
                                    <input type="number" step="0.01" name="amount" id="amount" class="form-control" style="padding-left: 45px;" placeholder="0.00">
                                </div>
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;">How much should be invoiced for this?</p>
                            </div>

                            <div class="form-group">
                                <label for="due_date">Target Due Date</label>
                                <input type="date" name="due_date" id="due_date" class="form-control">
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label for="status">Initial Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="pending" selected>Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label for="description">Detailed Description</label>
                                <textarea name="description" id="description" class="form-control" rows="4" placeholder="What exactly needs to be done for this milestone?"></textarea>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 40px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; padding: 15px; border-radius: 12px; font-size: 1rem;">
                                <i class="fas fa-plus-circle" style="margin-right: 8px;"></i> Create Milestone
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>


