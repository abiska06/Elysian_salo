<?php
require_once '../config/db.php';
require_role('admin');

$message = '';
$error = '';

// Handle Add Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $position = trim($_POST['position'] ?? '');

    if (!$name || !$email || !$password) {
        $error = "Name, Email and Password are required.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Create User
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'staff')");
            $stmt->execute([$email, $hashed_password]);
            $user_id = $pdo->lastInsertId();

            // 2. Create Staff Profile
            $stmt = $pdo->prepare("INSERT INTO staff (user_id, specialization) VALUES (?, ?)");
            $stmt->execute([$user_id, $position]);

            // Note: Staff table in DB (from check_db.php) has: staff_id, user_id, specialization, created_at.
            // It does NOT have 'name'. But 'users' has 'name'. 
            // check_db.php output for users: user_id, name, email, password, role...
            // So I should update users table with name as well.
            
            $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE user_id = ?");
            $stmt->execute([$name, $user_id]);

            $pdo->commit();
            $message = "Staff member created successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error creating staff: " . $e->getMessage();
        }
    }
}

// Handle Delete Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_staff'])) {
    $staff_id = $_POST['staff_id'];
    try {
        // Get user_id first
        $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        $staff = $stmt->fetch();
        
        if ($staff) {
            $pdo->beginTransaction();
            // Delete staff (cascade should handle it if set, but let's be safe)
            $pdo->prepare("DELETE FROM staff WHERE staff_id = ?")->execute([$staff_id]);
            // Delete user
            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$staff['user_id']]);
            $pdo->commit();
            $message = "Staff member deleted.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting staff: " . $e->getMessage();
    }
}

// Fetch Staff
$staffList = [];
try {
    $stmt = $pdo->query("
        SELECT s.staff_id, u.name, u.email, s.specialization 
        FROM staff s 
        JOIN users u ON s.user_id = u.user_id 
        ORDER BY u.name ASC
    ");
    $staffList = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error fetching staff: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - Elysian Salon</title>
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
        <h2 class="section-title">Manage Staff</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>Add New Staff</h3>
            <form method="POST" action="">
                <input type="hidden" name="add_staff" value="1">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Name</label>
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
                        <label>Position / Specialization</label>
                        <input type="text" name="position" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-2">Create Staff Account</button>
            </form>
        </div>

        <div class="card mt-2">
            <h3>Staff List</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staffList)): ?>
                            <tr><td colspan="5" class="text-center">No staff found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($staffList as $staff): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($staff['staff_id']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['specialization']); ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Are you sure?');">
                                            <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                                            <button type="submit" name="delete_staff" class="btn btn-small btn-danger">Delete</button>
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
