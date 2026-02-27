<?php

/**
 * Premium Animated Sidebar for FreelanceFlow
 * Light background with dark text - Professional & Clean
 */

if (!isLoggedIn()) return;

$user_id = getCurrentUserId();
$current_page = basename($_SERVER['PHP_SELF']);
$current_path = $_SERVER['PHP_SELF'];

// Check if sidebar is collapsed (via cookie)
$sidebar_collapsed = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] == 'true';

// Fetch Quick Stats for Sidebar
$sidebar_active_projects = 0;
$sidebar_pending_invoices = 0;
$sidebar_paid_this_month = 0;

if (isset($pdo) && isLoggedIn()) {
    try {
        // 1. Active Projects
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ? AND status IN ('active', 'in_progress')");
        $stmt->execute([$user_id]);
        $sidebar_active_projects = (int)$stmt->fetchColumn();

        // 2. Pending Invoices
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$user_id]);
        $sidebar_pending_invoices = (int)$stmt->fetchColumn();

        // 3. Paid This Month (Invoices)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? AND status = 'paid' AND MONTH(paid_date) = MONTH(CURRENT_DATE()) AND YEAR(paid_date) = YEAR(CURRENT_DATE())");
        $stmt->execute([$user_id]);
        $sidebar_paid_this_month = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Sidebar Stats Error: " . $e->getMessage());
    }
}

/**
 * Check if a menu item is active
 */
function isActive($path, $exact = false)
{
    global $current_path;
    if ($exact) {
        return strpos($current_path, $path) !== false && basename($current_path) == 'index.php';
    }
    return strpos($current_path, $path) !== false;
}
?>

<!-- Professional Light Sidebar -->
<aside class="professional-sidebar <?php echo $sidebar_collapsed ? 'collapsed' : ''; ?>" id="professionalSidebar">

    <!-- Light Background with Subtle Pattern -->
    <div class="sidebar-backdrop">
        <div class="backdrop-gradient gradient-1"></div>
        <div class="backdrop-gradient gradient-2"></div>
        <div class="backdrop-pattern"></div>
    </div>

    <!-- Sidebar Content (Scrolls with page) -->
    <div class="sidebar-content">

        <!-- Premium Header -->
        <div class="sidebar-header">
            <div class="brand-container">
                <div class="brand-icon">
                    <i class="fas fa-rocket"></i>
                    <div class="icon-glow"></div>
                </div>
                <div class="brand-text">
                    <span class="brand-name">FreelanceFlow</span>
                    <span class="brand-badge">PRO</span>
                </div>
            </div>
            <button class="collapse-trigger" id="collapseTrigger">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Quick Stats Bar (Minimal) -->
        <div class="quick-stats">
            <div class="stat-item">
                <span class="stat-value"><?php echo $sidebar_active_projects; ?></span>
                <span class="stat-label">Active</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
                <span class="stat-value"><?php echo $sidebar_pending_invoices; ?></span>
                <span class="stat-label">Pending</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
                <span class="stat-value"><?php echo $sidebar_paid_this_month; ?></span>
                <span class="stat-label">This Month</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="sidebar-nav">

            <!-- Dashboard -->
            <div class="nav-group">
                <a href="<?php echo BASE_URL; ?>dashboard/index.php" class="nav-link <?php echo isActive('/dashboard/') ? 'active' : ''; ?>" data-tooltip="Dashboard">
                    <div class="nav-icon">
                        <i class="fas fa-th-large"></i>
                    </div>
                    <span class="nav-label">Dashboard</span>
                    <?php if (isActive('/dashboard/')): ?>
                        <span class="active-indicator"></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- CRM Section -->
            <div class="nav-section">
                <div class="section-header">
                    <span class="section-title">Client Management</span>
                    <span class="section-line"></span>
                </div>

                <!-- Clients Dropdown -->
                <div class="dropdown-group <?php echo isActive('/clients/') ? 'expanded' : ''; ?>">
                    <button class="dropdown-trigger" onclick="toggleDropdown('clientsDropdown')">
                        <div class="trigger-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="trigger-label">Clients</span>
                        <i class="fas fa-chevron-right dropdown-arrow" id="clientsArrow"></i>
                        <?php if (isActive('/clients/')): ?>
                            <span class="active-dot"></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu" id="clientsDropdown">
                        <a href="<?php echo BASE_URL; ?>clients/index.php" class="dropdown-item <?php echo (isActive('/clients/index.php', true)) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>All Clients</span>
                            <?php if (isActive('/clients/index.php', true)): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>clients/add.php" class="dropdown-item <?php echo (isActive('/clients/add.php')) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>Add New Client</span>
                            <?php if (isActive('/clients/add.php')): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>

                <!-- Projects Dropdown -->
                <div class="dropdown-group <?php echo isActive('/projects/') || isActive('/milestones/') ? 'expanded' : ''; ?>">
                    <button class="dropdown-trigger" onclick="toggleDropdown('projectsDropdown')">
                        <div class="trigger-icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <span class="trigger-label">Projects</span>
                        <i class="fas fa-chevron-right dropdown-arrow" id="projectsArrow"></i>
                        <?php if (isActive('/projects/')): ?>
                            <span class="active-dot"></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu" id="projectsDropdown">
                        <a href="<?php echo BASE_URL; ?>projects/index.php" class="dropdown-item <?php echo (isActive('/projects/index.php', true)) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>All Projects</span>
                            <?php if (isActive('/projects/index.php', true)): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>projects/add.php" class="dropdown-item <?php echo (isActive('/projects/add.php')) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>New Project</span>
                            <?php if (isActive('/projects/add.php')): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>milestones/index.php" class="dropdown-item <?php echo (isActive('/milestones/')) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>Milestones</span>
                            <?php if (isActive('/milestones/')): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>

                <!-- Proposals Dropdown -->
                <div class="dropdown-group <?php echo isActive('/proposals/') ? 'expanded' : ''; ?>">
                    <button class="dropdown-trigger" onclick="toggleDropdown('proposalsDropdown')">
                        <div class="trigger-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <span class="trigger-label">Proposals</span>
                        <i class="fas fa-chevron-right dropdown-arrow" id="proposalsArrow"></i>
                        <?php if (isActive('/proposals/')): ?>
                            <span class="active-dot"></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu" id="proposalsDropdown">
                        <a href="<?php echo BASE_URL; ?>proposals/index.php" class="dropdown-item <?php echo (isActive('/proposals/index.php', true)) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>All Proposals</span>
                            <?php if (isActive('/proposals/index.php', true)): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>proposals/create.php" class="dropdown-item <?php echo (isActive('/proposals/create.php')) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>Create Proposal</span>
                            <?php if (isActive('/proposals/create.php')): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>

                <!-- Contracts Dropdown -->
                <div class="dropdown-group <?php echo isActive('/contracts/') ? 'expanded' : ''; ?>">
                    <button class="dropdown-trigger" onclick="toggleDropdown('contractsDropdown')">
                        <div class="trigger-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <span class="trigger-label">Contracts</span>
                        <i class="fas fa-chevron-right dropdown-arrow" id="contractsArrow"></i>
                        <?php if (isActive('/contracts/')): ?>
                            <span class="active-dot"></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu" id="contractsDropdown">
                        <a href="<?php echo BASE_URL; ?>contracts/index.php" class="dropdown-item <?php echo (isActive('/contracts/index.php', true)) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>All Contracts</span>
                            <?php if (isActive('/contracts/index.php', true)): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>contracts/create.php" class="dropdown-item <?php echo (isActive('/contracts/create.php')) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>New Contract</span>
                            <?php if (isActive('/contracts/create.php')): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Finance Section -->
            <div class="nav-section">
                <div class="section-header">
                    <span class="section-title">Financial</span>
                    <span class="section-line"></span>
                </div>

                <!-- Invoices Dropdown -->
                <div class="dropdown-group <?php echo isActive('/invoices/') ? 'expanded' : ''; ?>">
                    <button class="dropdown-trigger" onclick="toggleDropdown('invoicesDropdown')">
                        <div class="trigger-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <span class="trigger-label">Invoices</span>
                        <i class="fas fa-chevron-right dropdown-arrow" id="invoicesArrow"></i>
                        <?php if (isActive('/invoices/')): ?>
                            <span class="active-dot"></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu" id="invoicesDropdown">
                        <a href="<?php echo BASE_URL; ?>invoices/index.php" class="dropdown-item <?php echo (isActive('/invoices/index.php', true)) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>All Invoices</span>
                            <?php if (isActive('/invoices/index.php', true)): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>invoices/create.php" class="dropdown-item <?php echo (isActive('/invoices/create.php')) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>Create Invoice</span>
                            <?php if (isActive('/invoices/create.php')): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>

                <!-- Payments Dropdown -->
                <div class="dropdown-group <?php echo isActive('/payments/') ? 'expanded' : ''; ?>">
                    <button class="dropdown-trigger" onclick="toggleDropdown('paymentsDropdown')">
                        <div class="trigger-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <span class="trigger-label">Payments</span>
                        <i class="fas fa-chevron-right dropdown-arrow" id="paymentsArrow"></i>
                        <?php if (isActive('/payments/')): ?>
                            <span class="active-dot"></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu" id="paymentsDropdown">
                        <a href="<?php echo BASE_URL; ?>payments/index.php" class="dropdown-item <?php echo (isActive('/payments/index.php', true)) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>Payment History</span>
                            <?php if (isActive('/payments/index.php', true)): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>payments/create.php" class="dropdown-item <?php echo (isActive('/payments/create.php')) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>Record Payment</span>
                            <?php if (isActive('/payments/create.php')): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- System Section -->
            <div class="nav-section">
                <div class="section-header">
                    <span class="section-title">System</span>
                    <span class="section-line"></span>
                </div>

                <!-- Reminders Dropdown -->
                <div class="dropdown-group <?php echo isActive('/reminders/') ? 'expanded' : ''; ?>">
                    <button class="dropdown-trigger" onclick="toggleDropdown('remindersDropdown')">
                        <div class="trigger-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <span class="trigger-label">Reminders</span>
                        <i class="fas fa-chevron-right dropdown-arrow" id="remindersArrow"></i>
                        <?php if (isActive('/reminders/')): ?>
                            <span class="active-dot"></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu" id="remindersDropdown">
                        <a href="<?php echo BASE_URL; ?>reminders/index.php" class="dropdown-item <?php echo (isActive('/reminders/index.php', true)) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>All Reminders</span>
                            <?php if (isActive('/reminders/index.php', true)): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>reminders/create.php" class="dropdown-item <?php echo (isActive('/reminders/create.php')) ? 'active' : ''; ?>">
                            <i class="fas fa-circle"></i>
                            <span>Set Reminder</span>
                            <?php if (isActive('/reminders/create.php')): ?>
                                <span class="check-indicator">✓</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>

           

                <div class="nav-group logout">
                    <a href="<?php echo BASE_URL; ?>auth/logout.php" class="nav-link logout-link">
                        <div class="nav-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <span class="nav-label">Logout</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>
</aside>

<style>
    /* ===== PROFESSIONAL LIGHT SIDEBAR STYLES ===== */
    :root {
        --sidebar-width: 280px;
        --sidebar-collapsed: 85px;
        --primary: #4361ee;
        --primary-light: #4895ef;
        --primary-soft: #e2eafc;
        --secondary: #3f37c9;
        --accent: #f72585;
        --bg-light: #f8fafd;
        --bg-card: #ffffff;
        --text-dark: #1e293b;
        --text-medium: #475569;
        --text-light: #64748b;
        --text-muted: #94a3b8;
        --border-light: #e9edf4;
        --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.02);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.03);
        --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.03);
        --gradient-primary: linear-gradient(135deg, #4361ee, #3a0ca3);
        --gradient-soft: linear-gradient(135deg, #f8fafd, #e2eafc);
    }

    /* Main Sidebar Container */
    .professional-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--bg-light);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1000;
        overflow: visible;
        border-right: 1px solid var(--border-light);
    }

    /* Light Background with Subtle Pattern */
    .sidebar-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        pointer-events: none;
        z-index: 0;
    }

    .backdrop-gradient {
        position: absolute;
        border-radius: 50%;
        filter: blur(60px);
        opacity: 0.3;
    }

    .gradient-1 {
        width: 400px;
        height: 400px;
        background: var(--primary-light);
        top: -200px;
        right: -200px;
        opacity: 0.1;
    }

    .gradient-2 {
        width: 350px;
        height: 350px;
        background: var(--accent);
        bottom: -150px;
        left: -150px;
        opacity: 0.05;
    }

    .backdrop-pattern {
        position: absolute;
        width: 100%;
        height: 100%;
        background-image:
            radial-gradient(circle at 30px 30px, var(--primary) 0.5px, transparent 0.5px),
            radial-gradient(circle at 70px 120px, var(--primary-light) 1px, transparent 1px);
        background-size: 60px 60px, 100px 100px;
        opacity: 0.03;
    }

    /* Scrollable Content */
    .sidebar-content {
        position: relative;
        height: 100%;
        overflow-y: auto;
        overflow-x: hidden;
        z-index: 1;
        padding-bottom: 20px;
        scrollbar-width: thin;
        scrollbar-color: var(--text-muted) transparent;
    }

    /* Custom Scrollbar */
    .sidebar-content::-webkit-scrollbar {
        width: 3px;
    }

    .sidebar-content::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar-content::-webkit-scrollbar-thumb {
        background: var(--text-muted);
        border-radius: 20px;
        opacity: 0.3;
    }

    .sidebar-content::-webkit-scrollbar-thumb:hover {
        background: var(--primary);
    }

    /* Premium Header */
    .sidebar-header {
        padding: 25px 20px 15px;
        position: sticky;
        top: 0;
        background: var(--bg-light);
        z-index: 10;
        border-bottom: 1px solid var(--border-light);
    }

    .brand-container {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .brand-icon {
        position: relative;
        width: 44px;
        height: 44px;
        background: var(--gradient-primary);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        box-shadow: 0 8px 12px rgba(67, 97, 238, 0.2);
    }

    .icon-glow {
        position: absolute;
        width: 100%;
        height: 100%;
        background: inherit;
        border-radius: inherit;
        filter: blur(8px);
        opacity: 0.4;
        animation: softPulse 3s infinite;
    }

    @keyframes softPulse {

        0%,
        100% {
            opacity: 0.4;
            transform: scale(1);
        }

        50% {
            opacity: 0.6;
            transform: scale(1.05);
        }
    }

    .brand-text {
        display: flex;
        flex-direction: column;
    }

    .brand-name {
        font-weight: 700;
        font-size: 1.2rem;
        color: var(--text-dark);
        letter-spacing: -0.3px;
    }

    .brand-badge {
        font-size: 0.6rem;
        font-weight: 600;
        background: var(--gradient-primary);
        color: white;
        padding: 2px 8px;
        border-radius: 30px;
        width: fit-content;
        margin-top: 2px;
    }

    .collapse-trigger {
        width: 34px;
        height: 34px;
        border: none;
        background: white;
        border-radius: 10px;
        color: var(--text-medium);
        cursor: pointer;
        font-size: 1rem;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: auto;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
    }

    .collapse-trigger:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        transform: scale(1.05);
    }

    /* Quick Stats Bar */
    .quick-stats {
        display: flex;
        align-items: center;
        justify-content: space-around;
        background: white;
        margin: 15px 20px 20px;
        padding: 12px 10px;
        border-radius: 16px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
    }

    .stat-item {
        text-align: center;
        flex: 1;
    }

    .stat-value {
        display: block;
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-dark);
        line-height: 1.2;
    }

    .stat-label {
        font-size: 0.65rem;
        color: var(--text-light);
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .stat-divider {
        width: 1px;
        height: 20px;
        background: var(--border-light);
    }

    /* Navigation */
    .sidebar-nav {
        padding: 0 15px;
    }

    .nav-group {
        margin-bottom: 4px;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        text-decoration: none;
        color: var(--text-medium);
        border-radius: 12px;
        transition: all 0.2s ease;
        position: relative;
        font-weight: 500;
        z-index: 2;
    }

    .nav-link:hover {
        background: white;
        color: var(--primary);
        box-shadow: var(--shadow-sm);
        transform: translateX(3px);
    }

    .nav-link.active {
        background: var(--gradient-primary);
        color: white;
        box-shadow: 0 8px 12px rgba(67, 97, 238, 0.2);
    }

    .nav-icon {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        margin-right: 12px;
        background: white;
        color: var(--primary);
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }

    .nav-link:hover .nav-icon {
        background: var(--primary);
        color: white;
        transform: scale(1.05);
    }

    .nav-link.active .nav-icon {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        box-shadow: none;
    }

    .nav-label {
        flex: 1;
        font-size: 0.9rem;
    }

    .active-indicator {
        width: 6px;
        height: 6px;
        background: white;
        border-radius: 50%;
        margin-left: 8px;
        animation: gentlePulse 2s infinite;
    }

    @keyframes gentlePulse {

        0%,
        100% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.5;
            transform: scale(1.2);
        }
    }

    /* Section Headers */
    .nav-section {
        margin-top: 20px;
        margin-bottom: 8px;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 10px;
        margin-bottom: 8px;
    }

    .section-title {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-light);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .section-line {
        flex: 1;
        height: 1px;
        background: linear-gradient(90deg, var(--border-light), transparent);
    }

    /* Dropdown Styles */
    .dropdown-group {
        margin-bottom: 2px;
    }

    .dropdown-trigger {
        width: 100%;
        display: flex;
        align-items: center;
        padding: 10px 15px;
        background: transparent;
        border: none;
        color: var(--text-medium);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: left;
        font-weight: 500;
        z-index: 2;
        position: relative;
    }

    .dropdown-trigger:hover {
        background: white;
        color: var(--primary);
        box-shadow: var(--shadow-sm);
    }

    .trigger-icon {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        margin-right: 12px;
        background: white;
        color: var(--primary);
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }

    .dropdown-trigger:hover .trigger-icon {
        background: var(--primary);
        color: white;
    }

    .trigger-label {
        flex: 1;
        font-size: 0.9rem;
    }

    .dropdown-arrow {
        font-size: 0.75rem;
        transition: transform 0.2s ease;
        color: var(--text-light);
    }

    .dropdown-group.expanded .dropdown-arrow {
        transform: rotate(90deg);
        color: var(--primary);
    }

    .active-dot {
        width: 4px;
        height: 4px;
        background: var(--primary);
        border-radius: 50%;
        margin-left: 8px;
    }

    .dropdown-menu {
        margin-left: 46px;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        padding-left: 8px;
    }

    .dropdown-group.expanded .dropdown-menu {
        max-height: 300px;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        text-decoration: none;
        color: var(--text-light);
        font-size: 0.85rem;
        border-radius: 10px;
        margin: 2px 0;
        transition: all 0.2s ease;
        position: relative;
    }

    .dropdown-item i {
        font-size: 0.4rem;
        margin-right: 10px;
        color: var(--text-muted);
        transition: all 0.2s ease;
    }

    .dropdown-item:hover {
        background: white;
        color: var(--primary);
        transform: translateX(5px);
        box-shadow: var(--shadow-sm);
    }

    .dropdown-item:hover i {
        color: var(--primary);
    }

    .dropdown-item.active {
        background: var(--primary-soft);
        color: var(--primary);
        font-weight: 500;
    }

    .dropdown-item.active i {
        color: var(--primary);
    }

    .check-indicator {
        position: absolute;
        right: 12px;
        font-size: 0.7rem;
        color: var(--primary);
        font-weight: 600;
    }

    /* Footer Section */
    .nav-footer {
        margin-top: 30px;
        padding-top: 15px;
        border-top: 1px solid var(--border-light);
    }

    /* Logout Button */
    .logout {
        margin-top: 8px;
    }

    .logout-link {
        color: #ef4444;
    }

    .logout-link:hover {
        background: #fee2e2;
        color: #dc2626;
    }

    .logout-link:hover .nav-icon {
        background: #ef4444;
        color: white;
    }

    /* Collapsed State */
    .professional-sidebar.collapsed {
        width: var(--sidebar-collapsed);
    }

    .professional-sidebar.collapsed .brand-text,
    .professional-sidebar.collapsed .quick-stats,
    .professional-sidebar.collapsed .nav-label,
    .professional-sidebar.collapsed .trigger-label,
    .professional-sidebar.collapsed .dropdown-arrow,
    .professional-sidebar.collapsed .section-header,
    .professional-sidebar.collapsed .active-dot,
    .professional-sidebar.collapsed .check-indicator,
    .professional-sidebar.collapsed .dropdown-menu {
        display: none;
    }

    .professional-sidebar.collapsed .nav-link,
    .professional-sidebar.collapsed .dropdown-trigger {
        justify-content: center;
        padding: 10px;
        width: 100%;
        position: relative;
    }

    .professional-sidebar.collapsed .nav-icon,
    .professional-sidebar.collapsed .trigger-icon {
        margin-right: 0;
        min-width: 34px;
        min-height: 34px;
    }

    .professional-sidebar.collapsed .collapse-trigger {
        margin: 0 auto;
    }

    /* Tooltips for collapsed state */
    .professional-sidebar.collapsed [data-tooltip] {
        position: relative;
    }

    .professional-sidebar.collapsed [data-tooltip]:hover:after {
        content: attr(data-tooltip);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        background: var(--text-dark);
        color: white;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.75rem;
        white-space: nowrap;
        z-index: 1000;
        margin-left: 10px;
        font-weight: 500;
        box-shadow: var(--shadow-lg);
    }

    /* Main Content Adjustment */
    .main-content,
    .dashboard-main {
        margin-left: var(--sidebar-width);
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        min-height: 100vh;
        background: #ffffff;
    }

    .main-content.sidebar-collapsed,
    .dashboard-main.sidebar-collapsed {
        margin-left: var(--sidebar-collapsed);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .professional-sidebar {
            width: var(--sidebar-collapsed);
        }

        .professional-sidebar .brand-text,
        .professional-sidebar .quick-stats,
        .professional-sidebar .nav-label,
        .professional-sidebar .trigger-label,
        .professional-sidebar .dropdown-arrow,
        .professional-sidebar .section-header,
        .professional-sidebar .dropdown-menu {
            display: none;
        }

        .main-content,
        .dashboard-main {
            margin-left: var(--sidebar-collapsed);
        }
    }
</style>

<script>
    // Toggle dropdowns
    function toggleDropdown(dropdownId) {
        const sidebar = document.getElementById('professionalSidebar');
        const mainContent = document.querySelector('.main-content, .dashboard-main');
        
        // If sidebar is collapsed, force expand it first
        if (sidebar && sidebar.classList.contains('collapsed')) {
            sidebar.classList.remove('collapsed');
            
            if (mainContent) {
                mainContent.classList.remove('sidebar-collapsed');
            }
            
            document.cookie = "sidebar_collapsed=false; path=/; max-age=" + (365 * 24 * 60 * 60);
            
            // Allow a tiny delay for CSS transition before expanding the specific dropdown
            setTimeout(() => {
                const dropdown = document.getElementById(dropdownId);
                const group = dropdown.closest('.dropdown-group');
                group.classList.add('expanded'); // Ensure we expand it
            }, 50);
            
            return; // Don't toggle it off right after we expanded the sidebar
        }

        const dropdown = document.getElementById(dropdownId);
        const group = dropdown.closest('.dropdown-group');
        group.classList.toggle('expanded');
    }

    // Toggle sidebar collapse
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('professionalSidebar');
        const trigger = document.getElementById('collapseTrigger');
        const mainContent = document.querySelector('.main-content, .dashboard-main');

        if (trigger && sidebar) {
            trigger.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                const isCollapsed = sidebar.classList.contains('collapsed');

                if (mainContent) {
                    mainContent.classList.toggle('sidebar-collapsed', isCollapsed);
                }

                // Save preference
                document.cookie = "sidebar_collapsed=" + isCollapsed + "; path=/; max-age=" + (365 * 24 * 60 * 60);
            });
        }

        // Auto-expand dropdowns based on current page
        const currentPath = window.location.pathname;

        const dropdownMappings = [{
                path: '/clients/',
                id: 'clientsDropdown'
            },
            {
                path: '/projects/',
                id: 'projectsDropdown'
            },
            {
                path: '/milestones/',
                id: 'projectsDropdown'
            },
            {
                path: '/proposals/',
                id: 'proposalsDropdown'
            },
            {
                path: '/contracts/',
                id: 'contractsDropdown'
            },
            {
                path: '/invoices/',
                id: 'invoicesDropdown'
            },
            {
                path: '/payments/',
                id: 'paymentsDropdown'
            },
            {
                path: '/reminders/',
                id: 'remindersDropdown'
            }
        ];

        dropdownMappings.forEach(mapping => {
            if (currentPath.includes(mapping.path)) {
                const dropdown = document.getElementById(mapping.id);
                if (dropdown) {
                    dropdown.closest('.dropdown-group').classList.add('expanded');
                }
            }
        });
    }); 
</script>