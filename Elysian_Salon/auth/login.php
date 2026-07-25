<?php
require_once '../config/db.php';
session_start();
enforce_country_access(ALLOWED_COUNTRY);

if (isset($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['role'] ?? '');
    if ($role === 'admin') header("Location: ../dashboard_admin.php");
    elseif ($role === 'staff') header("Location: ../dashboard_staff.php");
    else header("Location: ../dashboard_customer.php");
    exit;
}

$error = '';
$stage = isset($_POST['stage']) ? $_POST['stage'] : 'password';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($stage === 'otp') {
        $otp_code = trim($_POST['otp_code'] ?? '');
        $pending_username = $_SESSION['otp_username'] ?? null;
        $expires_at = $_SESSION['otp_expires'] ?? 0;
        if (!$pending_username || !$otp_code) {
            $error = "Enter the OTP sent to your email.";
        } else {
            try {
                if (time() <= (int)$expires_at && hash_equals($_SESSION['otp_code'] ?? '', $otp_code)) {
                    $uidCol = get_user_identifier_column($pdo);
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE $uidCol = ?");
                    $stmt->execute([$pending_username]);
                    $user = $stmt->fetch();
                    if ($user) {
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['username'] = $user['username'];
                        try {
                            $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE $uidCol = ?")->execute([$pending_username]);
                        } catch (Exception $e) {}
                        $_SESSION['login_fail_count'] = 0;
                        $role = strtolower($user['role']);
                        $_SESSION['role'] = $role; // Store normalized role
                        
                        if ($role === 'customer') {
                            $st = $pdo->prepare("SELECT customer_id, name, email FROM customers WHERE user_id = ?");
                            $st->execute([$user['user_id']]);
                            $customer = $st->fetch();
                            if ($customer) {
                                $_SESSION['customer_id'] = $customer['customer_id'];
                                $_SESSION['name'] = $customer['name'];
                            } else {
                                $_SESSION['name'] = $user['name'] ?? 'Customer';
                            }
                            header("Location: ../dashboard_customer.php");
                        } elseif ($role === 'staff') {
                            $st = $pdo->prepare("SELECT staff_id FROM staff WHERE user_id = ?");
                            $st->execute([$user['user_id']]);
                            $staff = $st->fetch();
                            if ($staff) {
                                $_SESSION['staff_id'] = $staff['staff_id'];
                            }
                            $_SESSION['name'] = $user['name'] ?? 'Staff';
                            header("Location: ../dashboard_staff.php");
                        } else {
                            $_SESSION['name'] = "Administrator";
                            header("Location: ../dashboard_admin.php");
                        }
                        unset($_SESSION['otp_code'], $_SESSION['otp_expires'], $_SESSION['otp_username']);
                        exit;
                    } else {
                        $error = "Invalid session.";
                    }
                } else {
                    $error = "Invalid or expired OTP.";
                }
            } catch (Exception $e) {
                $error = "OTP verification failed: " . $e->getMessage();
            }
        }
    } elseif ($stage === 'email') {
        if (($_SESSION['login_fail_count'] ?? 0) < 3) {
            $error = "Email OTP is available after 3 failed login attempts.";
        } else {
        $emailInput = trim($_POST['email'] ?? '');
        if (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            $error = "Enter a valid email address.";
        } else {
            try {
                $uidCol = get_user_identifier_column($pdo);
                $user = null;
                // Try to find user via staff table first if needed, but let's just search users table first by email if possible
                // Assuming users table has email column (it does)
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$emailInput]);
                $user = $stmt->fetch();

                if (!$user) {
                    // Try username column just in case
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE $uidCol = ?");
                        $stmt->execute([$emailInput]);
                        $user = $stmt->fetch();
                    } catch (Exception $e) {}
                }
                
                if (!$user) {
                    // Auto-register logic (simplified)
                    $hashed_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO users (email, password, role, name) VALUES (?, ?, 'customer', 'Guest')")->execute([$emailInput, $hashed_password]);
                    $user_id = $pdo->lastInsertId();
                    
                    // Insert into customers
                    $pdo->prepare("INSERT INTO customers (user_id, name, email) VALUES (?, 'Guest', ?)")->execute([$user_id, $emailInput]);
                    
                    $stmt2 = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                    $stmt2->execute([$user_id]);
                    $user = $stmt2->fetch();
                }

                $otp_code = strval(random_int(100000, 999999));
                $_SESSION['otp_code'] = $otp_code;
                $_SESSION['otp_expires'] = time() + 600;
                $_SESSION['otp_username'] = $user[$uidCol] ?? $user['username'] ?? $user['email']; // Use whatever identifier works
                
                try {
                    $pdo->prepare("INSERT INTO otp_codes (user_id, code, expires_at, consumed) VALUES (?, ?, ?, 0)")->execute([
                        $user['user_id'], $otp_code, date('Y-m-d H:i:s', time() + 600)
                    ]);
                } catch (Exception $e) {}
                
                send_otp_email($emailInput, $otp_code);
                $stage = 'otp';
                $pending_user_id = null;
            } catch (Exception $e) {
                $error = "Failed to send OTP: " . $e->getMessage();
            }
        }
        }
        } else {
        $password = $_POST['password'] ?? '';
        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            try {
                $uidCol = get_user_identifier_column($pdo);
                $user = null;
                if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();
                    if (!$user) {
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE $uidCol = ?");
                        $stmt->execute([$username]);
                        $user = $stmt->fetch();
                    }
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE $uidCol = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();
                    if (!$user) {
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                        $stmt->execute([$username]);
                        $user = $stmt->fetch();
                    }
                }
                
                if ($user) {
                    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
                        $error = "Account locked due to too many attempts. Try later.";
                    } elseif (!empty($user['password_changed_at']) && strtotime($user['password_changed_at']) < strtotime('-30 days')) {
                        $error = "Password expired. Please reset your password.";
                    } elseif (password_verify($password, $user['password'])) {
                        $pdo->prepare("INSERT INTO login_attempts (user_id, username, success, ip) VALUES (NULL, ?, 1, ?)")->execute([$username, $_SERVER['REMOTE_ADDR'] ?? null]);
                        
                        // Password correct - Login immediately
                        try {
                            $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?")->execute([$user['user_id']]);
                        } catch (Exception $e) {}
                        $_SESSION['login_fail_count'] = 0;
                        
                        $role = strtolower($user['role']);
                        $_SESSION['role'] = $role;
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'] ?? $user['email'];
                        
                        if ($role === 'customer') {
                            $st = $pdo->prepare("SELECT customer_id, name, email FROM customers WHERE user_id = ?");
                            $st->execute([$user['user_id']]);
                            $customer = $st->fetch();
                            if ($customer) {
                                $_SESSION['customer_id'] = $customer['customer_id'];
                                $_SESSION['name'] = $customer['name'];
                            } else {
                                $_SESSION['name'] = $user['name'] ?? 'Customer';
                            }
                            header("Location: ../dashboard_customer.php");
                        } elseif ($role === 'staff') {
                            $st = $pdo->prepare("SELECT staff_id FROM staff WHERE user_id = ?");
                            $st->execute([$user['user_id']]);
                            $staff = $st->fetch();
                            if ($staff) {
                                $_SESSION['staff_id'] = $staff['staff_id'];
                            }
                            $_SESSION['name'] = $user['name'] ?? 'Staff';
                            header("Location: ../dashboard_staff.php");
                        } else {
                            $_SESSION['name'] = "Administrator";
                            header("Location: ../dashboard_admin.php");
                        }
                        exit;
                    } else {
                        $pdo->prepare("INSERT INTO login_attempts (user_id, username, success, ip) VALUES (NULL, ?, 0, ?)")->execute([$username, $_SERVER['REMOTE_ADDR'] ?? null]);
                        $attempts = intval($user['failed_attempts']) + 1;
                        $locked_until = null;
                        if ($attempts >= 5) {
                            $locked_until = date('Y-m-d H:i:s', time() + 3600);
                            $attempts = 5;
                        }
                        try {
                            $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE user_id = ?")->execute([$attempts, $locked_until, $user['user_id']]);
                        } catch (Exception $e) {}
                        $_SESSION['login_fail_count'] = ($_SESSION['login_fail_count'] ?? 0) + 1;
                        $error = "Invalid username or password.";
                    }
                } else {
                    $pdo->prepare("INSERT INTO login_attempts (user_id, username, success, ip) VALUES (NULL, ?, 0, ?)")->execute([$username, $_SERVER['REMOTE_ADDR'] ?? null]);
                    $_SESSION['login_fail_count'] = ($_SESSION['login_fail_count'] ?? 0) + 1;
                    $error = "Invalid username or password.";
                }
            } catch (Exception $e) {
                $error = "Login failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Elysian Salon</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Login to your account</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($stage === 'otp'): ?>
                <form method="POST" action="">
                    <input type="hidden" name="stage" value="otp">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($pending_user_id ?? ''); ?>">
                    <div class="form-group">
                        <label>Enter OTP sent to your email</label>
                        <input type="text" name="otp_code" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Verify OTP</button>
                </form>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="stage" value="password">
                    <div class="form-group">
                        <label>Email Address / Username</label>
                        <input type="text" name="username" class="form-control" required placeholder="Enter your email or username" value="<?php echo htmlspecialchars($username); ?>">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Login</button>
                </form>
                <?php if (($_SESSION['login_fail_count'] ?? 0) >= 3): ?>
                    <div class="mt-2" style="border-top: 1px solid #eee; padding-top: 15px;">
                        <p class="mb-2">Trouble logging in? Use OTP.</p>
                        <form method="POST" action="">
                            <input type="hidden" name="stage" value="email">
                            <div class="form-group">
                                <label>Login via Email OTP</label>
                                <input type="email" name="email" class="form-control" placeholder="Enter your email" value="<?php echo htmlspecialchars($username); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Send OTP</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <div class="mt-2">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
                <p><a href="../index.php">Back to Home</a></p>
            </div>
        </div>
    </div>
</body>
</html>
