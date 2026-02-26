<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../helpers/auth_helper.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();

// Ensure all pending reminders are set to email so they process automatically
$stmt = $pdo->prepare("UPDATE reminders SET medium = 'email' WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);

echo "All pending reminders have been updated to 'Email' medium. They should now send automatically when you visit the dashboard if they are due.";
echo "<br><a href='index.php'>Go back to Reminders</a>";
?>
