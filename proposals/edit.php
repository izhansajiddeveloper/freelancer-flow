<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$proposal_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

if (!$proposal_id) {
    header("Location: index.php");
    exit();
}

// Fetch clients for the dropdown
$stmt = $pdo->prepare("SELECT id, client_name FROM clients WHERE user_id = ? ORDER BY client_name ASC");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll();

// Fetch current proposal data
$stmt = $pdo->prepare("SELECT * FROM proposals WHERE id = ? AND user_id = ?");
$stmt->execute([$proposal_id, $user_id]);
$proposal = $stmt->fetch();

if (!$proposal) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'] ?? '';
    $project_title = trim($_POST['project_title'] ?? '');
    $project_overview = trim($_POST['project_overview'] ?? '');
    $project_scope = trim($_POST['project_scope'] ?? '');
    $milestone_breakdown = trim($_POST['milestone_breakdown'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $timeline = trim($_POST['timeline'] ?? '');
    $terms = trim($_POST['terms'] ?? '');
    $payment_terms = trim($_POST['payment_terms'] ?? '');
    $status = $_POST['status'] ?? 'draft';

    if (empty($client_id) || empty($project_title) || $price <= 0) {
        $error = "Client, Project Title, and Price are required. Price must be greater than 0.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE proposals SET client_id = ?, project_title = ?, project_overview = ?, project_scope = ?, milestone_breakdown = ?, price = ?, timeline = ?, terms = ?, payment_terms = ?, status = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$client_id, $project_title, $project_overview, $project_scope, $milestone_breakdown, $price, $timeline, $terms, $payment_terms, $status, $proposal_id, $user_id]);
            
            header("Location: index.php?success=updated");
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
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Edit Proposal</h2>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in" style="max-width: 900px; margin: 0 auto;">
                <div class="glass-card" style="padding: 40px; border-radius: 24px;">
                    <?php if ($error): ?>
                        <div style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; font-size: 0.9rem; border: 1px solid rgba(239, 68, 68, 0.2);">
                            <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="proposal-form">
                        <!-- Step 1: Client & Basic Info -->
                        <div style="margin-bottom: 30px;">
                            <h3 style="font-weight: 700; color: #1e293b; margin-bottom: 20px;">Basic Information</h3>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Select Client</label>
                                    <select name="client_id" required style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600; color: #1e293b;">
                                        <?php foreach ($clients as $c): ?>
                                            <option value="<?php echo $c['id']; ?>" <?php echo ($proposal['client_id'] == $c['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($c['client_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Project Title (Max 100 chars)</label>
                                    <input type="text" name="project_title" value="<?php echo htmlspecialchars($proposal['project_title']); ?>" maxlength="100" required style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600;">
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Overview & Scope -->
                        <div style="margin-bottom: 30px;">
                            <h3 style="font-weight: 700; color: #1e293b; margin-bottom: 20px;">Project Roadmap</h3>
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Project Overview (Brief summary)</label>
                                <textarea name="project_overview" maxlength="1000" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 500; min-height: 80px;"><?php echo htmlspecialchars($proposal['project_overview'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Detailed Project Scope (Deliverables)</label>
                                <textarea name="project_scope" maxlength="3000" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 500; min-height: 120px;"><?php echo htmlspecialchars($proposal['project_scope']); ?></textarea>
                            </div>
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Milestone Breakdown & Pricing</label>
                                <textarea name="milestone_breakdown" maxlength="2000" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 500; min-height: 100px;"><?php echo htmlspecialchars($proposal['milestone_breakdown'] ?? ''); ?></textarea>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Price (PKR)</label>
                                    <input type="number" name="price" step="0.01" value="<?php echo $proposal['price']; ?>" required style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 700;">
                                </div>
                                <div class="form-group">
                                    <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Timeline (e.g. 4 Weeks)</label>
                                    <input type="text" name="timeline" value="<?php echo htmlspecialchars($proposal['timeline']); ?>" maxlength="50" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600;">
                                </div>
                                <div class="form-group">
                                    <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Status</label>
                                    <select name="status" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600;">
                                        <option value="draft" <?php echo ($proposal['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                                        <option value="sent" <?php echo ($proposal['status'] == 'sent') ? 'selected' : ''; ?>>Sent</option>
                                        <option value="accepted" <?php echo ($proposal['status'] == 'accepted') ? 'selected' : ''; ?>>Accepted</option>
                                        <option value="rejected" <?php echo ($proposal['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Terms & Payment -->
                        <div style="margin-bottom: 40px;">
                            <h3 style="font-weight: 700; color: #1e293b; margin-bottom: 20px;">Terms & Payment</h3>
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">Payment Schedule (Max 500 chars)</label>
                                <textarea name="payment_terms" maxlength="500" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 500; min-height: 80px;"><?php echo htmlspecialchars($proposal['payment_terms'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; color: #64748b;">General Terms & Conditions (Max 2000 chars)</label>
                                <textarea name="terms" maxlength="2000" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 500; min-height: 100px;"><?php echo htmlspecialchars($proposal['terms']); ?></textarea>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px;">
                            <button type="submit" class="btn btn-primary" style="flex: 2; padding: 15px; border-radius: 16px; font-weight: 700;">Update Proposal</button>
                            <a href="index.php" class="btn btn-outline" style="flex: 1; padding: 15px; border-radius: 16px; font-weight: 700; text-align: center;">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>



<script>
    // Character Counter Logic
    function updateCharCount(id, max) {
        const textarea = document.getElementById(id);
        if (!textarea) {
            // Try by name if ID is missing (common in these forms)
            const byName = document.getElementsByName(id)[0];
            if (!byName) return;
            setupCounter(byName, max);
            return;
        }
        setupCounter(textarea, max);
    }

    function setupCounter(textarea, max) {
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

        if (!textarea.dataset.counterSet) {
            textarea.addEventListener('input', () => setupCounter(textarea, max));
            textarea.dataset.counterSet = "true";
        }
    }

    window.addEventListener('load', () => {
        const fields = [
            { name: 'project_overview', max: 1000 },
            { name: 'project_scope', max: 3000 },
            { name: 'milestone_breakdown', max: 2000 },
            { name: 'payment_terms', max: 500 },
            { name: 'terms', max: 2000 }
        ];

        fields.forEach(field => {
            const el = document.getElementsByName(field.name)[0];
            if (el) setupCounter(el, field.max);
        });
    });
</script>
