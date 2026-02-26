<?php
require_once 'config/config.php';
require_once 'config/db.php';

echo "Setting reminders to 'email' medium for testing...\n";
$stmt = $pdo->prepare("UPDATE reminders SET medium = 'email' WHERE status = 'pending'");
$stmt->execute();
echo "Updated " . $stmt->rowCount() . " rows.\n";

echo "Check reminders:\n";
$stmt = $pdo->query("SELECT id, user_id, status, medium, reminder_date FROM reminders");
$items = $stmt->fetchAll();
foreach ($items as $i) {
    echo "ID: {$i['id']}, User: {$i['user_id']}, Status: {$i['status']}, Medium: {$i['medium']}, Date: {$i['reminder_date']}\n";
}
?>
