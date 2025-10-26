<?php
// employee.php — Employee List / View (RB Stores)
// PHP 8.2+, PDO
declare(strict_types=1);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

date_default_timezone_set('Asia/Colombo');

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

// DB
$dsn = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    die("DB failed: " . htmlspecialchars($e->getMessage()));
}

$messages = [];
$errors   = [];

/* ----------------------
   Delete (CSRF protected)
----------------------- */
if (isset($_GET['delete'], $_GET['_csrf']) && hash_equals($CSRF, (string)$_GET['_csrf'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM employee WHERE EmployeeID=?");
        $stmt->execute([$id]);
        $_SESSION['flash'] = ['ok' => true, 'msg' => "Employee #$id deleted successfully."];
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['ok' => false, 'msg' => "Delete failed: " . htmlspecialchars($e->getMessage())];
    }
    header("Location: employee.php");
    exit();
}

// Flash (PRG)
if (!empty($_SESSION['flash'])) {
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    if ($f['ok']) $messages[] = $f['msg']; else $errors[] = $f['msg'];
}

/* ----------------------
   Filters
----------------------- */
$search_name    = trim((string)($_GET['name'] ?? ''));
$search_role    = trim((string)($_GET['role'] ?? ''));
$search_stype   = trim((string)($_GET['salary_type'] ?? ''));
$salary_min     = trim((string)($_GET['salary_min'] ?? ''));
$salary_max     = trim((string)($_GET['salary_max'] ?? ''));
$created_from   = trim((string)($_GET['created_from'] ?? '')); // yyyy-mm-dd
$created_to     = trim((string)($_GET['created_to'] ?? ''));   // yyyy-mm-dd

$params = [];
$where  = ["1"];

if ($search_name !== '') { $where[] = "e.Name LIKE ?"; $params[] = "%$search_name%"; }
if ($search_role !== '') { $where[] = "e.Role = ?"; $params[] = $search_role; }
if ($search_stype !== '') { $where[] = "e.SalaryType = ?"; $params[] = $search_stype; }
if ($salary_min !== '' && is_numeric($salary_min)) { $where[] = "e.Salary >= ?"; $params[] = (float)$salary_min; }
if ($salary_max !== '' && is_numeric($salary_max)) { $where[] = "e.Salary <= ?"; $params[] = (float)$salary_max; }
if ($created_from !== '') { $where[] = "DATE(e.CreatedAt) >= ?"; $params[] = $created_from; }
if ($created_to !== '')   { $where[] = "DATE(e.CreatedAt) <= ?"; $params[] = $created_to; }

$whereSql = implode(' AND ', $where);

/* ----------------------
   Query list (default newest first)
----------------------- */
$sql = "SELECT e.EmployeeID, e.Name, e.Role, e.Salary, e.SalaryType, e.Contact, e.Address, e.Image, e.CreatedAt, e.UpdatedAt
        FROM employee e
        WHERE $whereSql
        ORDER BY e.CreatedAt DESC, e.EmployeeID DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Summaries
$totalEmployees = count($employees);
$totalSalary    = 0.0;
foreach ($employees as $emp) { $totalSalary += (float)$emp['Salary']; }

// Distinct roles/types for filter
$roles = $pdo->query("SELECT DISTINCT Role FROM employee WHERE Role IS NOT NULL AND Role<>'' ORDER BY Role")->fetchAll(PDO::FETCH_COLUMN);
$salaryTypes = $pdo->query("SELECT DISTINCT SalaryType FROM employee WHERE SalaryType IS NOT NULL AND SalaryType<>'' ORDER BY SalaryType")->fetchAll(PDO::FETCH_COLUMN);

include 'header.php';
include 'sidebar.php';
?>
<style>
:root{
  --brand:#3b5683; --brand-dark:#324a70; --ring:rgba(59,86,131,.25);
  --text:#3b5683; --muted:#6b7c97; --border:#dfe6f2; --tint:#eef2f8; --tint-2:#e9eff7; --tint-hover:#dde7f6;
}
body{background:#ffffff;color:var(--text);margin:0;}
.container{margin-left:260px;padding:20px;max-width:1300px;}
@media(max-width:992px){.container{margin-left:0;}}
.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);margin-bottom:24px;border:1px solid var(--border);}
.card h1{font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid var(--border);margin:0;color:var(--text);}
.filter-form{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;margin:16px 20px}
.group{display:flex;flex-direction:column}
.label{font-weight:600;font-size:14px;margin-bottom:6px;color:var(--muted)}
.input,select{padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:#fff;color:var(--text);transition:border-color .2s, box-shadow .2s}
.input:focus,select:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--ring);}
.filter-btn{padding:9px 16px;border-radius:8px;font-weight:600;border:1px solid var(--brand);cursor:pointer;background:var(--brand);color:#fff;transition:filter .2s}
.filter-btn:hover{filter:brightness(1.05)}
.clear-btn{padding:9px 16px;border-radius:8px;font-weight:600;border:1px solid var(--border);background:#fff;color:var(--text)}
.summary{display:flex;gap:24px;padding:0 20px 16px 20px;font-size:15px;color:var(--text);flex-wrap:wrap}
.summary span b{color:#223652}
.actions-bar{display:flex;gap:10px;padding:0 20px 16px 20px;flex-wrap:wrap}
.btn{padding:8px 14px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:1px solid var(--border);background:#fff;color:var(--text)}
.table-wrapper{overflow:auto;padding:0 20px 20px 20px}
.table{width:100%;border-collapse:collapse;font-size:14px;min-width:1000px}
.table th{background:var(--tint);text-align:left;padding:10px;border-bottom:1px solid var(--border);font-size:12px;text-transform:uppercase;color:var(--text)}
.table td{padding:10px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text)}
.table tr:hover td{background:#f6f9fc}
.emp-cell{display:flex;align-items:center;gap:10px}
.avatar{width:34px;height:34px;border-radius:50%;object-fit:cover;border:1px solid var(--border);background:var(--tint);display:block}
.avatar-fallback{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--tint-2);color:var(--text);font-weight:700;border:1px solid var(--border)}
.badge{padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid var(--border)}
.badge.type{background:var(--tint);color:var(--text)}
.actions{display:flex;gap:6px}
.btn-small{padding:6px 10px;font-size:13px;border-radius:6px;text-decoration:none;display:inline-block;border:1px solid var(--border)}
.btn-view{background:var(--tint-2);color:var(--text)}
.btn-edit{background:var(--tint);color:var(--text)}
.btn-del{background:#fee2e2;color:#b91c1c;border-color:#fecaca}
.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.modal{position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;z-index:9999}
.modal-card{width:min(720px,95vw);background:#fff;border-radius:14px;box-shadow:0 15px 40px rgba(2,6,23,.25);overflow:hidden;border:1px solid var(--border)}
.modal-head{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--border)}
.modal-body{padding:16px}
.modal-close{background:transparent;border:none;font-size:22px;color:var(--text);cursor:pointer}
.profile{display:flex;gap:16px;align-items:flex-start}
.profile img{width:80px;height:80px;border-radius:10px;object-fit:cover;border:1px solid var(--border);background:var(--tint)}
.profile .fallback{width:80px;height:80px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:var(--tint-2);color:var(--text);font-size:28px;font-weight:800;border:1px solid var(--border)}
.profile .meta{flex:1}
.meta .row{display:grid;grid-template-columns:140px 1fr;gap:10px;margin-top:8px}
@media(max-width:520px){.meta .row{grid-template-columns:1fr}}
</style>

<div class="container">
  <div class="card">
    <h1>Employees</h1>

    <?php foreach($messages as $m): ?><div class="alert alert-success"><?= $m ?></div><?php endforeach; ?>
    <?php foreach($errors as $e): ?><div class="alert alert-error"><?= $e ?></div><?php endforeach; ?>

    <!-- Filters -->
    <form class="filter-form" method="get">
      <div class="group">
        <div class="label">Name</div>
        <input class="input" type="text" name="name" placeholder="Search name…" value="<?= htmlspecialchars($search_name) ?>">
      </div>

      <div class="group">
        <div class="label">Role</div>
        <select name="role">
          <option value="">All Roles</option>
          <?php foreach($roles as $r): ?>
            <option value="<?= htmlspecialchars($r) ?>" <?= $search_role===$r?'selected':'' ?>><?= htmlspecialchars($r) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="group">
        <div class="label">Salary Type</div>
        <select name="salary_type">
          <option value="">All Types</option>
          <?php foreach($salaryTypes as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= $search_stype===$t?'selected':'' ?>><?= htmlspecialchars($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="group">
        <div class="label">Salary Min</div>
        <input class="input" type="number" name="salary_min" step="0.01" min="0" placeholder="0" value="<?= htmlspecialchars($salary_min) ?>">
      </div>

      <div class="group">
        <div class="label">Salary Max</div>
        <input class="input" type="number" name="salary_max" step="0.01" min="0" placeholder="e.g., 200000" value="<?= htmlspecialchars($salary_max) ?>">
      </div>

      <div class="group">
        <div class="label">Created From</div>
        <input class="input" type="date" name="created_from" value="<?= htmlspecialchars($created_from) ?>">
      </div>

      <div class="group">
        <div class="label">Created To</div>
        <input class="input" type="date" name="created_to" value="<?= htmlspecialchars($created_to) ?>">
      </div>

      <button type="submit" class="filter-btn">Search</button>
      <a href="employee.php" class="clear-btn">Clear</a>
      <a href="add_employee.php" class="clear-btn">+ Add Employee</a>
    </form>

    <!-- Summary -->
    <div class="summary">
      <span>Total Employees: <b><?= $totalEmployees ?></b></span>
      <span>Total Salary: <b>LKR <?= number_format($totalSalary, 2) ?></b></span>
    </div>

    <!-- Actions -->
    <div class="actions-bar">
      <button type="button" class="btn" onclick="printEmployees()">🖨 Print</button>
      <button type="button" class="btn" onclick="exportEmployeesExcel()">📁 Export to Excel</button>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
      <table class="table" id="empTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Employee</th>
            <th>Role</th>
            <th>Salary (LKR)</th>
            <th>Type</th>
            <th>Contact</th>
            <th>Address</th>
            <th>Created</th>
            <th>Updated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if($employees): $sn=1; foreach($employees as $e):
          $img = (string)$e['Image'];
          $imgPath = $img ? "../assets/uploads/employees/" . $img : "";
          $initials = strtoupper(mb_substr(trim((string)$e['Name']),0,1));
        ?>
          <tr>
            <td><?= $sn++ ?></td>
            <td>
              <div class="emp-cell">
                <?php if ($img && file_exists(__DIR__ . "/../assets/uploads/employees/" . $img)): ?>
                  <img class="avatar" src="<?= htmlspecialchars($imgPath) ?>" alt="Avatar">
                <?php else: ?>
                  <div class="avatar-fallback"><?= htmlspecialchars($initials ?: "E") ?></div>
                <?php endif; ?>
                <div>
                  <div><b><?= htmlspecialchars((string)$e['Name']) ?></b></div>
                  <div style="font-size:12px;color:#64748b">ID: <?= (int)$e['EmployeeID'] ?></div>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars((string)$e['Role']) ?></td>
            <td>LKR <?= number_format((float)$e['Salary'], 2) ?></td>
            <td><span class="badge type"><?= htmlspecialchars((string)$e['SalaryType']) ?></span></td>
            <td><?= htmlspecialchars((string)$e['Contact']) ?></td>
            <td><?= htmlspecialchars((string)$e['Address']) ?></td>
            <td><?= htmlspecialchars((string)$e['CreatedAt']) ?></td>
            <td><?= htmlspecialchars((string)$e['UpdatedAt']) ?></td>
            <td>
              <div class="actions">
                <a href="#" class="btn-small btn-view" onclick="openViewModal(<?= (int)$e['EmployeeID'] ?>)">View</a>
                <a href="edit_employee.php?employee_id=<?= (int)$e['EmployeeID'] ?>" class="btn-small btn-edit">Edit</a>
                <a href="?delete=<?= (int)$e['EmployeeID'] ?>&amp;_csrf=<?= $CSRF ?>"
                   onclick="return confirm('Delete this employee?')"
                   class="btn-small btn-del">Delete</a>
              </div>
              <!-- Hidden details for modal & export -->
              <div id="emp-json-<?= (int)$e['EmployeeID'] ?>" style="display:none"
                   data-json='<?= json_encode([
                     'EmployeeID' => (int)$e['EmployeeID'],
                     'Name'       => (string)$e['Name'],
                     'Role'       => (string)$e['Role'],
                     'Salary'     => (float)$e['Salary'],
                     'SalaryType' => (string)$e['SalaryType'],
                     'Contact'    => (string)$e['Contact'],
                     'Address'    => (string)$e['Address'],
                     'Image'      => $img && file_exists(__DIR__ . "/../assets/uploads/employees/" . $img) ? $imgPath : null,
                     'CreatedAt'  => (string)$e['CreatedAt'],
                     'UpdatedAt'  => (string)$e['UpdatedAt'],
                   ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>'>
              </div>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="10">No employees found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Quick View Modal -->
<div class="modal" id="viewModal" aria-hidden="true">
  <div class="modal-card">
    <div class="modal-head">
      <div id="vmTitle" style="font-weight:700">Employee</div>
      <button class="modal-close" onclick="closeViewModal()">×</button>
    </div>
    <div class="modal-body">
      <div class="profile">
        <div id="vmAvatar"></div>
        <div class="meta">
          <div class="row"><div><b>Employee ID</b></div><div id="vmID"></div></div>
          <div class="row"><div><b>Name</b></div><div id="vmName"></div></div>
          <div class="row"><div><b>Role</b></div><div id="vmRole"></div></div>
          <div class="row"><div><b>Salary</b></div><div id="vmSalary"></div></div>
          <div class="row"><div><b>Salary Type</b></div><div id="vmSType"></div></div>
          <div class="row"><div><b>Contact</b></div><div id="vmContact"></div></div>
          <div class="row"><div><b>Address</b></div><div id="vmAddress"></div></div>
          <div class="row"><div><b>Created</b></div><div id="vmCreated"></div></div>
          <div class="row"><div><b>Updated</b></div><div id="vmUpdated"></div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Export & Print -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
/* ---------- Utilities ---------- */
function toTitleCase(name){
  return (name||'').toLowerCase().replace(/\b([a-z])/g, (m)=>m.toUpperCase());
}

/* -------- Modal logic -------- */
const modal = document.getElementById('viewModal');
function openViewModal(id){
  const holder = document.getElementById('emp-json-'+id);
  if (!holder) return;
  const data = JSON.parse(holder.dataset.json || '{}');

  document.getElementById('vmTitle').textContent = `Employee — ${data.Name ?? ''}`;

  const av = document.getElementById('vmAvatar');
  av.innerHTML = '';
  if (data.Image) {
    const img = document.createElement('img');
    img.src = data.Image; img.alt = 'Avatar'; av.appendChild(img);
  } else {
    const fb = document.createElement('div');
    fb.className = 'fallback';
    fb.textContent = (data.Name || 'E').trim().charAt(0).toUpperCase();
    av.appendChild(fb);
  }

  const fmt = (n) => new Intl.NumberFormat('en-US',{minimumFractionDigits:2, maximumFractionDigits:2}).format(n);
  document.getElementById('vmID').textContent      = data.EmployeeID ?? '';
  document.getElementById('vmName').textContent    = data.Name ?? '';
  document.getElementById('vmRole').textContent    = data.Role ?? '';
  document.getElementById('vmSalary').textContent  = data.Salary!=null ? `LKR ${fmt(data.Salary)}` : '';
  document.getElementById('vmSType').textContent   = data.SalaryType ?? '';
  document.getElementById('vmContact').textContent = data.Contact ?? '';
  document.getElementById('vmAddress').textContent = data.Address ?? '';
  document.getElementById('vmCreated').textContent = data.CreatedAt ?? '';
  document.getElementById('vmUpdated').textContent = data.UpdatedAt ?? '';

  modal.style.display = 'flex';
  modal.setAttribute('aria-hidden', 'false');
}
function closeViewModal(){ modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); }
modal?.addEventListener('click', (e)=>{ if(e.target===modal) closeViewModal(); });
document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeViewModal(); });

/* --------- Professional Print (A4, header/footer, no Actions column) --------- */
function printEmployees() {
  const src = document.getElementById('empTable');
  if (!src) return;

  const tbl = src.cloneNode(true);
  const ths = Array.from(tbl.tHead.rows[0].cells);
  const actionsIdx = ths.findIndex(th => th.textContent.trim().toLowerCase() === 'actions');
  if (actionsIdx > -1) {
    tbl.tHead.rows[0].deleteCell(actionsIdx);
    Array.from(tbl.tBodies[0].rows).forEach(r => r.deleteCell(actionsIdx));
  }

  const w = window.open('', '', 'width=1024,height=768');
  const now = new Date();
  const when = now.toLocaleString('en-GB', { hour12:false });

  const html = `
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Employee Report</title>
<style>
  @page { size: A4 portrait; margin: 12mm 10mm 14mm; }
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { font-family: system-ui, Segoe UI, Roboto, Arial, sans-serif; color:#223652; }
  .header { display:flex; align-items:center; gap:16px; margin-bottom:8px; }
  .header img { width:42px; height:42px; object-fit:contain; }
  .title { font-size:18px; font-weight:700; }
  .sub { color:#6b7c97; font-size:12px; }
  .summary { margin:8px 0 14px 0; font-size:13px; color:#3b5683; }
  .wrap { border:1px solid #dfe6f2; border-radius:10px; padding:10px; }
  table { width:100%; border-collapse:collapse; font-size:12px; }
  thead { display: table-header-group; }
  tfoot { display: table-footer-group; }
  th, td { border:1px solid #e5e7eb; padding:8px; vertical-align:middle; }
  thead th { background:#eef2f8; text-transform:uppercase; letter-spacing:.02em; font-size:11px; color:#3b5683; }
  .emp-cell { display:flex; align-items:center; gap:8px; }
  .avatar { width:38px; height:38px; border-radius:50%; object-fit:cover; border:1px solid #dfe6f2; background:#eef2f8; display:block; }
  .avatar-fallback { width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e9eff7;border:1px solid #dfe6f2;color:#3b5683;font-weight:700; }
  .muted { font-size:11px; color:#64748b; }
  .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align:center; font-size:11px; color:#6b7c97; }
  .footer:after { content: "Page " counter(page) " of " counter(pages); }
  colgroup col.col-idx{width:32px;} colgroup col.col-name{width:26%;} colgroup col.col-role{width:12%;}
  colgroup col.col-sal{width:11%;} colgroup col.col-type{width:8%;} colgroup col.col-cont{width:12%;}
  colgroup col.col-addr{width:14%;} colgroup col.col-cr{width:10%;} colgroup col.col-up{width:10%;}
  img { max-width:100%; height:auto; }
</style>
</head>
<body>
  <div class="header">
    <img src="/rbstorsg/assets/images/rb.png" alt="RB Stores">
    <div>
      <div class="title">Employee Report</div>
      <div class="sub">Generated on ${when}</div>
    </div>
  </div>
  <div class="summary">${document.querySelector('.summary')?.innerText || ''}</div>
  <div class="wrap">
    <table>
      <colgroup>
        <col class="col-idx"><col class="col-name"><col class="col-role"><col class="col-sal">
        <col class="col-type"><col class="col-cont"><col class="col-addr">
        <col class="col-cr"><col class="col-up">
      </colgroup>
      ${tbl.tHead.outerHTML}
      ${tbl.tBodies[0].outerHTML}
    </table>
  </div>
  <div class="footer"></div>
  <script>
    document.querySelectorAll('.emp-cell img').forEach(img => img.classList.add('avatar'));
    window.addEventListener('load', () => { window.print(); });
  <\/script>
</body>
</html>`;
  w.document.open(); w.document.write(html); w.document.close();
}

/* --------- Clean Excel Export (no Actions, Title-Case names, contact as text) --------- */
function exportEmployeesExcel(filename='Employees_Export'){
  // Collect clean data from JSON blobs (no DOM text)
  const holders = Array.from(document.querySelectorAll('[id^="emp-json-"]'));
  const rows = [];
  let sn = 1;
  holders.forEach(h => {
    const d = JSON.parse(h.dataset.json || '{}');
    rows.push({
      '#': sn++,
      'Employee': toTitleCase(d.Name || ''),
      'Role': d.Role || '',
      'Salary (LKR)': typeof d.Salary === 'number' ? d.Salary : Number(d.Salary||0),
      'Type': d.SalaryType || '',
      'Contact': String(d.Contact || ''),     // keep as string
      'Address': d.Address || '',
      'Created': d.CreatedAt || '',
      'Updated': d.UpdatedAt || ''
    });
  });

  // Build worksheet from JSON
  const ws = XLSX.utils.json_to_sheet(rows, {
    header: ['#','Employee','Role','Salary (LKR)','Type','Contact','Address','Created','Updated']
  });

  // Column widths
  ws['!cols'] = [
    {wch:4},{wch:28},{wch:16},{wch:14},{wch:10},{wch:14},{wch:20},{wch:12},{wch:12}
  ];

  // Format: salary numeric with 2 decimals; contact forced to text
  const range = XLSX.utils.decode_range(ws['!ref']);
  // find column indexes
  const salaryCol = 3; // 0-based: A #, B Employee, C Role, D Salary ...
  const contactCol = 5;
  for (let R = range.s.r + 1; R <= range.e.r; ++R) {
    const sCell = ws[XLSX.utils.encode_cell({r:R, c:salaryCol})];
    if (sCell) { sCell.t = 'n'; sCell.z = '#,##0.00'; }
    const cCell = ws[XLSX.utils.encode_cell({r:R, c:contactCol})];
    if (cCell) { cCell.t = 's'; } // text (prevents 7.14E+08)
  }

  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Employees');
  XLSX.writeFile(wb, filename + '.xlsx');
}
</script>

<?php include 'footer.php'; ?>
