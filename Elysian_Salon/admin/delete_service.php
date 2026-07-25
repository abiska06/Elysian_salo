<?php
require_once __DIR__ . '/../config/db.php';
require_login();
require_role('admin');

$id = $_GET['id'] ?? 0;

if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM services WHERE service_id = ?");
        $stmt->execute([$id]);
        header("Location: ../dashboard_admin.php?section=services&success=deleted");
    } catch (Exception $e) {
        // In a real app, handle foreign key constraint violations (e.g. existing appointments)
        header("Location: ../dashboard_admin.php?section=services&error=delete_failed");
    }
} else {
    header("Location: ../dashboard_admin.php?section=services");
}
exit;
?>
