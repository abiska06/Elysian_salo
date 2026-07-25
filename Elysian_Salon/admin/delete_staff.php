<?php
require_once __DIR__ . '/../config/db.php';
require_login();
require_role('admin');
$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE staff_id = ? AND status NOT IN ('completed','cancelled')")->execute([$id]);
        $pdo->prepare("DELETE FROM staff WHERE staff_id = ?")->execute([$id]);
        $pdo->commit();
        header("Location: ../dashboard_admin.php?section=staff&success=deleted");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: ../dashboard_admin.php?section=staff&error=delete_failed");
        exit;
    }
}
header("Location: ../dashboard_admin.php?section=staff");
exit;
