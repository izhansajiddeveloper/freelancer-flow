<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$error = '';
$success = '';

// Fetch clients
$stmt = $pdo->prepare("SELECT id, client_name FROM clients WHERE user_id = ? ORDER BY client_name ASC");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll();

// Fetch projects that are eligible for a new proposal:
// - No proposal linked OR
// - Linked proposal is 'rejected'
$stmt = $pdo->prepare("
    SELECT p.id, p.project_title, p.total_budget, p.start_date, p.deadline, p.client_id, p.description 
    FROM projects p
    LEFT JOIN proposals prop ON p.proposal_id = prop.id
    WHERE p.user_id = ? 
    AND (p.proposal_id IS NULL OR prop.status = 'rejected')
    ORDER BY p.created_at DESC
");
$stmt->execute([$user_id]);
$projects = $stmt->fetchAll();
$projects_json = json_encode($projects);

$selected_project_id = $_GET['project_id'] ?? $_POST['project_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get required fields
    $client_id     = $_POST['client_id'] ?? '';
    $project_title = trim($_POST['project_title'] ?? '');

    // Optional fields
    $project_overview   = trim($_POST['project_overview'] ?? '');
    $project_scope      = trim($_POST['project_scope'] ?? '');
    $milestone_breakdown = trim($_POST['milestone_breakdown'] ?? '');
    $price              = floatval($_POST['price'] ?? 0);
    $timeline           = trim($_POST['timeline'] ?? '');
    $terms              = trim($_POST['terms'] ?? '');
    $payment_terms      = trim($_POST['payment_terms'] ?? '');

    // Validation
    if (empty($client_id) || empty($project_title) || $price <= 0) {
        $error = "Client, Project Title, and Price are required and Price must be greater than 0.";
    } else {
        // Double check if project already has an active proposal
        if ($selected_project_id > 0) {
            $check_stmt = $pdo->prepare("
                SELECT prop.status 
                FROM projects p
                JOIN proposals prop ON p.proposal_id = prop.id
                WHERE p.id = ? AND p.user_id = ? AND prop.status IN ('draft', 'sent', 'accepted')
            ");
            $check_stmt->execute([$selected_project_id, $user_id]);
            if ($check_stmt->fetch()) {
                $error = "This project already has an active proposal. You cannot create another one unless the current one is rejected.";
            }
        }
    }

    if (empty($error)) {
        try {
            // Insert into database
            $stmt = $pdo->prepare("
                INSERT INTO proposals 
                (user_id, client_id, project_title, project_overview, project_scope, milestone_breakdown, price, timeline, terms, payment_terms, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
            ");
            $stmt->execute([
                $user_id,
                $client_id,
                $project_title,
                $project_overview,
                $project_scope,
                $milestone_breakdown,
                $price,
                $timeline,
                $terms,
                $payment_terms
            ]);

            $proposal_id = $pdo->lastInsertId();
            
            // Link proposal to project if provided
            if ($selected_project_id > 0) {
                $stmt = $pdo->prepare("UPDATE projects SET proposal_id = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$proposal_id, $selected_project_id, $user_id]);
            }

            $success = "Proposal created successfully!";
            header("Location: index.php?success=created");
            exit();
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
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
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Create New Proposal</h2>
            </div>
        </div>

        <div class="dashboard-container">
                <div style="display: grid; grid-template-columns: 1fr 300px; gap: 30px; max-width: 1200px; margin: 0 auto;">
                    <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                        <?php if ($error): ?>
                            <div style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; font-size: 0.9rem; border: 1px solid rgba(239, 68, 68, 0.2);">
                                <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="proposal-form">
                            <!-- Step 1: Client & Basic Info -->
                        <div style="margin-bottom: 30px;">
                            <h3 style="font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                                <span style="width: 28px; height: 28px; background: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">1</span>
                                Basic Information
                            </h3>
                            
                            <div class="form-group" style="margin-bottom: 20px; background: #f1f5f9; padding: 15px; border-radius: 12px; border: 1px dashed #cbd5e1;">
                                <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.8rem; color: #475569;">
                                    <i class="fas fa-magic"></i> Auto-fill from Existing Project? (Optional)
                                </label>
                                <select id="project_selector" name="project_id" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.85rem;">
                                    <option value="">-- Select Project to Copy Details --</option>
                                    <?php foreach ($projects as $proj): ?>
                                        <option value="<?php echo $proj['id']; ?>" <?php echo $selected_project_id == $proj['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($proj['project_title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Select Client</label>
                                    <select name="client_id" id="client_id" required style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600; color: #1e293b; transition: all 0.2s;">
                                        <option value="">-- Choose Client --</option>
                                        <?php foreach ($clients as $c): ?>
                                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['client_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Project Title (Max 100 chars)</label>
                                    <input type="text" name="project_title" id="project_title" placeholder="e.g. Modern E-commerce Redesign" maxlength="100" required style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600;">
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Overview & Scope -->
                        <div style="margin-bottom: 30px;">
                            <h3 style="font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                                <span style="width: 28px; height: 28px; background: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">2</span>
                                Project Roadmap
                            </h3>
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Project Overview (Brief summary of the goals)</label>
                                <textarea name="project_overview" id="project_overview" maxlength="1000" placeholder="This proposal outlines the scope, deliverables, timeline, and cost..." style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 500; min-height: 80px;"></textarea>
                            </div>
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Detailed Project Scope (Deliverables)</label>
                                <textarea name="project_scope" id="project_scope" maxlength="3000" placeholder="• Custom homepage design&#10;• Fully responsive layout..." style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 500; min-height: 120px;"></textarea>
                            </div>
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Milestone Breakdown & Pricing</label>
                                <textarea name="milestone_breakdown" id="milestone_breakdown" maxlength="2000" placeholder="Milestone 1: Design Phase - PKR 20,000&#10;Milestone 2: Development - PKR 40,000..." style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 500; min-height: 100px;"></textarea>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Total Price (Estimated)</label>
                                    <div style="position: relative;">
                                        <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #94a3b8;">PKR</span>
                                        <input type="number" name="price" id="price" step="0.01" placeholder="0.00" required style="width: 100%; padding: 12px 16px 12px 55px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 700;">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Timeline / Duration (e.g. 4 Weeks)</label>
                                    <input type="text" name="timeline" id="timeline" maxlength="50" placeholder="e.g. 3 Weeks" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600;">
                                </div>
                            </div>
                        </div>

                            <!-- Step 3: Terms & Payment -->
                            <div style="margin-bottom: 40px;">
                                <h3 style="font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                                    <span style="width: 28px; height: 28px; background: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">3</span>
                                    Terms & Payment
                                </h3>
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Payment Schedule (Max 500 chars)</label>
                                    <textarea name="payment_terms" maxlength="500" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 500; min-height: 80px;">• 50% advance payment before project commencement&#10;• 50% upon project completion</textarea>
                                </div>
                                <div class="form-group">
                                    <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">General Terms & Conditions (Max 2000 chars)</label>
                                    <textarea name="terms" maxlength="2000" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 500; min-height: 100px;">• Any additional features outside the defined scope will be quoted separately.&#10;• Delays in client feedback may impact the delivery timeline.</textarea>
                                </div>
                            </div>

                            <div style="display: flex; gap: 15px;">
                                <button type="submit" class="btn btn-primary" style="flex: 2; padding: 15px; border-radius: 16px; font-weight: 700; font-size: 1rem;">
                                    <i class="fas fa-file-invoice" style="margin-right: 10px;"></i> Save Draft
                                </button>
                                <a href="index.php" class="btn btn-outline" style="flex: 1; padding: 15px; border-radius: 16px; font-weight: 700; text-align: center;">Cancel</a>
                            </div>
                        </form>
                    </div>

                    <!-- Sidebar Tips -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div class="glass-card" style="padding: 25px; border-radius: 20px; background: var(--gradient-primary) !important;">
                            <h4 style="color: white; font-weight: 800; margin-bottom: 12px;"><i class="fas fa-lightbulb"></i> Pro Tip</h4>
                            <p style="color: rgba(255,255,255,0.9); font-size: 0.85rem; line-height: 1.6;">A winning proposal highlights the <strong>value</strong> you provide, not just the cost. Be specific about deliverables.</p>
                        </div>
                        <div class="glass-card" style="padding: 25px; border-radius: 20px;">
                            <h4 style="font-weight: 800; margin-bottom: 15px; font-size: 0.9rem; color: #1e293b;">Checklist</h4>
                            <ul style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 12px;">
                                <li style="font-size: 0.8rem; color: #64748b; display: flex; gap: 10px;"><i class="fas fa-check-circle" style="color: #10b981;"></i> Scope is clear</li>
                                <li style="font-size: 0.8rem; color: #64748b; display: flex; gap: 10px;"><i class="fas fa-check-circle" style="color: #10b981;"></i> Timeline is realistic</li>
                                <li style="font-size: 0.8rem; color: #64748b; display: flex; gap: 10px;"><i class="fas fa-check-circle" style="color: #10b981;"></i> Terms are defined</li>
                                <li style="font-size: 0.8rem; color: #64748b; display: flex; gap: 10px;"><i class="fas fa-check-circle" style="color: #10b981;"></i> Client info is correct</li>
                            </ul>
                        </div>
                    </div>
                </div>
        </div>
</main>
</div>

<script>
    const projects = <?php echo $projects_json; ?>;
    
    document.getElementById('project_selector').addEventListener('change', function() {
        const projectId = this.value;
        if (!projectId) return;
        
        const project = projects.find(p => p.id == projectId);
        if (project) {
            document.getElementById('project_title').value = project.project_title;
            document.getElementById('price').value = project.total_budget;
            document.getElementById('project_scope').value = project.description;
            document.getElementById('client_id').value = project.client_id;
            
            // Calculate timeline string if dates exist
            if (project.start_date && project.deadline) {
                const start = new Date(project.start_date);
                const end = new Date(project.deadline);
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                const weeks = Math.round(diffDays / 7);
                document.getElementById('timeline').value = weeks > 0 ? weeks + " Weeks" : diffDays + " Days";
            }
            
            // Visual feedback
            const card = this.closest('.form-group');
            card.style.background = '#f0fdf4';
            card.style.borderColor = '#86efac';
            setTimeout(() => {
                card.style.background = '#f1f5f9';
                card.style.borderColor = '#cbd5e1';
            }, 1000);
        }
    });
</script>

<script>
    // Trigger auto-fill if a project is pre-selected
    window.addEventListener('load', function() {
        const selector = document.getElementById('project_selector');
        if (selector && selector.value) {
            selector.dispatchEvent(new Event('change'));
        }
    });

    // Character Counter Logic
    function updateCharCount(id, max) {
        const textarea = document.getElementById(id);
        if (!textarea) return;
        const label = textarea.previousElementSibling;
        const count = textarea.value.length;
        
        let counter = label.querySelector('.char-counter');
        if (!counter) {
            counter = document.createElement('span');
            counter.className = 'char-counter';
            counter.style.fontSize = '0.7rem';
            counter.style.fontWeight = '600';
            counter.style.marginLeft = '10px';
            counter.style.color = '#94a3b8';
            label.appendChild(counter);
        }
        counter.textContent = `(${count}/${max})`;
        counter.style.color = count >= max ? '#ef4444' : '#94a3b8';
    }

    ['project_overview', 'project_scope', 'milestone_breakdown', 'terms'].forEach(id => {
        const textarea = document.getElementById(id);
        if(textarea) {
            const max = textarea.getAttribute('maxlength') || 2000;
            textarea.addEventListener('input', () => updateCharCount(id, max));
            updateCharCount(id, max); // Initial count
        }
    });
</script>

<style>
    .proposal-form .form-group input:focus, 
    .proposal-form .form-group textarea:focus,
    .proposal-form .form-group select:focus {
        border-color: var(--primary-color);
        background: white;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        outline: none;
    }
</style>


