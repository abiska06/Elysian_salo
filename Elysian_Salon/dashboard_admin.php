<?php
require_once __DIR__ . '/config/db.php';
require_login();
require_role('admin');

// Fetch Stats
$stats = [
    'staff' => $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn(),
    'services' => $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn(),
    'bookings' => $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn()
];
$labels = [];
$yms = [];
$usersTotal = [];
$usersAdmin = [];
$usersStaff = [];
$usersCustomer = [];
$monthCounts = [];
$serviceLabels = [];
$serviceCounts = [];
try {
    for ($i = 11; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("-$i months"));
        $yms[] = $ym;
        $labels[] = date('M', strtotime($ym . '-01'));
    }
    $uStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, role, COUNT(*) cnt FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym, role ORDER BY ym");
    $uStmt->execute();
    $uRows = $uStmt->fetchAll();
    $uMapTot = [];
    $uMapAdm = [];
    $uMapStf = [];
    $uMapCus = [];
    foreach ($uRows as $r) {
        $ym = $r['ym'];
        $role = strtolower($r['role'] ?? '');
        $cnt = (int)$r['cnt'];
        $uMapTot[$ym] = ($uMapTot[$ym] ?? 0) + $cnt;
        if ($role === 'admin') $uMapAdm[$ym] = $cnt;
        elseif ($role === 'staff') $uMapStf[$ym] = $cnt;
        else $uMapCus[$ym] = $cnt;
    }
    $aStmt = $pdo->prepare("SELECT DATE_FORMAT(appointment_date,'%Y-%m') ym, COUNT(*) cnt FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym ORDER BY ym");
    $aStmt->execute();
    $aRows = $aStmt->fetchAll();
    $aMap = [];
    foreach ($aRows as $r) { $aMap[$r['ym']] = (int)$r['cnt']; }
    $svcCols = get_table_columns($pdo, 'services');
    $svcIdCol = in_array('service_id', $svcCols, true) ? 'service_id' : (in_array('id', $svcCols, true) ? 'id' : 'service_id');
    $svcNameCol = in_array('service_name', $svcCols, true) ? 'service_name' : (in_array('name', $svcCols, true) ? 'name' : 'service_name');
    $sStmt = $pdo->prepare("SELECT s.$svcNameCol AS name, COUNT(*) cnt FROM appointments a JOIN services s ON a.service_id = s.$svcIdCol WHERE a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY s.$svcIdCol ORDER BY cnt DESC");
    $sStmt->execute();
    foreach ($sStmt->fetchAll() as $sr) {
        $serviceLabels[] = $sr['name'];
        $serviceCounts[] = (int)$sr['cnt'];
    }
    foreach ($yms as $ym) {
        $usersTotal[] = isset($uMapTot[$ym]) ? $uMapTot[$ym] : 0;
        $usersAdmin[] = isset($uMapAdm[$ym]) ? $uMapAdm[$ym] : 0;
        $usersStaff[] = isset($uMapStf[$ym]) ? $uMapStf[$ym] : 0;
        $usersCustomer[] = isset($uMapCus[$ym]) ? $uMapCus[$ym] : 0;
        $monthCounts[] = isset($aMap[$ym]) ? $aMap[$ym] : 0;
    }
} catch (Throwable $e) {}

// Determine active section
$section = $_GET['section'] ?? 'dashboard';

// Helper to fetch data for sections
$staffList = [];
$servicesList = [];
$appointmentsList = [];
$vouchersList = [];
$paymentsList = [];
$voucherTable = 'vouchers';

if ($section === 'finance') {
    try {
        $cols = get_table_columns($pdo, 'vouchers');
        if (!in_array('code', $cols, true)) {
            $voucherTable = 'discount_vouchers';
            $pdo->exec("CREATE TABLE IF NOT EXISTS discount_vouchers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                discount_type ENUM('percent','fixed') NOT NULL,
                discount_value DECIMAL(10,2) NOT NULL,
                expiry_date DATE NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $voucherTable = 'vouchers';
            try { $pdo->exec("ALTER TABLE vouchers ADD UNIQUE KEY uniq_code (code)"); } catch (Throwable $e) {}
            try { $pdo->exec("ALTER TABLE vouchers ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {}
        }
    } catch (Throwable $e) {}
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_voucher'])) {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $dtype = $_POST['discount_type'] ?? 'percent';
        $dval = trim($_POST['discount_value'] ?? '');
        $exp  = trim($_POST['expiry_date'] ?? '');
        $active = isset($_POST['is_active']) ? 1 : 0;
        $err = null;
        if (!$code || !preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) $err = 'Invalid voucher code';
        if (!in_array($dtype, ['percent','fixed'], true)) $err = 'Invalid discount type';
        if (!is_numeric($dval) || $dval <= 0) $err = 'Invalid discount value';
        if ($dtype === 'percent' && ($dval < 1 || $dval > 100)) $err = 'Percent must be 1-100';
        if (!$exp || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) $err = 'Invalid expiry date';
        if (!$err) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$voucherTable} WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetchColumn() > 0) {
                    $err = 'Code already exists';
                } else {
                    $ins = $pdo->prepare("INSERT INTO {$voucherTable} (code, discount_type, discount_value, expiry_date, is_active) VALUES (?, ?, ?, ?, ?)");
                    $ins->execute([$code, $dtype, $dval, $exp, $active]);
                    header("Location: ?section=finance&success=added");
                    exit;
                }
            } catch (Throwable $e) {
                $err = 'Failed to add voucher';
            }
        }
        if ($err) {
            header("Location: ?section=finance&error=" . urlencode($err));
            exit;
        }
    }
}

if ($section === 'staff') {
    $staffList = $pdo->query("SELECT s.*, u.name, u.email FROM staff s JOIN users u ON s.user_id = u.user_id")->fetchAll();
} elseif ($section === 'services') {
    $servicesList = $pdo->query("SELECT * FROM services")->fetchAll();
} elseif ($section === 'appointments') {
    $appointmentsList = $pdo->query("SELECT a.*, COALESCE(u.name, c.name) as customer_name, s.service_name, st_u.name as staff_name 
        FROM appointments a 
        LEFT JOIN customers c ON a.customer_id = c.customer_id 
        LEFT JOIN users u ON c.user_id = u.user_id
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN staff st ON a.staff_id = st.staff_id
        LEFT JOIN users st_u ON st.user_id = st_u.user_id
        ORDER BY a.appointment_date DESC")->fetchAll();
} elseif ($section === 'finance') {
    $vouchersList = $pdo->query("SELECT * FROM {$voucherTable} ORDER BY created_at DESC")->fetchAll();
} elseif ($section === 'payments') {
    try {
        $pcols = get_table_columns($pdo, 'payments');
        $idCol = in_array('payment_id', $pcols, true) ? 'payment_id' : (in_array('id', $pcols, true) ? 'id' : 'payment_id');
        $apptCol = in_array('appointment_id', $pcols, true) ? 'appointment_id' : 'appointment_id';
        $amtCol = in_array('amount', $pcols, true) ? 'amount' : 'amount';
        $methodCol = in_array('method', $pcols, true) ? 'method' : null;
        $payDateCol = in_array('payment_date', $pcols, true) ? 'payment_date' : (in_array('date', $pcols, true) ? 'date' : 'payment_date');
        $createdCol = in_array('created_at', $pcols, true) ? 'created_at' : null;
        $select = "SELECT p.$idCol AS payment_id, p.$apptCol AS appointment_id, p.$payDateCol AS payment_date, p.$amtCol AS amount";
        if ($methodCol) $select .= ", p.$methodCol AS method";
        else $select .= ", '-' AS method";
        if ($createdCol) $select .= ", p.$createdCol AS created_at";
        else $select .= ", p.$payDateCol AS created_at";
        $select .= " FROM payments p ORDER BY p.$payDateCol DESC";
        $stmt = $pdo->prepare($select);
        $stmt->execute();
        $paymentsList = $stmt->fetchAll();
    } catch (Exception $e) {
        $paymentsList = [];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Elysian</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="theme-admin">
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <img src="assets/img/logo.png" class="logo-img" alt="Elysian Salon logo">
            <!-- <span class="logo-text">Elysian Salon</span> -->
            </div>
            <div class="logo">Admin Panel</div>
            <nav class="sidebar-nav">
                <a href="?section=dashboard" class="<?php echo $section === 'dashboard' ? 'active' : ''; ?>">Dashboard Overview</a>
                <a href="?section=staff" class="<?php echo $section === 'staff' ? 'active' : ''; ?>">Manage Staff</a>
                <a href="?section=services" class="<?php echo $section === 'services' ? 'active' : ''; ?>">Manage Services</a>
                <a href="?section=appointments" class="<?php echo $section === 'appointments' ? 'active' : ''; ?>">View All Appointments</a>
                <a href="?section=payments" class="<?php echo $section === 'payments' ? 'active' : ''; ?>">Manage Payments</a>
                <a href="?section=finance" class="<?php echo $section === 'finance' ? 'active' : ''; ?>">Finance / Vouchers</a>
                <a href="account.php">Account</a>
                <a href="auth/logout.php" style="margin-top: 20px;">Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <header style="margin-bottom: 30px; border-bottom: 1px solid #ccc; padding-bottom: 10px;">
                <h2><?php echo ucfirst($section === 'dashboard' ? 'Dashboard Overview' : $section); ?></h2>
            </header>

            <?php if ($section === 'dashboard'): ?>
                <div class="card">
                    <h3>Welcome, Admin</h3>
                    <p>Select a section from the sidebar to manage the salon.</p>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div>
                            <div class="stat-number"><?php echo $stats['staff']; ?></div>
                            <div class="stat-label">Total Staff</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div>
                            <div class="stat-number"><?php echo $stats['services']; ?></div>
                            <div class="stat-label">Total Services</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div>
                            <div class="stat-number"><?php echo $stats['bookings']; ?></div>
                            <div class="stat-label">Total Bookings</div>
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:16px; align-items:flex-start; margin-top:20px;">
                    <div class="card" style="flex:1; min-width:0;">
                        <h3>Monthly User Growth</h3>
                        <canvas id="usersLine" width="440" height="240"></canvas>
                    </div>
                    <div class="card" style="flex:1; min-width:0;">
                        <h3>Bookings per Service (12 months)</h3>
                        <canvas id="servicesBar" width="440" height="240"></canvas>
                        <div id="servicesEmpty" style="display:none; color:#777; font-size:0.95rem;">No services booked in the last 12 months.</div>
                    </div>
                </div>
                

            <?php elseif ($section === 'staff'): ?>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3>Staff Members</h3>
                        
                    </div>
                    <div>
                            <input type="text" id="search-staff" class="form-control" placeholder="Search staff...">
                            <a href="admin/add_staff.php" class="btn btn-primary btn-small" style="margin-left:10px;">Add New Staff</a>
                            <button onclick="exportCSV('staff-table','staff.csv')" class="btn btn-small">Export CSV</button>
                        </div>
                    <div class="table-responsive">
                        <table id="staff-table" class="data-table">
                            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Specialization</th><th>Joined</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($staffList as $staff): ?>
                                <tr>
                                    <td>#<?php echo $staff['staff_id']; ?></td>
                                    <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['specialization']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($staff['created_at'])); ?></td>
                                    <td><a href="admin/delete_staff.php?id=<?php echo $staff['staff_id']; ?>" class="btn btn-small" onclick="return confirm('Delete this staff?');">Delete</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($section === 'services'): ?>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3>Services List</h3>
                    </div>
                    <div>
                            <input type="text" id="search-services" class="form-control" placeholder="Search services...">
                            <a href="admin/add_service.php" class="btn btn-primary btn-small" style="margin-left:10px;">Add Service</a>
                            <button onclick="exportCSV('services-table','services.csv')" class="btn btn-small">Export CSV</button>
                        </div>
                    <div class="table-responsive">
                        <table id="services-table" class="data-table">
                            <thead><tr><th>Service</th><th>Price (NPR)</th><th>Duration</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($servicesList as $svc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($svc['service_name']); ?></td>
                                    <td><?php echo htmlspecialchars($svc['price']); ?></td>
                                    <td><?php echo htmlspecialchars($svc['duration']); ?> min</td>
                                    <td>
                                        <a href="admin/edit_service.php?id=<?php echo $svc['service_id']; ?>" style="color:blue;">Edit</a> | 
                                        <a href="admin/delete_service.php?id=<?php echo $svc['service_id']; ?>" style="color:red;" onclick="return confirm('Delete?');">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($section === 'appointments'): ?>
                <div class="card">
                    <h3>All Appointments</h3>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <input type="text" id="search-appointments" class="form-control" placeholder="Search appointments...">
                        <button onclick="exportCSV('appointments-table','appointments.csv')" class="btn btn-small">Export CSV</button>
                    </div>
                    <div class="table-responsive">
                        <table id="appointments-table" class="data-table">
                            <thead><tr><th>ID</th><th>Customer</th><th>Service</th><th>Staff</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($appointmentsList as $appt): ?>
                                <tr>
                                    <td>#<?php echo $appt['appointment_id']; ?></td>
                                    <td><?php echo htmlspecialchars($appt['customer_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appt['staff_name'] ?? 'Unassigned'); ?></td>
                                    <td><?php echo $appt['appointment_date']; ?></td>
                                    <td><span class="badge badge-<?php echo strtolower($appt['status']); ?>"><?php echo ucfirst($appt['status']); ?></span></td>
                                    <td>
                                        <?php $st = strtolower(trim($appt['status'])); if (!in_array($st, ['completed','cancelled'])): ?>
                                            <a href="admin/cancel_appointment.php?id=<?php echo $appt['appointment_id']; ?>" class="btn btn-small">Cancel</a>
                                        <?php else: ?>
                                            <span style="color:#aaa;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($section === 'payments'): ?>
                <div class="card">
                    <h3>Manage Payments</h3>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <input type="text" id="search-payments" class="form-control" placeholder="Search payments...">
                        <button onclick="exportCSV('payments-table','payments.csv')" class="btn btn-small">Export CSV</button>
                    </div>
                    <div class="table-responsive">
                        <table id="payments-table" class="data-table">
                            <thead>
                                <tr>
                                    <th>payment_id</th>
                                    <th>appointment_id</th>
                                    <th>payment_date</th>
                                    <th>amount</th>
                                    <th>method</th>
                                    <th>status</th>
                                    <th>created_at</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($paymentsList)): ?>
                                    <tr><td colspan="6" class="text-center">No payment records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($paymentsList as $p): ?>
                                        <tr>
                                            <td>#<?php echo htmlspecialchars($p['payment_id']); ?></td>
                                            <td><?php echo htmlspecialchars($p['appointment_id']); ?></td>
                                            <td><?php echo htmlspecialchars($p['payment_date']); ?></td>
                                            <td><?php echo htmlspecialchars($p['amount']); ?></td>
                                            <td><?php echo htmlspecialchars($p['method']); ?></td>
                                            <td><?php echo htmlspecialchars($p['status'] ?? 'paid'); ?></td>
                                            <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($section === 'finance'): ?>
                <div class="card mb-2">
                    <h3>Vouchers</h3>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <input type="text" id="search-vouchers" class="form-control" placeholder="Search vouchers...">
                        <button onclick="exportCSV('vouchers-table','vouchers.csv')" class="btn btn-small">Export CSV</button>
                    </div>
                    <div class="card" style="margin-bottom:15px;">
                        <form method="POST" action="?section=finance" class="grid-4">
                            <input type="hidden" name="create_voucher" value="1">
                            <div class="form-group">
                                <label>Code</label>
                                <input type="text" name="code" class="form-control" placeholder="e.g. NEWYEAR25" required>
                            </div>
                            <div class="form-group">
                                <label>Discount Type</label>
                                <select name="discount_type" class="form-control">
                                    <option value="percent">Percent (%)</option>
                                    <option value="fixed">Fixed (NPR)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Value</label>
                                <input type="number" name="discount_value" class="form-control" step="0.01" min="1" required>
                            </div>
                            <div class="form-group">
                                <label>Expiry Date</label>
                                <input type="date" name="expiry_date" class="form-control" required>
                            </div>
                            <div class="form-group" style="align-self:flex-end;">
                                <label style="display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" name="is_active" checked>
                                    Active
                                </label>
                            </div>
                            <div class="form-group" style="align-self:flex-end;">
                                <button type="submit" class="btn btn-primary btn-small">Add Voucher</button>
                            </div>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table id="vouchers-table" class="data-table">
                            <thead><tr><th>Code</th><th>Discount</th><th>Expiry</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($vouchersList as $v): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($v['code']); ?></td>
                                    <td><?php echo $v['discount_type'] == 'percent' ? $v['discount_value'] . '%' : 'NPR ' . $v['discount_value']; ?></td>
                                    <td><?php echo $v['expiry_date']; ?></td>
                                    <td><?php echo $v['is_active'] ? 'Active' : 'Inactive'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <script>
            function exportCSV(tableId, filename) {
                const rows = Array.from(document.querySelectorAll(`#${tableId} tr`));
                const csv = rows.map(row => Array.from(row.children).map(td => `"${(td.innerText||'').replace(/"/g,'""')}"`).join(',')).join('\\n');
                const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = filename;
                link.click();
            }
            function bindSearch(inputId, tableId) {
                const inp = document.getElementById(inputId);
                const tbody = document.querySelector(`#${tableId} tbody`);
                inp && inp.addEventListener('input', () => {
                    const q = inp.value.toLowerCase();
                    Array.from(tbody.rows).forEach(r => {
                        r.style.display = r.innerText.toLowerCase().includes(q) ? '' : 'none';
                    });
                });
            }
            bindSearch('search-staff','staff-table');
            bindSearch('search-services','services-table');
            bindSearch('search-appointments','appointments-table');
            bindSearch('search-vouchers','vouchers-table');
            bindSearch('search-payments','payments-table');
            (function(){
                var labels = <?php echo json_encode($labels); ?>;
                var usersTotal = <?php echo json_encode($usersTotal); ?>;
                var usersAdmin = <?php echo json_encode($usersAdmin); ?>;
                var usersStaff = <?php echo json_encode($usersStaff); ?>;
                var usersCustomer = <?php echo json_encode($usersCustomer); ?>;
                var svcLabels = <?php echo json_encode($serviceLabels); ?>;
                var svcCounts = <?php echo json_encode($serviceCounts); ?>;
                var lc = document.getElementById('usersLine');
                var bc = document.getElementById('servicesBar');
                var svcEmpty = document.getElementById('servicesEmpty');
                function drawAxes(ctx, w, h, pad, maxY, xLabels, xIsMonths) {
                    ctx.strokeStyle = '#ccc';
                    ctx.beginPath(); ctx.moveTo(pad, h-pad); ctx.lineTo(w-pad, h-pad); ctx.stroke();
                    ctx.beginPath(); ctx.moveTo(pad, h-pad); ctx.lineTo(pad, pad); ctx.stroke();
                    ctx.fillStyle = '#666';
                    ctx.font = '12px sans-serif';
                    var ticks = 5;
                    for (var t=0; t<=ticks; t++) {
                        var val = Math.round(maxY * t / ticks);
                        var y = h - pad - ((h - pad*2) * t / ticks);
                        ctx.fillText(String(val), 4, y+4);
                        ctx.strokeStyle = '#eee';
                        ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(w-pad, y); ctx.stroke();
                    }
                    var aw = w - pad*2;
                    var step = xLabels.length>1 ? aw / (xLabels.length - 1) : aw;
                    ctx.fillStyle = '#666';
                    var approxWidth = 22; // ~ width for month labels like 'Jan'
                    var stride = 1;
                    if (step < approxWidth + 6) {
                        stride = Math.ceil((approxWidth + 6) / step);
                    }
                    for (var i=0; i<xLabels.length; i++) {
                        var x = pad + (xLabels.length>1 ? i*step : 0);
                        var lab = xLabels[i];
                        var tx = Math.max(pad, Math.min(x, w-pad-20));
                        if (xIsMonths) {
                            if (i % stride !== 0 && i !== xLabels.length-1) continue;
                            ctx.fillText(lab, tx-10, h-pad+14);
                        } else {
                            ctx.save();
                            ctx.translate(tx, h-pad+12);
                            ctx.rotate(-Math.PI/4);
                            ctx.fillText(lab.length>18 ? lab.slice(0,18)+'…' : lab, 0, 0);
                            ctx.restore();
                        }
                    }
                }
                function drawLineChart(canvas, labels, seriesTotal, breakdown) {
                    if (!canvas || !canvas.getContext) return;
                    var ctx = canvas.getContext('2d');
                    var w = canvas.width, h = canvas.height, pad = 40;
                    var max = Math.max.apply(null, seriesTotal.concat([1]));
                    ctx.clearRect(0,0,w,h);
                    drawAxes(ctx, w, h, pad, max, labels, true);
                    ctx.strokeStyle = '#6c63ff';
                    ctx.lineWidth = 2;
                    var aw = w - pad*2, ah = h - pad*2;
                    var pts = [];
                    ctx.beginPath();
                    for (var i=0; i<seriesTotal.length; i++) {
                        var x = pad + (seriesTotal.length>1 ? i * (aw / (seriesTotal.length-1)) : 0);
                        var y = h - pad - (ah * (seriesTotal[i] / max));
                        pts.push({x:x,y:y,idx:i});
                        if (i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
                    }
                    ctx.stroke();
                    var tip = document.createElement('div');
                    tip.style.position='absolute'; tip.style.padding='6px 8px'; tip.style.background='#333'; tip.style.color='#fff'; tip.style.borderRadius='4px'; tip.style.font='12px sans-serif'; tip.style.pointerEvents='none'; tip.style.display='none';
                    canvas.parentNode.appendChild(tip);
                    canvas.addEventListener('mousemove', function(ev){
                        var r = canvas.getBoundingClientRect();
                        var mx = ev.clientX - r.left, my = ev.clientY - r.top;
                        var found = null, mind = 12;
                        for (var k=0; k<pts.length; k++) {
                            var d = Math.hypot(mx-pts[k].x, my-pts[k].y);
                            if (d < mind) { mind = d; found = pts[k]; }
                        }
                        if (found) {
                            var i = found.idx;
                            var txt = labels[i] + ' | Total: ' + seriesTotal[i] + ' | admin: ' + breakdown.admin[i] + ' | staff: ' + breakdown.staff[i] + ' | customer: ' + breakdown.customer[i];
                            tip.innerText = txt;
                            tip.style.left = (r.left + found.x + 10) + 'px';
                            tip.style.top  = (r.top + found.y - 10) + 'px';
                            tip.style.display='block';
                        } else {
                            tip.style.display='none';
                        }
                    });
                }
                function drawBarChart(canvas, labels, counts) {
                    if (!canvas || !canvas.getContext) return;
                    var ctx = canvas.getContext('2d');
                    var w = canvas.width, h = canvas.height, pad = 50;
                    var max = Math.max.apply(null, counts.concat([1]));
                    ctx.clearRect(0,0,w,h);
                    drawAxes(ctx, w, h, pad, max, labels, false);
                    ctx.save(); ctx.fillStyle='#666'; ctx.font='12px sans-serif';
                    ctx.fillText('Services', Math.floor(w/2)-30, h-8);
                    ctx.translate(12, Math.floor(h/2));
                    ctx.rotate(-Math.PI/2);
                    ctx.fillText('Bookings', 0, 0);
                    ctx.restore();
                    var aw = w - pad*2, ah = h - pad*2;
                    var bw = Math.max(8, Math.floor(aw / labels.length) - 14);
                    var bars = [];
                    for (var i=0; i<counts.length; i++) {
                        var x = pad + i * (aw / labels.length) + 8;
                        var bh = ah * (counts[i] / max);
                        var y = h - pad - bh;
                        bars.push({x:x,y:y,w:bw,h:bh,idx:i});
                        ctx.fillStyle = '#ff6b6b';
                        ctx.fillRect(x, y, bw, bh);
                    }
                    var tip = document.createElement('div');
                    tip.style.position='absolute'; tip.style.padding='6px 8px'; tip.style.background='#333'; tip.style.color='#fff'; tip.style.borderRadius='4px'; tip.style.font='12px sans-serif'; tip.style.pointerEvents='none'; tip.style.display='none';
                    canvas.parentNode.appendChild(tip);
                    canvas.addEventListener('mousemove', function(ev){
                        var r = canvas.getBoundingClientRect();
                        var mx = ev.clientX - r.left, my = ev.clientY - r.top;
                        var f = null;
                        for (var k=0; k<bars.length; k++) {
                            var b = bars[k];
                            if (mx>=b.x && mx<=b.x+b.w && my>=b.y && my<=b.y+b.h) { f = b; break; }
                        }
                        if (f) {
                            var i = f.idx;
                            var txt = labels[i] + ' | ' + counts[i];
                            tip.innerText = txt;
                            tip.style.left = (r.left + f.x + f.w + 8) + 'px';
                            tip.style.top  = (r.top + f.y - 10) + 'px';
                            tip.style.display='block';
                        } else {
                            tip.style.display='none';
                        }
                    });
                }
                drawLineChart(lc, labels, usersTotal, {admin:usersAdmin, staff:usersStaff, customer:usersCustomer});
                if (svcCounts && svcCounts.length > 0) {
                    if (svcEmpty) svcEmpty.style.display = 'none';
                    drawBarChart(bc, svcLabels, svcCounts);
                } else {
                    if (svcEmpty) svcEmpty.style.display = 'block';
                }
            })();
            </script>
        </div>
    </div>
</body>
</html>
