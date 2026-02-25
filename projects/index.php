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

// --- System Reconciler: Automatically synchronize project statuses based on business rules ---
try {
    // 1. Sync based on Proposal Status
    // Draft or Sent -> Pending
    $pdo->prepare("
        UPDATE projects p
        INNER JOIN proposals prop ON p.proposal_id = prop.id
        SET p.status = 'pending'
        WHERE prop.status IN ('draft', 'sent') AND p.user_id = ?
    ")->execute([$user_id]);

    // Accepted -> In Progress
    $pdo->prepare("
        UPDATE projects p
        INNER JOIN proposals prop ON p.proposal_id = prop.id
        SET p.status = 'in_progress'
        WHERE prop.status = 'accepted' AND p.status IN ('pending', 'cancelled') AND p.user_id = ?
    ")->execute([$user_id]);

    // Rejected -> Cancelled
    $pdo->prepare("
        UPDATE projects p
        INNER JOIN proposals prop ON p.proposal_id = prop.id
        SET p.status = 'cancelled'
        WHERE prop.status = 'rejected' AND p.status IN ('pending', 'in_progress') AND p.user_id = ?
    ")->execute([$user_id]);

    // 2. Sync based on Invoice Status (Payment Completed flow)
    // If all project invoices are 'paid', mark as completed
    $pdo->prepare("
        UPDATE projects p
        SET p.status = 'completed'
        WHERE p.status = 'in_progress' AND p.user_id = ?
        AND EXISTS (SELECT 1 FROM invoices i WHERE i.project_id = p.id)
        AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.project_id = p.id AND i.status != 'paid')
    ")->execute([$user_id]);
} catch (PDOException $e) {
    // Silent fail or log
}

// Fetch all projects for this user with client names
$stmt = $pdo->prepare("
    SELECT p.*, c.client_name 
    FROM projects p 
    LEFT JOIN clients c ON p.client_id = c.id 
    WHERE p.user_id = ? 
    ORDER BY p.deadline ASC
");
$stmt->execute([$user_id]);
$projects = $stmt->fetchAll();

// Calculate Stats
$total_projects = count($projects);
$pending_projects = 0;
$in_progress_projects = 0;
$completed_projects = 0;
foreach($projects as $p) {
    if($p['status'] == 'pending') $pending_projects++;
    if($p['status'] == 'in_progress') $in_progress_projects++;
    if($p['status'] == 'completed') $completed_projects++;
}

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Project Portfolio</h2>
            </div>
            <div class="topbar-actions">
                <a href="add.php" class="btn btn-primary" style="border-radius: 12px; padding: 12px 24px; font-weight: 700;">
                    <i class="fas fa-plus-circle" style="margin-right: 8px;"></i> New Project
                </a>
            </div>
        </div>

        <div class="dashboard-container">
            <!-- Project Stats Overview -->
            <div class="animate-fade-in" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="glass-card stat-mini-card">
                    <div class="mini-icon" style="background: rgba(148, 163, 184, 0.1); color: #64748b;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="mini-label">Pending</div>
                        <div class="mini-value"><?php echo $pending_projects; ?></div>
                    </div>
                </div>
                <div class="glass-card stat-mini-card">
                    <div class="mini-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <div>
                        <div class="mini-label">In Progress</div>
                        <div class="mini-value"><?php echo $in_progress_projects; ?></div>
                    </div>
                </div>
                <div class="glass-card stat-mini-card">
                    <div class="mini-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div>
                        <div class="mini-label">Completed</div>
                        <div class="mini-value"><?php echo $completed_projects; ?></div>
                    </div>
                </div>
            </div>

            <!-- Search and filter row -->
            <div class="animate-fade-in" style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; gap: 20px; animation-delay: 0.1s;">
                <div style="position: relative; flex: 1; max-width: 400px;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="projectSearch" placeholder="Search projects by title or client..." style="width: 100%; padding: 12px 15px 12px 45px; border-radius: 12px; border: 1px solid var(--border-color); background: white; font-size: 0.9rem;">
                </div>
                <div style="display: flex; gap: 10px;">
                    <select id="statusFilter" style="padding: 12px 15px; border-radius: 12px; border: 1px solid var(--border-color); background: white; font-size: 0.9rem; color: var(--text-main); font-weight: 600;">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="on_hold">On Hold</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="animate-fade-in" style="animation-delay: 0.2s;">
                <div class="glass-card" style="padding: 0; overflow: visible; border-radius: 20px;">
                    <div style="overflow-x: auto;">
                        <table class="dashboard-table premium-table">
                            <thead>
                                <tr>
                                    <th style="padding-left: 30px;">Project Details</th>
                                    <th>Client</th>
                                    <th>Deadline</th>
                                    <th>Budget</th>
                                    <th>Status</th>
                                    <th style="text-align: right; padding-right: 30px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="projectTableBody">
                                <?php if (empty($projects)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 80px 30px;">
                                            <div style="width: 80px; height: 80px; background: var(--light-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: var(--text-muted); font-size: 2rem;">
                                                <i class="fas fa-folder-open"></i>
                                            </div>
                                            <h3 style="font-weight: 700; color: var(--text-main);">No Projects Found</h3>
                                            <p style="color: var(--text-muted); margin-bottom: 25px;">You haven't added any projects yet. Time to launch something new!</p>
                                            <a href="add.php" class="btn btn-primary" style="padding: 12px 30px;">Add New Project</a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($projects as $project): ?>
                                        <tr class="project-row" data-title="<?php echo strtolower($project['project_title']); ?>" data-client="<?php echo strtolower($project['client_name']); ?>" data-status="<?php echo $project['status']; ?>">
                                            <td style="padding-left: 30px;">
                                                <div style="display: flex; align-items: center; gap: 15px;">
                                                    <div class="project-icon-box">
                                                        <i class="fas fa-folder"></i>
                                                    </div>
                                                    <div>
                                                        <div class="project-title-cell"><?php echo htmlspecialchars($project['project_title']); ?></div>
                                                        <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 500;"><?php echo htmlspecialchars($project['project_type'] ?: 'General'); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="client-tag">
                                                    <?php echo htmlspecialchars($project['client_name'] ?: 'Unknown Client'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                    $deadline = strtotime($project['deadline']);
                                                    $is_overdue = ($deadline < time() && $project['status'] == 'active');
                                                ?>
                                                <div style="font-size: 0.85rem; font-weight: 600; color: <?php echo $is_overdue ? '#ef4444' : 'var(--text-main)'; ?>;">
                                                    <?php echo date('M d, Y', $deadline); ?>
                                                </div>
                                                <?php if($is_overdue): ?>
                                                    <div style="font-size: 0.65rem; color: #ef4444; font-weight: 700;">OVERDUE</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem;">
                                                    <?php echo $project['currency']; ?> <?php echo number_format($project['total_budget']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                    $status_map = [
                                                        'pending' => ['class' => 'status-pending', 'icon' => 'fa-clock'],
                                                        'in_progress' => ['class' => 'status-active', 'icon' => 'fa-play-circle'],
                                                        'completed' => ['class' => 'status-completed', 'icon' => 'fa-check-circle'],
                                                        'on_hold' => ['class' => 'status-on-hold', 'icon' => 'fa-pause-circle'],
                                                        'cancelled' => ['class' => 'status-cancelled', 'icon' => 'fa-times-circle']
                                                    ];
                                                    $current_status = $status_map[$project['status']] ?? ['class' => '', 'icon' => 'fa-question-circle'];
                                                ?>
                                                <span class="premium-status <?php echo $current_status['class']; ?>">
                                                    <i class="fas <?php echo $current_status['icon']; ?>" style="margin-right: 5px; font-size: 0.7rem;"></i>
                                                    <?php 
                                                        $status_label = ucfirst(str_replace('_', ' ', $project['status']));
                                                        if ($project['status'] === 'in_progress') $status_label = 'In Progress';
                                                        echo $status_label;
                                                    ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right; padding-right: 30px;">
                                                <div class="action-flex">
                                                    <a href="view.php?id=<?php echo $project['id']; ?>" class="p-action-btn view" title="View Project">
                                                        <i class="far fa-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $project['id']; ?>" class="p-action-btn edit" title="Edit Project">
                                                        <i class="far fa-edit"></i>
                                                    </a>
                                                    <button onclick="confirmDelete(<?php echo $project['id']; ?>)" class="p-action-btn delete" title="Delete Project">
                                                        <i class="far fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
    .stat-mini-card {
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        border-radius: 16px;
        transition: transform 0.3s ease;
    }
    .stat-mini-card:hover { transform: translateY(-3px); }
    .mini-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    .mini-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .mini-value { font-size: 1.4rem; font-weight: 800; color: var(--text-main); line-height: 1.1; }

    .project-icon-box {
        width: 40px;
        height: 40px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        font-size: 1rem;
    }
    .project-row:hover .project-icon-box {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    .premium-table { border-collapse: separate; border-spacing: 0; width: 100%; }
    .premium-table thead th {
        background: #f8fafc;
        padding: 18px 24px;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: #64748b;
        font-weight: 700;
        border-bottom: 2px solid #f1f5f9;
        text-align: left;
    }
    .project-row { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; }
    .project-row:hover { background: #fbfcfe; box-shadow: inset 4px 0 0 var(--primary-color); }
    .project-row td { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    
    .project-title-cell { font-weight: 700; color: #1e293b; font-size: 1rem; margin-bottom: 3px; }
    .project-row:hover .project-title-cell { color: var(--primary-color); }

    .client-tag {
        display: inline-flex;
        padding: 6px 12px;
        background: rgba(79, 70, 229, 0.05);
        color: var(--primary-color);
        border-radius: 10px;
        font-size: 0.8rem;
        font-weight: 700;
    }

    /* Status Badges */
    .premium-status { padding: 6px 14px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; }
    .status-active { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
    .status-pending { background: rgba(148, 163, 184, 0.1); color: #64748b; }
    .status-completed { background: rgba(16, 185, 129, 0.1); color: #059669; }
    .status-on-hold { background: rgba(245, 158, 11, 0.1); color: #d97706; }
    .status-cancelled { background: rgba(239, 68, 68, 0.1); color: #dc2626; }

    /* Action Buttons */
    .action-flex { display: flex; gap: 10px; justify-content: flex-end; }
    .p-action-btn {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        text-decoration: none;
    }
    .p-action-btn:hover { 
        background: white; 
        color: var(--primary-color); 
        border-color: var(--primary-color); 
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.12);
    }
</style>

<script>
    // Live Search Logic
    const searchInput = document.getElementById('projectSearch');
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('.project-row');

    function filterTable() {
        const query = searchInput.value.toLowerCase();
        const status = statusFilter.value;

        tableRows.forEach(row => {
            const title = row.dataset.title;
            const client = row.dataset.client;
            const rowStatus = row.dataset.status;

            const matchesQuery = title.includes(query) || client.includes(query);
            const matchesStatus = status === 'all' || rowStatus === status;

            row.style.display = (matchesQuery && matchesStatus) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterTable);
    statusFilter.addEventListener('change', filterTable);

    function confirmDelete(id) {
        if (confirm('Are you sure you want to permanently delete this project? This will also remove any associated invoices.')) {
            window.location.href = 'delete.php?id=' + id;
        }
    }
</script>

<?php include_once '../includes/footer.php'; ?>
