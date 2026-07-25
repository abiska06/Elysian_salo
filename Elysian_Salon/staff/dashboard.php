<?php
require_once '../config/db.php';
require_role('staff');

$staff_id = $_SESSION['staff_id'];

// Fetch Assigned Appointments
try {
    $stmt = $pdo->prepare("
        SELECT a.id, c.name as customer_name, s.name as service_name, a.appointment_date, a.status 
        FROM appointments a 
        JOIN customers c ON a.customer_id = c.id 
        JOIN services s ON a.service_id = s.id 
        WHERE a.staff_id = ? 
        ORDER BY a.appointment_date ASC
    ");
    $stmt->execute([$staff_id]);
    $appointments = $stmt->fetchAll();
} catch (Exception $e) {
    $appointments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Elysian Salon</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container nav-content">
            <div class="logo">
                <img src="../assets/img/logo.svg" class="logo-img" alt="Elysian Salon logo">
                <span class="logo-text">Elysian Salon (Staff)</span>
            </div>
            <div class="nav-links">
                <span>Hello, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="../auth/logout.php" class="btn btn-small btn-primary">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 class="mt-2">My Schedule</h2>

        <div class="card mt-2">
            <h3>Assigned Appointments</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr><td colspan="4" class="text-center">No appointments assigned to you.</td></tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $appt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($appt['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($appt['appointment_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($appt['status']); ?>">
                                            <?php echo ucfirst($appt['status']); ?>
                                        </span>
                                    </td>
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
