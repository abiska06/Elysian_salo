<?php
require_once '../config/db.php';
require_role('customer');

$customer_id = $_SESSION['customer_id'];
$message = '';
$error = '';

// Handle Appointment Booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $service_id = $_POST['service_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    
    // Combine date and time
    $appointment_datetime = $date . ' ' . $time;

    if (empty($service_id) || empty($date) || empty($time)) {
        $error = "Please select a service, date, and time.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO appointments (customer_id, service_id, appointment_date, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$customer_id, $service_id, $appointment_datetime]);
            $message = "Appointment booked successfully! Waiting for confirmation.";
        } catch (Exception $e) {
            $error = "Booking failed: " . $e->getMessage();
        }
    }
}

// Fetch Services
try {
    $stmt = $pdo->query("SELECT * FROM services");
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    $services = []; // Handle case where table might not exist or be empty
}

// Fetch Customer Appointments
try {
    $stmt = $pdo->prepare("
        SELECT a.id, s.name as service_name, s.price, a.appointment_date, a.status 
        FROM appointments a 
        JOIN services s ON a.service_id = s.id 
        WHERE a.customer_id = ? 
        ORDER BY a.appointment_date DESC
    ");
    $stmt->execute([$customer_id]);
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
    <title>Customer Dashboard - Elysian Salon</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container nav-content">
            <div class="logo">
                <img src="../assets/img/logo.svg" class="logo-img" alt="Elysian Salon logo">
                <span class="logo-text">Elysian Salon</span>
            </div>
            <div class="nav-links">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="../auth/logout.php" class="btn btn-small btn-primary">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 class="mt-2">Customer Dashboard</h2>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Booking Section -->
            <div class="card">
                <h3>Book an Appointment</h3>
                <form method="POST" action="">
                    <input type="hidden" name="book_appointment" value="1">
                    <div class="form-group">
                        <label>Select Service</label>
                        <select name="service_id" class="form-control" required>
                            <option value="">-- Choose Service --</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>">
                                    <?php echo htmlspecialchars($service['name']); ?> - $<?php echo htmlspecialchars($service['price']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="appointment_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Time</label>
                        <input type="time" name="appointment_time" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Book Now</button>
                </form>
            </div>

            <!-- Available Services List -->
            <div class="card">
                <h3>Our Services</h3>
                <?php if (empty($services)): ?>
                    <p>No services available at the moment.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($services as $service): ?>
                            <li style="margin-bottom: 10px; list-style: none; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                                <strong><?php echo htmlspecialchars($service['name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($service['description'] ?? ''); ?></small><br>
                                <span>Duration: <?php echo htmlspecialchars($service['duration'] ?? 'N/A'); ?> mins</span> | 
                                <span>Price: $<?php echo htmlspecialchars($service['price']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Appointment History -->
        <div class="card mt-2">
            <h3>Your Appointments</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Date & Time</th>
                            <th>Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr><td colspan="4" class="text-center">No appointments found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $appt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($appt['appointment_date'])); ?></td>
                                    <td>$<?php echo htmlspecialchars($appt['price']); ?></td>
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
