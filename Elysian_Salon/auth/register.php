<?php
require_once '../config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($name) || empty($email) || empty($phone) || empty($username) || empty($password)) {
        $error = "All fields are required.";
    } else {
        try {
            $uidCol = get_user_identifier_column($pdo);
            $pdo->beginTransaction();
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT 1 FROM users WHERE $uidCol = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Username already exists.";
            } else {
                // Insert into users table
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                // If the identifier column is 'email', use the email variable. Otherwise use username.
                $loginVal = ($uidCol === 'email') ? $email : $username;
                
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'customer')");
                $stmt->execute([$username, $email, $hashed_password]);
                $user_id = $pdo->lastInsertId();

                list($nameCol, $emailCol, $phoneCol) = resolve_customer_columns($pdo);
                $stmt = $pdo->prepare("INSERT INTO customers (user_id, $nameCol, $emailCol, $phoneCol) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $name, $email, $phone]);

                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                $success = "Registration successful! You can now login.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Elysian Salon</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Create Account</h2>
            <p class="mb-2">Join Elysian Salon today</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <br><a href="login.php">Login here</a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Sign Up</button>
                </form>
                <div class="mt-2">
                    <p>Already have an account? <a href="login.php">Login</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
