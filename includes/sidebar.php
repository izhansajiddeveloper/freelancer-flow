<?php
/**
 * Fixed Admin-style Sidebar for FreelanceFlow
 * Collapsible with Submenus and Stats
 */

if (!isLoggedIn()) return;

$user_id = getCurrentUserId();
$current_page = basename($_SERVER['PHP_SELF']);

// Session data
$user_name = $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Freelancer';
$user_email = $_SESSION['user_email'] ?? 'hello@freelanceflow.ai';
$user_role = $_SESSION['user_role'] ?? 'Pro Freelancer';

// Fetch counts for badges using PDO
$active_projects_count = 0;
$pending_invoices_count = 0;
$total_clients_count = 0;

if (isset($pdo)) {
    // Active projects count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $active_projects_count = $stmt->fetchColumn();

    // Pending invoices (placeholder for actual invoice table)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$user_id]);
        $pending_invoices_count = $stmt->fetchColumn();
    } catch (Exception $e) { $pending_invoices_count = 0; }

    // Total clients count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_clients_count = $stmt->fetchColumn();

    // Pending milestones count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM milestones WHERE user_id = ? AND status != 'completed'");
    $stmt->execute([$user_id]);
    $pending_milestones_count = $stmt->fetchColumn();
}

// Check if sidebar is collapsed (via cookie)
$sidebar_collapsed = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] == 'true';
?>

<!-- Fixed Professional Sidebar -->
<aside class="admin-sidebar <?php echo $sidebar_collapsed ? 'collapsed' : ''; ?>" id="adminSidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-icon">
                <i class="fas fa-rocket"></i>
            </div>
            <span class="logo-text">FreelanceFlow</span>
        </div>
        <button class="sidebar-collapse-btn" id="sidebarCollapseBtn">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <!-- User Profile Section -->
    <div class="sidebar-profile">
        <div class="profile-avatar-wrapper">
            <div class="profile-avatar">
                <?php
                $initials = '';
                $name_parts = explode(' ', $user_name);
                foreach ($name_parts as $part) { if(!empty($part)) $initials .= strtoupper(substr($part, 0, 1)); }
                echo substr($initials, 0, 2);
                ?>
            </div>
            <span class="online-indicator"></span>
        </div>
        <div class="profile-info">
            <h4><?php echo htmlspecialchars($user_name); ?></h4>
            <p><?php echo htmlspecialchars($user_email); ?></p>
            <span class="role-badge">
                <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($user_role); ?>
            </span>
        </div>
    </div>

    <!-- Quick Stats Mini Cards -->
    <div class="sidebar-stats">
        <div class="stat-item">
            <span class="stat-value"><?php echo $active_projects_count; ?></span>
            <span class="stat-label">Active</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <span class="stat-value"><?php echo $total_clients_count; ?></span>
            <span class="stat-label">Clients</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <span class="stat-value"><?php echo $pending_invoices_count; ?></span>
            <span class="stat-label">Due</span>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <!-- Main Section -->
        <div class="nav-section">
            <h5 class="nav-section-title">Main Dashboard</h5>
            <ul class="nav-menu">
                <li class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>dashboard/index.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                        <span class="nav-badge live">Live</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Management Section -->
        <div class="nav-section">
            <h5 class="nav-section-title">Core Management</h5>
            <ul class="nav-menu">
                <!-- Clients -->
                <li class="nav-item has-submenu <?php echo (strpos($_SERVER['PHP_SELF'], 'clients/') !== false) ? 'expanded active' : ''; ?>">
                    <a href="#" class="nav-link submenu-toggle">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Clients</span>
                        <i class="fas fa-chevron-right submenu-arrow"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="<?php echo BASE_URL; ?>clients/index.php"><i class="fas fa-list"></i> Directory</a></li>
                        <li><a href="<?php echo BASE_URL; ?>clients/add.php"><i class="fas fa-user-plus"></i> New Client</a></li>
                    </ul>
                </li>

                <!-- Projects -->
                <li class="nav-item has-submenu <?php echo (strpos($_SERVER['PHP_SELF'], 'projects/') !== false) ? 'expanded active' : ''; ?>">
                    <a href="#" class="nav-link submenu-toggle">
                        <i class="fas fa-project-diagram"></i>
                        <span class="nav-text">Projects</span>
                        <?php if ($active_projects_count > 0): ?>
                            <span class="nav-badge info"><?php echo $active_projects_count; ?></span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-right submenu-arrow"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="<?php echo BASE_URL; ?>projects/index.php"><i class="fas fa-folder-open"></i> Portfolio</a></li>
                        <li><a href="<?php echo BASE_URL; ?>projects/add.php"><i class="fas fa-plus-circle"></i> Launch New</a></li>
                    </ul>
                </li>

                <!-- Proposals -->
                <li class="nav-item has-submenu <?php echo (strpos($_SERVER['PHP_SELF'], 'proposals/') !== false) ? 'expanded active' : ''; ?>">
                    <a href="#" class="nav-link submenu-toggle">
                        <i class="fas fa-file-signature"></i>
                        <span class="nav-text">Proposals</span>
                        <i class="fas fa-chevron-right submenu-arrow"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="<?php echo BASE_URL; ?>proposals/index.php"><i class="fas fa-list-alt"></i> View All</a></li>
                        <li><a href="<?php echo BASE_URL; ?>proposals/create.php"><i class="fas fa-pen-nib"></i> Create New</a></li>
                    </ul>
                </li>

                <!-- Contracts -->
                <li class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'contracts') !== false) ? 'active' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="fas fa-file-contract"></i>
                        <span class="nav-text">Contracts</span>
                    </a>
                </li>

                <!-- Invoices -->
                <li class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'invoices') !== false) ? 'active' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span class="nav-text">Invoices</span>
                        <?php if ($pending_invoices_count > 0): ?>
                            <span class="nav-badge warning">Due</span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Payments -->
                <li class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'payments') !== false) ? 'active' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="fas fa-credit-card"></i>
                        <span class="nav-text">Payments</span>
                    </a>
                </li>

                <!-- Reminders -->
                <li class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'reminders') !== false) ? 'active' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="fas fa-calendar-check"></i>
                        <span class="nav-text">Reminders</span>
                    </a>
                </li>

                <!-- Notifications -->
                <li class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'notifications') !== false) ? 'active' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="fas fa-bell"></i>
                        <span class="nav-text">Notifications</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- System Status Footer -->
        <div class="sidebar-footer">
            <div class="system-status">
                <div class="status-item">
                    <span class="status-dot active"></span>
                    <span class="status-text">Cloud Sync Active</span>
                </div>
            </div>

            <div class="sidebar-actions">
                <a href="<?php echo BASE_URL; ?>auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sign Out</span>
                </a>
            </div>
        </div>
    </nav>
</aside>

<style>
    /* ===== FIXED ADMIN SIDEBAR STYLES ===== */
    :root {
        --sidebar-width: 280px;
        --sidebar-collapsed-width: 80px;
        --sidebar-bg: #FFFFFF;
        --sidebar-hover: #F3F4F6;
        --sidebar-active: #EEF2FF;
        --sidebar-border: #E5E7EB;
        --sidebar-text: #1E293B;
        --sidebar-text-light: #64748B;
        --sidebar-primary: #4F46E5;
    }

    /* Fixed Sidebar Layout */
    .admin-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--sidebar-bg);
        border-right: 1px solid var(--sidebar-border);
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        overflow-x: hidden;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1000;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.02);
    }

    /* Collapsed State */
    .admin-sidebar.collapsed { width: var(--sidebar-collapsed-width); }

    .admin-sidebar.collapsed .logo-text,
    .admin-sidebar.collapsed .profile-info,
    .admin-sidebar.collapsed .nav-text,
    .admin-sidebar.collapsed .nav-badge,
    .admin-sidebar.collapsed .submenu-arrow,
    .admin-sidebar.collapsed .status-text,
    .admin-sidebar.collapsed .logout-btn span,
    .admin-sidebar.collapsed .stat-label,
    .admin-sidebar.collapsed .role-badge,
    .admin-sidebar.collapsed .nav-section-title {
        display: none;
    }

    .admin-sidebar.collapsed .nav-link { justify-content: center; padding: 15px 0; }
    .admin-sidebar.collapsed .nav-link i { margin-right: 0; font-size: 1.3rem; }
    .admin-sidebar.collapsed .profile-avatar-wrapper { margin: 0 auto; }
    .admin-sidebar.collapsed .sidebar-stats { flex-direction: column; padding: 10px; }
    .admin-sidebar.collapsed .stat-item { padding: 8px 0; }
    .admin-sidebar.collapsed .stat-divider { display: none; }

    /* Sidebar Header */
    .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px;
        border-bottom: 1px solid var(--sidebar-border);
    }

    .sidebar-logo { display: flex; align-items: center; gap: 12px; }
    .logo-icon {
        width: 32px;
        height: 32px;
        background: var(--gradient-primary);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
    }

    .logo-text { font-weight: 800; font-size: 1.2rem; color: #1e293b; letter-spacing: -0.5px; }

    .sidebar-collapse-btn {
        width: 30px; height: 30px; border-radius: 8px;
        background: #f8fafc; border: 1px solid #e2e8f0;
        color: #64748b; display: flex; align-items: center;
        justify-content: center; cursor: pointer; transition: all 0.2s;
    }
    .sidebar-collapse-btn:hover { background: var(--sidebar-primary); color: white; border-color: var(--sidebar-primary); }
    .admin-sidebar.collapsed .sidebar-collapse-btn i { transform: rotate(180deg); }

    /* Profile Section */
    .sidebar-profile { display: flex; align-items: center; gap: 16px; padding: 24px; border-bottom: 1px solid var(--sidebar-border); }
    .profile-avatar-wrapper { position: relative; }
    .profile-avatar {
        width: 44px; height: 44px; background: var(--gradient-primary);
        border-radius: 14px; display: flex; align-items: center;
        justify-content: center; color: white; font-weight: 800;
        font-size: 1.1rem; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    }
    .online-indicator {
        position: absolute; bottom: -2px; right: -2px; width: 14px; height: 14px;
        background: #10B981; border: 2.5px solid white; border-radius: 50%;
    }
    .profile-info h4 { font-weight: 700; color: #1e293b; margin-bottom: 2px; font-size: 0.95rem; }
    .profile-info p { font-size: 0.75rem; color: #64748b; margin-bottom: 8px; }
    .role-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: #eef2ff; color: #4f46e5; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }

    /* Stats Row */
    .sidebar-stats {
        display: flex; align-items: center; justify-content: space-around;
        padding: 15px; background: #f8fafc; margin: 15px 20px;
        border-radius: 16px; border: 1px solid #e2e8f0;
    }
    .stat-value { display: block; font-size: 1.3rem; font-weight: 800; color: var(--sidebar-primary); line-height: 1.2; }
    .stat-label { font-size: 0.65rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
    .stat-divider { width: 1px; height: 25px; background: #e2e8f0; }

    /* Navigation */
    .sidebar-nav { flex: 1; padding: 10px 15px; }
    .nav-section { margin-bottom: 24px; }
    .nav-section-title { padding: 0 15px; margin-bottom: 10px; font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
    .nav-menu { list-style: none; padding: 0; margin: 0; }
    .nav-item { margin-bottom: 4px; border-radius: 12px; }
    .nav-link { display: flex; align-items: center; padding: 12px 15px; color: #475569; text-decoration: none; border-radius: 10px; transition: all 0.2s; }
    .nav-link:hover { background: #f1f5f9; color: var(--sidebar-primary); }
    .nav-item.active > .nav-link { background: var(--sidebar-active); color: var(--sidebar-primary); font-weight: 700; }
    .nav-link i { width: 24px; font-size: 1.1rem; margin-right: 12px; color: #64748b; }
    .nav-item.active .nav-link i { color: var(--sidebar-primary); }
    .nav-text { flex: 1; font-size: 0.9rem; }
    .nav-badge { padding: 3px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 700; color: white; margin-left: 8px; }
    .nav-badge.live { background: #ef4444; animation: pulse-red 2s infinite; }
    .nav-badge.warning { background: #f59e0b; }
    .nav-badge.info { background: #3b82f6; }
    @keyframes pulse-red { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }
    .submenu-arrow { margin-left: auto; font-size: 0.75rem; transition: transform 0.3s ease; }
    .nav-item.expanded .submenu-arrow { transform: rotate(90deg); }

    /* Submenu */
    .submenu { list-style: none; padding: 0; margin: 5px 0 0 0; max-height: 0; overflow: hidden; transition: max-height 0.3s ease; background: #f8fafc; border-radius: 10px; }
    .nav-item.expanded .submenu { max-height: 400px; }
    .submenu li a { display: flex; align-items: center; padding: 10px 15px 10px 50px; color: #64748b; text-decoration: none; font-size: 0.85rem; font-weight: 600; }
    .submenu li a:hover { background: #eef2ff; color: var(--sidebar-primary); }
    .submenu li a i { margin-right: 12px; font-size: 0.8rem; width: 18px; }

    /* Footer */
    .sidebar-footer { padding: 20px; border-top: 1px solid var(--sidebar-border); }
    .status-item { display: flex; align-items: center; gap: 10px; font-size: 0.8rem; color: #64748b; margin-bottom: 15px; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; }
    .status-dot.active { background: #10B981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.4); }
    .logout-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; background: #fee2e2; color: #dc2626; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 0.85rem; transition: 0.2s; }
    .logout-btn:hover { background: #fecaca; transform: translateY(-2px); }

    /* Scrollbar */
    .admin-sidebar::-webkit-scrollbar { width: 5px; }
    .admin-sidebar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('adminSidebar');
        const collapseBtn = document.getElementById('sidebarCollapseBtn');
        const mainContent = document.querySelector('.main-content');

        // Toggle Expand/Collapse
        if (collapseBtn && sidebar) {
            collapseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('collapsed');
                
                // Save state to cookie
                const isCollapsed = sidebar.classList.contains('collapsed');
                document.cookie = "sidebar_collapsed=" + isCollapsed + "; path=/; max-age=" + (365 * 24 * 60 * 60);
                
                // Update main content margin
                if (mainContent) {
                    mainContent.style.marginLeft = isCollapsed ? '80px' : '280px';
                }
            });
        }

        // Submenu Toggles
        document.querySelectorAll('.submenu-toggle').forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const parent = this.closest('.nav-item');
                parent.classList.toggle('expanded');
            });
        });

        // Initialize Margin
        const updateMargin = () => {
            const isCollapsed = sidebar.classList.contains('collapsed');
            if (mainContent) {
                mainContent.style.marginLeft = isCollapsed ? '80px' : '280px';
            }
        };
        updateMargin();
    });
</script>
