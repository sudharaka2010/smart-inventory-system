<?php
// view_billing.php  (FULL UPDATED — Paid + Changed badges; Edit only for Paid)
// Sticky header table, clean export/print, safe delete with CSRF

declare(strict_types=1);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

date_default_timezone_set('Asia/Colombo');

/* ------------------ CSRF ------------------ */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

/* ------------------ DB ------------------ */
$dsn = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
try {
  $pdo = new PDO($dsn, 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  die("DB failed: " . htmlspecialchars($e->getMessage()));
}

$messages = [];
$errors   = [];

/* helper for formatted Order Code */
function order_code(int $id, string $orderDate): string {
  $y = @date('Y', strtotime($orderDate)) ?: date('Y');
  return 'ORD-' . $y . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
}

/* ------------------ Delete (GET; CSRF protected) ------------------ */
if (isset($_GET['delete'], $_GET['_csrf']) && hash_equals($CSRF, (string)$_GET['_csrf'])) {
  $id = (int)$_GET['delete'];
  try {
    $pdo->prepare("DELETE FROM `order` WHERE OrderID=?")->execute([$id]);
    $messages[] = "✅ Order #$id deleted successfully.";
  } catch (Throwable $e) {
    $errors[] = "Delete failed: " . htmlspecialchars($e->getMessage());
  }
}

/* ------------------ Filters ------------------ */
$search_date     = $_GET['search_date'] ?? '';
$search_customer = (int)($_GET['customer_id'] ?? 0);
$order_q         = trim((string)($_GET['order_q'] ?? ''));

$sql = "SELECT o.OrderID,o.InvoiceID,c.NAME AS CustomerName,o.OrderDate,
               o.SubTotal,o.Discount,o.VAT,o.TotalAmount,o.AmountPaid,o.Balance,o.Status
        FROM `order` o
        JOIN customer c ON o.CustomerID=c.CustomerID
        WHERE 1";
$params=[];

if ($search_date)      { $sql.=" AND DATE(o.OrderDate)=?"; $params[]=$search_date; }
if ($search_customer>0){ $sql.=" AND o.CustomerID=?";     $params[]=$search_customer; }
if ($order_q !== '') {
  if (ctype_digit($order_q)) {
    $sql.=" AND (o.OrderID = ? OR o.InvoiceID LIKE ?)";
    $params[] = (int)$order_q; $params[] = "%{$order_q}%";
  } else {
    $sql.=" AND (o.InvoiceID LIKE ?)";
    $params[] = "%{$order_q}%";
  }
}

$sql.=" ORDER BY o.OrderDate DESC, o.OrderID DESC";
$stmt=$pdo->prepare($sql);
$stmt->execute($params);
$orders=$stmt->fetchAll();

$totalInvoices = count($orders);
$totalAmount   = array_sum(array_column($orders,'TotalAmount'));

$customers=$pdo->query("SELECT CustomerID,NAME FROM customer ORDER BY NAME")->fetchAll();

/* ------------------ Layout Includes ------------------ */
include 'header.php';
include 'sidebar.php';
?>
<style>
/* ====== Layout & Card — brand blue (#3b5683) ====== */
body{background:#ffffff;color:#3b5683;margin:0;}
.container{ margin-left:260px;padding:20px;max-width:100%; }
@media(max-width:992px){.container{margin-left:0;}}

.card{
  background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);margin-bottom:24px;
  border:1px solid #dfe6f2;
}
.card h1{
  font-size:22px;font-weight:600;padding:18px 20px;margin:0;
  border-bottom:1px solid #dfe6f2;color:#3b5683;
}

/* ====== Filters ====== */
.filter-form{ display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;margin:16px 20px }
.field{display:flex;flex-direction:column;gap:6px; width:220px;}
.label{font-weight:600;font-size:14px;margin-bottom:0;color:#6b7c97;text-align:center;}
.input,select{
  padding:9px 12px;border:1px solid #dfe6f2;border-radius:8px;font-size:14px;background:#fff;color:#3b5683;
  transition:border-color .2s, box-shadow .2s, background .2s, color .2s;
}
.input:focus,select:focus{
  outline:none;border-color:#3b5683;box-shadow:0 0 0 3px rgba(59,86,131,.25);
}

/* Buttons: same size; clear has no underline */
.filter-btn,.clear-btn,.billing-btn{
  display:inline-flex;align-items:center;justify-content:center;
  height:40px;min-width:110px;padding:0 16px;border-radius:8px;font-weight:600;font-size:14px;
  text-decoration:none; /* ensure anchors don't underline */
}

/* Primary filter button (brand) */
.filter-btn{border:1px solid #3b5683;cursor:pointer;background:#3b5683;color:#fff;}
.filter-btn:hover{background:#3b5683;filter:brightness(1.05);}
.filter-btn:active{background:#324a70;}
.filter-btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(59,86,131,.25);}

/* Ghost clear button */
.clear-btn{
  border:1px solid #dfe6f2;cursor:pointer;background:#fff;color:#3b5683;
  transition:background .2s, color .2s, box-shadow .2s;
}
.clear-btn:hover{background:#e9eff7;}
.clear-btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(59,86,131,.25);}

/* New Billing button (success accent) — SAME SIZE */
.billing-btn{
  border:1px solid #10b981;background:#10b981;color:#ffffff;cursor:pointer;
  transition:filter .2s, box-shadow .2s;
}
.billing-btn:hover{filter:brightness(1.05);}
.billing-btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(16,185,129,.25);}

/* ====== Summary & Actions ====== */
.summary{display:flex;gap:24px;padding:0 20px 16px 20px;font-size:15px;color:#3b5683}
.summary span b{color:#223652}
.actions-bar{display:flex;gap:10px;padding:0 20px 16px 20px}
.btn{
  padding:8px 14px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;
  border:1px solid #dfe6f2;background:#fff;color:#3b5683;transition:all .2s;
}
.btn:hover{background:#e9eff7;}
.btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(59,86,131,.25);}

/* ====== Scrollable Table ====== */
.table-shell{padding:0 20px 20px 20px;}
.table-scroll{
  max-height:62vh; overflow:auto; border:1px solid #dfe6f2; border-radius:12px;
  box-shadow: inset 0 1px 0 rgba(0,0,0,0.02); background:#fff;
}

/* custom scrollbar (webkit) */
.table-scroll::-webkit-scrollbar{height:10px;width:10px}
.table-scroll::-webkit-scrollbar-thumb{background:#c7d2e5;border-radius:10px;border:2px solid #f6f9fc}
.table-scroll::-webkit-scrollbar-track{background:#f6f9fc;border-radius:10px}

/* table */
.table{width:100%;border-collapse:separate;border-spacing:0;font-size:14px;min-width:960px;}
.table thead th{
  position:sticky; top:0; z-index:2;
  background:#eef2f8;
  text-align:center;
  padding:12px 10px;
  border-bottom:1px solid #dfe6f2;
  font-size:12px; text-transform:uppercase; letter-spacing:.02em;
  color:#3b5683;
}
.table tbody td{
  padding:10px 10px;
  border-bottom:1px solid #dfe6f2;
  vertical-align:middle;
  background:#fff;
  color:#3b5683;
}
.table tbody tr:nth-child(even) td{ background:#fcfcfd; }
.table tbody tr:hover td{ background:#f6f9fc; }

/* alignment helpers */
.t-center{text-align:center;}
.t-right{text-align:right;}
.t-now{white-space:nowrap;}
.t-ellipsis{
  max-width:220px;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}

/* column sizing */
.col-no{width:64px;}
.col-order{width:160px;}
.col-invoice{width:200px;}
.col-customer{width:220px;}
.col-date{width:160px;}
.col-money{width:130px;}
.col-status{width:160px;}
.col-actions{width:180px; white-space:nowrap;}

/* status badges (kept semantic colors) */
.badge{padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700;display:inline-block}
.badge.paid{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.badge.pending{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}
.badge.changed{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}

/* actions */
.actions{display:flex;gap:6px}
.btn-small{padding:6px 10px;font-size:13px;border-radius:6px;text-decoration:none;display:inline-block;border:1px solid #dfe6f2}
.btn-view{background:#e9eff7;color:#3b5683}
.btn-edit{background:#eef2f8;color:#3b5683}
.btn-del{background:#fee2e2;color:#b91c1c;border-color:#fecaca}
.btn-view:hover{background:#dde7f6}
.btn-edit:hover{background:#e3e9f5}
.btn-del:hover{background:#fecaca}

/* alerts */
.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

/* code badge (for Order Code) */
.code-badge{
  display:inline-block;background:#eef2f8;border:1px solid #dfe6f2;color:#3b5683;
  padding:4px 8px;border-radius:6px;font-weight:700;font-size:12px;letter-spacing:.02em;
}

/* mobile */
@media (max-width: 992px){
  .table{min-width:900px;}
  .table-scroll{max-height:64vh;}
}
</style>

<div class="container">
  <div class="card">
    <h1>View Orders</h1>

    <?php foreach($messages as $m): ?><div class="alert alert-success"><?= $m ?></div><?php endforeach; ?>
    <?php foreach($errors as $e): ?><div class="alert alert-error"><?= $e ?></div><?php endforeach; ?>

    <!-- Filters -->
    <form method="get" class="filter-form">
      <div class="field">
        <div class="label">Search by Date</div>
        <input type="date" name="search_date" class="input" value="<?= htmlspecialchars($search_date) ?>">
      </div>
      <div class="field">
        <div class="label">Search by Customer</div>
        <select name="customer_id">
          <option value="">All Customers</option>
          <?php foreach($customers as $c): ?>
            <option value="<?= $c['CustomerID']?>" <?= $search_customer==$c['CustomerID']?'selected':''?>>
              <?= htmlspecialchars($c['NAME']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <div class="label">Order / Invoice</div>
        <input type="text" name="order_q" class="input" placeholder="e.g. 102 or INV-2025..." value="<?= htmlspecialchars($order_q) ?>">
      </div>

      <button type="submit" class="filter-btn">Filter</button>
      <a href="view_billing.php" class="clear-btn">Clear</a>
      <!-- NEW: Billing button (same size) -->
      <a href="billing.php" class="billing-btn">Billing</a>
    </form>

    <!-- Summary -->
    <div class="summary">
      <span>Total Invoices: <b><?= $totalInvoices ?></b></span>
      <span>Total Amount: <b>LKR <?= number_format((float)$totalAmount,2) ?></b></span>
    </div>

    <!-- Actions -->
    <div class="actions-bar">
      <button type="button" class="btn" onclick="printTable()">🖨 Print</button>
      <button type="button" class="btn" onclick="exportTableToExcel('ordersTable')">📁 Export to Excel</button>
    </div>

    <!-- Table -->
    <div class="table-shell">
      <div class="table-scroll">
        <table class="table" id="ordersTable" aria-label="Orders table">
          <thead>
            <tr>
              <th class="col-no">No</th>
              <th class="col-order">Order ID</th>
              <th class="col-invoice">Invoice</th>
              <th class="col-customer">Customer</th>
              <th class="col-date">Date</th>
              <th class="col-money">Subtotal</th>
              <th class="col-money">Discount%</th>
              <th class="col-money">VAT%</th>
              <th class="col-money">Total</th>
              <th class="col-money">Paid</th>
              <th class="col-money">Balance</th>
              <th class="col-status">Status</th>
              <th class="col-actions no-print no-export">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if($orders): $sn=1; foreach($orders as $r): ?>
            <tr>
              <td class="t-center"><?php echo $sn++; ?></td>

              <td>
                <span class="code-badge"><?=
                  htmlspecialchars(order_code((int)$r['OrderID'], (string)$r['OrderDate']))
                ?></span>
              </td>

              <?php
                $inv       = (string)$r['InvoiceID'];
                $cust      = (string)$r['CustomerName'];
                $status    = (string)$r['Status'];

                // === Robust status normalization/detection ===
                $normStatus = preg_replace('/\s+/', ' ', trim($status)); // collapse spaces + trim
                // Edit allowed for any status containing the standalone word "paid" (not "unpaid")
                $isPaid    = (bool)preg_match('/\bpaid\b/i', $normStatus);
                // Badge for "Changed" if the word appears
                $isChanged = (bool)preg_match('/\bchanged\b/i', $normStatus);
              ?>
              <td class="t-ellipsis" title="<?= htmlspecialchars($inv) ?>"><?= htmlspecialchars($inv) ?></td>
              <td class="t-ellipsis" title="<?= htmlspecialchars($cust) ?>"><?= htmlspecialchars($cust) ?></td>

              <td class="t-center t-now"><?= htmlspecialchars($r['OrderDate']) ?></td>

              <td class="t-right">LKR <?= number_format((float)$r['SubTotal'],2) ?></td>
              <td class="t-right"><?= rtrim(rtrim(number_format((float)$r['Discount'],2),'0'),'.') ?>%</td>
              <td class="t-right"><?= rtrim(rtrim(number_format((float)$r['VAT'],2),'0'),'.') ?>%</td>

              <td class="t-right"><b>LKR <?= number_format((float)$r['TotalAmount'],2) ?></b></td>
              <td class="t-right">LKR <?= number_format((float)$r['AmountPaid'],2) ?></td>
              <td class="t-right">LKR <?= number_format((float)$r['Balance'],2) ?></td>

              <td class="t-center">
                <?php if ($isPaid): ?>
                  <span class="badge paid">Paid</span>
                  <?php if ($isChanged): ?>
                    <span class="badge changed">Changed</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge pending"><?= htmlspecialchars($normStatus) ?></span>
                <?php endif; ?>
              </td>

              <td class="col-actions no-print">
                <div class="actions">
                  <a href="invoice.php?order_id=<?= (int)$r['OrderID'] ?>" class="btn-small btn-view" target="_blank">View</a>
                  <?php if ($isPaid): ?>
                    <a href="edit_billing.php?order_id=<?= (int)$r['OrderID'] ?>" class="btn-small btn-edit">Edit</a>
                  <?php endif; ?>
                  <a href="?delete=<?= (int)$r['OrderID'] ?>&amp;_csrf=<?= htmlspecialchars($CSRF) ?>"
                     onclick="return confirm('Delete this order?')" class="btn-small btn-del">Delete</a>
                </div>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="13" class="t-center" style="color:#6b7280;">No orders found.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Excel lib -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js" integrity="sha512-4B6Qw8QhB0n6e1D1kN6YQjzEwjH5uLwWm2z2l9bE1Z8eZQyD8n1QYy0mQqV4fJ0b3Qf4JY0y5vG7Q2F1m7FqNQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
/* Ensure XLSX (fallback loader if CSP blocks above) */
window._ensureXLSX = () => new Promise((resolve, reject) => {
  if (window.XLSX) return resolve();
  const s = document.createElement('script');
  s.src = "https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js";
  s.onload = () => resolve();
  s.onerror = () => {
    const f = document.createElement('script');
    f.src = "https://unpkg.com/xlsx@0.18.5/dist/xlsx.full.min.js";
    f.onload = () => resolve();
    f.onerror = (e) => reject(e);
    document.head.appendChild(f);
  };
  document.head.appendChild(s);
});

/* Company block for print */
const COMPANY = {
  name: 'RB Stores',
  address: 'Liberty Plaza, Kollupitiya, Colombo 03',
  phone: '+94 77 123 4567',
  email: 'info@rbstores.lk',
  logo: '../assets/img/logo.png'
};

/* Clone table without Actions column or special UI elements */
function cloneTableWithoutActions(tableId){
  const table = document.getElementById(tableId).cloneNode(true);

  // remove anything marked no-print/no-export
  table.querySelectorAll('.no-print, .no-export').forEach(el => el.remove());

  // remove actions column by header class
  const ths = Array.from(table.querySelectorAll('thead th'));
  const idx = ths.findIndex(th => th.classList.contains('col-actions'));
  if (idx > -1) {
    table.querySelectorAll('tr').forEach(tr => {
      if (tr.children[idx]) tr.removeChild(tr.children[idx]);
    });
  }

  // make links/badges plain text
  table.querySelectorAll('a').forEach(a => a.replaceWith(document.createTextNode(a.textContent.trim())));
  table.querySelectorAll('.badge, .code-badge').forEach(el => el.replaceWith(document.createTextNode(el.textContent.trim())));

  return table;
}

/* Print (A4, logo header) */
function printTable(){
  const cleanTable = cloneTableWithoutActions('ordersTable');
  const html = `
    <html>
      <head>
        <title>Orders</title>
        <meta charset="utf-8">
        <style>
          @page { size: A4; margin: 14mm; }
          body{font-family:system-ui,Segoe UI,Roboto,Arial;background:#fff;color:#111;}
          .head{display:flex;align-items:center;gap:12px;margin-bottom:10px;}
          .head img{height:48px;width:auto;object-fit:contain}
          .head .meta{line-height:1.25}
          .title{margin:8px 0 14px 0;font-size:18px;font-weight:700}
          .muted{color:#555;font-size:12px}
          table{width:100%;border-collapse:collapse}
          th,td{border:1px solid #ddd;padding:8px;text-align:left;font-size:12px}
          thead th{background:#f3f4f6;text-transform:uppercase}
        </style>
      </head>
      <body>
        <div class="head">
          <img src="${COMPANY.logo}" alt="Logo" onerror="this.style.display='none'">
          <div class="meta">
            <div style="font-weight:700;font-size:16px">${COMPANY.name}</div>
            <div class="muted">${COMPANY.address}</div>
            <div class="muted">${COMPANY.phone} · ${COMPANY.email}</div>
          </div>
        </div>
        <div class="title">Orders</div>
        ${cleanTable.outerHTML}
      </body>
    </html>`;
  const w = window.open('', '', 'height=800,width=1100');
  w.document.write(html); w.document.close(); w.focus(); w.print();
}

/* CSV fallback */
function _exportCSVFromTable(tableEl, filename){
  const rows = Array.from(tableEl.querySelectorAll('tr')).map(tr =>
    Array.from(tr.children).map(td =>
      `"${(td.textContent || '').replace(/"/g,'""').trim()}"`
    ).join(',')
  ).join('\r\n');
  const blob = new Blob([rows], {type: 'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename + '.csv';
  document.body.appendChild(a);
  a.click();
  a.remove();
}

/* Excel (without Actions) */
async function exportTableToExcel(tableID, filename='Orders_Export'){
  const cleanTable = cloneTableWithoutActions(tableID);
  try {
    await window._ensureXLSX();
    if (!window.XLSX) throw new Error('XLSX not available');

    const wb = XLSX.utils.table_to_book(cleanTable, { sheet: "Orders" });
    const stamp = new Date().toISOString().slice(0,10);
    XLSX.writeFile(wb, filename + '_' + stamp + ".xlsx");
  } catch (err) {
    console.warn('XLSX export failed, falling back to CSV:', err);
    _exportCSVFromTable(cleanTable, filename + '_' + new Date().toISOString().slice(0,10));
  }
}
</script>

<?php include 'footer.php'; ?>
