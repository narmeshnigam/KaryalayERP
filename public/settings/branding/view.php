<?php
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

$conn = createConnection(true);
$row = null;
if ($conn) {
  $res = mysqli_query($conn, 'SELECT * FROM branding_settings ORDER BY id ASC LIMIT 1');
  $row = $res ? mysqli_fetch_assoc($res) : null; if ($res) mysqli_free_result($res);
}
function h($s){ return htmlspecialchars($s ?? ''); }
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header"><h1>Organization Info</h1></div>
    <div class="card" style="padding:24px;">
      <?php if(!$row): ?>
        <p class="muted">Branding not configured yet.</p>
      <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;">
          <div><strong>Company Name</strong><div><?php echo h($row['org_name']); ?></div></div>
          <div><strong>Legal Name</strong><div><?php echo h($row['legal_name']); ?></div></div>
          <div><strong>Tagline</strong><div><?php echo h($row['tagline']); ?></div></div>
          <div><strong>Email</strong><div><?php echo h($row['email']); ?></div></div>
          <div><strong>Phone</strong><div><?php echo h($row['phone']); ?></div></div>
          <div><strong>Website</strong><div><?php echo h($row['website']); ?></div></div>
          <div><strong>GSTIN</strong><div><?php echo h($row['gstin']); ?></div></div>
          <div style="grid-column:1/-1;"><strong>Address</strong><div><?php echo h($row['address_line1']); ?> <?php echo h($row['address_line2']); ?>, <?php echo h($row['city']); ?>, <?php echo h($row['state']); ?> <?php echo h($row['zip']); ?>, <?php echo h($row['country']); ?></div></div>
          <div style="grid-column:1/-1;"><strong>Footer Text</strong><div><?php echo h($row['footer_text']); ?></div></div>
        </div>
        <div style="display:flex;gap:20px;margin-top:16px;flex-wrap:wrap;">
          <?php if(!empty($row['logo_light'])): ?><div><div class="muted">Light Logo</div><img src="<?php echo $row['logo_light']; ?>" style="max-height:70px;"></div><?php endif; ?>
          <?php if(!empty($row['logo_dark'])): ?><div><div class="muted">Dark Logo</div><img src="<?php echo $row['logo_dark']; ?>" style="max-height:70px;"></div><?php endif; ?>
          <?php if(!empty($row['logo_square'])): ?><div><div class="muted">Square Icon</div><img src="<?php echo $row['logo_square']; ?>" style="max-height:70px;"></div><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
<?php if($conn) closeConnection($conn); ?>
