<?php
require_once 'config/config.php';
require_once 'config/db.php';
$user_id = 1; // Manual test for Izhan Sajid
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Manual execution of mail.php for User 1...\n";
include 'reminders/mail.php';
echo "Finished.\n";
?>
