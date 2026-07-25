<?php
require_once __DIR__ . '/config/db.php';
require_login();
require_role('customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $custId = $_SESSION['customer_id'] ?? null;
    $serviceId = intval($_POST['service_id'] ?? 0);
    $staffId = !empty($_POST['staff_id']) ? intval($_POST['staff_id']) : null;
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');
    $voucherCode = strtoupper(trim($_POST['voucher_code'] ?? ''));

    if (!$custId || !$serviceId || !$date || !$time) {
        // Handle error or redirect back
        header("Location: dashboard_customer.php?error=missing_fields");
        exit;
    }

    $dateTime = $date . ' ' . $time . ':00';

    try {
        $cols = get_table_columns($pdo, 'appointments');
        $dateCol = 'appointment_date';
        if (in_array('date_time', $cols)) $dateCol = 'date_time';
        if (!in_array('final_price', $cols)) { try { $pdo->exec("ALTER TABLE appointments ADD COLUMN final_price DECIMAL(10,2) NULL"); } catch (Throwable $e) {} }
        if (!in_array('voucher_code', $cols)) { try { $pdo->exec("ALTER TABLE appointments ADD COLUMN voucher_code VARCHAR(50) NULL"); } catch (Throwable $e) {} }

        $svcStmt = $pdo->prepare("SELECT price FROM services WHERE service_id = ?");
        $svcStmt->execute([$serviceId]);
        $svc = $svcStmt->fetch();
        $basePrice = $svc ? floatval($svc['price']) : 0.0;

        $voucherTable = 'vouchers';
        $vcols = get_table_columns($pdo, 'vouchers');
        if (!in_array('code', $vcols, true)) $voucherTable = 'discount_vouchers';

        $finalPrice = $basePrice;
        $appliedCode = null;
        if ($voucherCode) {
            $vstmt = $pdo->prepare("SELECT code, discount_type, discount_value, expiry_date, is_active FROM {$voucherTable} WHERE code = ?");
            $vstmt->execute([$voucherCode]);
            $v = $vstmt->fetch();
            if ($v && intval($v['is_active']) === 1 && strtotime($v['expiry_date']) >= strtotime(date('Y-m-d'))) {
                if ($v['discount_type'] === 'percent') {
                    $finalPrice = round(max(0, $basePrice * (1.0 - (floatval($v['discount_value'])/100.0))), 2);
                } else {
                    $finalPrice = round(max(0, $basePrice - floatval($v['discount_value'])), 2);
                }
                $appliedCode = $v['code'];
            }
        }

        $stmt = $pdo->prepare("INSERT INTO appointments (customer_id, staff_id, service_id, $dateCol, status, final_price, voucher_code) VALUES (?, ?, ?, ?, 'pending', ?, ?)");
        $stmt->execute([$custId, $staffId, $serviceId, $dateTime, $finalPrice, $appliedCode]);
        
        header("Location: dashboard_customer.php?success=booked");
        exit;
    } catch (Exception $e) {
        header("Location: dashboard_customer.php?error=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: dashboard_customer.php");
    exit;
}
?>
