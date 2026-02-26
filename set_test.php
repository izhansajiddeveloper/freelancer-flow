<?php
require_once 'config/db.php';
$pdo->query("UPDATE reminders SET reminder_date = CURDATE() WHERE status = 'pending' LIMIT 1");
echo "One pending reminder moved to TODAY for testing purposes.\n";
?>
