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

// Fetch client data
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$client = $stmt->fetch();

if (!$client) {
    header("Location: index.php");
    exit();
}

// Fetch client stats (Total Projects, Total Revenue)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE client_id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$total_projects = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(total_budget) FROM projects WHERE client_id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$total_value = $stmt->fetchColumn() ?: 0;

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800;">Client Profile</h2>
            </div>
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline" style="margin-right: 10px; font-size: 0.85rem; padding: 10px 20px; border-radius: 12px; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-arrow-left" style="font-size: 0.8rem;"></i> Back to Clients
                </a>
                <a href="edit.php?id=<?php echo $client['id']; ?>" class="btn btn-outline" style="margin-right: 10px; padding: 10px 20px; border-radius: 12px;">
                    <i class="far fa-edit"></i> Edit Details
                </a>
                <a href="../projects/add.php?client_id=<?php echo $client['id']; ?>" class="btn btn-primary" style="padding: 10px 20px; border-radius: 12px;">
                    <i class="fas fa-rocket"></i> Create Project
                </a>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="animate-fade-in" style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
                <!-- Left Column: Info Card -->
                <div>
                    <div class="glass-card" style="padding: 30px; text-align: center;">
                        <div style="width: 100px; height: 100px; border-radius: 30px; background: var(--gradient-primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; margin: 0 auto 20px;">
                            <?php echo strtoupper(substr($client['client_name'], 0, 1)); ?>
                        </div>
                        <h2 style="font-weight: 800; margin-bottom: 5px;"><?php echo htmlspecialchars($client['client_name']); ?></h2>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;"><?php echo htmlspecialchars($client['company_name']); ?></p>
                        
                        <span class="status-badge <?php echo $client['status'] == 'active' ? 'active' : 'on-hold'; ?>">
                            <?php echo ucfirst($client['status']); ?>
                        </span>

                        <div style="margin-top: 30px; text-align: left;">
                            <div class="info-row">
                                <i class="far fa-envelope"></i>
                                <span><?php echo htmlspecialchars($client['email']); ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($client['phone'] ?: 'No phone provided'); ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($client['address'] ?: 'No address provided'); ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-globe"></i>
                                <span><?php echo htmlspecialchars($client['country'] ?: 'Global'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Stats & Notes -->
                <div style="display: flex; flex-direction: column; gap: 30px;">
                    <!-- Mini Stats -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="glass-card vibrant-primary shadow-lg" style="padding: 24px; color: black;">
                            <h3 style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; margin-bottom: 5px;">Total Projects</h3>
                            <div style="font-size: 2rem; font-weight: 800;"><?php echo $total_projects; ?></div>
                        </div>
                        <div class="glass-card vibrant-success shadow-lg" style="padding: 24px; color: black;">
                            <h3 style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; margin-bottom: 5px;">Total Value</h3>
                            <div style="font-size: 2rem; font-weight: 800;">Rs. <?php echo number_format($total_value); ?></div>
                        </div>
                    </div>

                    <!-- Notes Section -->
                    <div class="glass-card" style="padding: 30px;">
                        <h3 style="font-weight: 700; margin-bottom: 20px;">Professional Notes</h3>
                        <div style="background: rgba(0,0,0,0.02); padding: 20px; border-radius: 12px; min-height: 100px; color: var(--text-main); line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($client['notes'] ?: 'No notes available for this client.')); ?>
                        </div>
                    </div>

                    <!-- Recent Activity Placeholder -->
                    <div class="glass-card" style="padding: 30px;">
                        <h3 style="font-weight: 700; margin-bottom: 20px;">Recent Projects</h3>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">Project history integration coming soon.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
    .info-row {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px 0;
        border-bottom: 1px solid rgba(0,0,0,0.03);
        font-size: 0.9rem;
        color: var(--text-main);
    }
    .info-row i {
        width: 20px;
        color: var(--primary-color);
        text-align: center;
    }
    .status-badge {
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-badge.active { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .status-badge.on-hold { background: rgba(100, 116, 139, 0.1); color: #64748b; }
</style>

<?php include_once '../includes/footer.php'; ?>
