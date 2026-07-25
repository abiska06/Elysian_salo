<?php
require_once __DIR__ . '/../config/db.php';
require_login();
require_role('admin');

$id = $_GET['id'] ?? 0;
$service = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE service_id = ?");
    $stmt->execute([$id]);
    $service = $stmt->fetch();
} catch (Exception $e) {
    die("Error loading service.");
}

if (!$service) {
    die("Service not found.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['service_name']);
    $price = floatval($_POST['price']);
    $duration = intval($_POST['duration']);

    if (!$name || $price <= 0 || $duration <= 0) {
        $error = "Valid Name, Price, and Duration are required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE services SET service_name = ?, price = ?, duration = ? WHERE service_id = ?");
            $stmt->execute([$name, $price, $duration, $id]);
            header("Location: ../dashboard_admin.php?section=services&success=updated");
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
    <title>Edit Service - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="theme-admin">
    <div class="container" style="max-width: 600px; margin-top: 50px;">
        <div class="card">
            <h2>Edit Service</h2>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Service Name</label>
                    <input type="text" name="service_name" class="form-control" value="<?php echo htmlspecialchars($service['service_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Price (NPR)</label>
                    <input type="number" name="price" step="0.01" class="form-control" value="<?php echo htmlspecialchars($service['price']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Duration (Minutes)</label>
                    <input type="number" name="duration" class="form-control" value="<?php echo htmlspecialchars($service['duration']); ?>" required>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:20px;">
                    <a href="../dashboard_admin.php?section=services" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Service</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
