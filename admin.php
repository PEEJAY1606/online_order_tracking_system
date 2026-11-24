<?php
require 'db.php';
require 'functions.php';
require_admin();
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'add', 'orders', 'customers'];
if (!in_array($page, $allowedPages, true)) $page = 'dashboard';
if (isset($_GET['edit'])) $page = 'add';
$pageTitles = ['dashboard' => 'Dashboard', 'add' => 'Add Order', 'orders' => 'All Orders', 'customers' => 'Customer Management'];
$error = '';
$success = '';
$statuses = ['Pending','Processing','Shipped','Out for Delivery','Delivered','Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $orderNumber = trim($_POST['order_number'] ?? '');
            $name = trim($_POST['customer_name'] ?? '');
            $email = trim($_POST['customer_email'] ?? '');
            $product = trim($_POST['product_name'] ?? '');
            $quantity = (int)($_POST['quantity'] ?? 0);
            $status = $_POST['status'] ?? 'Pending';
            $note = trim($_POST['tracking_note'] ?? '');
            if (!$orderNumber || !$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$product || $quantity < 1 || !in_array($status, $statuses, true)) {
                throw new Exception('Please complete all fields with valid information.');
            }
            // Keep a customer profile in sync whenever an order is created or edited.
            $customerStmt = $pdo->prepare('INSERT INTO customers (customer_name, customer_email) VALUES (?, ?) ON DUPLICATE KEY UPDATE customer_name=VALUES(customer_name)');
            $customerStmt->execute([$name, strtolower($email)]);
            if ($id > 0) {
                $oldStmt = $pdo->prepare('SELECT status FROM orders WHERE id=?');
                $oldStmt->execute([$id]);
                $old = $oldStmt->fetch();
                if (!$old) throw new Exception('The order could not be found.');
                $stmt = $pdo->prepare('UPDATE orders SET order_number=?, customer_name=?, customer_email=?, product_name=?, quantity=?, status=?, tracking_note=? WHERE id=?');
                $stmt->execute([$orderNumber,$name,$email,$product,$quantity,$status,$note,$id]);
                if ($old['status'] !== $status || $note !== '') record_order_status($pdo, $id, $status, $note);
                log_activity($pdo, 'Updated order', $orderNumber . ' — ' . $status);
                $success = 'Order updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO orders (order_number, customer_name, customer_email, product_name, quantity, status, tracking_note) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$orderNumber,$name,$email,$product,$quantity,$status,$note]);
                $newId = (int)$pdo->lastInsertId();
                record_order_status($pdo, $newId, $status, $note ?: 'Order created');
                log_activity($pdo, 'Created order', $orderNumber . ' — ' . $status);
                $success = 'Order added successfully.';
            }
        } elseif ($action === 'save_customer') {
            $customerId = (int)($_POST['customer_id'] ?? 0);
            $customerName = trim($_POST['customer_name'] ?? '');
            $customerEmail = strtolower(trim($_POST['customer_email'] ?? ''));
            $phone = trim($_POST['contact_number'] ?? '');
            $address = trim($_POST['address'] ?? '');
            if ($customerName === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) throw new Exception('Enter a customer name and valid email address.');
            if ($customerId > 0) {
                $stmt = $pdo->prepare('UPDATE customers SET customer_name=?, customer_email=?, contact_number=?, address=? WHERE id=?');
                $stmt->execute([$customerName,$customerEmail,$phone,$address,$customerId]);
                log_activity($pdo, 'Updated customer', $customerName . ' — ' . $customerEmail);
                $success = 'Customer updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO customers (customer_name, customer_email, contact_number, address) VALUES (?,?,?,?)');
                $stmt->execute([$customerName,$customerEmail,$phone,$address]);
                log_activity($pdo, 'Added customer', $customerName . ' — ' . $customerEmail);
                $success = 'Customer added successfully.';
            }
        } elseif ($action === 'delete_customer') {
            $customerId = (int)($_POST['customer_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT customer_name, customer_email FROM customers WHERE id=?');
            $stmt->execute([$customerId]); $customer = $stmt->fetch();
            if ($customer) {
                $count = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE LOWER(customer_email)=LOWER(?)');
                $count->execute([$customer['customer_email']]);
                if ((int)$count->fetchColumn() > 0) throw new Exception('This customer has order records. You cannot delete the profile while orders are linked to this email.');
                $pdo->prepare('DELETE FROM customers WHERE id=?')->execute([$customerId]);
                log_activity($pdo, 'Deleted customer', $customer['customer_name']);
            }
            $success = 'Customer deleted successfully.';
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT order_number FROM orders WHERE id=?');
            $stmt->execute([$id]);
            $deleted = $stmt->fetchColumn();
            $stmt = $pdo->prepare('DELETE FROM orders WHERE id=?');
            $stmt->execute([$id]);
            if ($deleted) log_activity($pdo, 'Deleted order', (string)$deleted);
            $success = 'Order deleted successfully.';
        }
    } catch (PDOException $ex) {
        $error = 'Could not save the order. Check that the order number is unique and that the feature upgrade SQL has been imported.';
    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}
$q = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR product_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($statusFilter !== '' && in_array($statusFilter, $statuses, true)) {
    $where[] = 'status = ?'; $params[] = $statusFilter;
}
$sql = 'SELECT * FROM orders' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY order_date DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $orders = $stmt->fetchAll();
$totalOrders = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='Pending'")->fetchColumn();
$processingCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('Processing','Shipped','Out for Delivery')")->fetchColumn();
$deliveredCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='Delivered'")->fetchColumn();
$cancelledCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='Cancelled'")->fetchColumn();
$monthLabels = []; $monthValues = [];
$monthly = $pdo->query("SELECT DATE_FORMAT(order_date, '%b %Y') AS month_label, COUNT(*) AS total FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY YEAR(order_date), MONTH(order_date), DATE_FORMAT(order_date, '%b %Y') ORDER BY YEAR(order_date), MONTH(order_date)")->fetchAll();
foreach ($monthly as $m) { $monthLabels[] = $m['month_label']; $monthValues[] = (int)$m['total']; }
$customerSearch = trim($_GET['customer_q'] ?? '');
$customerParams = [];
$customerSql = "SELECT c.*, COUNT(o.id) AS total_orders, MAX(o.order_date) AS last_order FROM customers c LEFT JOIN orders o ON LOWER(o.customer_email)=LOWER(c.customer_email)";
if ($customerSearch !== '') { $customerSql .= ' WHERE c.customer_name LIKE ? OR c.customer_email LIKE ? OR c.contact_number LIKE ?'; $likeCustomer = '%' . $customerSearch . '%'; $customerParams = [$likeCustomer,$likeCustomer,$likeCustomer]; }
$customerSql .= ' GROUP BY c.id ORDER BY c.created_at DESC';
$customerStmt = $pdo->prepare($customerSql); $customerStmt->execute($customerParams); $customers = $customerStmt->fetchAll();
$totalCustomers = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$customerWithOrders = (int)$pdo->query('SELECT COUNT(DISTINCT LOWER(customer_email)) FROM orders')->fetchColumn();
$repeatCustomers = (int)$pdo->query('SELECT COUNT(*) FROM (SELECT LOWER(customer_email) e FROM orders GROUP BY LOWER(customer_email) HAVING COUNT(*) > 1) x')->fetchColumn();
$selectedCustomer = null; $customerOrders = [];
if (isset($_GET['customer'])) { $cs = $pdo->prepare('SELECT * FROM customers WHERE id=?'); $cs->execute([(int)$_GET['customer']]); $selectedCustomer = $cs->fetch(); if ($selectedCustomer) { $os=$pdo->prepare('SELECT * FROM orders WHERE LOWER(customer_email)=LOWER(?) ORDER BY order_date DESC'); $os->execute([$selectedCustomer['customer_email']]); $customerOrders=$os->fetchAll(); } }
$recentActivity = [];
try { $recentActivity = $pdo->query('SELECT admin_username, action, details, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 6')->fetchAll(); } catch (Throwable $ignored) {}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>OrderTrack | <?= e($pageTitles[$page]) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="style.css" rel="stylesheet">
<style>
.analytics-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(260px,1fr);gap:16px;margin-bottom:16px}.analytics-card{background:#fff;border:1px solid var(--line);border-radius:15px;padding:20px;box-shadow:0 5px 18px rgba(24,52,94,.04)}.analytics-card h3{font-size:15px;font-weight:800;margin:0 0 16px}.chart-wrap{height:230px;position:relative}.mini-status{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #edf1f7;font-size:13px}.mini-status:last-child{border:0}.filter-select{min-width:150px}.activity-item{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #edf1f7;font-size:13px}.activity-item:last-child{border:0}.activity-dot{width:9px;height:9px;border-radius:50%;background:#2685ff;margin-top:5px;flex:none}
@media(max-width:900px){.analytics-grid{grid-template-columns:1fr}}
</style></head><body><div class="app-shell">
<aside class="sidebar"><a class="brand" href="admin.php"><span class="brand-mark">🚚</span><span><strong>Order<span style="color:#42a5ff">Track</span></strong><small>TRACK · MANAGE · DELIVER</small></span></a>
<div class="side-label">Workspace</div><nav>
<a class="side-link <?= $page==='dashboard'?'active':'' ?>" href="admin.php?page=dashboard"><span class="ico">▦</span>Dashboard</a><a class="side-link <?= $page==='add'?'active':'' ?>" href="admin.php?page=add"><span class="ico">＋</span>Add Order</a><a class="side-link <?= $page==='orders'?'active':'' ?>" href="admin.php?page=orders"><span class="ico">☷</span>All Orders</a><a class="side-link <?= $page==='customers'?'active':'' ?>" href="admin.php?page=customers"><span class="ico">♙</span>Customers</a><a class="side-link" href="index.php"><span class="ico">⌕</span>Tracking Page</a><a class="side-link" href="export.php"><span class="ico">⇩</span>Export Reports</a>
<div class="side-label mt-4">Account</div><a class="side-link" href="activity.php"><span class="ico">◷</span>Activity Logs</a><a class="side-link" href="settings.php"><span class="ico">⚙</span>Settings</a><a class="side-link" href="logout.php"><span class="ico">⇥</span>Logout</a></nav>
<div class="side-bottom"><div class="cube">▧</div><strong class="text-white">Fast. Secure. Reliable.</strong><div class="mt-2">Manage every delivery in one place.</div><div style="height:4px;width:55px;border-radius:4px;background:#2f9dff;margin-top:15px"></div></div></aside>
<main class="main"><header class="topbar"><div><div class="fw-bold"><?= e($pageTitles[$page]) ?></div><div class="crumb">Workspace / <?= e($pageTitles[$page]) ?></div></div><div class="user-pill"><div class="avatar">A</div><span><?= e($_SESSION['admin_username'] ?? 'Admin') ?></span></div></header>
<div class="content"><section class="hero"><div class="d-flex align-items-center position-relative" style="z-index:1"><div class="hero-icon">▣</div><div><h1><?= e($pageTitles[$page]) ?></h1><p><?php if ($page === 'dashboard'): ?>Overview of your order tracking system.<?php elseif ($page === 'add'): ?>Create a new customer order or update an existing one.<?php elseif ($page === 'customers'): ?>Manage customer profiles and review their order history.<?php else: ?>Search, filter, and manage all customer orders.<?php endif; ?></p></div></div></section>
<?php if ($error): ?><div class="alert alert-danger mb-3"><?= e($error) ?></div><?php endif; ?><?php if ($success): ?><div class="alert alert-success mb-3"><?= e($success) ?></div><?php endif; ?>
<?php if ($page === 'dashboard'): ?>
<section class="stats"><div class="stat-card"><div><small>Total Orders</small><strong><?= $totalOrders ?></strong></div><div class="stat-icon">▤</div></div><div class="stat-card"><div><small>Pending Orders</small><strong><?= $pendingCount ?></strong></div><div class="stat-icon">◷</div></div><div class="stat-card"><div><small>In Progress</small><strong><?= $processingCount ?></strong></div><div class="stat-icon">↗</div></div><div class="stat-card"><div><small>Delivered</small><strong><?= $deliveredCount ?></strong></div><div class="stat-icon">✓</div></div></section>
<section class="analytics-grid"><div class="analytics-card"><h3>Order Activity · Last 7 Months</h3><div class="chart-wrap"><canvas id="ordersChart"></canvas></div></div><div class="analytics-card"><h3>Order Status Overview</h3>
<div class="mini-status"><span>Pending</span><strong><?= $pendingCount ?></strong></div><div class="mini-status"><span>In Progress</span><strong><?= $processingCount ?></strong></div><div class="mini-status"><span>Delivered</span><strong><?= $deliveredCount ?></strong></div><div class="mini-status"><span>Cancelled</span><strong><?= $cancelledCount ?></strong></div><div class="mini-status"><span>Total</span><strong><?= $totalOrders ?></strong></div></div></section>
<?php endif; ?>
<?php if ($page === 'add'): ?>
<section class="panel" id="order-form"><div class="panel-head"><div><h2><?= $edit ? 'Edit Order' : '＋ Add New Order' ?></h2><div class="sub"><?= $edit ? 'Update the order details below.' : 'Enter the customer and delivery information.' ?></div></div><span class="status s-delivered">● System Online</span></div><div class="panel-body"><form method="post" class="row g-3">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
<div class="col-md-4"><label class="form-label">Order Number</label><input class="form-control" name="order_number" placeholder="e.g. ORD-2026-1001" value="<?= e($edit['order_number'] ?? '') ?>" required></div><div class="col-md-4"><label class="form-label">Customer Name</label><input class="form-control" name="customer_name" placeholder="e.g. Juan Dela Cruz" value="<?= e($edit['customer_name'] ?? '') ?>" required></div><div class="col-md-4"><label class="form-label">Customer Email</label><input class="form-control" type="email" name="customer_email" placeholder="e.g. juan@example.com" value="<?= e($edit['customer_email'] ?? '') ?>" required></div>
<div class="col-md-4"><label class="form-label">Product Name</label><input class="form-control" name="product_name" placeholder="e.g. Wireless Headphones" value="<?= e($edit['product_name'] ?? '') ?>" required></div><div class="col-md-2"><label class="form-label">Quantity</label><input class="form-control" type="number" min="1" name="quantity" value="<?= e($edit['quantity'] ?? 1) ?>" required></div><div class="col-md-3"><label class="form-label">Order Status</label><select class="form-select" name="status"><?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= (($edit['status'] ?? 'Pending') === $s) ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Tracking Note</label><input class="form-control" name="tracking_note" placeholder="e.g. Package received by courier" value="<?= e($edit['tracking_note'] ?? '') ?>"></div>
<div class="col-12 d-flex gap-2 pt-1"><button class="btn btn-primary"><?= $edit ? '✓ Update Order' : '➤ Add Order' ?></button><?php if ($edit): ?><a class="btn btn-soft px-3" href="admin.php?page=add">Cancel</a><?php endif; ?></div></form></div></section>
<?php endif; ?>
<?php if ($page === 'orders'): ?>
<section class="panel" id="all-orders"><div class="panel-head flex-wrap"><div><h2>☷ All Orders</h2><div class="sub">Search, filter, view, and manage customer orders</div></div><div class="d-flex gap-2 flex-wrap align-items-center"><a href="export.php" class="btn btn-soft btn-sm">⇩ Export CSV</a></div></div>
<form method="get" class="px-4 py-3 d-flex gap-2 flex-wrap border-bottom" style="border-color:var(--line)!important"><input type="hidden" name="page" value="orders"><input type="search" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:310px" placeholder="Search order, customer, product..."><select class="form-select filter-select" name="status"><option value="">All statuses</option><?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $statusFilter===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select><button class="btn btn-primary btn-sm px-3">Apply Filters</button><a href="admin.php?page=orders" class="btn btn-soft btn-sm">Clear</a></form>
<div class="table-responsive"><table class="table mb-0"><thead><tr><th>#</th><th>Order Number</th><th>Customer</th><th>Product</th><th>Qty</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php if (!$orders): ?><tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📦</div><strong>No orders found</strong><div>Try changing your search or add a new order above.</div></div></td></tr><?php endif; ?>
<?php foreach ($orders as $i=>$o): ?><tr><td class="text-muted"><?= $i+1 ?></td><td><span class="order-id"><?= e($o['order_number']) ?></span></td><td><div class="customer-name"><?= e($o['customer_name']) ?></div><div class="customer-email"><?= e($o['customer_email']) ?></div></td><td><?= e($o['product_name']) ?></td><td><?= e($o['quantity']) ?></td><td><span class="status <?= e(status_class($o['status'])) ?>"><?= e($o['status']) ?></span></td><td class="text-nowrap"><button type="button" class="action-link action-view" data-bs-toggle="modal" data-bs-target="#viewOrder" data-number="<?= e($o['order_number']) ?>" data-customer="<?= e($o['customer_name']) ?>" data-email="<?= e($o['customer_email']) ?>" data-product="<?= e($o['product_name']) ?>" data-qty="<?= e($o['quantity']) ?>" data-status="<?= e($o['status']) ?>" data-note="<?= e($o['tracking_note'] ?? '') ?>" data-date="<?= e($o['order_date']) ?>" data-updated="<?= e($o['updated_at']) ?>">View</button> <a class="action-link action-edit" href="admin.php?page=add&edit=<?= e($o['id']) ?>">Edit</a> <form method="post" class="d-inline" onsubmit="return confirm('Delete this order permanently?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($o['id']) ?>"><button class="action-link action-delete">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div><div class="px-4 py-3 d-flex justify-content-between border-top" style="border-color:var(--line)!important"><small class="text-muted">Showing <?= count($orders) ?> of <?= $totalOrders ?> orders</small><small class="text-muted">OrderTrack Admin System</small></div></section>
<?php endif; ?>
<?php if ($page === 'customers'): ?>
<section class="stats"><div class="stat-card"><div><small>Total Customers</small><strong><?= $totalCustomers ?></strong></div><div class="stat-icon">♙</div></div><div class="stat-card"><div><small>Customers with Orders</small><strong><?= $customerWithOrders ?></strong></div><div class="stat-icon">▤</div></div><div class="stat-card"><div><small>Repeat Customers</small><strong><?= $repeatCustomers ?></strong></div><div class="stat-icon">↻</div></div><div class="stat-card"><div><small>New Profiles</small><strong><?= $totalCustomers ?></strong></div><div class="stat-icon">＋</div></div></section>
<section class="panel mb-4"><div class="panel-head"><div><h2>＋ Add Customer</h2><div class="sub">Create a customer profile. Customers added through new orders are saved automatically.</div></div></div><div class="panel-body"><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_customer"><div class="col-md-3"><label class="form-label">Customer Name</label><input class="form-control" name="customer_name" required></div><div class="col-md-3"><label class="form-label">Email Address</label><input class="form-control" type="email" name="customer_email" required></div><div class="col-md-2"><label class="form-label">Contact Number</label><input class="form-control" name="contact_number"></div><div class="col-md-3"><label class="form-label">Address</label><input class="form-control" name="address"></div><div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100">Add</button></div></form></div></section>
<section class="panel"><div class="panel-head flex-wrap"><div><h2>♙ Customer Directory</h2><div class="sub">Search customers and view their order history</div></div></div><form method="get" class="px-4 py-3 d-flex gap-2"><input type="hidden" name="page" value="customers"><input class="form-control" name="customer_q" value="<?= e($customerSearch) ?>" placeholder="Search name, email, or phone"><button class="btn btn-primary btn-sm">Search</button><a class="btn btn-soft btn-sm" href="admin.php?page=customers">Clear</a></form><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Customer</th><th>Contact</th><th>Total Orders</th><th>Last Order</th><th>Actions</th></tr></thead><tbody><?php if (!$customers): ?><tr><td colspan="5"><div class="empty-state"><strong>No customers found</strong><div>Add a customer or create an order to populate this list.</div></div></td></tr><?php endif; ?><?php foreach ($customers as $c): ?><tr><td><div class="customer-name"><?= e($c['customer_name']) ?></div><div class="customer-email"><?= e($c['customer_email']) ?></div></td><td><?= e($c['contact_number'] ?: '—') ?></td><td><span class="order-id"><?= (int)$c['total_orders'] ?></span></td><td><?= e($c['last_order'] ? date('M d, Y', strtotime($c['last_order'])) : 'No orders yet') ?></td><td class="text-nowrap"><a class="action-link action-view" href="admin.php?page=customers&customer=<?= (int)$c['id'] ?>">Order History</a> <button class="action-link action-edit" type="button" data-bs-toggle="modal" data-bs-target="#editCustomer" data-id="<?= (int)$c['id'] ?>" data-name="<?= e($c['customer_name']) ?>" data-email="<?= e($c['customer_email']) ?>" data-phone="<?= e($c['contact_number'] ?? '') ?>" data-address="<?= e($c['address'] ?? '') ?>">Edit</button> <form method="post" class="d-inline" onsubmit="return confirm('Delete this customer profile? Profiles with linked orders are protected.');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_customer"><input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>"><button class="action-link action-delete">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php if ($selectedCustomer): ?><section class="panel mt-4"><div class="panel-head"><div><h2>Order History: <?= e($selectedCustomer['customer_name']) ?></h2><div class="sub"><?= e($selectedCustomer['customer_email']) ?> · <?= count($customerOrders) ?> order(s)</div></div><a class="btn btn-soft btn-sm" href="admin.php?page=customers">Close</a></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Order Number</th><th>Product</th><th>Qty</th><th>Status</th><th>Order Date</th></tr></thead><tbody><?php if (!$customerOrders): ?><tr><td colspan="5" class="text-center p-4">No orders for this customer yet.</td></tr><?php endif; ?><?php foreach ($customerOrders as $co): ?><tr><td class="order-id"><?= e($co['order_number']) ?></td><td><?= e($co['product_name']) ?></td><td><?= (int)$co['quantity'] ?></td><td><span class="status <?= e(status_class($co['status'])) ?>"><?= e($co['status']) ?></span></td><td><?= e(date('M d, Y', strtotime($co['order_date']))) ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
<div class="modal fade" id="editCustomer" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_customer"><input type="hidden" name="customer_id" id="ec-id"><label class="form-label">Name</label><input class="form-control mb-3" name="customer_name" id="ec-name" required><label class="form-label">Email</label><input class="form-control mb-3" type="email" name="customer_email" id="ec-email" required><label class="form-label">Contact Number</label><input class="form-control mb-3" name="contact_number" id="ec-phone"><label class="form-label">Address</label><textarea class="form-control" name="address" id="ec-address" rows="2"></textarea></div><div class="modal-footer"><button class="btn btn-primary">Save Changes</button></div></form></div></div>
<?php endif; ?>
</div></main></div>
<div class="modal fade" id="viewOrder" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:16px"><div class="modal-header"><h5 class="modal-title fw-bold">Order Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><small class="text-muted">Order Number</small><h5 id="v-number" class="fw-bold mb-0"></h5></div><div class="row g-3"><div class="col-6"><small class="text-muted">Customer</small><div id="v-customer" class="fw-semibold"></div></div><div class="col-6"><small class="text-muted">Email</small><div id="v-email"></div></div><div class="col-6"><small class="text-muted">Product</small><div id="v-product"></div></div><div class="col-6"><small class="text-muted">Quantity</small><div id="v-qty"></div></div><div class="col-6"><small class="text-muted">Status</small><div id="v-status"></div></div><div class="col-6"><small class="text-muted">Order Date</small><div id="v-date"></div></div><div class="col-12"><small class="text-muted">Tracking Note</small><div id="v-note" class="p-3 rounded-3 mt-1" style="background:#f4f7fc"></div></div><div class="col-12"><small class="text-muted">Last Updated</small><div id="v-updated"></div></div></div><hr class="my-4"><div class="text-center"><div class="fw-bold mb-1">Customer Tracking QR Code</div><div class="small text-muted mb-3">Scan to open this order's public tracking page.</div><div id="orderQrCode" class="d-inline-flex align-items-center justify-content-center p-3 bg-white border rounded-3" style="min-width:180px;min-height:180px"></div><div class="small text-muted mt-2" id="qrOrderLabel"></div><div class="d-flex justify-content-center gap-2 flex-wrap mt-3"><button type="button" class="btn btn-primary btn-sm" id="downloadOrderQr" disabled>Download QR (PNG)</button><a class="btn btn-soft btn-sm" id="openTrackingLink" href="#" target="_blank" rel="noopener">Open Tracking Page</a></div></div></div><div class="modal-footer"><button class="btn btn-soft" data-bs-dismiss="modal">Close</button></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>
const viewOrderModal=document.getElementById('viewOrder');
if(viewOrderModal){
 viewOrderModal.addEventListener('show.bs.modal',function(ev){
  const d=ev.relatedTarget.dataset;
  ['number','customer','email','product','qty','status','date','note','updated'].forEach(k=>document.getElementById('v-'+k).textContent=d[k]||'—');
  const qrBox=document.getElementById('orderQrCode');
  const downloadBtn=document.getElementById('downloadOrderQr');
  const openLink=document.getElementById('openTrackingLink');
  qrBox.innerHTML='';
  downloadBtn.disabled=true;
  document.getElementById('qrOrderLabel').textContent='Order: '+(d.number||'');
  const trackingUrl=new URL('index.php',window.location.href);
  trackingUrl.searchParams.set('order',d.number||'');
  openLink.href=trackingUrl.href;
  if(window.QRCode && d.number){
   new QRCode(qrBox,{text:trackingUrl.href,width:180,height:180,colorDark:'#10264d',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.H});
   downloadBtn.disabled=false;
  } else {
   qrBox.textContent='QR generator unavailable. Check your internet connection.';
  }
 });
}
const qrDownload=document.getElementById('downloadOrderQr');
if(qrDownload){qrDownload.addEventListener('click',function(){
 const canvas=document.querySelector('#orderQrCode canvas');
 const img=document.querySelector('#orderQrCode img');
 const dataUrl=canvas?canvas.toDataURL('image/png'):(img?img.src:'');
 if(!dataUrl)return;
 const a=document.createElement('a');
 a.href=dataUrl;
 a.download='OrderTrack-'+(document.getElementById('v-number').textContent||'order')+'-QR.png';
 document.body.appendChild(a);a.click();a.remove();
});}
const editCustomerModal=document.getElementById('editCustomer');if(editCustomerModal){editCustomerModal.addEventListener('show.bs.modal',function(ev){const d=ev.relatedTarget.dataset;document.getElementById('ec-id').value=d.id;document.getElementById('ec-name').value=d.name;document.getElementById('ec-email').value=d.email;document.getElementById('ec-phone').value=d.phone;document.getElementById('ec-address').value=d.address;});}
const chartCanvas=document.getElementById('ordersChart');if(chartCanvas){new Chart(chartCanvas,{type:'bar',data:{labels:<?= json_encode($monthLabels) ?>,datasets:[{label:'Orders',data:<?= json_encode($monthValues) ?>,backgroundColor:'#2583f5',borderRadius:7,maxBarThickness:36}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:'#edf1f7'}},x:{grid:{display:false}}}}})}
</script></body></html>
