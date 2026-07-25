<?php
require_once __DIR__ . '/config/db.php';
require_login();
require_role('customer');

$custId = $_SESSION['customer_id'] ?? null;
$staffId = intval($_POST['staff_id'] ?? 0);
$date = trim($_POST['date'] ?? '');
$time = trim($_POST['time'] ?? '');

if (!$custId || !$staffId || !$date || !$time) {
    header("Location: dashboard_customer.php");
    exit;
}

$dateTime = $date . ' ' . $time . ':00';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        staff_id INT NOT NULL,
        service_id INT NULL,
        date_time DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $stmt = $pdo->prepare("INSERT INTO appointments (customer_id, staff_id, service_id, date_time, status) VALUES (?, ?, NULL, ?, 'pending')");
    $stmt->execute([$custId, $staffId, $dateTime]);
} catch (Exception $e) {
    // swallow for now
}

header("Location: dashboard_customer.php");
exit;
?>
