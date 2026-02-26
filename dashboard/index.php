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
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.amount), 0) FROM payments p 
        JOIN invoices i ON p.invoice_id = i.id 
        WHERE i.user_id = ? AND MONTH(p.payment_date) = MONTH(CURRENT_DATE()) AND YEAR(p.payment_date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$user_id]);
    $earnings_month = $stmt->fetchColumn() ?: 0;

    // 2. Total Clients
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_clients = $stmt->fetchColumn() ?: 0;

    // 3. Active Projects
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $active_projects = $stmt->fetchColumn() ?: 0;

    // 4. Overdue Invoices
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? AND status = 'overdue'");
    $stmt->execute([$user_id]);
    $overdue_invoices = $stmt->fetchColumn() ?: 0;

    // 5. Pending Invoices
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_invoices = $stmt->fetchColumn() ?: 0;

    // 6. Paid Invoices (This Month)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM payments p 
        JOIN invoices i ON p.invoice_id = i.id 
        WHERE i.user_id = ? AND MONTH(p.payment_date) = MONTH(CURRENT_DATE()) AND YEAR(p.payment_date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$user_id]);
    $paid_invoices = $stmt->fetchColumn() ?: 0;

    // 7. Total Revenue (All Time)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.amount), 0) FROM payments p 
        JOIN invoices i ON p.invoice_id = i.id 
        WHERE i.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $total_revenue = $stmt->fetchColumn() ?: 0;

    // 8. This Month vs Last Month Growth
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN MONTH(payment_date) = MONTH(CURRENT_DATE()) THEN amount ELSE 0 END), 0) as this_month,
            COALESCE(SUM(CASE WHEN MONTH(payment_date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) THEN amount ELSE 0 END), 0) as last_month
        FROM payments p
        JOIN invoices i ON p.invoice_id = i.id
        WHERE i.user_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
    ");
    $stmt->execute([$user_id]);
    $growth_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $growth_percentage = 0;
    if ($growth_data['last_month'] > 0) {
        $growth_percentage = round((($growth_data['this_month'] - $growth_data['last_month']) / $growth_data['last_month']) * 100, 1);
    }

    // Chart Data - Monthly Revenue for Last 6 Months
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(payment_date, '%b') as month,
            DATE_FORMAT(payment_date, '%Y-%m') as month_sort,
            COALESCE(SUM(amount), 0) as total
        FROM payments p
        JOIN invoices i ON p.invoice_id = i.id
        WHERE i.user_id = ? 
            AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month, month_sort
        ORDER BY month_sort ASC
    ");
    $stmt->execute([$user_id]);
    $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sales_labels = [];
    $sales_values = [];
    foreach ($sales_data as $row) { 
        $sales_labels[] = $row['month']; 
        $sales_values[] = (float)$row['total']; 
    }
    
    if (empty($sales_labels)) { 
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        $sales_labels = $months; 
        $sales_values = [0, 0, 0, 0, 0, 0]; 
    }

    // Weekly Revenue Data
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(payment_date, '%a') as day,
            COALESCE(SUM(amount), 0) as total
        FROM payments p
        JOIN invoices i ON p.invoice_id = i.id
        WHERE i.user_id = ? 
            AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)
        GROUP BY day
        ORDER BY MIN(payment_date) ASC
    ");
    $stmt->execute([$user_id]);
    $weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $weekly_labels = [];
    $weekly_values = [];
    foreach ($weekly_data as $row) { 
        $weekly_labels[] = $row['day']; 
        $weekly_values[] = (float)$row['total']; 
    }
    
    if (empty($weekly_labels)) { 
        $weekly_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']; 
        $weekly_values = [0, 0, 0, 0, 0, 0, 0]; 
    }

    // Project Status Distribution
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM projects 
        WHERE user_id = ? 
        GROUP BY status
    ");
    $stmt->execute([$user_id]);
    $proj_dist = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $status_config = [
        'active' => ['label' => 'Active', 'color' => '#10b981', 'bg' => '#d1fae5'],
        'pending' => ['label' => 'Pending', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
        'completed' => ['label' => 'Completed', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
        'on_hold' => ['label' => 'On Hold', 'color' => '#ef4444', 'bg' => '#fee2e2'],
        'cancelled' => ['label' => 'Cancelled', 'color' => '#6b7280', 'bg' => '#f3f4f6']
    ];
    
    $proj_labels = [];
    $proj_counts = [];
    $proj_colors = [];
    $proj_bg_colors = [];
    
    foreach ($proj_dist as $row) { 
        $status = $row['status'];
        $proj_labels[] = $status_config[$status]['label'] ?? ucfirst($status);
        $proj_counts[] = (int)$row['count'];
        $proj_colors[] = $status_config[$status]['color'] ?? '#6b7280';
        $proj_bg_colors[] = $status_config[$status]['bg'] ?? '#f3f4f6';
    }

    // Invoice Status Distribution
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count,
               SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_amount,
               SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as pending_amount,
               SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END) as overdue_amount
        FROM invoices 
        WHERE user_id = ? 
        GROUP BY status
    ");
    $stmt->execute([$user_id]);
    $invoice_dist = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $invoice_statuses = [];
    $invoice_counts = [];
    $invoice_amounts = [];
    $invoice_colors = [
        'paid' => '#10b981',
        'pending' => '#f59e0b',
        'overdue' => '#ef4444'
    ];
    
    foreach ($invoice_dist as $row) {
        $invoice_statuses[] = ucfirst($row['status']);
        $invoice_counts[] = (int)$row['count'];
    }

    // Recent Transactions with more details
    $stmt = $pdo->prepare("
        SELECT 
            i.invoice_number,
            c.client_name,
            i.total_amount,
            i.status,
            i.created_at,
            COALESCE(p.amount, 0) as paid_amount,
            p.payment_date,
            DATEDIFF(CURDATE(), i.due_date) as days_overdue
        FROM invoices i 
        JOIN clients c ON i.client_id = c.id 
        LEFT JOIN payments p ON i.id = p.invoice_id
        WHERE i.user_id = ? 
        ORDER BY i.created_at DESC 
        LIMIT 8
    ");
    $stmt->execute([$user_id]);
    $recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Upcoming Deadlines (Next 14 days)
    $stmt = $pdo->prepare("
        SELECT 
            title,
            due_date,
            priority,
            DATEDIFF(due_date, CURDATE()) as days_left
        FROM reminders 
        WHERE user_id = ? 
            AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
            AND status = 'pending'
        ORDER BY due_date ASC 
        LIMIT 6
    ");
    $stmt->execute([$user_id]);
    $upcoming_deadlines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent Clients with company
    $stmt = $pdo->prepare("
        SELECT 
            client_name,
            email,
            created_at,
            company,
            phone
        FROM clients 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top Projects by Budget
    $stmt = $pdo->prepare("
        SELECT 
            p.name as project_name,
            c.client_name,
            p.budget,
            p.status,
            p.progress
        FROM projects p
        JOIN clients c ON p.client_id = c.id
        WHERE p.user_id = ?
        ORDER BY p.budget DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $top_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent Activity Feed
$stmt = $pdo->prepare("
    (SELECT 'payment' as type, CONCAT('Payment received from ', c.client_name) as description, p.payment_date as date
     FROM payments p
     JOIN invoices i ON p.invoice_id = i.id
     JOIN clients c ON i.client_id = c.id
     WHERE i.user_id = ?
     ORDER BY p.payment_date DESC LIMIT 3)
    UNION ALL
    (SELECT 'project' as type, CONCAT('Project \"', name, '\" ', status) as description, updated_at as date
     FROM projects
     WHERE user_id = ?
     ORDER BY updated_at DESC LIMIT 3)
    UNION ALL
    (SELECT 'client' as type, CONCAT('New client: ', client_name) as description, created_at as date
     FROM clients
     WHERE user_id = ?
     ORDER BY created_at DESC LIMIT 3)
    ORDER BY 3 DESC LIMIT 6
");

    $stmt->execute([$user_id, $user_id, $user_id]);
    $activity_feed = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Notification count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reminders 
        WHERE user_id = ? 
            AND status = 'pending' 
            AND due_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ");
    $stmt->execute([$user_id]);
    $notification_count = $stmt->fetchColumn() ?: 0;

    // Currency totals by status
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_total,
            SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as pending_total,
            SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END) as overdue_total
        FROM invoices 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $invoice_totals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $paid_total = $invoice_totals['paid_total'] ?? 0;
    $pending_total = $invoice_totals['pending_total'] ?? 0;
    $overdue_total = $invoice_totals['overdue_total'] ?? 0;

} catch (Exception $e) { 
    error_log("Dashboard Error: " . $e->getMessage());
    $earnings_month = $total_clients = $active_projects = $overdue_invoices = $total_revenue = $pending_invoices = $paid_invoices = 0;
    $recent_sales = $upcoming_deadlines = $recent_clients = $top_projects = $activity_feed = [];
    $notification_count = 0;
    $growth_percentage = 0;
    $paid_total = $pending_total = $overdue_total = 0;
    $sales_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    $sales_values = [0, 0, 0, 0, 0, 0];
    $weekly_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $weekly_values = [0, 0, 0, 0, 0, 0, 0];
}
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
        /* ===== PROFESSIONAL DASHBOARD WITH COLORFUL CHARTS ===== */
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
            
            /* Chart Colors - Vibrant but Professional */
            --chart-1: #2563eb;    /* Blue */
            --chart-2: #10b981;    /* Green */
            --chart-3: #f59e0b;    /* Orange */
            --chart-4: #ef4444;    /* Red */
            --chart-5: #8b5cf6;    /* Purple */
            --chart-6: #ec4899;    /* Pink */
            --chart-7: #14b8a6;    /* Teal */
            --chart-8: #f97316;    /* Orange */
            
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
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            
            /* Transitions */
            --transition: all 0.2s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-main);
            color: var(--gray-800);
            overflow-x: hidden;
        }

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Main Content */
        .dashboard-main {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
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
            padding: 0 30px;
            height: 70px;
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
            gap: 20px;
        }

        .menu-toggle {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--border-light);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.1rem;
        }

        .menu-toggle:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
            color: var(--gray-900);
        }

        .page-title {
            font-size: 1.25rem;
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
            gap: 20px;
        }

        /* Search Bar */
        .search-wrapper {
            position: relative;
            width: 300px;
        }

        .search-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 0.9rem;
        }

        .search-wrapper input {
            width: 100%;
            height: 40px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 0 40px 0 35px;
            font-size: 0.9rem;
            background: white;
        }

        .search-wrapper input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-shortcut {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--gray-100);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            color: var(--gray-500);
        }

        /* Notifications */
        .notification-wrapper {
            position: relative;
        }

        .notification-btn {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--border-light);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .notification-btn:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--danger);
            color: white;
            font-size: 0.65rem;
            font-weight: 600;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }

        .notification-dropdown {
            position: absolute;
            top: 50px;
            right: 0;
            width: 340px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-xl);
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
            padding: 16px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .notification-header h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .notification-header span {
            font-size: 0.75rem;
            color: var(--primary);
            font-weight: 500;
            cursor: pointer;
        }

        .notification-list {
            max-height: 320px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--gray-100);
        }

        .notification-item:hover {
            background: var(--gray-50);
        }

        .notification-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-content h5 {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 2px;
        }

        .notification-content p {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        /* User Menu */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 4px 4px 12px;
            background: white;
            border: 1px solid var(--border-light);
            border-radius: 40px;
            cursor: pointer;
        }

        .user-menu:hover {
            border-color: var(--gray-300);
            background: var(--gray-50);
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-800);
            display: block;
        }

        .user-role {
            font-size: 0.7rem;
            color: var(--gray-500);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 36px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Dashboard Container */
        .dashboard-container {
            padding: 30px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Welcome Section */
        .welcome-section {
            margin-bottom: 30px;
        }

        .welcome-section h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 5px;
        }

        .welcome-section p {
            color: var(--gray-500);
            font-size: 1rem;
        }

        /* Stats Overview */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-card .label {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-bottom: 5px;
        }

        .stat-card .value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .stat-card .trend {
            font-size: 0.75rem;
            color: var(--success);
            background: var(--success-bg);
            padding: 2px 8px;
            border-radius: 20px;
            margin-top: 5px;
            display: inline-block;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .stat-icon.blue {
            background: var(--primary-bg);
            color: var(--primary);
        }

        .stat-icon.green {
            background: var(--success-bg);
            color: var(--success);
        }

        .stat-icon.orange {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .stat-icon.red {
            background: var(--danger-bg);
            color: var(--danger);
        }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 30px 0;
        }

        .kpi-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--border-light);
            transition: var(--transition);
        }

        .kpi-card:hover {
            box-shadow: var(--shadow-lg);
            border-color: var(--gray-300);
        }

        .kpi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
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

        .kpi-icon.red {
            background: var(--card-red);
        }

        .kpi-icon.purple {
            background: var(--card-purple);
        }

        .kpi-badge {
            padding: 4px 10px;
            background: var(--gray-100);
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--gray-600);
        }

        .kpi-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 5px;
        }

        .kpi-label {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-bottom: 15px;
        }

        .kpi-footer {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.75rem;
        }

        .trend-up {
            color: var(--success);
            background: var(--success-bg);
            padding: 2px 8px;
            border-radius: 20px;
        }

        .trend-down {
            color: var(--danger);
            background: var(--danger-bg);
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr 1.2fr;
            gap: 20px;
            margin: 30px 0;
        }

        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--border-light);
        }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .chart-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .chart-period {
            padding: 4px 10px;
            background: var(--gray-100);
            border-radius: 20px;
            font-size: 0.7rem;
            color: var(--gray-600);
        }

        .chart-container {
            height: 240px;
            position: relative;
        }

        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-light);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--gray-600);
        }

        .legend-color {
            width: 10px;
            height: 10px;
            border-radius: 4px;
        }

        /* Tables Grid */
        .tables-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
            margin: 30px 0;
        }

        .table-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        .table-search {
            position: relative;
        }

        .table-search i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 0.8rem;
        }

        .table-search input {
            height: 34px;
            padding: 0 10px 0 30px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            font-size: 0.8rem;
            background: var(--gray-50);
        }

        .btn-export {
            height: 34px;
            padding: 0 12px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            background: white;
            color: var(--gray-700);
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-export:hover {
            background: var(--gray-100);
        }

        .table-responsive {
            overflow-x: auto;
            max-height: 340px;
            overflow-y: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 12px 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--border-light);
        }

        .data-table td {
            padding: 12px 20px;
            font-size: 0.85rem;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
        }

        .data-table tr:hover td {
            background: var(--gray-50);
        }

        /* Status Badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
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

        .status-badge.active {
            background: var(--info-bg);
            color: var(--info);
        }

        .priority-badge {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
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
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .progress-bar {
            width: 100px;
            height: 6px;
            background: var(--gray-200);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 3px;
        }

        .row-action {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
            cursor: pointer;
        }

        .row-action:hover {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        /* Activity Feed */
        .activity-feed {
            padding: 0 20px 20px;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-content p {
            font-size: 0.85rem;
            color: var(--gray-700);
            margin-bottom: 2px;
        }

        .activity-time {
            font-size: 0.7rem;
            color: var(--gray-500);
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
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
            
            .stats-grid,
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            
            .search-wrapper {
                width: 200px;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease forwards;
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
                    <button class="menu-toggle" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
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
                            <span class="user-role">Administrator</span>
                        </div>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 2)); ?>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Dashboard Content -->
            <div class="dashboard-container">

                <!-- Welcome Section -->
                <div class="welcome-section animate-fade-in">
                    <h1>Good <?php echo date('a') == 'am' ? 'morning' : 'afternoon'; ?>, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?> 👋</h1>
                    <p>Here's what's happening with your business today.</p>
                </div>

                
                      
                            

                <!-- KPI Cards -->
                <div class="kpi-grid">
                    <!-- Revenue Card -->
                    <div class="kpi-card animate-fade-in" style="animation-delay: 0.1s">
                        <div class="kpi-header">
                            <div class="kpi-icon blue">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <span class="kpi-badge">This Month</span>
                        </div>
                        <div class="kpi-value">$<?php echo number_format($earnings_month, 2); ?></div>
                        <div class="kpi-label">Monthly Revenue</div>
                        <div class="kpi-footer">
                            <span class="trend-up">
                                <i class="fas fa-arrow-up"></i> <?php echo abs($growth_percentage); ?>%
                            </span>
                            <span>vs last month</span>
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
                        <div class="kpi-value"><?php echo number_format($total_clients); ?></div>
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
                            <span class="kpi-badge">Active</span>
                        </div>
                        <div class="kpi-value"><?php echo number_format($active_projects); ?></div>
                        <div class="kpi-label">Active Projects</div>
                        <div class="kpi-footer">
                            <span><?php echo $active_projects > 0 ? 'In progress' : 'No active projects'; ?></span>
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

                <!-- Charts Grid - Colorful Charts -->
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
                                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--gray-500);">
                                                No recent transactions
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_sales as $sale): ?>
                                            <tr>
                                                <td><strong>#<?php echo htmlspecialchars($sale['invoice_number']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($sale['client_name']); ?></td>
                                                <td><strong>$<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                                                <td>
                                                    <span class="status-badge <?php echo strtolower($sale['status']); ?>">
                                                        <?php echo ucfirst($sale['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></td>
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

                    <!-- Right Column: Deadlines, Clients, Activity -->
                    <div>
                        <!-- Upcoming Deadlines -->
                        <div class="table-card" style="margin-bottom: 20px;">
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
                                                    No upcoming deadlines
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($upcoming_deadlines as $deadline): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($deadline['title']); ?></td>
                                                    <td>
                                                        <?php echo date('M d', strtotime($deadline['due_date'])); ?>
                                                        <small style="color: var(--gray-500); display: block;">
                                                            (<?php echo $deadline['days_left']; ?> days)
                                                        </small>
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
                        <div class="table-card" style="margin-bottom: 20px;">
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
                                                        <div style="display: flex; align-items: center; gap: 10px;">
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

                        <!-- Top Projects -->
                        <?php if (!empty($top_projects)): ?>
                        <div class="table-card">
                            <div class="table-header">
                                <h3>Top Projects</h3>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Project</th>
                                            <th>Budget</th>
                                            <th>Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_projects as $project): ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($project['project_name']); ?></div>
                                                    <small style="color: var(--gray-500);"><?php echo htmlspecialchars($project['client_name']); ?></small>
                                                </td>
                                                <td><strong>$<?php echo number_format($project['budget']); ?></strong></td>
                                                <td>
                                                    <div class="progress-bar">
                                                        <div class="progress-fill" style="width: <?php echo $project['progress'] ?? 0; ?>%"></div>
                                                    </div>
                                                    <small style="font-size: 0.65rem;"><?php echo $project['progress'] ?? 0; ?>%</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Activity Feed -->
                <?php if (!empty($activity_feed)): ?>
                <div class="table-card" style="margin-top: 20px;">
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
        // Toggle Sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('professionalSidebar');
            const mainContent = document.getElementById('dashboardMain');

            if (sidebar) {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
                document.cookie = "sidebar_collapsed=" + sidebar.classList.contains('collapsed');
            }
        });

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

        // Chart Colors - Vibrant Palette
        const chartColors = [
            '#2563eb', // Blue
            '#10b981', // Green
            '#f59e0b', // Orange
            '#ef4444', // Red
            '#8b5cf6', // Purple
            '#ec4899', // Pink
            '#14b8a6', // Teal
            '#f97316'  // Orange
        ];

        // 1. Monthly Revenue Chart - Bar Chart with Multiple Colors
        const monthlyCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($sales_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($sales_values); ?>,
                    backgroundColor: chartColors.slice(0, <?php echo count($sales_labels); ?>),
                    borderRadius: 4,
                    barPercentage: 0.6,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' $' + context.raw.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f3f4f6' },
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Project Distribution Chart - Doughnut with Status Colors
        const projectCtx = document.getElementById('projectChart').getContext('2d');
        const projectChart = new Chart(projectCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($proj_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($proj_counts); ?>,
                    backgroundColor: <?php echo json_encode($proj_colors); ?>,
                    borderWidth: 0,
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.raw + ' projects';
                            }
                        }
                    }
                }
            }
        });

        // Project Legend
        const projLabels = <?php echo json_encode($proj_labels); ?>;
        const projColors = <?php echo json_encode($proj_colors); ?>;
        const projCounts = <?php echo json_encode($proj_counts); ?>;
        
        const legendHtml = projLabels.map((label, index) => {
            return `
                <div class="legend-item">
                    <span class="legend-color" style="background: ${projColors[index]}"></span>
                    <span>${label}: ${projCounts[index]}</span>
                </div>
            `;
        }).join('');
        
        document.getElementById('projectLegend').innerHTML = legendHtml;

        // 3. Weekly Revenue Chart - Line Chart with Gradient
        const weeklyCtx = document.getElementById('weeklyRevenueChart').getContext('2d');
        const weeklyGradient = weeklyCtx.createLinearGradient(0, 0, 0, 200);
        weeklyGradient.addColorStop(0, '#2563eb');
        weeklyGradient.addColorStop(1, '#8b5cf6');
        
        new Chart(weeklyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($weekly_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($weekly_values); ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.05)',
                    borderWidth: 3,
                    pointBackgroundColor: chartColors,
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' $' + context.raw;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f3f4f6' },
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    },
                    x: { grid: { display: false } }
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

        // Animate elements on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = 1;
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        });

        document.querySelectorAll('.kpi-card, .chart-card, .table-card, .stat-card').forEach(el => {
            el.style.opacity = 0;
            el.style.transform = 'translateY(10px)';
            el.style.transition = 'all 0.5s ease';
            observer.observe(el);
        });
    </script>
</body>

</html>