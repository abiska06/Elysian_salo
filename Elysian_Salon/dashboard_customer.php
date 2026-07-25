<?php
require_once __DIR__ . '/config/db.php';
require_login();
require_role('customer');

// Fetch Services
$services = [];
try {
    $stmt = $pdo->query("SELECT * FROM services ORDER BY service_name ASC");
    $services = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch History
$history = [];
try {
    $custId = $_SESSION['customer_id'] ?? null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment']) && $custId) {
        $apptId = intval($_POST['appointment_id'] ?? 0);
        try {
            $check = $pdo->prepare("SELECT appointment_date, status FROM appointments WHERE appointment_id = ? AND customer_id = ?");
            $check->execute([$apptId, $custId]);
            $row = $check->fetch();
            if ($row) {
                $now = date('Y-m-d H:i:s');
                $eligible = (strtolower($row['status']) !== 'completed' && strtolower($row['status']) !== 'cancelled' && $row['appointment_date'] >= $now);
                if ($eligible) {
                    $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?")->execute([$apptId]);
                    header("Location: dashboard_customer.php?success=cancelled");
                    exit;
                } else {
                    header("Location: dashboard_customer.php?error=cannot_cancel");
                    exit;
                }
            } else {
                header("Location: dashboard_customer.php?error=not_found");
                exit;
            }
        } catch (Exception $e) {
            header("Location: dashboard_customer.php?error=cancel_failed");
            exit;
        }
    }
    if ($custId) {
        $sql = "
            SELECT a.appointment_id, a.appointment_date, a.status, s.service_name, COALESCE(a.final_price, s.price) AS price, a.voucher_code, u.name as staff_name 
            FROM appointments a 
            LEFT JOIN services s ON a.service_id = s.service_id 
            LEFT JOIN staff st ON a.staff_id = st.staff_id 
            LEFT JOIN users u ON st.user_id = u.user_id 
            WHERE a.customer_id = ? 
            ORDER BY a.appointment_date DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$custId]);
        $history = $stmt->fetchAll();
    }
} catch (Exception $e) {}

$userName = $_SESSION['name'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Elysian Salon</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero-customer {
            background: linear-gradient(135deg, #E6A4B4 0%, #F3D1DC 100%);
            border-radius: 0 0 40px 40px;
            padding: 60px 20px;
            text-align: center;
            color: #fff;
            margin-bottom: 40px;
            box-shadow: 0 10px 20px rgba(230, 164, 180, 0.3);
        }
        .hero-customer h1 { color: #fff; font-size: 2.5rem; margin-bottom: 10px; }
        .hero-customer p { color: #fff; font-size: 1.1rem; opacity: 0.9; }
        .service-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid #eee;
            transition: 0.3s;
        }
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            border-color: var(--primary-color);
        }
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="theme-customer">
    <nav class="navbar" style="background-color: var(--primary-color);">
        <div class="container nav-content">
            <div class="logo">
                <div class="logo">
                <img src="assets/img/logo.png" class="logo-img" alt="Elysian Salon logo">
                <!-- <span class="logo-text">Elysian Salon</span> -->
            </div>
            </div>
            <div class="nav-links">
                <a href="#services">Services</a>
                <a href="#book">Book Now</a>
                <a href="#history">My Appointments</a>
                <a href="account.php" >Account</a>
                <a href="auth/logout.php" class="btn btn-small" style="background:#fff; color:var(--primary-color);">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-customer">
        <h1>Welcome, <?php echo htmlspecialchars($userName); ?></h1>
        <p>Relax, Refresh, Revitalize. Book your next session today.</p>
    </div>

    <div class="container">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Appointment booked successfully!</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">Error: <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <!-- Services Section -->
        <div id="services" style="margin-bottom: 50px;">
            <h2 class="section-title" style="color: var(--primary-color);">Our Premium Services</h2>
            <div class="grid-3">
                <?php foreach ($services as $svc): ?>
                    <div class="service-card">
                        <h3 style="color: #4A4A4A;"><?php echo htmlspecialchars($svc['service_name']); ?></h3>
                        <p style="color: var(--primary-color); font-weight: 700; font-size: 1.2rem; margin: 10px 0;">
                            NPR <?php echo htmlspecialchars($svc['price']); ?>
                        </p>
                        <p style="color: #777; font-size: 0.9rem;"><?php echo htmlspecialchars($svc['duration']); ?> mins</p>
                        <p style="margin-top: 10px; color: #555;"><?php echo htmlspecialchars($svc['description'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Booking Section -->
        <div id="book" style="margin-bottom: 50px;">
            <div class="card" style="border-top: 5px solid var(--primary-color);">
                <h2 style="margin-bottom: 20px; text-align: center; color: var(--primary-color);">Book an Appointment</h2>
                <form method="POST" action="book_appointment.php">
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Choose Service</label>
                            <select name="service_id" class="form-control" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($services as $svc): ?>
                                    <option value="<?php echo $svc['service_id']; ?>">
                                        <?php echo htmlspecialchars($svc['service_name']); ?> (NPR <?php echo $svc['price']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Preferred Stylist</label>
                            <select name="staff_id" class="form-control">
                                <option value="">Any Staff</option>
                                <?php
                                try {
                                    $stfStmt = $pdo->query("SELECT s.staff_id, u.name FROM staff s JOIN users u ON s.user_id = u.user_id");
                                    foreach ($stfStmt->fetchAll() as $staff) {
                                        echo '<option value="' . $staff['staff_id'] . '">' . htmlspecialchars($staff['name']) . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Time</label>
                            <input type="time" name="time" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Apply Voucher</label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" name="voucher_code" class="form-control" placeholder="Enter voucher code (optional)">
                            <button type="button" class="btn btn-small" style="background:#e38ca8; color:#fff;">Apply Voucher</button>
                        </div>
                        <small style="color:#777;">Valid active vouchers will be applied at booking.</small>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px; font-size: 1.1rem;">Confirm Booking</button>
                </form>
            </div>
        </div>

        <!-- History Section -->
        <div id="history">
            <h2 class="section-title" style="color: var(--primary-color);">My Appointment History</h2>
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Service</th>
                                <th>Staff</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr><td colspan="6" class="text-center">No history yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($history as $appt): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y h:i A', strtotime($appt['appointment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                        <td><?php echo htmlspecialchars($appt['staff_name'] ?? 'Any Staff'); ?></td>
                                        <td>
                                            NPR <?php echo htmlspecialchars($appt['price']); ?>
                                            <?php if (!empty($appt['voucher_code'])): ?>
                                                <span class="badge" style="background:#6c63ff; color:#fff; margin-left:8px;">Voucher: <?php echo htmlspecialchars($appt['voucher_code']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($appt['status']); ?>">
                                                <?php echo ucfirst($appt['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $isFuture = (strtotime($appt['appointment_date']) >= time());
                                            $st = strtolower(trim($appt['status']));
                                            if ($isFuture && !in_array($st, ['completed','cancelled'])): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                                    <button type="submit" name="cancel_appointment" class="btn btn-small" style="background:#ff8a80; color:#fff;">Cancel</button>
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
        </div>
    </div>
    
    <div style="height: 50px;"></div>
</body>
</html>
