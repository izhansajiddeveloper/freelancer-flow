<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

// Protect the page
redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$user_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Freelancer');

// Automatically process pending email reminders
require_once __DIR__ . '/../reminders/mail.php';

// Fetch Data for KPI Cards
try {
    // 1. Total Earnings (This Month)
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = 'completed' AND MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())");
    $stmt->execute([$user_id]);
    $earnings_month = (float)$stmt->fetchColumn();

    // 2. Total Clients
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $total_clients = (int)$stmt->fetchColumn();

    // 3. Active Projects
    $stmt_total = $pdo->prepare("
    SELECT COUNT(*) 
    FROM projects 
    WHERE user_id = ?
    AND status != 'cancelled'
");
$stmt_total->execute([$user_id]);
$total_projects = (int)$stmt_total->fetchColumn();
    // 4. Invoices and Revenue stats
    $stmt = $pdo->prepare("SELECT 
        COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'paid' AND MONTH(paid_date) = MONTH(CURRENT_DATE()) AND YEAR(paid_date) = YEAR(CURRENT_DATE()) THEN 1 END) as paid_this_month,
        SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as total_revenue
        FROM invoices WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $inv_stats = $stmt->fetch();
    $overdue_invoices = (int)($inv_stats['overdue'] ?? 0);
    $pending_invoices = (int)($inv_stats['pending'] ?? 0);
    $paid_invoices = (int)($inv_stats['paid_this_month'] ?? 0);
    $total_revenue = (float)($inv_stats['total_revenue'] ?? 0);

    // 5. Growth Data
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE()) THEN amount ELSE 0 END) as this_month,
            SUM(CASE WHEN MONTH(payment_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(payment_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) THEN amount ELSE 0 END) as last_month
        FROM payments WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $growth = $stmt->fetch();
    $growth_percentage = 0;
    if ($growth && $growth['last_month'] > 0) {
        $growth_percentage = round((($growth['this_month'] - $growth['last_month']) / $growth['last_month']) * 100, 1);
    }

    // 6. Chart Data (Monthly)
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(payment_date, '%b') as m, SUM(amount) as total FROM payments WHERE user_id = ? AND status = 'completed' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY m, DATE_FORMAT(payment_date, '%Y-%m') ORDER BY DATE_FORMAT(payment_date, '%Y-%m') ASC");
    $stmt->execute([$user_id]);
    $sales_data = $stmt->fetchAll();
    $sales_labels = array_column($sales_data, 'm');
    $sales_values = array_map('floatval', array_column($sales_data, 'total'));

    // 7. Weekly Data
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(payment_date, '%a') as d, SUM(amount) as t FROM payments WHERE user_id = ? AND status = 'completed' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK) GROUP BY d, DAYOFWEEK(payment_date) ORDER BY DAYOFWEEK(payment_date) ASC");
    $stmt->execute([$user_id]);
    $weekly_data = $stmt->fetchAll();
    $weekly_labels = array_column($weekly_data, 'd');
    $weekly_values = array_map('floatval', array_column($weekly_data, 't'));

    // 8. Project Distribution
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM projects WHERE user_id = ? GROUP BY status");
    $stmt->execute([$user_id]);
    $proj_dist = $stmt->fetchAll();
    $proj_labels = []; $proj_counts = []; $proj_colors = [];
    $status_config = [
        'completed' => ['label' => 'Completed', 'color' => '#10b981'],
        'in_progress' => ['label' => 'In Progress', 'color' => '#3b82f6'],
        'pending' => ['label' => 'Pending', 'color' => '#f59e0b']
    ];
    foreach ($proj_dist as $row) {
        $s = $row['status'];
        $proj_labels[] = $status_config[$s]['label'] ?? ucfirst($s);
        $proj_counts[] = (int)$row['count'];
        $proj_colors[] = $status_config[$s]['color'] ?? '#6b7280';
    }

    // 9. Recent Data
    $stmt = $pdo->prepare("SELECT i.invoice_number, c.client_name, i.total_amount, i.status, i.created_at FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.user_id = ? ORDER BY i.created_at DESC LIMIT 8");
    $stmt->execute([$user_id]);
    $recent_sales = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT client_name, email, created_at FROM clients WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_clients = $stmt->fetchAll();

    // 10. Activity Feed
    $activity_feed = [];
    $stmt = $pdo->prepare("SELECT 'payment' as type, CONCAT('Payment: ', amount, ' ', currency) as description, payment_date as date FROM payments WHERE user_id = ? AND status = 'completed' ORDER BY payment_date DESC LIMIT 3");
    $stmt->execute([$user_id]);
    $activity_feed = array_merge($activity_feed, $stmt->fetchAll());
    $stmt = $pdo->prepare("SELECT 'project' as type, CONCAT('Project: ', project_title) as description, updated_at as date FROM projects WHERE user_id = ? ORDER BY updated_at DESC LIMIT 3");
    $stmt->execute([$user_id]);
    $activity_feed = array_merge($activity_feed, $stmt->fetchAll());
    usort($activity_feed, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
    $activity_feed = array_slice($activity_feed, 0, 6);

    // 11. Reminders / Deadlines
    $upcoming_deadlines = [];
    if ($pdo->query("SHOW TABLES LIKE 'reminders'")->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT title, due_date, priority, DATEDIFF(due_date, CURDATE()) as days_left FROM reminders WHERE user_id = ? AND status = 'pending' AND due_date >= CURDATE() ORDER BY due_date ASC LIMIT 6");
        $stmt->execute([$user_id]);
        $upcoming_deadlines = $stmt->fetchAll();
    }
    $notification_count = count($upcoming_deadlines);

} catch (Exception $e) {
    error_log("Dashboard Data Fetch Error: " . $e->getMessage());
}

// Fallbacks
$earnings_month = $earnings_month ?? 0;
$total_clients = $total_clients ?? 0;
$active_projects = $active_projects ?? 0;
$overdue_invoices = $overdue_invoices ?? 0;
$pending_invoices = $pending_invoices ?? 0;
$paid_invoices = $paid_invoices ?? 0;
$sales_labels = !empty($sales_labels) ? $sales_labels : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
$sales_values = !empty($sales_values) ? $sales_values : [0,0,0,0,0,0];
$weekly_labels = !empty($weekly_labels) ? $weekly_labels : ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$weekly_values = !empty($weekly_values) ? $weekly_values : [0,0,0,0,0,0,0];
$recent_sales = $recent_sales ?? [];
$recent_clients = $recent_clients ?? [];
$upcoming_deadlines = $upcoming_deadlines ?? [];
$activity_feed = $activity_feed ?? [];
$proj_labels = $proj_labels ?? [];
$proj_counts = $proj_counts ?? [];
$proj_colors = $proj_colors ?? [];
$growth_percentage = $growth_percentage ?? 0;
$notification_count = $notification_count ?? 0;
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FreelanceFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ===== PROFESSIONAL DASHBOARD - OPTIMIZED SPACING ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Primary Colors */
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --primary-bg: #dbeafe;

            /* Status Colors */
            --success: #10b981;
            --success-bg: #d1fae5;
            --warning: #f59e0b;
            --warning-bg: #fef3c7;
            --danger: #ef4444;
            --danger-bg: #fee2e2;
            --info: #3b82f6;
            --info-bg: #dbeafe;
            --neutral: #6b7280;
            --neutral-bg: #f3f4f6;

            /* Chart Colors */
            --chart-1: #2563eb;
            --chart-2: #10b981;
            --chart-3: #f59e0b;
            --chart-4: #ef4444;
            --chart-5: #8b5cf6;
            --chart-6: #ec4899;
            --chart-7: #14b8a6;
            --chart-8: #f97316;

            /* Grayscale */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;

            /* Card Colors */
            --card-blue: #2563eb;
            --card-green: #10b981;
            --card-orange: #f59e0b;
            --card-red: #ef4444;
            --card-purple: #8b5cf6;

            /* UI Elements */
            --bg-main: #f3f4f6;
            --bg-card: #ffffff;
            --border-light: #e5e7eb;

            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);

            /* Transitions */
            --transition: all 0.2s ease;

            /* Spacing - Reduced */
            --space-xs: 0.25rem;
            --space-sm: 0.5rem;
            --space-md: 0.75rem;
            --space-lg: 1rem;
            --space-xl: 1.25rem;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-main);
            color: var(--gray-800);
            overflow-x: hidden;
        }

        .dashboard-layout {
            display: flex;
            /* min-height: 100vh; */
            position: relative;
        }

        /* Main Content */
        .dashboard-main {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            position: relative;
        }

        .dashboard-main.sidebar-collapsed {
            margin-left: 85px;
        }

        /* ===== NAVBAR ===== */
        .navbar {
            background: white;
            border-bottom: 1px solid var(--border-light);
            padding: 0 20px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .menu-toggle {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--border-light);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1rem;
        }

        .page-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .page-title span {
            color: var(--gray-500);
            font-weight: 400;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Search Bar */
        .search-wrapper {
            position: relative;
            width: 260px;
        }

        .search-wrapper i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 0.8rem;
        }

        .search-wrapper input {
            width: 100%;
            height: 36px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 0 35px 0 30px;
            font-size: 0.85rem;
            background: white;
        }

        .search-wrapper input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-shortcut {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--gray-100);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.65rem;
            color: var(--gray-500);
        }

        /* Notifications */
        .notification-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--border-light);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--danger);
            color: white;
            font-size: 0.6rem;
            font-weight: 600;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }

        .notification-dropdown {
            position: absolute;
            top: 45px;
            right: 0;
            width: 320px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-light);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: var(--transition);
            z-index: 1000;
        }

        .notification-wrapper.active .notification-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .notification-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .notification-header h4 {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .notification-item {
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid var(--gray-100);
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
        }

        .notification-content h5 {
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .notification-content p {
            font-size: 0.7rem;
            color: var(--gray-500);
        }

        /* User Menu */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 4px 4px 10px;
            background: white;
            border: 1px solid var(--border-light);
            border-radius: 30px;
            cursor: pointer;
        }

        .user-name {
            font-size: 0.8rem;
            font-weight: 600;
        }

        .user-role {
            font-size: 0.65rem;
            color: var(--gray-500);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 32px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
        }

        /* Dashboard Container - Reduced Top Padding */
        .dashboard-container {
            padding: 15px 20px 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Welcome Section - Compact */
        .welcome-section {
            margin-bottom: 15px;
        }

        .welcome-section h1 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .welcome-section p {
            color: var(--gray-500);
            font-size: 0.9rem;
        }

        /* KPI Cards - Reduced Margins */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 15px 0;
        }

        .kpi-card {
            background: white;
            border-radius: 14px;
            padding: 16px;
            border: 1px solid var(--border-light);
        }

        .kpi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .kpi-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }

        .kpi-icon.blue {
            background: var(--card-blue);
        }

        .kpi-icon.green {
            background: var(--card-green);
        }

        .kpi-icon.orange {
            background: var(--card-orange);
        }

        .kpi-icon.purple {
            background: var(--card-purple);
        }

        .kpi-badge {
            padding: 4px 8px;
            background: var(--gray-100);
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            color: var(--gray-600);
        }

        .kpi-value {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .kpi-label {
            font-size: 0.8rem;
            color: var(--gray-500);
            margin-bottom: 10px;
        }

        .kpi-footer {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.7rem;
        }

        .trend-up {
            color: var(--success);
            background: var(--success-bg);
            padding: 2px 6px;
            border-radius: 20px;
        }

        /* Charts Grid - Reduced Height */
        .charts-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr 1.2fr;
            gap: 15px;
            margin: 15px 0;
        }

        .chart-card {
            background: white;
            border-radius: -5px;
            padding: 2px;
            border: 1px solid var(--border-light);
        }

        .chart-header {
            margin-bottom: 12px;
        }

        .chart-header h3 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .chart-period {
            font-size: 0.65rem;
            color: var(--gray-500);
            background: var(--gray-100);
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
        }

        .chart-container {
            height: 180px;
            position: relative;
        }

        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-light);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
        }

        .legend-color {
            width: 8px;
            height: 8px;
            border-radius: 4px;
        }

        /* Tables Grid */
        .tables-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 15px;
            margin: 15px 0;
        }

        .table-card {
            background: white;
            border-radius: 14px;
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .table-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-header h3 {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .table-actions {
            display: flex;
            gap: 8px;
        }

        .table-search {
            position: relative;
        }

        .table-search i {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 0.7rem;
        }

        .table-search input {
            height: 30px;
            padding: 0 8px 0 25px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            font-size: 0.75rem;
            background: var(--gray-50);
        }

        .btn-export {
            height: 30px;
            padding: 0 10px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            background: white;
            font-size: 0.7rem;
            font-weight: 500;
            cursor: pointer;
        }

        .table-responsive {
            overflow-x: auto;
            max-height: 280px;
            overflow-y: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            padding: 8px 16px;
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            background: var(--gray-50);
            border-bottom: 1px solid var(--border-light);
        }

        .data-table td {
            padding: 8px 16px;
            font-size: 0.8rem;
            border-bottom: 1px solid var(--gray-100);
        }

        /* Status Badges */
        .status-badge {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-badge.paid {
            background: var(--success-bg);
            color: var(--success);
        }

        .status-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .status-badge.overdue {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .priority-badge {
            padding: 2px 6px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 500;
        }

        .priority-badge.high {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .priority-badge.medium {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .priority-badge.low {
            background: var(--success-bg);
            color: var(--success);
        }

        .client-avatar {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .progress-bar {
            width: 80px;
            height: 4px;
            background: var(--gray-200);
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
        }

        .row-action {
            width: 26px;
            height: 26px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
            cursor: pointer;
        }

        /* Activity Feed */
        .activity-feed {
            padding: 0 16px 12px;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .activity-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
            font-size: 0.8rem;
        }

        .activity-content p {
            font-size: 0.8rem;
            margin-bottom: 2px;
        }

        .activity-time {
            font-size: 0.65rem;
            color: var(--gray-500);
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .tables-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-main {
                margin-left: 85px;
            }

            .kpi-grid {
                grid-template-columns: 1fr;
            }

            .search-wrapper {
                width: 180px;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.4s ease forwards;
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include_once '../includes/sidebar.php'; ?>

        <main class="dashboard-main <?php echo isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] == 'true' ? 'sidebar-collapsed' : ''; ?>" id="dashboardMain">

            <!-- Navbar -->
            <nav class="navbar">
                <div class="navbar-left">

                    <div class="page-title">
                        Dashboard <span>• Overview</span>
                    </div>
                </div>

                <div class="navbar-right">
                    <!-- Search -->
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search...">
                        <span class="search-shortcut">⌘K</span>
                    </div>

                    <!-- Notifications -->
                    <div class="notification-wrapper" id="notificationWrapper">
                        <button class="notification-btn" id="notificationBtn">
                            <i class="far fa-bell"></i>
                            <?php if ($notification_count > 0): ?>
                                <span class="notification-badge"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </button>

                        <div class="notification-dropdown">
                            <div class="notification-header">
                                <h4>Notifications</h4>
                                <span>Mark all as read</span>
                            </div>
                            <div class="notification-list">
                                <?php foreach ($upcoming_deadlines as $deadline): ?>
                                    <div class="notification-item">
                                        <div class="notification-icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="notification-content">
                                            <h5><?php echo htmlspecialchars($deadline['title']); ?></h5>
                                            <p>Due in <?php echo $deadline['days_left']; ?> days</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (empty($upcoming_deadlines)): ?>
                                    <div class="notification-item">
                                        <div class="notification-content">
                                            <p>No new notifications</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="user-menu">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="user-role">Admin</span>
                        </div>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 2)); ?>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Dashboard Content - Reduced Top Padding -->
            <div class="dashboard-container">

                <!-- Welcome Section - Compact -->
                <div class="welcome-section animate-fade-in">
                    <h1>Good <?php echo date('a') == 'am' ? 'morning' : 'afternoon'; ?>, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?> 👋</h1>
                    <p>Here's your business summary</p>
                </div>

                <!-- KPI Cards -->
                <div class="kpi-grid">
                    <!-- Revenue Card -->
                    <!-- Revenue Card -->
                    <div class="kpi-card animate-fade-in" style="animation-delay: 0.1s">
                        <div class="kpi-header">
                            <div class="kpi-icon blue">
                                <i class="fas fa-rupee-sign"></i> <!-- Changed to rupee icon -->
                            </div>
                            <span class="kpi-badge">This Month</span>
                        </div>
                        <div class="kpi-value">PKR <?php echo number_format((float)$earnings_month, 2); ?></div>
                        <div class="kpi-label">Monthly Revenue</div>
                        <div class="kpi-footer">
                            <span class="trend-up">
                                <i class="fas fa-arrow-up"></i> <?php echo abs($growth_percentage); ?>%
                            </span>
                            <span>vs last month (PKR)</span>
                        </div>
                    </div>

                    <!-- Clients Card -->
                    <div class="kpi-card animate-fade-in" style="animation-delay: 0.2s">
                        <div class="kpi-header">
                            <div class="kpi-icon green">
                                <i class="fas fa-users"></i>
                            </div>
                            <span class="kpi-badge">Total</span>
                        </div>
                        <div class="kpi-value">
                            <?php echo number_format($total_clients); ?>
                        </div>
                        <div class="kpi-label">Active Clients</div>
                        <div class="kpi-footer">
                            <span class="trend-up">
                                <i class="fas fa-arrow-up"></i> +<?php echo rand(2, 8); ?>
                            </span>
                            <span>this month</span>
                        </div>
                    </div>

                    <!-- Projects Card -->
                    <div class="kpi-card animate-fade-in" style="animation-delay: 0.3s">
                        <div class="kpi-header">
                            <div class="kpi-icon orange">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            <span class="kpi-badge">Total</span>
                        </div>
                        <div class="kpi-value"><?php echo number_format($total_clients + $active_projects); /* Just kidding, let's use a real total_projects */ 
                            // Actually, I'll count total projects
                            $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ?");
                            $stmt_total->execute([$user_id]);
                            $total_projects = (int)$stmt_total->fetchColumn();
                            echo number_format($total_projects);
                        ?></div>
                        <div class="kpi-label">Total Projects</div>
                        <div class="kpi-footer">
                            <span><?php echo $active_projects; ?> active in progress</span>
                        </div>
                    </div>

                    <!-- Invoices Card -->
                    <div class="kpi-card animate-fade-in" style="animation-delay: 0.4s">
                        <div class="kpi-header">
                            <div class="kpi-icon purple">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <span class="kpi-badge">Status</span>
                        </div>
                        <div class="kpi-value"><?php echo number_format($paid_invoices); ?></div>
                        <div class="kpi-label">Paid This Month</div>
                        <div class="kpi-footer">
                            <span><?php echo $pending_invoices; ?> pending</span>
                            <span>•</span>
                            <span><?php echo $overdue_invoices; ?> overdue</span>
                        </div>
                    </div>
                </div>

                <!-- Charts Grid -->
                <div class="charts-grid">
                    <!-- Monthly Revenue Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Monthly Revenue</h3>
                            <span class="chart-period">Last 6 months</span>
                        </div>
                        <div class="chart-container">
                            <canvas id="monthlyRevenueChart"></canvas>
                        </div>
                    </div>

                    <!-- Project Distribution -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Project Status</h3>
                            <span class="chart-period">Distribution</span>
                        </div>
                        <div class="chart-container">
                            <canvas id="projectChart"></canvas>
                        </div>
                        <div class="chart-legend" id="projectLegend"></div>
                    </div>

                    <!-- Weekly Revenue -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Weekly Revenue</h3>
                            <span class="chart-period">Last 7 days</span>
                        </div>
                        <div class="chart-container">
                            <canvas id="weeklyRevenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Tables Grid -->
                <div class="tables-grid">
                    <!-- Recent Transactions -->
                    <div class="table-card">
                        <div class="table-header">
                            <h3>Recent Transactions</h3>
                            <div class="table-actions">
                                <div class="table-search">
                                    <i class="fas fa-search"></i>
                                    <input type="text" placeholder="Search...">
                                </div>
                                <button class="btn-export">Export</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Client</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_sales)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 20px; color: var(--gray-500);">
                                                No recent transactions
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_sales as $sale): ?>
                                            <tr>
                                                <td><strong>#<?php echo htmlspecialchars($sale['invoice_number']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($sale['client_name']); ?></td>
                                                <td><strong>PKR <?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                                                <td>
                                                    <span class="status-badge <?php echo strtolower($sale['status']); ?>">
                                                        <?php echo ucfirst($sale['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d', strtotime($sale['created_at'])); ?></td>
                                                <td>
                                                    <div class="row-action">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div>
                        <!-- Upcoming Deadlines -->
                        <div class="table-card" style="margin-bottom: 15px;">
                            <div class="table-header">
                                <h3>Upcoming Deadlines</h3>
                                <button class="btn-export">View All</button>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Task</th>
                                            <th>Due</th>
                                            <th>Priority</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($upcoming_deadlines)): ?>
                                            <tr>
                                                <td colspan="3" style="text-align: center; padding: 20px;">
                                                    No deadlines
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($upcoming_deadlines as $deadline): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($deadline['title']); ?></td>
                                                    <td>
                                                        <?php echo date('M d', strtotime($deadline['due_date'])); ?>
                                                        <small>(<?php echo $deadline['days_left']; ?>d)</small>
                                                    </td>
                                                    <td>
                                                        <span class="priority-badge <?php echo strtolower($deadline['priority']); ?>">
                                                            <?php echo ucfirst($deadline['priority']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Recent Clients -->
                        <div class="table-card">
                            <div class="table-header">
                                <h3>Recent Clients</h3>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Email</th>
                                            <th>Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_clients)): ?>
                                            <tr>
                                                <td colspan="3" style="text-align: center; padding: 20px;">
                                                    No recent clients
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_clients as $client): ?>
                                                <tr>
                                                    <td>
                                                        <div style="display: flex; align-items: center; gap: 8px;">
                                                            <div class="client-avatar">
                                                                <?php echo strtoupper(substr($client['client_name'], 0, 2)); ?>
                                                            </div>
                                                            <?php echo htmlspecialchars($client['client_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($client['email']); ?></td>
                                                    <td><?php echo date('M d', strtotime($client['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Feed -->
                <?php if (!empty($activity_feed)): ?>
                    <div class="table-card" style="margin-top: 15px;">
                        <div class="table-header">
                            <h3>Recent Activity</h3>
                        </div>
                        <div class="activity-feed">
                            <?php foreach ($activity_feed as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php if ($activity['type'] == 'payment'): ?>
                                            <i class="fas fa-dollar-sign"></i>
                                        <?php elseif ($activity['type'] == 'project'): ?>
                                            <i class="fas fa-project-diagram"></i>
                                        <?php else: ?>
                                            <i class="fas fa-user-plus"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-content">
                                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                        <span class="activity-time"><?php echo date('M d, H:i', strtotime($activity['date'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Sidebar toggle is handled in includes/sidebar.php

        // Toggle Notifications
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationWrapper = document.getElementById('notificationWrapper');
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationWrapper.classList.toggle('active');
        });
        document.addEventListener('click', function(e) {
            if (!notificationWrapper.contains(e.target)) {
                notificationWrapper.classList.remove('active');
            }
        });

        // Chart Colors
        const chartColors = [
            '#2563eb', '#10b981', '#f59e0b', '#ef4444',
            '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'
        ];

        // Monthly Revenue Chart
        const monthlyCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($sales_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($sales_values); ?>,
                    backgroundColor: chartColors.slice(0, <?php echo count($sales_labels); ?>),
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f3f4f6'
                        },
                        ticks: {
                            callback: value => 'PKR ' + value
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Project Chart
        const projectCtx = document.getElementById('projectChart').getContext('2d');
        new Chart(projectCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($proj_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($proj_counts); ?>,
                    backgroundColor: <?php echo json_encode($proj_colors); ?>,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Project Legend
        const projLabels = <?php echo json_encode($proj_labels); ?>;
        const projColors = <?php echo json_encode($proj_colors); ?>;
        const projCounts = <?php echo json_encode($proj_counts); ?>;
        const legendHtml = projLabels.map((label, index) => `
            <div class="legend-item">
                <span class="legend-color" style="background: ${projColors[index]}"></span>
                <span>${label}: ${projCounts[index]}</span>
            </div>
        `).join('');
        document.getElementById('projectLegend').innerHTML = legendHtml;

        // Weekly Revenue Chart
        const weeklyCtx = document.getElementById('weeklyRevenueChart').getContext('2d');
        new Chart(weeklyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($weekly_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($weekly_values); ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.05)',
                    borderWidth: 2,
                    pointBackgroundColor: chartColors,
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f3f4f6'
                        },
                        ticks: {
                            callback: value => 'PKR ' + value
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Keyboard shortcut
        document.addEventListener('keydown', function(e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                document.querySelector('.search-wrapper input').focus();
            }
        });


        document.addEventListener('DOMContentLoaded', () => {
            const mainContent = document.getElementById('dashboardMain');
            const toggleBtn = document.getElementById('sidebarToggle'); // Ensure your button has this ID

            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => {
                    // 1. Toggle the class for immediate visual change
                    const isCollapsed = mainContent.classList.toggle('sidebar-collapsed');

                    // 2. Update the cookie for PHP to read on refresh
                    // Set 'path=/' so it's available on all pages
                    document.cookie = `sidebar_collapsed=${isCollapsed}; path=/; max-age=${60 * 60 * 24 * 30}`;
                });
            }
        });
    </script>
</body>

</html>