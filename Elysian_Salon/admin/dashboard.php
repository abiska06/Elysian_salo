<?php
require_once '../config/db.php';
require_role('admin');

$message = '';
$error = '';

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
        $stmt->execute([$new_status, $appointment_id]);
        $message = "Appointment status updated.";
    } catch (Exception $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Fetch All Appointments
try {
    $stmt = $pdo->query("
        SELECT a.appointment_id, c.name as customer_name, s.service_name, a.appointment_date, a.status 
        FROM appointments a 
        JOIN customers c ON a.customer_id = c.customer_id 
        JOIN services s ON a.service_id = s.service_id 
        ORDER BY a.appointment_date DESC
    ");
    $appointments = $stmt->fetchAll();
} catch (Exception $e) {
    $appointments = [];
    $error = "Error fetching appointments: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Elysian Salon</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container nav-content">
            <div class="logo">
                <img src="../assets/img/logo.svg" class="logo-img" alt="Elysian Salon logo">
                <span class="logo-text">Elysian Salon (Admin)</span>
            </div>
            <div class="nav-links">
                <a href="../auth/logout.php" class="btn btn-small btn-primary">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 class="mt-2">Appointment Management</h2>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card mt-2">
            <h3>All Appointments</h3>
            <div style="display:flex; gap:12px; align-items:center; margin:10px 0;">
                <input type="text" id="tableSearch" class="form-control" placeholder="Search... " style="max-width:300px;">
                <button id="exportCsv" class="btn btn-small">Export CSV</button>
            </div>
            <div class="table-responsive">
                <table id="appointmentsTable">
                    <thead>
                        <tr>
                            <th data-sort="number">ID</th>
                            <th data-sort="text">Customer</th>
                            <th data-sort="text">Service</th>
                            <th data-sort="date">Date & Time</th>
                            <th data-sort="text">Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr><td colspan="6" class="text-center">No appointments found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $appt): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($appt['id']); ?></td>
                                    <td><?php echo htmlspecialchars($appt['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($appt['appointment_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($appt['status']); ?>">
                                            <?php echo ucfirst($appt['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="" style="display:inline-block;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            <select name="status" onchange="this.form.submit()" class="form-control" style="width: auto; padding: 5px; font-size: 12px;">
                                                <option value="pending" <?php if($appt['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                                                <option value="confirmed" <?php if($appt['status'] == 'confirmed') echo 'selected'; ?>>Confirmed</option>
                                                <option value="completed" <?php if($appt['status'] == 'completed') echo 'selected'; ?>>Completed</option>
                                                <option value="cancelled" <?php if($appt['status'] == 'cancelled') echo 'selected'; ?>>Cancelled</option>
                                            </select>
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
    <script>
    (function(){
        const table = document.getElementById('appointmentsTable');
        const searchInput = document.getElementById('tableSearch');
        const exportBtn = document.getElementById('exportCsv');
        function filterRows() {
            const q = (searchInput.value || '').toLowerCase();
            for (const tr of table.tBodies[0].rows) {
                let show = false;
                for (const td of tr.cells) {
                    if (td.innerText.toLowerCase().includes(q)) { show = true; break; }
                }
                tr.style.display = show ? '' : 'none';
            }
        }
        searchInput && searchInput.addEventListener('input', filterRows);
        function getCellValue(tr, idx) { return tr.children[idx].innerText; }
        function comparer(idx, type, asc) {
            return (a, b) => {
                let v1 = getCellValue(asc ? a : b, idx);
                let v2 = getCellValue(asc ? b : a, idx);
                if (type === 'number') return parseFloat(v1.replace('#','')) - parseFloat(v2.replace('#',''));
                if (type === 'date') return new Date(v1) - new Date(v2);
                return v1.localeCompare(v2);
            };
        }
        for (const th of table.tHead.rows[0].cells) {
            const type = th.getAttribute('data-sort');
            if (!type) continue;
            let asc = true;
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => {
                const tbody = table.tBodies[0];
                Array.from(tbody.rows)
                    .sort(comparer(th.cellIndex, type, asc = !asc))
                    .forEach(tr => tbody.appendChild(tr));
            });
        }
        exportBtn && exportBtn.addEventListener('click', () => {
            const rows = [];
            rows.push(Array.from(table.tHead.rows[0].cells).map(th => th.innerText).slice(0,5)); // exclude Action
            for (const tr of table.tBodies[0].rows) {
                if (tr.style.display === 'none') continue;
                rows.push(Array.from(tr.cells).slice(0,5).map(td => td.innerText));
            }
            const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
            const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'appointments.csv';
            a.click();
            URL.revokeObjectURL(url);
        });
    })();
    </script>
</body>
</html>
