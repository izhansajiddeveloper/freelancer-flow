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

// Fetch all clients for this user
$stmt = $pdo->prepare("SELECT * FROM clients WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll();

// Calculate Mini-Stats
$total_clients = count($clients);
$active_clients = 0;
foreach($clients as $c) if($c['status'] == 'active') $active_clients++;
$inactive_clients = $total_clients - $active_clients;

$hide_navbar = true;
include_once '../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <h2 style="font-weight: 800; letter-spacing: -0.5px;">Client Management</h2>
            </div>
            <div class="topbar-actions">
                <a href="add.php" class="btn btn-primary" style="border-radius: 12px; padding: 12px 24px; font-weight: 700;">
                    <i class="fas fa-plus-circle" style="margin-right: 8px;"></i> Add Client
                </a>
            </div>
        </div>

        <div class="dashboard-container">
            <!-- Client Stats Overview -->
            <div class="animate-fade-in" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="glass-card stat-mini-card">
                    <div class="mini-icon" style="background: rgba(79, 70, 229, 0.1); color: var(--primary-color);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="mini-label">Total Clients</div>
                        <div class="mini-value"><?php echo $total_clients; ?></div>
                    </div>
                </div>
                <div class="glass-card stat-mini-card">
                    <div class="mini-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div>
                        <div class="mini-label">Active Users</div>
                        <div class="mini-value"><?php echo $active_clients; ?></div>
                    </div>
                </div>
                <div class="glass-card stat-mini-card">
                    <div class="mini-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div>
                        <div class="mini-label">Inactive/On Hold</div>
                        <div class="mini-value"><?php echo $inactive_clients; ?></div>
                    </div>
                </div>
            </div>

            <!-- search and filter row -->
            <div class="animate-fade-in" style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; gap: 20px; animation-delay: 0.1s;">
                <div style="position: relative; flex: 1; max-width: 400px;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="clientSearch" placeholder="Search by name, email or company..." style="width: 100%; padding: 12px 15px 12px 45px; border-radius: 12px; border: 1px solid var(--border-color); background: white; font-size: 0.9rem;">
                </div>
                <div style="display: flex; gap: 10px;">
                    <select id="statusFilter" style="padding: 12px 15px; border-radius: 12px; border: 1px solid var(--border-color); background: white; font-size: 0.9rem; color: var(--text-main); font-weight: 600;">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="animate-fade-in" style="animation-delay: 0.2s;">
                <div class="glass-card" style="padding: 0; overflow: visible; border-radius: 20px;">
                    <div style="overflow-x: auto;">
                        <table class="dashboard-table premium-table">
                            <thead>
                                <tr>
                                    <th style="padding-left: 30px;">Client Info</th>
                                    <th>Company</th>
                                    <th>Contact Details</th>
                                    <th>Status</th>
                                    <th style="text-align: right; padding-right: 30px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="clientTableBody">
                                <?php if (empty($clients)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 80px 30px;">
                                            <div style="width: 80px; height: 80px; background: var(--light-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: var(--text-muted); font-size: 2rem;">
                                                <i class="fas fa-user-plus"></i>
                                            </div>
                                            <h3 style="font-weight: 700; color: var(--text-main);">No Clients Yet</h3>
                                            <p style="color: var(--text-muted); margin-bottom: 25px;">Start building your professional network by adding your first client.</p>
                                            <a href="add.php" class="btn btn-primary" style="padding: 12px 30px;">Get Started</a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($clients as $client): ?>
                                        <tr class="client-row" data-name="<?php echo strtolower($client['client_name']); ?>" data-email="<?php echo strtolower($client['email']); ?>" data-company="<?php echo strtolower($client['company_name']); ?>" data-status="<?php echo $client['status']; ?>">
                                            <td style="padding-left: 30px;">
                                                <div style="display: flex; align-items: center; gap: 15px;">
                                                    <?php 
                                                        $colors = ['#4f46e5', '#10b981', '#f59e0b', '#3b82f6', '#ec4899', '#8b5cf6'];
                                                        $idx = abs(crc32($client['client_name'])) % count($colors);
                                                        $avatar_color = $colors[$idx];
                                                    ?>
                                                    <div class="client-avatar" style="background: <?php echo $avatar_color; ?>15; color: <?php echo $avatar_color; ?>;">
                                                        <?php echo strtoupper(substr($client['client_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="client-name-cell"><?php echo htmlspecialchars($client['client_name']); ?></div>
                                                        <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 500;">Joined <?php echo date('M Y', strtotime($client['created_at'])); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="company-tag">
                                                    <i class="fas fa-building" style="font-size: 0.75rem; margin-right: 6px; opacity: 0.6;"></i>
                                                    <?php echo htmlspecialchars($client['company_name'] ?: 'Independent'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.85rem; margin-bottom: 4px;">
                                                    <a href="mailto:<?php echo $client['email']; ?>" style="color: var(--text-main); text-decoration: none;">
                                                        <i class="far fa-envelope" style="width: 18px; color: var(--primary-color); opacity: 0.7;"></i> 
                                                        <?php echo htmlspecialchars($client['email']); ?>
                                                    </a>
                                                </div>
                                                <?php if($client['phone']): ?>
                                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                                    <i class="fas fa-phone-alt" style="width: 18px; color: var(--text-muted); opacity: 0.5;"></i> 
                                                    <?php echo htmlspecialchars($client['phone']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $status_class = $client['status'] == 'active' ? 'status-active' : 'status-on-hold';
                                                    $status_icon = $client['status'] == 'active' ? 'fa-check-circle' : 'fa-clock';
                                                ?>
                                                <span class="premium-status <?php echo $status_class; ?>">
                                                    <i class="fas <?php echo $status_icon; ?>" style="margin-right: 5px; font-size: 0.7rem;"></i>
                                                    <?php echo ucfirst($client['status']); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right; padding-right: 30px;">
                                                <div class="action-flex">
                                                    <a href="view.php?id=<?php echo $client['id']; ?>" class="p-action-btn view" title="View Profile">
                                                        <i class="far fa-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $client['id']; ?>" class="p-action-btn edit" title="Edit Client">
                                                        <i class="far fa-edit"></i>
                                                    </a>
                                                    <button onclick="confirmDelete(<?php echo $client['id']; ?>)" class="p-action-btn delete" title="Remove Client">
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
    /* Premium Stats Cards */
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

    /* Avatar Style */
    .client-avatar {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.1rem;
    }

    /* Table Enhancements */
    .premium-table { 
        border-collapse: separate; 
        border-spacing: 0; 
        width: 100%;
        margin-top: 10px;
    }
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
    .client-row { 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }
    .client-row:hover { 
        background: #fbfcfe;
        box-shadow: inset 4px 0 0 var(--primary-color);
    }
    .client-row td { 
        padding: 20px 24px; 
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .client-row:last-child td { border-bottom: none; }
    
    .client-name-cell { 
        font-weight: 700; 
        color: #1e293b; 
        font-size: 1rem; 
        margin-bottom: 3px;
        transition: color 0.2s;
    }
    .client-row:hover .client-name-cell { color: var(--primary-color); }
    
    .company-tag {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        background: #f1f5f9;
        border-radius: 10px;
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        border: 1px solid transparent;
        transition: all 0.2s;
    }
    .client-row:hover .company-tag { background: white; border-color: #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }

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
    .p-action-btn i { font-size: 0.95rem; }
    .p-action-btn:hover { 
        background: white; 
        color: var(--primary-color); 
        border-color: var(--primary-color); 
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.12);
    }
    .p-action-btn.view:hover { color: #4f46e5; border-color: #4f46e5; }
    .p-action-btn.edit:hover { color: #f59e0b; border-color: #f59e0b; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.12); }
    .p-action-btn.delete:hover { color: #ef4444; border-color: #ef4444; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.12); }
</style>

<script>
    // Live Search Logic
    const searchInput = document.getElementById('clientSearch');
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('.client-row');

    function filterTable() {
        const query = searchInput.value.toLowerCase();
        const status = statusFilter.value;

        tableRows.forEach(row => {
            const name = row.dataset.name;
            const email = row.dataset.email;
            const company = row.dataset.company;
            const rowStatus = row.dataset.status;

            const matchesQuery = name.includes(query) || email.includes(query) || company.includes(query);
            const matchesStatus = status === 'all' || rowStatus === status;

            if (matchesQuery && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', filterTable);
    statusFilter.addEventListener('change', filterTable);

    function confirmDelete(id) {
        if (confirm('Are you sure you want to permanently remove this client? All associated data will be archived.')) {
            window.location.href = 'delete.php?id=' + id;
        }
    }
</script>

<?php include_once '../includes/footer.php'; ?>
