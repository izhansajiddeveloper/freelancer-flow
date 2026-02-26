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

// Fetch project data with client name, proposal status, and contract status
$stmt = $pdo->prepare("
    SELECT p.*, c.client_name, c.email as client_email, c.company_name, 
           prop.status as proposal_status, prop.id as proposal_linked_id,
           cont.status as contract_status, cont.id as contract_id
    FROM projects p 
    LEFT JOIN clients c ON p.client_id = c.id 
    LEFT JOIN proposals prop ON p.proposal_id = prop.id
    LEFT JOIN contracts cont ON cont.project_id = p.id
    WHERE p.id = ? AND p.user_id = ?
");
$stmt->execute([$id, $user_id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: index.php");
    exit();
}

$proposal_status = $project['proposal_status'] ?? '';
$contract_status = $project['contract_status'] ?? '';

// Proposal Logic: Show create if none exists or if rejected
$can_create_proposal = (empty($project['proposal_linked_id']) || $proposal_status === 'rejected');

// Contract Logic: Show create if proposal is accepted AND (no contract exists or it's terminated)
$can_create_contract = ($proposal_status === 'accepted' && (empty($project['contract_id']) || $contract_status === 'terminated'));

// Milestones Logic: Only visible/active if contract is signed
$is_contract_signed = ($contract_status === 'signed');

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Project Details</h2>
            </div>
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline" style="margin-right: 10px; font-size: 0.85rem; padding: 10px 20px; border-radius: 12px; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-arrow-left" style="font-size: 0.8rem;"></i> Back to Portfolio
                </a>
                <?php if ($project['status'] !== 'completed'): ?>
                    <a href="edit.php?id=<?php echo $project['id']; ?>" class="btn btn-outline" style="margin-right: 10px; padding: 10px 20px; border-radius: 12px;">
                        <i class="far fa-edit"></i> Edit Project
                    </a>
                    
                    <?php if ($can_create_proposal): ?>
                        <a href="../proposals/create.php?project_id=<?php echo $project['id']; ?>" class="btn btn-primary" style="padding: 10px 20px; border-radius: 12px;">
                            <i class="fas fa-file-signature"></i> Create Proposal
                        </a>
                    <?php endif; ?>

                    <?php if ($can_create_contract): ?>
                        <a href="../contracts/create.php?project_id=<?php echo $project['id']; ?>" class="btn btn-primary" style="padding: 10px 20px; border-radius: 12px; background: #059669; border-color: #059669;">
                            <i class="fas fa-file-contract"></i> Create Contract
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in" style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                <!-- Main Content -->
                <div style="display: flex; flex-direction: column; gap: 30px;">
                    <!-- Project Overview Card -->
                    <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px;">
                            <div>
                                <h1 style="font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo htmlspecialchars($project['project_title']); ?></h1>
                                <div style="display: flex; gap: 15px; align-items: center;">
                                    <span class="type-tag"><?php echo htmlspecialchars($project['project_type'] ?: 'General Project'); ?></span>
                                    <span class="status-badge-premium <?php echo str_replace('_', '-', $project['status']); ?>">
                                        <?php 
                                            $p_label = ucfirst(str_replace('_', ' ', $project['status']));
                                            if ($project['status'] === 'in_progress') $p_label = 'In Progress';
                                            echo $p_label; 
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Total Budget</div>
                                <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary-color);">
                                    <?php echo $project['currency']; ?> <?php echo number_format($project['total_budget'], 2); ?>
                                </div>
                            </div>
                        </div>

                        <hr style="border: 0; border-top: 1px solid #f1f5f9; margin: 30px 0;">

                        <h3 style="font-weight: 700; margin-bottom: 15px; color: #334155;">Project Description</h3>
                        <div style="color: #475569; line-height: 1.8; font-size: 1rem;">
                            <?php echo nl2br(htmlspecialchars($project['description'] ?: 'No description provided for this project.')); ?>
                        </div>
                    </div>

                    <!-- Milestones Section -->
                    <div class="glass-card" style="padding: 30px;">
                        <?php
                        // Fetch milestones for display and progress calculation
                        $stmt = $pdo->prepare("SELECT * FROM milestones WHERE project_id = ? AND user_id = ? ORDER BY due_date ASC");
                        $stmt->execute([$id, $user_id]);
                        $all_milestones = $stmt->fetchAll();

                        // Determine the exact blocker for milestones
                        $milestone_blocker = '';
                        $blocker_link = '';
                        $blocker_btn_text = '';

                        if (empty($project['proposal_linked_id'])) {
                            $milestone_blocker = "Proposal Required";
                            $blocker_message = "You need to create a project proposal first.";
                            $blocker_link = "../proposals/create.php?project_id=" . $project['id'];
                            $blocker_btn_text = "Create Proposal";
                        } elseif ($proposal_status !== 'accepted') {
                            $milestone_blocker = "Proposal Approval Required";
                            $blocker_message = "The project proposal is currently <strong>" . strtoupper($proposal_status) . "</strong>. It must be accepted by the client to proceed.";
                            $blocker_link = "../proposals/generate.php?id=" . $project['proposal_linked_id'];
                            $blocker_btn_text = "Review Proposal";
                        } elseif (empty($project['contract_id'])) {
                            $milestone_blocker = "Contract Required";
                            $blocker_message = "The proposal is accepted! Now you need to create the project contract.";
                            $blocker_link = "../contracts/create.php?project_id=" . $project['id'];
                            $blocker_btn_text = "Create Contract";
                        } elseif ($contract_status !== 'signed') {
                            $milestone_blocker = "Contract Signing Required";
                            $blocker_message = "The contract is currently <strong>" . strtoupper($contract_status) . "</strong>. Both parties must sign to unlock milestones.";
                            $blocker_link = "../contracts/view.php?id=" . $project['contract_id'];
                            $blocker_btn_text = "View Contract";
                        }
                        ?>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                            <h3 style="font-weight: 700;">Project Milestones</h3>
                            <?php if ($is_contract_signed): ?>
                                <?php if ($project['status'] !== 'completed'): ?>
                                    <a href="../milestones/add.php?project_id=<?php echo $project['id']; ?>" class="btn btn-primary" style="font-size: 0.8rem; padding: 8px 16px;">
                                        <i class="fas fa-plus"></i> Add Milestone
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="font-size: 0.85rem; color: #ef4444; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <i class="fas fa-lock"></i> <?php echo $milestone_blocker; ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!$is_contract_signed): ?>
                            <div style="text-align: center; padding: 60px 40px; background: #fff1f2; border: 1px dashed #fda4af; border-radius: 20px;">
                                <div style="width: 60px; height: 60px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: #ef4444; font-size: 1.5rem; box-shadow: 0 10px 15px -3px rgba(225, 29, 72, 0.1);">
                                    <i class="fas <?php echo empty($project['proposal_linked_id']) ? 'fa-file-invoice' : 'fa-file-contract'; ?>"></i>
                                </div>
                                <h4 style="font-weight: 800; color: #9f1239; margin-bottom: 10px;"><?php echo $milestone_blocker; ?></h4>
                                <p style="color: #be123c; font-size: 0.9rem; max-width: 350px; margin: 0 auto 25px;"><?php echo $blocker_message; ?></p>
                                <a href="<?php echo $blocker_link; ?>" class="btn" style="background: #e11d48; color: white; font-weight: 700; border-radius: 12px; padding: 10px 25px;"><?php echo $blocker_btn_text; ?></a>
                            </div>
                        <?php else: ?>
                            <div class="milestone-list">
                                <?php if (empty($all_milestones)): ?>
                                    <?php if ($project['status'] === 'completed'): ?>
                                        <div class="milestone-item" style="padding: 24px; background: white; border-radius: 20px; border: 1px solid #f1f5f9; margin-bottom: 20px;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                                <div style="display: flex; gap: 15px; align-items: flex-start;">
                                                    <div style="padding: 10px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; color: #10b981;">
                                                        <i class="fas fa-check-circle"></i>
                                                    </div>
                                                    <div>
                                                        <h4 style="font-weight: 700; margin-bottom: 4px;">Project Finished</h4>
                                                        <div style="display: flex; gap: 15px; font-size: 0.8rem; font-weight: 600;">
                                                            <span style="color: var(--primary-color);"><i class="fas fa-tag"></i> Rs. <?php echo number_format($project['total_budget'], 2); ?></span>
                                                            <span style="color: #64748b;">
                                                                <i class="far fa-calendar-alt"></i> Delivered on <?php echo $project['updated_at'] ? date('M d, Y', strtotime($project['updated_at'])) : date('M d, Y'); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 15px;">This project has been successfully completed and delivered to the client.</p>
                                            <div style="display: flex; gap: 6px;">
                                                <span class="status-btn-small active completed">Completed</span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 40px; background: rgba(0,0,0,0.02); border-radius: 16px; border: 1px dashed var(--border-color);">
                                            <i class="fas fa-tasks" style="font-size: 2rem; color: #cbd5e1; margin-bottom: 15px; display: block;"></i>
                                            <p style="color: var(--text-muted);">No milestones defined for this project yet.</p>
                                            <p style="font-size: 0.8rem; margin-top: 5px;">Break your project into trackable phases to manage progress.</p>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php foreach ($all_milestones as $m): ?>
                                        <div class="milestone-item" style="padding: 24px; background: white; border-radius: 20px; border: 1px solid #f1f5f9; margin-bottom: 20px; transition: var(--transition);">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                                <div style="display: flex; gap: 15px; align-items: flex-start;">
                                                    <div style="padding: 10px; background: <?php 
                                                        if($m['status'] == 'completed' || $project['status'] == 'completed') echo 'rgba(16, 185, 129, 0.1)';
                                                        elseif($m['status'] == 'in_progress') echo 'rgba(59, 130, 246, 0.1)';
                                                        else echo 'rgba(79, 70, 229, 0.1)';
                                                    ?>; border-radius: 12px; color: <?php 
                                                        if($m['status'] == 'completed' || $project['status'] == 'completed') echo '#10b981';
                                                        elseif($m['status'] == 'in_progress') echo '#3b82f6';
                                                        else echo 'var(--primary-color)';
                                                    ?>;">
                                                        <i class="fas <?php 
                                                            if($m['status'] == 'completed' || $project['status'] == 'completed') echo 'fa-check-circle';
                                                            elseif($m['status'] == 'in_progress') echo 'fa-spinner fa-spin';
                                                            else echo 'fa-clock';
                                                        ?>"></i>
                                                    </div>
                                                    <div>
                                                        <h4 style="font-weight: 700; margin-bottom: 4px;"><?php echo htmlspecialchars($m['title']); ?></h4>
                                                        <div style="display: flex; gap: 15px; font-size: 0.8rem; font-weight: 600;">
                                                            <span style="color: var(--primary-color);"><i class="fas fa-tag"></i> Rs. <?php echo number_format($m['amount'], 2); ?></span>
                                                            <span style="color: <?php echo (strtotime($m['due_date']) < time() && $m['status'] != 'completed' && $project['status'] != 'completed') ? '#ef4444' : '#64748b'; ?>;">
                                                                <i class="far fa-calendar-alt"></i> <?php echo $m['due_date'] ? date('M d, Y', strtotime($m['due_date'])) : 'No date'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($project['status'] !== 'completed'): ?>
                                                    <div style="display: flex; gap: 8px;">
                                                        <a href="../milestones/edit.php?id=<?php echo $m['id']; ?>" class="action-icon-link" title="Edit"><i class="far fa-edit"></i></a>
                                                        <a href="../milestones/delete.php?id=<?php echo $m['id']; ?>" class="action-icon-link delete" onclick="return confirm('Delete this milestone?')"><i class="far fa-trash-alt"></i></a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 15px;"><?php echo htmlspecialchars($m['description']); ?></p>

                                            <?php if ($project['status'] !== 'completed'): ?>
                                                <div style="display: flex; gap: 6px;">
                                                    <a href="../milestones/update_status.php?id=<?php echo $m['id']; ?>&status=pending" class="status-btn-small <?php echo $m['status'] == 'pending' ? 'active pending' : ''; ?>">Pending</a>
                                                    <a href="../milestones/update_status.php?id=<?php echo $m['id']; ?>&status=in_progress" class="status-btn-small <?php echo $m['status'] == 'in_progress' ? 'active in-progress' : ''; ?>">In Progress</a>
                                                    <a href="../milestones/update_status.php?id=<?php echo $m['id']; ?>&status=completed" class="status-btn-small <?php echo $m['status'] == 'completed' ? 'active completed' : ''; ?>">Completed</a>
                                                </div>
                                            <?php else: ?>
                                                <div style="display: flex; gap: 6px;">
                                                    <span class="status-btn-small active completed">Completed</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Timeline & Progress -->
                    <div class="glass-card" style="padding: 30px;">
                        <h3 style="font-weight: 700; margin-bottom: 25px;">Delivery Timeline</h3>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                            <div class="timeline-point">
                                <div class="label">Start Date</div>
                                <div class="date"><?php echo $project['start_date'] ? date('M d, Y', strtotime($project['start_date'])) : 'Not set'; ?></div>
                            </div>
                            <div class="timeline-point" style="text-align: right;">
                                <div class="label">Deadline</div>
                                <div class="date" style="color: #ef4444; font-weight: 700;"><?php echo $project['deadline'] ? date('M d, Y', strtotime($project['deadline'])) : 'Not set'; ?></div>
                            </div>
                        </div>
                        <?php
                        $total_m = count($all_milestones);
                        $completed_m = 0;
                        foreach($all_milestones as $m) if($m['status'] == 'completed') $completed_m++;
                        
                        if ($project['status'] == 'completed') {
                            $percent = 100;
                            $completed_m = $total_m;
                        } else {
                            $percent = ($total_m > 0) ? ($completed_m / $total_m) * 100 : 0;
                        }
                        ?>
                        <div class="progress-bar-container" style="height: 12px; background: #f1f5f9; border-radius: 10px;">
                            <div class="progress-bar-fill" style="width: <?php echo $percent; ?>%; height: 100%; border-radius: 10px;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 0.8rem; font-weight: 600; color: #64748b;">
                            <span>Milestones Completed: <?php echo $completed_m; ?> / <?php echo $total_m; ?></span>
                            <span><?php echo round($percent); ?>% Done</span>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Content -->
                <div style="display: flex; flex-direction: column; gap: 30px;">
                    <!-- Client Info Card -->
                    <div class="glass-container-card">
                        <div class="glass-card" style="padding: 30px; border-radius: 20px;">
                            <h3 style="font-weight: 700; margin-bottom: 20px; font-size: 1.1rem;">Client Relationship</h3>
                            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                                <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(79, 70, 229, 0.1); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem;">
                                    <?php echo strtoupper(substr($project['client_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($project['client_name']); ?></div>
                                    <div style="font-size: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($project['company_name']); ?></div>
                                </div>
                            </div>
                            <a href="../clients/view.php?id=<?php echo $project['client_id']; ?>" class="btn btn-outline" style="width: 100%; justify-content: center; font-size: 0.85rem;">
                                <i class="far fa-user" style="margin-right: 8px;"></i> View Client Profile
                            </a>
                        </div>
                    </div>

                    <!-- Proposal Quick Card -->
                    <div class="glass-card vibrant-primary shadow-lg" style="padding: 30px; color: white; background: var(--gradient-primary); border: none;">
                        <h3 style="font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 5px; color: black;">Strategic Management</h3>
                        <p style="font-size: 0.8rem; margin-bottom: 25px; opacity: 0.8; color: black;">Manage your project documentation and legal compliance.</p>
                        
                        <?php if ($project['status'] !== 'completed' && $can_create_proposal): ?>
                            <a href="../proposals/create.php?project_id=<?php echo $project['id']; ?>" class="btn" style="width: 100%; background: white; color: var(--primary-color); border: none; font-weight: 700; border-radius: 12px; justify-content: center;">
                                <i class="fas fa-file-signature"></i> Draft New Proposal
                            </a>
                        <?php elseif ($project['status'] !== 'completed' && $can_create_contract): ?>
                            <a href="../contracts/create.php?project_id=<?php echo $project['id']; ?>" class="btn" style="width: 100%; background: white; color: #059669; border: none; font-weight: 700; border-radius: 12px; justify-content: center;">
                                <i class="fas fa-file-contract"></i> Create Contract
                            </a>
                        <?php else: ?>
                            <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; text-align: center; font-size: 0.8rem; color: black; font-weight: 600;">
                                <i class="fas <?php echo ($project['status'] === 'completed') ? 'fa-check-double' : (($is_contract_signed) ? 'fa-check-circle' : 'fa-hourglass-half'); ?>"></i>
                                <?php 
                                    if ($project['status'] === 'completed') echo 'Project is Completed';
                                    elseif ($is_contract_signed) echo 'Project is Active';
                                    else echo 'Waiting for Action';
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
    .type-tag {
        padding: 5px 12px;
        background: #f1f5f9;
        color: #475569;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-badge-premium {
        padding: 5px 12px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-badge-premium.in-progress { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
    .status-badge-premium.pending { background: rgba(148, 163, 184, 0.1); color: #64748b; }
    .status-badge-premium.completed { background: rgba(16, 185, 129, 0.1); color: #059669; }
    .status-badge-premium.on-hold { background: rgba(245, 158, 11, 0.1); color: #d97706; }

    .timeline-point .label { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 4px; }
    .timeline-point .date { font-size: 1rem; font-weight: 700; color: #1e293b; }
</style>


