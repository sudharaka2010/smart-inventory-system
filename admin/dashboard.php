<?php
// ✅ Errors (dev only; turn off in prod)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Session + Auth
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: /auth/login.php");
    exit();
}

// ✅ DB (mysqli)
require_once(__DIR__ . '/../includes/db_connect.php');
if (!isset($conn) || !$conn instanceof mysqli) {
    http_response_code(500);
    echo "<h1>Database not connected.</h1>";
    exit();
}

// ✅ Helpers
function getSafeValue(mysqli $conn, string $query, string $field) {
    $res = $conn->query($query);
    if ($res && ($row = $res->fetch_assoc())) return $row[$field] ?? 0;
    return 0;
}
function fetchAll(mysqli $conn, string $query): array {
    $rows = [];
    if ($res = $conn->query($query)) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free();
    }
    return $rows;
}
function n2($v){ return number_format((float)$v, 2, '.', ','); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ✅ KPIs (no user input)
$kpi = [];
$kpi['customers']      = getSafeValue($conn, "SELECT COUNT(*) AS c FROM customer", 'c');
$kpi['orders']         = getSafeValue($conn, "SELECT COUNT(*) AS c FROM `order`", 'c');
$kpi['stock_qty']      = (int)getSafeValue($conn, "SELECT COALESCE(SUM(Quantity),0) AS s FROM inventoryitem", 's');
$kpi['low_stock_cnt']  = (int)getSafeValue($conn, "SELECT COUNT(*) AS c FROM inventoryitem WHERE Quantity <= 5", 'c');
$kpi['returns']        = (int)getSafeValue($conn, "SELECT COUNT(*) AS c FROM returnitem", 'c');
$kpi['pending_orders'] = (int)getSafeValue($conn, "SELECT COUNT(*) AS c FROM `order` WHERE Status='Pending'", 'c');
$kpi['deliveries_today'] = (int)getSafeValue($conn, "SELECT COUNT(*) AS c FROM transport WHERE DeliveryDate = CURDATE()", 'c');

// Revenue blocks (Option A: Balance = TotalAmount - AmountPaid)
$rev = getSafeValue($conn, "
  SELECT JSON_OBJECT(
    'total_amount', ROUND(COALESCE(SUM(TotalAmount),0),2),
    'amount_paid', ROUND(COALESCE(SUM(AmountPaid),0),2),
    'balance_due', ROUND(COALESCE(SUM(TotalAmount - COALESCE(AmountPaid,0)),0),2)
  ) AS j
", 'j');
$rev = $rev ? json_decode($rev, true) : ['total_amount'=>0,'amount_paid'=>0,'balance_due'=>0];

// ✅ Tables
$recentOrders = fetchAll($conn, "
  SELECT o.OrderID, o.InvoiceID, o.OrderDate, o.TotalAmount, o.Status,
         c.Name AS CustomerName
  FROM `order` o
  LEFT JOIN customer c ON c.CustomerID = o.CustomerID
  ORDER BY o.OrderDate DESC, o.OrderID DESC
  LIMIT 10
");

$topItems = fetchAll($conn, "
  SELECT ii.ItemID, ii.Name AS ItemName, COALESCE(SUM(od.Quantity),0) AS QtySold
  FROM inventoryitem ii
  JOIN orderdetails od ON od.ItemID = ii.ItemID
  GROUP BY ii.ItemID, ii.Name
  ORDER BY QtySold DESC
  LIMIT 5
");

$lowStock = fetchAll($conn, "
  SELECT ItemID, InvoiceID, Name, Quantity
  FROM inventoryitem
  WHERE Quantity <= 10
  ORDER BY Quantity ASC
  LIMIT 10
");

// ✅ Layout includes
$headerFile = __DIR__ . '/header.php';
$sidebarFile = __DIR__ . '/sidebar.php';
$footerFile = __DIR__ . '/footer.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - RB Stores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="/assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php if (file_exists($headerFile)) include $headerFile; ?>
<?php if (file_exists($sidebarFile)) include $sidebarFile; ?>

<main class="main-content">
  <h2>Welcome to <span class="highlight">RB Stores Dashboard</span></h2>
  <p class="muted">As of <?=date('Y-m-d');?> • Balance uses <strong>TotalAmount − AmountPaid</strong></p>

  <!-- KPI Grid -->
  <section class="status-panel">
    <h3>Business Overview</h3>
    <div class="status-grid">
      <div class="status-card">
        <i class="fas fa-user-friends"></i>
        <h4>Customers</h4>
        <p><?=number_format((int)$kpi['customers']);?></p>
      </div>
      <div class="status-card">
        <i class="fas fa-receipt"></i>
        <h4>Orders</h4>
        <p><?=number_format((int)$kpi['orders']);?></p>
      </div>
      <div class="status-card">
        <i class="fas fa-coins"></i>
        <h4>Revenue (TotalAmount)</h4>
        <p>Rs <?=n2($rev['total_amount']);?></p>
      </div>
      <div class="status-card">
        <i class="fas fa-hand-holding-usd"></i>
        <h4>Amount Paid</h4>
        <p>Rs <?=n2($rev['amount_paid']);?></p>
      </div>
      <div class="status-card">
        <i class="fas fa-balance-scale"></i>
        <h4>Balance Due</h4>
        <p class="danger">Rs <?=n2($rev['balance_due']);?></p>
      </div>
      <a href="/admin/low_stock.php" class="status-card-link">
        <div class="status-card">
          <i class="fas fa-warehouse"></i>
          <h4>Low Stock (≤5)</h4>
          <p><?=number_format((int)$kpi['low_stock_cnt']);?></p>
        </div>
      </a>
      <div class="status-card">
        <i class="fas fa-cubes"></i>
        <h4>Items in Stock</h4>
        <p><?=number_format((int)$kpi['stock_qty']);?></p>
      </div>
      <div class="status-card">
        <i class="fas fa-hourglass-half"></i>
        <h4>Pending Orders</h4>
        <p><?=number_format((int)$kpi['pending_orders']);?></p>
      </div>
      <a href="/admin/pending_deliveries.php" class="status-card-link">
        <div class="status-card">
          <i class="fas fa-truck-loading"></i>
          <h4>Deliveries Today</h4>
          <p><?=number_format((int)$kpi['deliveries_today']);?></p>
        </div>
      </a>
      <div class="status-card">
        <i class="fas fa-undo-alt"></i>
        <h4>Returns</h4>
        <p><?=number_format((int)$kpi['returns']);?></p>
      </div>
    </div>
  </section>

  <!-- Tables -->
  <section class="cards-2">
    <div class="card">
      <div class="section-title">Recent Orders</div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Invoice</th>
              <th>Customer</th>
              <th>Date</th>
              <th>Total (Rs)</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$recentOrders): ?>
              <tr><td colspan="6" class="muted">No orders yet.</td></tr>
            <?php else: foreach ($recentOrders as $o): ?>
              <tr>
                <td>#<?= (int)$o['OrderID']; ?></td>
                <td class="accent"><?= h($o['InvoiceID'] ?: '—'); ?></td>
                <td><?= h($o['CustomerName'] ?? 'Guest'); ?></td>
                <td><?= h($o['OrderDate']); ?></td>
                <td><?= n2($o['TotalAmount']); ?></td>
                <td>
                  <?php
                    $status = $o['Status'] ?? 'Pending';
                    $cls = ($status==='Paid')?'ok':(($status==='Pending')?'warn':'danger');
                  ?>
                  <span class="badge <?=$cls;?>"><?= h($status); ?></span>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="section-title">Top Items (by Qty Sold)</div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Item</th><th>Qty Sold</th></tr></thead>
          <tbody>
            <?php if (!$topItems): ?>
              <tr><td colspan="2" class="muted">No sales yet.</td></tr>
            <?php else: foreach ($topItems as $t): ?>
              <tr>
                <td><?= h($t['ItemName']); ?> (ID: <?= (int)$t['ItemID']; ?>)</td>
                <td><?= number_format((int)$t['QtySold']); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div class="section-title" style="margin-top:12px">Low Stock (≤ 10)</div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Item</th><th>Qty</th><th>Invoice</th></tr></thead>
          <tbody>
            <?php if (!$lowStock): ?>
              <tr><td colspan="3" class="muted">All good.</td></tr>
            <?php else: foreach ($lowStock as $ls): ?>
              <?php
                $q = (int)$ls['Quantity'];
                $qCls = ($q <= 3)?'danger':(($q <= 7)?'warn':'');
              ?>
              <tr>
                <td><?= h($ls['Name']); ?> (ID: <?= (int)$ls['ItemID']; ?>)</td>
                <td class="<?=$qCls;?>"><?= number_format($q); ?></td>
                <td><?= h($ls['InvoiceID']); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</main>

<?php if (file_exists($footerFile)) include $footerFile; ?>

</body>
</html>
