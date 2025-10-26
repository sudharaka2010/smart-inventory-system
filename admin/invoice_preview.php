<?php
// invoice_preview.php — CLEAN PRINT/PDF VERSION (RB Stores schema)
// Screen = preview + actions; Print/PDF = invoice only (no extra words)

declare(strict_types=1);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
date_default_timezone_set('Asia/Colombo');

/* -------- COMPANY CONFIG -------- */
$COMPANY = [
  'name'    => 'RB Stores',
  'tagline' => 'Rainwater Solutions Inventory & Order Management',
  'logo'    => '/rbstorsg/assets/images/rb.png', // update if needed
  'address' => 'Liberty Plaza, 2nd Floor, Kollupitiya, Colombo',
  'phone'   => '+94 77 123 4567',
  'email'   => 'info@rbstores.lk',
];

/* -------- HELPERS -------- */
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money(float $n): string { return number_format($n, 2); }

/* -------- RENDER MODE -------- */
$PLAIN = isset($_GET['plain']) && $_GET['plain'] === '1';

/* -------- OPTIONAL LAYOUT INCLUDES (screen only) -------- */
if (!$PLAIN) {
  if (file_exists(__DIR__.'/header.php'))  include __DIR__.'/header.php';
  if (file_exists(__DIR__.'/sidebar.php')) include __DIR__.'/sidebar.php';
}

/* -------- DB -------- */
$dsn = 'mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4';
try {
  $pdo = new PDO($dsn, 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo 'DB connection failed';
  exit;
}

/* -------- INPUTS -------- */
$orderId    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$invoiceIdQ = isset($_GET['invoice']) ? trim($_GET['invoice']) : '';

/* -------- FETCH ORDER (fallback: latest) -------- */
if ($orderId > 0) {
  $q = $pdo->prepare("SELECT * FROM `order` WHERE `OrderID` = ? LIMIT 1");
  $q->execute([$orderId]);
} elseif ($invoiceIdQ !== '') {
  $q = $pdo->prepare("SELECT * FROM `order` WHERE `InvoiceID` = ? LIMIT 1");
  $q->execute([$invoiceIdQ]);
} else {
  $q = $pdo->query("SELECT * FROM `order` ORDER BY `OrderID` DESC LIMIT 1");
}
$order = $q->fetch();
if (!$order) { http_response_code(404); echo 'Invoice not found'; exit; }

/* -------- CUSTOMER -------- */
$customer = null;
if (!empty($order['CustomerID'])) {
  $c = $pdo->prepare("
    SELECT CustomerID, NAME AS CustomerName, Phone AS CustomerPhone, Email AS CustomerEmail, Address AS CustomerAddress
    FROM customer WHERE CustomerID = ? LIMIT 1
  ");
  $c->execute([$order['CustomerID']]);
  $customer = $c->fetch() ?: null;
}

/* -------- ITEMS -------- */
$itemsStmt = $pdo->prepare("
  SELECT
    od.OrderDetailID,
    od.Quantity AS Qty,
    od.Subtotal AS LineSubtotal,
    ii.NAME AS ItemName,
    ii.Description AS ItemDescription,
    ii.Price AS ItemUnitPrice
  FROM orderdetails od
  LEFT JOIN inventoryitem ii ON ii.ItemID = od.ItemID
  WHERE od.OrderID = ?
  ORDER BY od.OrderDetailID ASC
");
$itemsStmt->execute([$order['OrderID']]);
$items = $itemsStmt->fetchAll();

/* -------- TOTALS -------- */
$subTotal     = (float)($order['SubTotal'] ?? 0);
$discount     = (float)($order['Discount'] ?? 0);
$vat          = (float)($order['VAT'] ?? 0);
$totalAmount  = (float)($order['TotalAmount'] ?? max(0, $subTotal - $discount + $vat));
$amountPaid   = (float)($order['AmountPaid'] ?? 0);
$balance      = (float)($order['Balance'] ?? max(0, $totalAmount - $amountPaid));
$paymentMethod= (string)($order['PaymentMethod'] ?? 'N/A');
$status       = (string)($order['Status'] ?? 'Pending');
$invoiceId    = (string)($order['InvoiceID'] ?? ('INV-'.str_pad((string)$order['OrderID'], 6, '0', STR_PAD_LEFT)));
$createdAt    = !empty($order['OrderDate']) ? $order['OrderDate'] : date('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice <?= h($invoiceId) ?> — <?= h($COMPANY['name']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* ------- SCREEN THEME (white card like your about.php) ------- */
body{background:#f4f6f9;color:#1e293b;margin:0}
.container{<?php if(!$PLAIN): ?>margin-left:260px;<?php endif; ?>padding:20px;max-width:1200px}
@media(max-width:992px){.container{margin-left:0;padding:14px}}

.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.06);margin-bottom:16px;border:1px solid #e5e7eb}
.card .screen-bar{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e5e7eb;padding:12px 16px}
.screen-title{font-size:20px;font-weight:600;margin:0}
.btns{display:flex; gap:8px}
.btn{background:#2563eb;color:#fff;border:none;border-radius:10px;padding:10px 14px;font-weight:600;cursor:pointer}
.btn.secondary{background:#0f172a}

.section{padding:14px 18px}
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}
.col-4{grid-column:span 4}
.col-6{grid-column:span 6}
.col-8{grid-column:span 8}
.col-12{grid-column:span 12}
@media(max-width:992px){.col-4,.col-6,.col-8{grid-column:span 12}}

.logo{height:70px;width:auto;border-radius:10px;border:1px solid #e5e7eb;background:#fff;object-fit:contain}
.small{color:#64748b;font-size:13px}
.badge{background:#10b981;color:#fff;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:700}
.muted{color:#64748b}

/* table */
.table-wrap{overflow:auto;border:1px solid #e5e7eb;border-radius:12px}
table{width:100%;border-collapse:collapse;min-width:720px}
th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:14px;vertical-align:top}
thead th{background:#f8fafc;text-align:left}
.t-right{text-align:right}
.t-center{text-align:center}

.totals{border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fafafa}
.trow{display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px dashed #e5e7eb}
.trow:last-child{border-bottom:none}
.grand{font-weight:800}
.due{font-weight:800;color:#b91c1c}

.note{background:#fafafa;border:1px solid #e5e7eb;border-radius:12px;padding:12px}
.hr{height:1px;background:#e5e7eb;margin:12px 0}
.right{text-align:right}

/* ------- PRINT OPTIMIZATION ------- */
@page{ size:A4; margin:14mm }
@media print{
  /* remove app chrome & screen-only text */
  .screen-bar, .btns, .no-print, header, .app-header, .topbar, .navbar, #sidebar, .sidebar, nav, .footer{ display:none !important }
  body{ background:#fff }
  .container{ margin:0 !important; padding:0 !important; max-width:none }
  .card{ box-shadow:none; border:none; border-radius:0 }
  .section{ padding:0 0 8px 0 } /* tighter for paper */
  /* table: no inner scroll, repeat header, clean breaks */
  .table-wrap{ border:none; overflow:visible }
  thead{display:table-header-group}
  tfoot{display:table-footer-group}
  tr, img, .note, .totals{ break-inside:avoid }
  th,td{ padding:8px 10px }
}
/* Hint: to remove browser header/footer (URL/date), turn OFF "Headers and footers" in print dialog. */
</style>
</head>
<body>
  <div class="container">
    <div class="card">

      <!-- SCREEN-ONLY BAR (hidden in print) -->
      <div class="screen-bar no-print">
        <h1 class="screen-title">Invoice Preview</h1>
        <div class="btns">
          <?php if(!$PLAIN): ?>
            <a class="btn secondary" href="view_billing.php">← Back</a>
            <a class="btn" target="_blank" href="invoice_preview.php?<?= $orderId? 'id='.(int)$order['OrderID'] : 'invoice='.urlencode($invoiceId) ?>&plain=1">Open Clean Preview</a>
          <?php endif; ?>
          <button class="btn" onclick="window.print()">Print / Save PDF</button>
        </div>
      </div>

      <!-- INVOICE BODY (shown on screen & print) -->
      <div class="section">
        <!-- Header: Logo + Company + Invoice meta -->
        <div class="grid" style="align-items:center">
          <div class="col-4" style="display:flex;gap:14px;align-items:center">
            <img src="<?= h($COMPANY['logo']) ?>" alt="logo" class="logo">
            <div>
              <div style="font-weight:700;font-size:20px"><?= h($COMPANY['name']) ?></div>
              <div class="small"><?= h($COMPANY['tagline']) ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="small"><?= h($COMPANY['address']) ?></div>
            <div class="small">Tel: <?= h($COMPANY['phone']) ?> • Email: <?= h($COMPANY['email']) ?></div>
          </div>
          <div class="col-4 right">
            <div style="font-size:18px;font-weight:800;letter-spacing:1px">INVOICE</div>
            <div><b>Invoice #:</b> <?= h($invoiceId) ?></div>
            <div><b>Order ID:</b> <?= (int)$order['OrderID'] ?></div>
            <div><b>Date:</b> <?= h($createdAt) ?></div>
            <div><b>Status:</b> <span class="badge"><?= h($status) ?></span></div>
          </div>
        </div>

        <div class="hr"></div>

        <!-- Bill To / From -->
        <div class="grid">
          <div class="col-6">
            <h3>Bill To</h3>
            <div><b><?= h($customer['CustomerName'] ?? 'Walk-in Customer') ?></b></div>
            <?php if (!empty($customer['CustomerAddress'])): ?>
              <div class="small"><?= h($customer['CustomerAddress']) ?></div>
            <?php endif; ?>
            <div class="small">
              <?= !empty($customer['CustomerPhone']) ? 'Tel: '.h($customer['CustomerPhone']) : '' ?>
              <?= !empty($customer['CustomerEmail']) ? ' • Email: '.h($customer['CustomerEmail']) : '' ?>
            </div>
          </div>
          <div class="col-6">
            <h3>From</h3>
            <div><b><?= h($COMPANY['name']) ?></b></div>
            <div class="small"><?= h($COMPANY['address']) ?></div>
            <div class="small">Tel: <?= h($COMPANY['phone']) ?> • Email: <?= h($COMPANY['email']) ?></div>
            <div class="small">Payment Method: <b><?= h($paymentMethod) ?></b></div>
          </div>
        </div>

        <div class="hr"></div>

        <!-- Items -->
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Item</th>
                <th>Description</th>
                <th class="t-right">Qty</th>
                <th class="t-right">Unit Price (LKR)</th>
                <th class="t-right">Line Total (LKR)</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$items): ?>
                <tr><td colspan="6" class="t-center muted">No items found.</td></tr>
              <?php else: $i=1; foreach ($items as $it):
                $qty  = (float)($it['Qty'] ?? 0);
                $line = (float)($it['LineSubtotal'] ?? 0);
                $unit = $qty > 0 && $line > 0 ? ($line / $qty) : (float)($it['ItemUnitPrice'] ?? 0);
              ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= h($it['ItemName'] ?? 'Item') ?></td>
                <td><?= h($it['ItemDescription'] ?? '') ?></td>
                <td class="t-right"><?= money($qty) ?></td>
                <td class="t-right"><?= money($unit) ?></td>
                <td class="t-right"><?= money($line > 0 ? $line : ($unit * $qty)) ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <div class="hr"></div>

        <!-- Notes + Totals -->
        <div class="grid">
          <div class="col-8">
            <div class="note">
              <b>Notes</b>
              <div class="small" style="margin-top:6px">
                <?= nl2br(h($order['Notes'] ?? 'Thank you for your business!')) ?>
              </div>
            </div>
          </div>
          <div class="col-4">
            <div class="totals">
              <div class="trow"><span>Sub Total</span><span>LKR <?= money($subTotal) ?></span></div>
              <div class="trow"><span>Discount</span><span>− LKR <?= money($discount) ?></span></div>
              <div class="trow"><span>VAT</span><span>+ LKR <?= money($vat) ?></span></div>
              <div class="trow grand"><span>Grand Total</span><span>LKR <?= money($totalAmount) ?></span></div>
              <div class="trow"><span>Amount Paid</span><span>− LKR <?= money($amountPaid) ?></span></div>
              <div class="trow due"><span>Balance Due</span><span>LKR <?= money($balance) ?></span></div>
            </div>
          </div>
        </div>

        <!-- Screen-only meta line (hidden in print) -->
        <div class="hr no-print"></div>
        <div class="small right muted no-print">
          Generated: <?= h(date('Y-m-d H:i')) ?> • <?= h($COMPANY['name']) ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
<?php if (!$PLAIN && file_exists(__DIR__.'/footer.php')) include __DIR__.'/footer.php'; ?>
