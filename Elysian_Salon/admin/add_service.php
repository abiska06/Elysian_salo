<?php
require_once __DIR__ . '/../config/db.php';
require_login();
require_role('admin');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['service_name']);
    $price = floatval($_POST['price']);
    $duration = intval($_POST['duration']);
    $desc = trim($_POST['description']);

    if (!$name || $price <= 0 || $duration <= 0) {
        $error = "Valid Name, Price, and Duration are required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO services (service_name, price, duration) VALUES (?, ?, ?)");
            // Note: services table might not have description based on schema check earlier 
            // (service_id, service_name, price, duration, created_at). 
            // Wait, schema check output: "service_id, service_name, price, duration, created_at"
            // It does NOT have description. I should check if I need to add it or ignore it.
            // The customer dashboard displays description: $svc['description'] ?? ''.
            // If I want to support description, I should alter the table.
            // For now, I will insert what exists.
            
            $stmt->execute([$name, $price, $duration]);
            header("Location: ../dashboard_admin.php?section=services&success=created");
            exit;
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Service - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="theme-admin">
    <div class="container" style="max-width: 600px; margin-top: 50px;">
        <div class="card">
            <h2>Add New Service</h2>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Service Name</label>
                    <input type="text" name="service_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Price (NPR)</label>
                    <input type="number" name="price" step="0.01" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Duration (Minutes)</label>
                    <input type="number" name="duration" class="form-control" required>
                </div>
                <!-- 
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control"></textarea>
                </div> 
                -->
                <div style="display:flex; justify-content:space-between; margin-top:20px;">
                    <a href="../dashboard_admin.php?section=services" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Add Service</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
