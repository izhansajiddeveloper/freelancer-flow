<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'helpers/auth_helper.php';

$user_id = getCurrentUserId();
echo "<h2>Reminder Diagnostic (User ID: $user_id)</h2>";

$stmt = $pdo->prepare("SELECT * FROM reminders WHERE user_id = ?");
$stmt->execute([$user_id]);
$rems = $stmt->fetchAll();

echo "<table border='1'><tr><th>ID</th><th>Type</th><th>Medium</th><th>Date</th><th>Status</th><th>Due/Past?</th></tr>";
foreach ($rems as $r) {
    $due_past = (strtotime($r['reminder_date']) <= strtotime(date('Y-m-d'))) ? "YES" : "NO";
    echo "<tr>
            <td>{$r['id']}</td>
            <td>{$r['reminder_type']}</td>
            <td>{$r['medium']}</td>
            <td>{$r['reminder_date']}</td>
            <td>{$r['status']}</td>
            <td>$due_past</td>
          </tr>";
}
echo "</table>";

echo "<p>Current Server Date: " . date('Y-m-d') . "</p>";
echo "<p>Current Timezone: " . date_default_timezone_get() . "</p>";

include 'reminders/process_background.php';
echo "<p>Background processor attempted.</p>";
?>
