<?php
require_once __DIR__ . '/../config/db.php';
require_login();
require_role('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $specialization = trim($_POST['specialization']);

    if (!$name || !$email || !$password || !$specialization) {
        $error = "All fields are required.";
    } else {
        try {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Email already registered.";
            } else {
                $pdo->beginTransaction();

                // Create User
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                // username can be email or derived. Let's use email as username for simplicity or name
                $username = strtolower(str_replace(' ', '', $name)) . rand(100,999);
                
                $stmt = $pdo->prepare("INSERT INTO users (username, name, email, password, role) VALUES (?, ?, ?, ?, 'staff')");
                $stmt->execute([$username, $name, $email, $hashed]);
                $userId = $pdo->lastInsertId();

                // Create Staff
                $stmt = $pdo->prepare("INSERT INTO staff (user_id, specialization) VALUES (?, ?)");
                $stmt->execute([$userId, $specialization]);

                $pdo->commit();
                header("Location: ../dashboard_admin.php?section=staff&success=created");
                exit;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Staff - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="theme-admin">
    <div class="container" style="max-width: 600px; margin-top: 50px;">
        <div class="card">
            <h2>Add New Staff Member</h2>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Specialization</label>
                    <input type="text" name="specialization" class="form-control" placeholder="e.g. Hair Stylist, Nail Artist" required>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:20px;">
                    <a href="../dashboard_admin.php?section=staff" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Staff</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
