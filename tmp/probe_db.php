<?php
require_once 'c:\xampp\htdocs\freelance-flow\config\db.php';
$stmt = $pdo->query("DESCRIBE invoices");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>
