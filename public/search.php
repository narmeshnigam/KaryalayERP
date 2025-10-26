<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$q = trim($_GET['q'] ?? '');
$page_title = 'Search - ' . APP_NAME;
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

$conn = createConnection(true);
function t_exists($c,$t){ $t=mysqli_real_escape_string($c,$t); $r=mysqli_query($c,"SHOW TABLES LIKE '$t'"); $ok=$r&&mysqli_num_rows($r)>0; if($r)mysqli_free_result($r); return $ok; }
$results = ['employees'=>[],'leads'=>[],'tasks'=>[]];
if ($q !== '' && $conn) {
  $like = '%' . mysqli_real_escape_string($conn, $q) . '%';
  if (t_exists($conn,'employees')){
    $sql = "SELECT id, employee_code, first_name, last_name, department FROM employees WHERE CONCAT(first_name,' ',last_name) LIKE '$like' OR employee_code LIKE '$like' OR official_email LIKE '$like' LIMIT 20";
    $r = mysqli_query($conn,$sql); while($r && $row=mysqli_fetch_assoc($r)){ $results['employees'][]=$row; } if($r) mysqli_free_result($r);
  }
  if (t_exists($conn,'crm_leads')){
    $sql = "SELECT id, name, company_name, phone, email FROM crm_leads WHERE name LIKE '$like' OR company_name LIKE '$like' OR phone LIKE '$like' OR email LIKE '$like' LIMIT 20";
    $r = mysqli_query($conn,$sql); while($r && $row=mysqli_fetch_assoc($r)){ $results['leads'][]=$row; } if($r) mysqli_free_result($r);
  }
  if (t_exists($conn,'crm_tasks')){
    $sql = "SELECT id, title, status, due_date FROM crm_tasks WHERE title LIKE '$like' LIMIT 20";
    $r = mysqli_query($conn,$sql); while($r && $row=mysqli_fetch_assoc($r)){ $results['tasks'][]=$row; } if($r) mysqli_free_result($r);
  }
}
if ($conn) closeConnection($conn);
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
        <div>
          <h1>🔎 Search</h1>
          <p>Results for: <strong><?php echo htmlspecialchars($q); ?></strong></p>
        </div>
        <form method="get" action="<?php echo APP_URL; ?>/public/search.php" style="display:flex;gap:8px;align-items:center;">
          <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search..." class="form-control" style="min-width:260px;" />
          <button class="btn btn-primary" type="submit">Search</button>
        </form>
      </div>
    </div>

    <?php if ($q===''): ?>
      <div class="card"><div class="alert alert-info">Type a keyword to search employees, leads, and tasks.</div></div>
    <?php else: ?>
      <div class="grid-2">
        <div class="card" style="padding:24px;">
          <h3 style="margin-top:0;">Employees</h3>
          <div style="overflow:auto;">
            <table class="table"><thead><tr><th>Code</th><th>Name</th><th>Department</th></tr></thead><tbody>
              <?php if(empty($results['employees'])): ?><tr><td colspan="3" class="muted">No results.</td></tr>
              <?php else: foreach($results['employees'] as $r): ?>
                <tr><td><?php echo htmlspecialchars($r['employee_code']); ?></td><td><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></td><td><?php echo htmlspecialchars($r['department']); ?></td></tr>
              <?php endforeach; endif; ?>
            </tbody></table>
          </div>
        </div>
        <div class="card" style="padding:24px;">
          <h3 style="margin-top:0;">CRM Leads</h3>
          <div style="overflow:auto;">
            <table class="table"><thead><tr><th>Name</th><th>Company</th><th>Phone</th><th>Email</th></tr></thead><tbody>
              <?php if(empty($results['leads'])): ?><tr><td colspan="4" class="muted">No results.</td></tr>
              <?php else: foreach($results['leads'] as $r): ?>
                <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo htmlspecialchars($r['company_name']); ?></td><td><?php echo htmlspecialchars($r['phone']); ?></td><td><?php echo htmlspecialchars($r['email']); ?></td></tr>
              <?php endforeach; endif; ?>
            </tbody></table>
          </div>
        </div>
      </div>
      <div class="card" style="padding:24px;">
        <h3 style="margin-top:0;">CRM Tasks</h3>
        <div style="overflow:auto;">
          <table class="table"><thead><tr><th>Title</th><th>Status</th><th>Due</th></tr></thead><tbody>
            <?php if(empty($results['tasks'])): ?><tr><td colspan="3" class="muted">No results.</td></tr>
            <?php else: foreach($results['tasks'] as $r): ?>
              <tr><td><?php echo htmlspecialchars($r['title']); ?></td><td><?php echo htmlspecialchars($r['status']); ?></td><td><?php echo htmlspecialchars($r['due_date']); ?></td></tr>
            <?php endforeach; endif; ?>
          </tbody></table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>