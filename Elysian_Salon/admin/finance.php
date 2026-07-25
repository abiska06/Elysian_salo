<?php
require_once '../config/db.php';
require_role('admin');

$payments = [];
try {
    $stmt = $pdo->query("
        SELECT p.payment_id, p.amount, p.method, p.payment_date, c.name as customer_name, s.name as service_name 
        FROM payments p 
        LEFT JOIN appointments a ON p.appointment_id = a.id 
        LEFT JOIN customers c ON a.customer_id = c.customer_id 
        LEFT JOIN services s ON a.service_id = s.id 
        ORDER BY p.payment_date DESC
    ");
    // Note: check_db said customers key is customer_id. appointments key is id.
    // Let's verify appointments columns again to be safe about join keys.
    // appointments: id, customer_id, staff_id, service_id...
    // customers: customer_id...
    // So a.customer_id = c.customer_id is correct.
    
    $payments = $stmt->fetchAll();
} catch (Exception $e) {
    // $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Records - Elysian Salon</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container nav-content">
            <div class="logo">
                <a href="../dashboard_admin.php" style="text-decoration:none; color:inherit;">
                    <img src="../assets/img/logo.png" class="logo-img" alt="Elysian Salon logo">
                    <span class="logo-text">Elysian Admin</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="../dashboard_admin.php">Dashboard</a>
                <a href="../auth/logout.php" class="btn btn-small btn-primary">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 class="section-title">Finance Records</h2>
        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="6" class="text-center">No payment records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($pay['payment_id']); ?></td>
                                    <td><?php echo htmlspecialchars($pay['customer_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($pay['service_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($pay['amount']); ?></td>
                                    <td><?php echo htmlspecialchars($pay['method']); ?></td>
                                    <td><?php echo htmlspecialchars($pay['payment_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
