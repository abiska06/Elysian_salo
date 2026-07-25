<?php
require_once __DIR__ . '/config/db.php';
require_login();
require_role('staff');

$staffId = $_SESSION['staff_id'] ?? null;
$today = date('Y-m-d');

// Fetch Appointments (Today & Upcoming)
$appointments = [];
try {
    if ($staffId) {
        $stmt = $pdo->prepare("
            SELECT a.*, c.name as customer_name, c.phone as customer_phone, s.service_name, s.duration 
            FROM appointments a 
            LEFT JOIN customers c ON a.customer_id = c.customer_id 
            LEFT JOIN services s ON a.service_id = s.service_id 
            WHERE a.staff_id = ? AND a.appointment_date >= ?
            ORDER BY a.appointment_date ASC
        ");
        $stmt->execute([$staffId, $today]);
        $appointments = $stmt->fetchAll();
    }
} catch (Exception $e) {}

// Fetch Services (Read Only)
$services = [];
try {
    $services = $pdo->query("SELECT * FROM services ORDER BY service_name ASC")->fetchAll();
} catch (Exception $e) {}

// Update Status Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $apptId = intval($_POST['appointment_id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    try {
        $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?")->execute([$status, $apptId]);
        if (strtolower($status) === 'completed' && $apptId) {
            try {
                $pcols = get_table_columns($pdo, 'payments');
                if (!in_array('payment_id', $pcols, true)) { try { $pdo->exec("ALTER TABLE payments ADD COLUMN payment_id INT AUTO_INCREMENT PRIMARY KEY"); } catch (Throwable $e) {} }
                if (!in_array('method', $pcols, true)) { try { $pdo->exec("ALTER TABLE payments ADD COLUMN method VARCHAR(20) NULL"); } catch (Throwable $e) {} }
                if (!in_array('status', $pcols, true)) { try { $pdo->exec("ALTER TABLE payments ADD COLUMN status ENUM('paid','pending','partial','failed') DEFAULT 'paid'"); } catch (Throwable $e) {} }
                if (!in_array('created_at', $pcols, true)) { try { $pdo->exec("ALTER TABLE payments ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {} }
                if (!in_array('receipt_no', $pcols, true)) { try { $pdo->exec("ALTER TABLE payments ADD COLUMN receipt_no VARCHAR(50) NULL"); } catch (Throwable $e) {} }
                $chk = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE appointment_id = ?");
                $chk->execute([$apptId]);
                if (intval($chk->fetchColumn()) === 0) {
                    $q = $pdo->prepare("SELECT a.appointment_id, a.final_price, a.voucher_code, s.price AS base_price FROM appointments a LEFT JOIN services s ON a.service_id = s.service_id WHERE a.appointment_id = ?");
                    $q->execute([$apptId]);
                    $row = $q->fetch();
                    $amount = $row ? (isset($row['final_price']) && $row['final_price'] !== null ? floatval($row['final_price']) : floatval($row['base_price'])) : 0.0;
                    $rcSeed = date('Ymd');
                    $rcCount = $pdo->query("SELECT COUNT(*) FROM payments WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                    $receipt = 'RCPT-' . $rcSeed . '-' . str_pad((string)($rcCount+1), 4, '0', STR_PAD_LEFT);
                    $ins = $pdo->prepare("INSERT INTO payments (appointment_id, amount, payment_date, method, status, receipt_no) VALUES (?, ?, NOW(), ?, 'paid', ?)");
                    $ins->execute([$apptId, $amount, 'cash', $receipt]);
                }
            } catch (Throwable $e) {}
        }
        header("Location: dashboard_staff.php");
        exit;
    } catch (Exception $e) {}
}

$activeTab = $_GET['tab'] ?? 'appointments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Elysian</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="theme-staff">
    <nav class="navbar" style="background-color: var(--primary-color);">
        <div class="container nav-content">
            <div class="logo">
                <div class="logo">
                <img src="assets/img/logo.png" class="logo-img" alt="Elysian Salon logo">
                <!-- <span class="logo-text">Elysian Salon</span> -->
            </div>
                
            </div>
            <div class="nav-links">
                
                <a href="?tab=appointments" class="<?php echo $activeTab === 'appointments' ? 'active-link' : ''; ?>">My Appointments</a>
                <a href="?tab=services" class="<?php echo $activeTab === 'services' ? 'active-link' : ''; ?>">Service List</a>
                <a href="account.php" >Account</a>
                <a href="auth/logout.php" class="btn btn-small" style="background:#fff; color:var(--primary-color);">Logout</a>
               
            </div>
        </div>
    </nav>

    <div class="container" style="padding-top: 40px;">
        <?php if ($activeTab === 'appointments'): ?>
            <h2 class="section-title">Staff Dashboard</h2>
            <div class="card">
                    <h3>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h3>
                    <p>View your appointments and update service status here.</p>
                </div>
            <h2 class="section-title">My Appointments (Today & Upcoming)</h2>
            
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($appointments)): ?>
                                <tr><td colspan="6" class="text-center">No upcoming appointments.</td></tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $appt): ?>
                                    <tr>
                                        <td><?php echo date('M d, h:i A', strtotime($appt['appointment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($appt['customer_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($appt['customer_phone'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($appt['service_name']); ?> (<?php echo $appt['duration']; ?>m)</td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($appt['status']); ?>">
                                                <?php echo ucfirst($appt['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php $st = strtolower(trim($appt['status'] ?? '')); if (in_array($st, ['pending','confirmed'])): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" name="update_status" class="btn btn-small btn-primary">Mark Completed</button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color:#aaa;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($activeTab === 'services'): ?>
            <h2 class="section-title">Service List (Read-Only)</h2>
            <div class="grid-3">
                <?php foreach ($services as $svc): ?>
                    <div class="info-card">
                        <h3><?php echo htmlspecialchars($svc['service_name']); ?></h3>
                        <p class="price">NPR <?php echo htmlspecialchars($svc['price']); ?></p>
                        <p class="duration"><?php echo htmlspecialchars($svc['duration']); ?> mins</p>
                        <p style="font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($svc['description'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
