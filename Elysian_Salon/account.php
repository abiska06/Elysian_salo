<?php
require_once __DIR__ . '/config/db.php';
require_login();

$userId = $_SESSION['user_id'];
$role = strtolower($_SESSION['role'] ?? 'customer');
$message = '';
$error = '';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS deleted_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        role VARCHAR(20) NOT NULL,
        payload LONGTEXT NOT NULL,
        deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("DELETE FROM deleted_accounts WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)");
} catch (Exception $e) {}

// Load user
$user = [];
try {
    $stmt = $pdo->prepare("SELECT user_id, username, email, name, role, password_changed_at FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    $error = "Failed to load account.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $newUsername = trim($_POST['username'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');
        if (!$newUsername || !$newEmail) {
            $error = "Username and Email are required.";
        } else {
            try {
                $checkU = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id <> ?");
                $checkU->execute([$newUsername, $userId]);
                $checkE = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ?");
                $checkE->execute([$newEmail, $userId]);
                if ($checkU->fetch()) {
                    $error = "Username already taken.";
                } elseif ($checkE->fetch()) {
                    $error = "Email already in use.";
                } else {
                    $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?")->execute([$newUsername, $newEmail, $userId]);
                    $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'profile_update', ?)")->execute([$userId, json_encode(['username'=>$newUsername,'email'=>$newEmail])]);
                    $message = "Profile updated.";
                    $_SESSION['username'] = $newUsername;
                }
            } catch (Exception $e) {
                $error = "Update failed: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!$current || !$new || !$confirm) {
            $error = "All password fields are required.";
        } elseif ($new !== $confirm) {
            $error = "New passwords do not match.";
        } else {
            try {
                $st = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                $st->execute([$userId]);
                $row = $st->fetch();
                if (!$row || !password_verify($current, $row['password'])) {
                    $error = "Current password is incorrect.";
                } else {
                    $hashed = password_hash($new, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ?, password_changed_at = NOW(), failed_attempts = 0, locked_until = NULL WHERE user_id = ?")->execute([$hashed, $userId]);
                    $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'password_change', NULL)")->execute([$userId]);
                    $message = "Password changed successfully.";
                }
            } catch (Exception $e) {
                $error = "Password change failed: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_account'])) {
        try {
            $pdo->beginTransaction();
            $uStmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $uStmt->execute([$userId]);
            $uRow = $uStmt->fetch();
            $cStmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
            $cStmt->execute([$userId]);
            $cRow = $cStmt->fetch();
            $sStmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ?");
            $sStmt->execute([$userId]);
            $sRow = $sStmt->fetch();
            $apps = [];
            if ($cRow) {
                $aStmt = $pdo->prepare("SELECT * FROM appointments WHERE customer_id = ?");
                $aStmt->execute([$cRow['customer_id']]);
                $apps = array_merge($apps, $aStmt->fetchAll());
            }
            if ($sRow) {
                $a2Stmt = $pdo->prepare("SELECT * FROM appointments WHERE staff_id = ?");
                $a2Stmt->execute([$sRow['staff_id']]);
                $apps = array_merge($apps, $a2Stmt->fetchAll());
            }
            $payload = json_encode([
                'user' => $uRow,
                'customer' => $cRow,
                'staff' => $sRow,
                'appointments' => $apps
            ]);
            $pdo->prepare("INSERT INTO deleted_accounts (user_id, role, payload) VALUES (?, ?, ?)")->execute([$userId, $role, $payload]);
            if ($cRow) {
                $pdo->prepare("DELETE FROM appointments WHERE customer_id = ?")->execute([$cRow['customer_id']]);
                $pdo->prepare("DELETE FROM customers WHERE customer_id = ?")->execute([$cRow['customer_id']]);
            }
            if ($sRow) {
                $pdo->prepare("DELETE FROM appointments WHERE staff_id = ?")->execute([$sRow['staff_id']]);
                $pdo->prepare("DELETE FROM staff WHERE staff_id = ?")->execute([$sRow['staff_id']]);
            }
            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$userId]);
            $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'account_delete', NULL)")->execute([$userId]);
            $pdo->commit();
            session_unset();
            session_destroy();
            header("Location: auth/login.php?deleted=1");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Account deletion failed.";
        }
    }
    // Reload user after changes
    try {
        $stmt = $pdo->prepare("SELECT user_id, username, email, name, role, password_changed_at FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .account-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    </style>
</head>
<body class="<?php echo $role === 'admin' ? 'theme-admin' : ($role === 'staff' ? 'theme-staff' : 'theme-customer'); ?>">
    <div class="container" style="max-width: 720px; padding-top: 30px;">
        <div class="account-header">
            <h2>Account Settings</h2>
            <a href="<?php echo $role === 'admin' ? 'dashboard_admin.php' : ($role === 'staff' ? 'dashboard_staff.php' : 'dashboard_customer.php'); ?>" class="btn btn-small">Back</a>
        </div>
        <?php if (!empty($error)): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (!empty($message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <div class="card">
            <h3>Profile</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">Save Profile</button>
            </form>
        </div>
        <div class="card" style="margin-top:20px;">
            <h3>Change Password</h3>
            <p class="mb-2">Password expires monthly. Use your current password to set a new one.</p>
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
            </form>
        </div>
        <div class="card" style="margin-top:20px;">
            <h3>Danger Zone</h3>
            <p class="mb-2">Delete this account. Data will be restorable for 1 month.</p>
            <form method="POST" onsubmit="return confirm('Delete your account? This can be restored within 1 month.');">
                <button type="submit" name="delete_account" class="btn btn-small" style="background:#ff8a80; color:#fff;">Delete Account</button>
            </form>
        </div>
    </div>
</body>
</html>
