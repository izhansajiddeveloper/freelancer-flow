<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$filter = $_GET['filter'] ?? 'all';

// Fetch Milestones with Project Names
$sql = "SELECT m.*, p.project_title, c.client_name 
        FROM milestones m 
        JOIN projects p ON m.project_id = p.id 
        JOIN clients c ON p.client_id = c.id
        WHERE m.user_id = ?";

if ($filter === 'upcoming') {
    $sql .= " AND m.status != 'completed' AND m.due_date >= CURDATE() ORDER BY m.due_date ASC";
} elseif ($filter === 'overdue') {
    $sql .= " AND m.status != 'completed' AND m.due_date < CURDATE() ORDER BY m.due_date ASC";
} elseif ($filter === 'completed') {
    $sql .= " AND m.status = 'completed' ORDER BY m.updated_at DESC";
} else {
    $sql .= " ORDER BY m.created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$milestones = $stmt->fetchAll();

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Milestone Central</h2>
            </div>
            <div class="topbar-actions">
                <div class="filter-group" style="display: flex; gap: 8px;">
                    <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.75rem; padding: 6px 14px; border-radius: 10px;">All</a>
                    <a href="?filter=upcoming" class="btn <?php echo $filter === 'upcoming' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.75rem; padding: 6px 14px; border-radius: 10px;">Upcoming</a>
                    <a href="?filter=completed" class="btn <?php echo $filter === 'completed' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.75rem; padding: 6px 14px; border-radius: 10px;">Done</a>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in">
                <div style="margin-bottom: 30px;">
                    <h3 style="font-weight: 700;">Global Deliverables</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">Management of all project phases and payment checkpoints.</p>
                </div>

                <div class="milestone-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 24px;">
                    <?php if (empty($milestones)): ?>
                        <div class="glass-card" style="grid-column: 1 / -1; padding: 60px; text-align: center; border-radius: 24px;">
                            <div style="width: 80px; height: 80px; background: rgba(79, 70, 229, 0.05); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                <i class="fas fa-tasks" style="font-size: 2.5rem; color: var(--primary-color);"></i>
                            </div>
                            <h4 style="font-weight: 800; color: #1e293b; margin-bottom: 10px;">No Deliverables Found</h4>
                            <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto 25px;">You haven't created any milestones for this filter. Milestones help you bill clients as you complete project phases.</p>
                            <a href="../projects/index.php" class="btn btn-primary" style="padding: 12px 24px; border-radius: 12px;">Go to Projects</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($milestones as $m): ?>
                            <div class="glass-card milestone-card" style="padding: 24px; border-radius: 24px; position: relative; overflow: hidden; display: flex; flex-direction: column;">
                                <!-- Header: Project & Client -->
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--primary-color); letter-spacing: 1px; display: flex; align-items: center; gap: 6px;">
                                        <i class="fas fa-project-diagram"></i> <?php echo htmlspecialchars($m['project_title']); ?>
                                    </div>
                                    <div class="status-indicator <?php echo $m['status']; ?>" style="width: 10px; height: 10px; border-radius: 50%;"></div>
                                </div>

                                <!-- Body: Title & Desc -->
                                <div style="margin-bottom: 20px;">
                                    <h4 style="font-weight: 800; font-size: 1.2rem; margin-bottom: 8px; color: #1e293b;"><?php echo htmlspecialchars($m['title']); ?></h4>
                                    <p style="font-size: 0.85rem; color: #64748b; line-height: 1.6;"><?php echo htmlspecialchars($m['description'] ?: 'No additional details provided.'); ?></p>
                                </div>

                                <!-- Highlights Info -->
                                <div style="background: rgba(0,0,0,0.02); padding: 15px; border-radius: 16px; margin-bottom: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Milestone Value</div>
                                        <div style="font-weight: 800; color: #1e293b;">Rs. <?php echo number_format($m['amount'], 2); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Due Date</div>
                                        <div style="font-weight: 800; color: <?php echo (strtotime($m['due_date']) < time() && $m['status'] != 'completed') ? '#ef4444' : '#1e293b'; ?>;">
                                            <?php echo $m['due_date'] ? date('M d, Y', strtotime($m['due_date'])) : 'Open'; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Section: Update Status -->
                                <div style="padding-top: 20px; border-top: 1px solid rgba(0,0,0,0.05); margin-top: auto;">
                                    <h5 style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px;">Update Status</h5>
                                    <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                                        <a href="update_status.php?id=<?php echo $m['id']; ?>&status=pending" class="status-btn <?php echo $m['status'] == 'pending' ? 'active pending' : ''; ?>">Pending</a>
                                        <a href="update_status.php?id=<?php echo $m['id']; ?>&status=in_progress" class="status-btn <?php echo $m['status'] == 'in_progress' ? 'active in-progress' : ''; ?>">In Progress</a>
                                        <a href="update_status.php?id=<?php echo $m['id']; ?>&status=completed" class="status-btn <?php echo $m['status'] == 'completed' ? 'active completed' : ''; ?>">Completed</a>
                                    </div>

                                    <!-- Bottom Links -->
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div style="display: flex; gap: 10px;">
                                            <a href="edit.php?id=<?php echo $m['id']; ?>" class="action-icon-link" title="Edit Milestone"><i class="far fa-edit"></i></a>
                                            <a href="delete.php?id=<?php echo $m['id']; ?>" class="action-icon-link delete" title="Delete" onclick="return confirm('Delete this milestone?')"><i class="far fa-trash-alt"></i></a>
                                        </div>
                                        <a href="../projects/view.php?id=<?php echo $m['project_id']; ?>" class="btn btn-outline" style="font-size: 0.75rem; padding: 6px 12px; border-radius: 8px;">
                                            View Project <i class="fas fa-external-link-alt" style="font-size: 0.6rem; margin-left: 5px;"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
    .status-indicator.pending { background: #f59e0b; box-shadow: 0 0 8px rgba(245, 158, 11, 0.4); }
    .status-indicator.in_progress { background: #3b82f6; box-shadow: 0 0 8px rgba(59, 130, 246, 0.4); }
    .status-indicator.completed { background: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.4); }

    .status-btn {
        flex: 1;
        text-align: center;
        padding: 8px 4px;
        font-size: 0.7rem;
        font-weight: 700;
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        transition: all 0.2s;
    }
    .status-btn:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }
    .status-btn.active.pending { background: #fffbeb; color: #d97706; border-color: #fcd34d; }
    .status-btn.active.in-progress { background: #eff6ff; color: #2563eb; border-color: #93c5fd; }
    .status-btn.active.completed { background: #f0fdf4; color: #166534; border-color: #86efac; }

    .action-icon-link {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: #f1f5f9;
        color: #475569;
        transition: all 0.2s;
    }
    .action-icon-link:hover {
        background: var(--primary-color);
        color: white;
    }
    .action-icon-link.delete:hover {
        background: #ef4444;
    }

    .milestone-card {
        transition: var(--transition);
        border: 1px solid rgba(0,0,0,0.03) !important;
    }
    .milestone-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border-color: rgba(79, 70, 229, 0.2) !important;
    }
</style>


