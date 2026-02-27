<?php
require 'config/db.php';
$user_id = 1;

$queries = [
    "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE user_id = ? AND status = 'completed' AND payment_date >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01') AND payment_date <= LAST_DAY(CURRENT_DATE())",
    "SELECT COUNT(*) FROM clients WHERE user_id = ?",
    "SELECT COUNT(*) FROM projects WHERE user_id = ? AND status = 'active'",
    "SELECT COUNT(*) FROM invoices WHERE user_id = ? AND status = 'overdue'",
    "SELECT COUNT(*) FROM invoices WHERE user_id = ? AND status = 'pending'",
    "SELECT COUNT(*) FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.user_id = ? AND MONTH(p.payment_date) = MONTH(CURRENT_DATE()) AND YEAR(p.payment_date) = YEAR(CURRENT_DATE())",
    "SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.user_id = ?",
    "SELECT COALESCE(SUM(CASE WHEN MONTH(payment_date) = MONTH(CURRENT_DATE()) THEN amount ELSE 0 END), 0) as this_month, COALESCE(SUM(CASE WHEN MONTH(payment_date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) THEN amount ELSE 0 END), 0) as last_month FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.user_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)",
    "SELECT DATE_FORMAT(payment_date, '%b') as month, DATE_FORMAT(payment_date, '%Y-%m') as month_sort, COALESCE(SUM(amount), 0) as total FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.user_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY month, month_sort ORDER BY month_sort ASC",
    "SELECT DATE_FORMAT(payment_date, '%a') as day, COALESCE(SUM(amount), 0) as total FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.user_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK) GROUP BY day ORDER BY MIN(payment_date) ASC",
    "SELECT status, COUNT(*) as count FROM projects WHERE user_id = ? GROUP BY status",
    "SELECT i.invoice_number, c.client_name, i.total_amount, i.status, i.created_at, COALESCE(p.amount, 0) as paid_amount, p.payment_date, DATEDIFF(CURDATE(), i.due_date) as days_overdue FROM invoices i JOIN clients c ON i.client_id = c.id LEFT JOIN payments p ON i.id = p.invoice_id WHERE i.user_id = ? ORDER BY i.created_at DESC LIMIT 8",
    "SELECT title, due_date, priority, DATEDIFF(due_date, CURDATE()) as days_left FROM reminders WHERE user_id = ? AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND status = 'pending' ORDER BY due_date ASC LIMIT 6",
    "SELECT client_name, email, created_at, company, phone FROM clients WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    "SELECT p.name as project_name, c.client_name, p.budget, p.status, p.progress FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.user_id = ? ORDER BY p.budget DESC LIMIT 5",
    "(SELECT 'payment' as type, CONCAT('Payment received from ', c.client_name) as description, p.payment_date as date FROM payments p JOIN invoices i ON p.invoice_id = i.id JOIN clients c ON i.client_id = c.id WHERE i.user_id = ? ORDER BY p.payment_date DESC LIMIT 3) UNION ALL (SELECT 'project' as type, CONCAT('Project \"', name, '\" ', status) as description, updated_at as date FROM projects WHERE user_id = ? ORDER BY updated_at DESC LIMIT 3) UNION ALL (SELECT 'client' as type, CONCAT('New client: ', client_name) as description, created_at as date FROM clients WHERE user_id = ? ORDER BY created_at DESC LIMIT 3) ORDER BY 3 DESC LIMIT 6",
    "SELECT COUNT(*) FROM reminders WHERE user_id = ? AND status = 'pending' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)",
    "SELECT SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_total, SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as pending_total, SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END) as overdue_total FROM invoices WHERE user_id = ?"
];

foreach ($queries as $i => $q) {
    try {
        $stmt = $pdo->prepare($q);
        // some queries have 3 params in union all
        if (substr_count($q, '?') == 3) {
            $stmt->execute([$user_id, $user_id, $user_id]);
        } else {
            $stmt->execute([$user_id]);
        }
        echo "Query $i: OK\n";
    } catch (Exception $e) {
        echo "Query $i: FAILED: " . $e->getMessage() . "\n";
    }
}
