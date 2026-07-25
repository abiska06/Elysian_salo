<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', '25123780'); // Change this to your actual database name
define('DB_USER', 'root');      // Change this to your database username
define('DB_PASS', '');          // Change this to your database password
define('DB_CHARSET', 'utf8mb4');
define('ALLOWED_COUNTRY', 'NP');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(50) NULL,
            success TINYINT(1) NOT NULL,
            ip VARCHAR(64) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS otp_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            code VARCHAR(10) NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        try { $pdo->exec("ALTER TABLE users ADD COLUMN failed_attempts INT NOT NULL DEFAULT 0"); } catch (\Throwable $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN locked_until DATETIME NULL"); } catch (\Throwable $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN password_changed_at DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (\Throwable $e) {}
    } catch (\Throwable $e) {}
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Helper function to check login status
function require_login() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }
}

// Helper function to check role
function require_role($role) {
    require_login();
    if (strtolower($_SESSION['role']) !== strtolower($role)) {
        echo "Access Denied. You do not have permission to view this page.";
        exit;
    }
}

function enforce_country_access($allowedCountryCode) {
    if (!$allowedCountryCode) {
        return;
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $url = "http://ip-api.com/json/" . $ip;
    $resp = @file_get_contents($url);
    if ($resp === false) {
        return;
    }
    $data = json_decode($resp, true);
    if (!$data || !isset($data['countryCode'])) {
        return;
    }
    if (strtoupper($data['countryCode']) !== strtoupper($allowedCountryCode)) {
        http_response_code(403);
        echo "Access restricted to $allowedCountryCode.";
        exit;
    }
}

function send_otp_email($toEmail, $code) {
    require_once __DIR__ . '/mail.php';
    $subject = "Your Elysian Salon OTP Code";
    $message = "Your OTP code is: " . $code . "\nThis code expires in 10 minutes.";
    $fromEmail = getenv('MAIL_FROM_EMAIL') ?: (defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'no-reply@elysian-salon.local');
    $fromName = getenv('MAIL_FROM_NAME') ?: (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Elysian Salon');
    $sgKey = getenv('SENDGRID_API_KEY');
    if ($sgKey) {
        $payload = [
            "personalizations" => [[ "to" => [[ "email" => $toEmail ]] ]],
            "from" => [ "email" => $fromEmail, "name" => $fromName ],
            "subject" => $subject,
            "content" => [[ "type" => "text/plain", "value" => $message ]]
        ];
        $ch = curl_init("https://api.sendgrid.com/v3/mail/send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $sgKey,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    $headers = "From: " . $fromEmail;
    @mail($toEmail, $subject, $message, $headers);
    
    // Log OTP for local testing
    file_put_contents(__DIR__ . '/../otp_log.txt', date('Y-m-d H:i:s') . " - To: $toEmail - Code: $code\n", FILE_APPEND);
}

function get_user_identifier_column(PDO $pdo) {
    $candidates = ['username', 'user_name', 'email'];
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users'");
    $stmt->execute([DB_NAME]);
    $cols = array_map(function($r){ return $r['COLUMN_NAME']; }, $stmt->fetchAll());
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) {
            return $c;
        }
    }
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(50) NULL");
        $pdo->exec("ALTER TABLE users ADD UNIQUE KEY uniq_username (username)");
        return 'username';
    } catch (Exception $e) {
        return 'username';
    }
}

function get_table_columns(PDO $pdo, $table) {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
    $stmt->execute([DB_NAME, $table]);
    return array_map(function($r){ return $r['COLUMN_NAME']; }, $stmt->fetchAll());
}

function ensure_customer_columns(PDO $pdo) {
    $cols = get_table_columns($pdo, 'customers');
    $need = [];
    if (!in_array('name', $cols, true)) $need[] = "ADD COLUMN name VARCHAR(100) NULL";
    if (!in_array('email', $cols, true)) $need[] = "ADD COLUMN email VARCHAR(100) NULL";
    if (!in_array('phone', $cols, true)) $need[] = "ADD COLUMN phone VARCHAR(20) NULL";
    if ($need) {
        try {
            $pdo->exec("ALTER TABLE customers " . implode(', ', $need));
        } catch (Exception $e) {}
    }
}

function resolve_customer_columns(PDO $pdo) {
    $cols = get_table_columns($pdo, 'customers');
    $nameCandidates = ['name','full_name','fullname','customer_name'];
    $emailCandidates = ['email','email_address','mail'];
    $phoneCandidates = ['phone','mobile','phone_number','contact','contact_number'];
    $pick = function($cands) use ($cols) {
        foreach ($cands as $c) if (in_array($c, $cols, true)) return $c;
        return $cands[0];
    };
    $nameCol = $pick($nameCandidates);
    $emailCol = $pick($emailCandidates);
    $phoneCol = $pick($phoneCandidates);
    ensure_customer_columns($pdo);
    return [$nameCol, $emailCol, $phoneCol];
}
?>
