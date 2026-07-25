<?php
require_once '../config/db.php';
require_role('admin');

$message = '';
$error = '';

// Handle Add/Edit Service
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_service'])) {
        $name = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $duration = intval($_POST['duration'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (!$name || $price <= 0) {
            $error = "Name and valid Price are required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO services (name, price, duration, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $price, $duration, $description]);
                $message = "Service added successfully.";
            } catch (Exception $e) {
                $error = "Error adding service: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_service'])) {
        $id = $_POST['service_id'];
        try {
            $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
            $message = "Service deleted.";
        } catch (Exception $e) {
            $error = "Error deleting service: " . $e->getMessage();
        }
    }
}

// Fetch Services
$services = [];
try {
    $stmt = $pdo->query("SELECT * FROM services ORDER BY name ASC");
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error fetching services: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - Elysian Salon</title>
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
        <h2 class="section-title">Manage Services</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>Add New Service</h3>
            <form method="POST" action="">
                <input type="hidden" name="add_service" value="1">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Service Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Price (NPR)</label>
                        <input type="number" step="0.01" name="price" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-2">Add Service</button>
            </form>
        </div>

        <div class="card mt-2">
            <h3>Service List</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Price (NPR)</th>
                            <th>Duration (mins)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services)): ?>
                            <tr><td colspan="5" class="text-center">No services found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($services as $svc): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($svc['id']); ?></td>
                                    <td><?php echo htmlspecialchars($svc['name']); ?></td>
                                    <td><?php echo htmlspecialchars($svc['price']); ?></td>
                                    <td><?php echo htmlspecialchars($svc['duration']); ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this service?');">
                                            <input type="hidden" name="service_id" value="<?php echo $svc['id']; ?>">
                                            <button type="submit" name="delete_service" class="btn btn-small btn-danger">Delete</button>
                                        </form>
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
