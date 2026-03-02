<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$error = '';
$success = '';

// Check if project_id is provided via GET (from Project View)
$project_id = $_GET['project_id'] ?? 0;
$preselected_project = null;

if ($project_id) {
    $stmt = $pdo->prepare("
        SELECT p.*, prop.id as proposal_id, prop.project_overview, prop.price as proposal_price, prop.status as proposal_status,
               u.full_name as user_name, u.email as user_email, u.phone as user_phone, u.job_title
        FROM projects p
        LEFT JOIN proposals prop ON p.proposal_id = prop.id
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND p.user_id = ?
    ");
    $stmt->execute([$project_id, $user_id]);
    $preselected_project = $stmt->fetch();
}

// Fetch all projects along with their accepted proposal info
$stmt = $pdo->prepare("
    SELECT p.*, prop.id as proposal_id, prop.project_overview, prop.price as proposal_price, prop.status as proposal_status
    FROM projects p
    LEFT JOIN proposals prop ON p.proposal_id = prop.id
    LEFT JOIN contracts cont ON p.id = cont.project_id
    WHERE p.user_id = ? AND cont.id IS NULL
    ORDER BY p.created_at DESC
");
$stmt->execute([$user_id]);
$projects_data = $stmt->fetchAll();
$projects_json = json_encode($projects_data);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_project_id  = $_POST['project_id'] ?? 0;
    $selected_proposal_id = $_POST['proposal_id'] ?? 0;
    $contract_details = trim($_POST['contract_details'] ?? '');
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;

    if (empty($selected_project_id) || empty($contract_details)) {
        $error = "Project and Contract Details are required.";
    } else {
        try {
            // Get client info from project
            $stmt = $pdo->prepare("SELECT client_id FROM projects WHERE id = ? AND user_id = ?");
            $stmt->execute([$selected_project_id, $user_id]);
            $proj_info = $stmt->fetch();

            $stmt = $pdo->prepare("
                INSERT INTO contracts (user_id, client_id, project_id, proposal_id, contract_details, start_date, end_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')
            ");
            $stmt->execute([
                $user_id,
                $proj_info['client_id'],
                $selected_project_id,
                $selected_proposal_id ?: null,
                $contract_details,
                $start_date,
                $end_date
            ]);

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
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Create Legal Contract</h2>
            </div>
        </div>

        <div class="dashboard-container" style="max-width: 1000px; margin: 0 auto;">
            <div class="animate-fade-in">
                <?php if ($error): ?>
                    <div style="background: #fef2f2; color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; border: 1px solid #fee2e2;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Target Project</label>
                                <?php if ($preselected_project): ?>
                                    <div style="padding: 14px 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; font-weight: 700; color: #4f46e5;">
                                        <i class="fas fa-folder"></i> <?php echo htmlspecialchars($preselected_project['project_title']); ?>
                                        <input type="hidden" name="project_id" value="<?php echo $preselected_project['id']; ?>">
                                        <input type="hidden" id="hidden_proposal_id" name="proposal_id" value="<?php echo $preselected_project['proposal_id']; ?>">
                                    </div>
                                <?php else: ?>
                                    <select id="project_selector" name="project_id" style="width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; background: white;" required>
                                        <option value="">-- Select Project --</option>
                                        <?php foreach ($projects_data as $p): ?>
                                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['project_title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" id="hidden_proposal_id" name="proposal_id" value="">
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #1e293b;">Contract Dates</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="date" id="start_date" name="start_date" value="<?php echo $preselected_project['start_date'] ?? ''; ?>" style="flex: 1; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px;">
                                    <input type="date" id="end_date" name="end_date" value="<?php echo $preselected_project['deadline'] ?? ''; ?>" style="flex: 1; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px;">
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom: 30px;">
                            <label style="display: block; font-weight: 700; margin-bottom: 15px; color: #1e293b; display: flex; justify-content: space-between;">
                                <span>Contract Terms & Conditions (Markdown Headers Supported)</span>
                                <span style="font-size: 0.75rem; color: #64748b; font-weight: normal;">(Use ### for section headings)</span>
                            </label>
                            <textarea id="contract_details" name="contract_details" rows="25" style="width: 100%; padding: 20px; border: 1px solid #e2e8f0; border-radius: 16px; background: #f8fafc; line-height: 1.6; outline: none; font-family: 'Courier New', Courier, monospace; font-size: 0.9rem;" placeholder="Enter legal terms..."><?php 
                            if ($preselected_project) {
                                $today = date('d F Y');
                                $start = $preselected_project['start_date'] ? date('d F Y', strtotime($preselected_project['start_date'])) : 'TBD';
                                $deadline = $preselected_project['deadline'] ? date('d F Y', strtotime($preselected_project['deadline'])) : 'TBD';
                                $budget = number_format($preselected_project['total_budget'], 2);
                                $currency = $preselected_project['currency'] ?? 'PKR';
                                $desc = $preselected_project['description'] ?: "Service Agreement for " . $preselected_project['project_title'];
                                
                                echo "This Service Agreement ('Agreement') is made and entered into as of {$today}, by and between the Freelancer and the Client.

### 1. PROJECT DETAILS
Project Title: {$preselected_project['project_title']}
{$desc}

Start Date: {$start}
End Date: {$deadline}

### 2. SCOPE OF WORK
The Freelancer agrees to provide the following services:
• Initial consultation and requirement gathering
• Execution of project tasks as per scope
• Delivery of high-quality deliverables including documentation

### 3. PAYMENT TERMS
Total Contract Value: {$currency} {$budget}

Payment Structure:
• 50% advance payment before project commencement
• 50% upon final approval before delivery of final files

### 4. TIMELINE
Delays in client feedback or core requirement changes may extend the delivery timeline accordingly.

### 5. INTELLECTUAL PROPERTY & OWNERSHIP
• Full ownership rights transfer to the Client after full payment is received.
• The Freelancer retains the right to showcase the work in professional portfolio for self-promotion.

### 6. CONFIDENTIALITY
Both parties agree to keep all proprietary information, trade secrets, and non-public data secure and confidential.

### 7. TERMINATION
Either party may terminate this Agreement with 7 days written notice. Payment for all work completed up to termination shall be due immediately.

### 8. ACCEPTANCE
By signing below, both parties acknowledge they have read, understood, and agreed to the terms outlined in this Agreement.";
                            }
                            ?></textarea>
                        </div>

                        <div style="display: flex; gap: 15px;">
                            <button type="submit" class="btn btn-primary" style="flex: 2; padding: 16px; border-radius: 16px; font-weight: 800; background: var(--gradient-primary); border: none;">
                                <i class="fas fa-file-contract"></i> Draft Contract Agreement
                            </button>
                            <a href="index.php" class="btn btn-outline" style="flex: 1; padding: 16px; border-radius: 16px; font-weight: 700; text-align: center;">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    const projects = <?php echo $projects_json; ?>;
    const projectSelector = document.getElementById('project_selector');
    const hiddenPropId = document.getElementById('hidden_proposal_id');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const textArea = document.getElementById('contract_details');

    if (projectSelector) {
        projectSelector.addEventListener('change', function() {
            const projectId = this.value;
            textArea.value = "";
            hiddenPropId.value = "";
            startDateInput.value = "";
            endDateInput.value = "";

            if (!projectId) return;

            const p = projects.find(item => item.id == projectId);
            if (p) {
                if (p.start_date) startDateInput.value = p.start_date;
                if (p.deadline) endDateInput.value = p.deadline;
                if (p.proposal_id) hiddenPropId.value = p.proposal_id;

                const today = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' });
                const budgetFormatted = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(p.total_budget);
                const startStr = p.start_date ? new Date(p.start_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' }) : 'TBD';
                const endStr = p.deadline ? new Date(p.deadline).toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' }) : 'TBD';
                const currency = p.currency || 'PKR';
                const desc = p.description || ("Service Agreement for " + p.project_title);

                textArea.value = `This Service Agreement ('Agreement') is made and entered into as of ${today}, by and between the Freelancer and the Client.

### 1. PROJECT DETAILS
Project Title: ${p.project_title}
${desc}

Start Date: ${startStr}
End Date: ${endStr}

### 2. SCOPE OF WORK
The Freelancer agrees to provide the following services:
• Initial consultation and requirement gathering
• Execution of project tasks as per scope
• Delivery of high-quality deliverables including documentation

### 3. PAYMENT TERMS
Total Contract Value: ${currency} ${budgetFormatted}

Payment Structure:
• 50% advance payment before project commencement
• 50% upon final approval before delivery of final files

### 4. TIMELINE
Delays in client feedback or core requirement changes may extend the delivery timeline accordingly.

### 5. INTELLECTUAL PROPERTY & OWNERSHIP
• Full ownership rights transfer to the Client after full payment is received.
• The Freelancer retains the right to showcase the work in professional portfolio for self-promotion.

### 6. CONFIDENTIALITY
Both parties agree to keep all proprietary information, trade secrets, and non-public data secure and confidential.

### 7. TERMINATION
Either party may terminate this Agreement with 7 days written notice. Payment for all work completed up to termination shall be due immediately.

### 8. ACCEPTANCE
By signing below, both parties acknowledge they have read, understood, and agreed to the terms outlined in this Agreement.`;
            }
        });
    }
</script>
