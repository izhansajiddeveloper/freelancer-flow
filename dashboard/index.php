<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
echo "<!-- DEBUG: current user_id is " . var_export($user_id, true) . " -->";
echo "<!-- DEBUG: Session data: " . var_export($_SESSION, true) . " -->";
$user_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Freelancer');
$user_role = $_SESSION['user_role'] ?? 'Freelancer';

// 1. Fetch Basic Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_clients = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ? AND status = 'in_progress'");
$stmt->execute([$user_id]);
$active_projects = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_invoices = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(p.amount) FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.user_id = ?");
$stmt->execute([$user_id]);
$total_earnings = $stmt->fetchColumn() ?: 0;

// 2. Fetch Recent Notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$unread_notifications = $stmt->fetchAll();
$unread_count = count($unread_notifications);

// 3. Fetch Recent Projects (detailed)
$stmt = $pdo->prepare("
    SELECT p.*, c.client_name 
    FROM projects p 
    JOIN clients c ON p.client_id = c.id 
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC LIMIT 5
");
$stmt->execute([$user_id]);
$recent_projects = $stmt->fetchAll();

// 4. Fetch Upcoming Deadlines
$stmt = $pdo->prepare("
    SELECT project_title, deadline, status 
    FROM projects 
    WHERE user_id = ? AND status = 'active' AND deadline >= CURDATE() 
    ORDER BY deadline ASC LIMIT 5
");
$stmt->execute([$user_id]);
$upcoming_deadlines = $stmt->fetchAll();

// 5. Fetch Monthly Revenue for Chart
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(payment_date, '%b') as month,
        SUM(amount) as total
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    WHERE i.user_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY p.payment_date ASC
");
$stmt->execute([$user_id]);
$monthly_revenue = $stmt->fetchAll();

$revenue_labels = [];
$revenue_data = [];
foreach ($monthly_revenue as $row) {
    $revenue_labels[] = $row['month'];
    $revenue_data[] = (float)$row['total'];
}

// Fallback data if empty
if (empty($revenue_data)) {
    $revenue_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    $revenue_data = [0, 0, 0, 0, 0, 0];
}

// 6. Project Distribution Data
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM projects WHERE user_id = ? GROUP BY status");
$stmt->execute([$user_id]);
$dist_data = $stmt->fetchAll();

$dist_labels = [];
$dist_values = [];
$dist_colors = [];
$status_colors = [
    'active' => '#4f46e5',
    'completed' => '#10b981',
    'on_hold' => '#f59e0b',
    'cancelled' => '#ef4444'
];

foreach ($dist_data as $row) {
    $dist_labels[] = ucfirst(str_replace('_', ' ', $row['status']));
    $dist_values[] = (int)$row['count'];
    $dist_colors[] = $status_colors[$row['status']] ?? '#94a3b8';
}

if (empty($dist_values)) {
    $dist_labels = ['No Data'];
    $dist_values = [1];
    $dist_colors = ['#e2e8f0'];
}

include_once '../includes/header.php';
?>

<style>
    /* Force hide the main landing navbar on the dashboard */
    .navbar { display: none !important; }
    /* Fix dashboard wrapper padding and overall alignment */
    .dashboard-wrapper {
        margin-top: 0;
        height: 100vh;
        overflow: hidden;
    }
    .main-content {
        height: 100vh;
        overflow-y: auto;
        padding-bottom: 50px;
    }
    /* Debug styling - make comments visible */
    .debug-info {
        background: #f0f0f0;
        padding: 10px;
        margin: 10px 0;
        border-left: 4px solid #4f46e5;
        font-family: monospace;
        font-size: 12px;
        white-space: pre-wrap;
        display: none; /* Hide by default, remove 'display: none' to see debug info */
    }
</style>

<!-- Debug output visible in HTML source -->
<!-- ================================== -->
<!-- DEBUG: Check browser's "View Page Source" to see the debug comments -->
<!-- ================================== -->

<div class="dashboard-wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Dashboard Topbar -->
        <div class="dashboard-topbar">
            <div class="topbar-left">
                <div class="topbar-search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search everywhere...">
                </div>
            </div>
            
            <div class="topbar-actions">
                <div class="notif-bell">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="notif-badge"></span>
                    <?php endif; ?>
                </div>
                
                <div class="user-profile-toggle">
                    <?php 
                    $user_img = "https://ui-avatars.com/api/?name=" . urlencode($user_name) . "&background=4f46e5&color=fff";
                    if (!empty($_SESSION['user_image'])) {
                        $image_path = ROOT_PATH . 'assets/uploads/profiles/' . $_SESSION['user_image'];
                        if (file_exists($image_path)) {
                            $user_img = BASE_URL . 'assets/uploads/profiles/' . $_SESSION['user_image'];
                        }
                    }
                    ?>
                    <img src="<?php echo $user_img; ?>" alt="Avatar">
                    <div class="user-info-text">
                        <span class="user-name"><?php echo explode(' ', $user_name)[0]; ?></span>
                        <span class="user-role">Freelancer</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            <!-- Welcome Header -->
            <div class="animate-fade-in" style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <h1 style="font-size: 1.8rem; font-weight: 800; letter-spacing: -0.5px;">Dashboard Overview</h1>
                    <p style="color: var(--text-muted); font-size: 1rem;">Here's what's happening in your business today.</p>
                </div>
                <div class="dashboard-date" style="background: var(--card-bg); padding: 10px 20px; border-radius: 12px; border: 1px solid var(--border-color); font-weight: 600; color: var(--text-muted);">
                    <i class="far fa-calendar-alt" style="margin-right: 8px;"></i> <?php echo date('F d, Y'); ?>
                </div>
            </div>

            <!-- Stats Grid - Using Vibrant Styles -->
            <div class="stat-grid animate-fade-in" style="animation-delay: 0.1s;">
                <div class="stat-card vibrant-primary shadow-lg">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Clients</h3>
                        <div class="stat-value"><?php echo number_format($total_clients); ?></div>
                    </div>
                </div>
                <div class="stat-card vibrant-success shadow-lg">
                    <div class="stat-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Active Projects</h3>
                        <div class="stat-value"><?php echo number_format($active_projects); ?></div>
                    </div>
                </div>
                <div class="stat-card vibrant-warning shadow-lg">
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Invoices Due</h3>
                        <div class="stat-value"><?php echo number_format($pending_invoices); ?></div>
                    </div>
                </div>
                <div class="stat-card vibrant-info shadow-lg">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Earnings</h3>
                        <div class="stat-value">Rs. <?php echo number_format($total_earnings); ?></div>
                    </div>
                </div>
            </div>

            <!-- Main Charts Row -->
            <div class="dashboard-grid">
                <!-- Revenue Chart -->
                <div class="chart-card glass-card animate-fade-in" style="animation-delay: 0.3s;">
                    <div class="chart-header">
                        <div>
                            <h3>Revenue Overview</h3>
                            <p style="font-size: 0.75rem; color: var(--text-muted);">Last 6 months performance</p>
                        </div>
                        <div class="chart-options">
                            <span class="badge" style="background: rgba(79, 70, 229, 0.1); color: var(--primary-color);">Growth: +12%</span>
                        </div>
                    </div>
                    <div class="chart-body" style="height: 320px;">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>

                <!-- Project Distribution -->
                <div class="chart-card glass-card animate-fade-in" style="animation-delay: 0.4s;">
                    <div class="chart-header">
                        <h3>Work Distribution</h3>
                    </div>
                    <div class="chart-body" style="height: 240px; display: flex; align-items: center; justify-content: center;">
                        <canvas id="distChart"></canvas>
                    </div>
                    <div style="margin-top: 30px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                            <span style="font-size: 0.85rem; color: var(--text-muted);"><i class="fas fa-circle" style="color: var(--primary-color); margin-right: 8px;"></i> Active</span>
                            <span style="font-weight: 700;">60%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: 60%;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 20px; margin-bottom: 15px;">
                            <span style="font-size: 0.85rem; color: var(--text-muted);"><i class="fas fa-circle" style="color: var(--secondary-color); margin-right: 8px;"></i> Completed</span>
                            <span style="font-weight: 700;">40%</span>
                        </div>
                        <div class="progress-bar-container" style="background: rgba(16, 185, 129, 0.1);">
                            <div class="progress-bar-fill" style="width: 40%; background: var(--secondary-color);"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Row -->
            <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr;">
                <!-- Recent Projects -->
                <div class="chart-card glass-card animate-fade-in" style="animation-delay: 0.5s;">
                    <div class="chart-header">
                        <h3>Recent Projects</h3>
                        <a href="<?php echo BASE_URL; ?>projects/index.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Client</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_projects)): ?>
                                    <tr><td colspan="3" style="text-align: center; padding: 20px;">No projects found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_projects as $p): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($p['project_title']); ?></div>
                                            <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo $p['project_type']; ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($p['client_name']); ?></td>
                                        <td>
                                            <?php 
                                            $status_class = 'status-badge ' . str_replace('_', '-', $p['status']);
                                            ?>
                                            <span class="<?php echo $status_class; ?>"><?php echo ucfirst($p['status']); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Upcoming Deadlines -->
                <div class="chart-card glass-card animate-fade-in" style="animation-delay: 0.6s;">
                    <div class="chart-header">
                        <h3>Upcoming Deadlines</h3>
                        <span style="font-size: 0.75rem; color: #ef4444; font-weight: 600;"><i class="fas fa-exclamation-circle"></i> Priority</span>
                    </div>
                    <div class="deadline-list">
                        <?php if (empty($upcoming_deadlines)): ?>
                            <div style="text-align: center; padding: 20px; color: var(--text-muted);">Great! No immediate deadlines.</div>
                        <?php else: ?>
                            <?php foreach ($upcoming_deadlines as $d): ?>
                                <?php 
                                $days_left = ceil((strtotime($d['deadline']) - time()) / 86400);
                                $display_days = $days_left == 0 ? "Today" : ($days_left == 1 ? "Tomorrow" : "$days_left days left");
                                ?>
                                <div class="deadline-item" style="display: flex; align-items: center; justify-content: space-between; padding: 15px; border-bottom: 1px solid var(--border-color);">
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <div style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo $days_left <= 3 ? '#ef4444' : '#f59e0b'; ?>;"></div>
                                        <div>
                                            <div style="font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars($d['project_title']); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($d['deadline'])); ?></div>
                                        </div>
                                    </div>
                                    <div style="font-weight: 600; font-size: 0.8rem; color: <?php echo $days_left <= 3 ? '#ef4444' : '#f59e0b'; ?>;"><?php echo $display_days; ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Milestone Tracker Row -->
            <div class="dashboard-grid" style="grid-template-columns: 100%; margin-top: 30px;">
                <div class="chart-card glass-card animate-fade-in" style="animation-delay: 0.65s;">
                    <div class="chart-header">
                        <h3>Recent Milestones</h3>
                        <a href="<?php echo BASE_URL; ?>milestones/index.php" class="view-all-link">Manage All Tasks <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT m.*, p.project_title 
                        FROM milestones m 
                        JOIN projects p ON m.project_id = p.id 
                        WHERE m.user_id = ? 
                        ORDER BY m.created_at DESC LIMIT 4
                    ");
                    $stmt->execute([$user_id]);
                    $recent_milestones = $stmt->fetchAll();
                    ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; padding: 10px 0;">
                        <?php if (empty($recent_milestones)): ?>
                            <div style="grid-column: 1 / -1; text-align: center; padding: 30px; color: var(--text-muted);">No milestones found. Start by adding one to a project.</div>
                        <?php else: ?>
                            <?php foreach ($recent_milestones as $m): ?>
                                <div style="background: white; border: 1px solid var(--border-color); padding: 20px; border-radius: 16px;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                        <div style="font-size: 0.7rem; color: var(--primary-color); font-weight: 800; text-transform: uppercase;"><?php echo htmlspecialchars($m['project_title']); ?></div>
                                        <span class="status-badge-small <?php echo str_replace('_', '-', $m['status']); ?>"><?php echo ucfirst($m['status']); ?></span>
                                    </div>
                                    <h5 style="font-weight: 700; margin-bottom: 15px;"><?php echo htmlspecialchars($m['title']); ?></h5>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="<?php echo BASE_URL; ?>milestones/update_status.php?id=<?php echo $m['id']; ?>&status=completed" class="btn btn-outline" style="flex: 1; padding: 6px; font-size: 0.7rem; border-radius: 8px;">Mark Done</a>
                                        <a href="<?php echo BASE_URL; ?>milestones/edit.php?id=<?php echo $m['id']; ?>" class="btn btn-outline" style="padding: 6px 10px; font-size: 0.7rem; border-radius: 8px;"><i class="far fa-edit"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Lead Analysis Row -->
            <div class="dashboard-grid" style="grid-template-columns: 100%;">
                 <div class="chart-card glass-card animate-fade-in" style="animation-delay: 0.7s;">
                    <div class="chart-header">
                        <h3>Client Acquisition & Leads</h3>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 40px; align-items: center;">
                        <div style="height: 300px;">
                            <canvas id="leadChart"></canvas>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="lead-stat-mini" style="background: rgba(79, 70, 229, 0.05); padding: 20px; border-radius: 16px; text-align: center;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color);">45</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Total Inquiries</div>
                            </div>
                            <div class="lead-stat-mini" style="background: rgba(16, 185, 129, 0.05); padding: 20px; border-radius: 16px; text-align: center;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--secondary-color);">12</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Converted</div>
                            </div>
                            <div class="lead-stat-mini" style="background: rgba(245, 158, 11, 0.05); padding: 20px; border-radius: 16px; text-align: center;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;">Rs. 150k</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Pipeline Value</div>
                            </div>
                            <div class="lead-stat-mini" style="background: rgba(59, 130, 246, 0.05); padding: 20px; border-radius: 16px; text-align: center;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #3b82f6;">85%</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Reply Rate</div>
                            </div>
                        </div>
                    </div>
                 </div>
            </div>

            <!-- Footer -->
            <footer style="margin-top: 50px; padding: 30px 0; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; color: var(--text-muted); font-size: 0.85rem;">
                <div>&copy; <?php echo date('Y'); ?> <span style="color: var(--primary-color); font-weight: 700;">FreelanceFlow</span> Terminal. All rights reserved.</div>
                <div style="display: flex; gap: 24px;">
                    <a href="#" style="transition: color 0.2s;">System Status</a>
                    <a href="#" style="transition: color 0.2s;">API Reference</a>
                    <a href="#" style="transition: color 0.2s;">Support</a>
                </div>
            </footer>
        </div>
    </main>
</div>

<style>
    /* Internal styles for dashboard enhancements */
    .view-all-link {
        font-size: 0.85rem;
        color: var(--primary-color);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .view-all-link:hover {
        padding-right: 5px;
    }
    
    .dashboard-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    .dashboard-table th {
        text-align: left;
        padding: 12px 10px;
        color: var(--text-muted);
        font-weight: 600;
        border-bottom: 1px solid var(--border-color);
    }
    .dashboard-table td {
        padding: 15px 10px;
        border-bottom: 1px solid rgba(0,0,0,0.03);
    }
    
    .status-badge {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-badge.active { background: rgba(79, 70, 229, 0.1); color: var(--primary-color); }
    .status-badge.completed { background: rgba(16, 185, 129, 0.1); color: var(--secondary-color); }
    .status-badge.on-hold { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    
    .user-profile-toggle {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 6px 16px;
        background: var(--light-bg);
        border-radius: 12px;
        cursor: pointer;
        transition: var(--transition);
        border: 1px solid transparent;
    }
    .user-profile-toggle:hover {
        border-color: var(--border-color);
        background: white;
    }
    .user-profile-toggle img {
        width: 35px;
        height: 35px;
        border-radius: 10px;
        object-fit: cover;
    }
    .user-info-text {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }
    .user-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: var(--text-main);
    }
    .user-role {
        font-size: 0.7rem;
        color: var(--text-muted);
    }
    
    .topbar-left {
        flex: 1;
    }
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    Chart.defaults.font.family = "'Outfit', sans-serif";
    Chart.defaults.color = '#64748b';

    // 1. Line Chart (Revenue)
    const ctxPerf = document.getElementById('performanceChart').getContext('2d');
    const pGradient = ctxPerf.createLinearGradient(0, 0, 0, 300);
    pGradient.addColorStop(0, 'rgba(79, 70, 229, 0.25)');
    pGradient.addColorStop(1, 'rgba(79, 70, 229, 0)');

    new Chart(ctxPerf, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($revenue_labels); ?>,
            datasets: [{
                label: 'Revenue',
                data: <?php echo json_encode($revenue_data); ?>,
                borderColor: '#4f46e5',
                borderWidth: 4,
                fill: true,
                backgroundColor: pGradient,
                tension: 0.45,
                pointRadius: 6,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#4f46e5',
                pointBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    titleFont: { size: 14, weight: 'bold' },
                    bodyFont: { size: 13 },
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: Rs. ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                    ticks: { callback: value => 'Rs.' + (value >= 1000 ? (value/1000) + 'k' : value) }
                },
                x: { grid: { display: false, drawBorder: false } }
            }
        }
    });

    // 2. Doughnut Chart (Distribution)
    new Chart(document.getElementById('distChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($dist_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($dist_values); ?>,
                backgroundColor: <?php echo json_encode($dist_colors); ?>,
                borderWidth: 0,
                cutout: '80%',
                hoverOffset: 12
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 10,
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.label + ': ' + context.parsed + ' projects';
                        }
                    }
                }
            }
        }
    });

    // 3. Polar Area Chart (Leads)
    new Chart(document.getElementById('leadChart'), {
        type: 'polarArea',
        data: {
            labels: ['New Leads', 'Proposals Sent', 'Negotiation', 'Wins'],
            datasets: [{
                data: [18, 12, 7, 5],
                backgroundColor: [
                    'rgba(79, 70, 229, 0.7)', 
                    'rgba(59, 130, 246, 0.7)', 
                    'rgba(245, 158, 11, 0.7)', 
                    'rgba(16, 185, 129, 0.7)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { 
                    position: 'right',
                    labels: { boxWidth: 12, usePointStyle: true, padding: 20 }
                } 
            },
            scales: { r: { grid: { display: false }, ticks: { display: false } } }
        }
    });
</script>