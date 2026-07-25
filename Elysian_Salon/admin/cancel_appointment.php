<?php
require_once __DIR__ . '/../config/db.php';
require_login();
require_role('admin');
$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?")->execute([$id]);
        header("Location: ../dashboard_admin.php?section=appointments&success=cancelled");
        exit;
    } catch (Exception $e) {
        header("Location: ../dashboard_admin.php?section=appointments&error=cancel_failed");
        exit;
    }
}
header("Location: ../dashboard_admin.php?section=appointments");
exit;
