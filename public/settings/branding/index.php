<?php
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
$role = strtolower($_SESSION['role'] ?? 'employee');
if ($role !== 'admin') { header('Location: ../../unauthorized.php'); exit; }

$conn = createConnection(true);
$branding = null;
if ($conn) {
  $res = mysqli_query($conn, 'SELECT * FROM branding_settings ORDER BY id ASC LIMIT 1');
  $branding = $res ? mysqli_fetch_assoc($res) : null; if ($res) mysqli_free_result($res);
}

function val($arr, $k, $d=''){ return htmlspecialchars($arr[$k] ?? $d); }
?>
<style>
  .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:20px; }
  .logo-card { border:1px solid #e6edf5; border-radius:10px; padding:16px; background:#fff; }
  .logo-preview { height:80px; display:flex; align-items:center; justify-content:center; background:#f7f9fc; border-radius:8px; margin-bottom:10px; }
  .counter { font-size:12px; color:#6c757d; float:right; }
</style>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header"><h1>Branding & Organization Settings</h1></div>

    <div class="card" style="padding:24px; margin-bottom:20px;">
      <form id="brandingForm">
        <div class="grid">
          <div class="form-group"><label>Company Name</label><input name="org_name" class="form-control" maxlength="150" value="<?php echo val($branding,'org_name'); ?>"/></div>
          <div class="form-group"><label>Legal Name</label><input name="legal_name" class="form-control" maxlength="150" value="<?php echo val($branding,'legal_name'); ?>"/></div>
          <div class="form-group"><label>Tagline <span class="counter" id="tagCount">0/100</span></label><input name="tagline" id="tagline" class="form-control" maxlength="100" value="<?php echo val($branding,'tagline'); ?>"/></div>
          <div class="form-group"><label>Footer Text <span class="counter" id="footerCount">0/150</span></label><input name="footer_text" id="footer_text" class="form-control" maxlength="150" value="<?php echo val($branding,'footer_text'); ?>"/></div>
          <div class="form-group"><label>Email</label><input name="email" class="form-control" value="<?php echo val($branding,'email'); ?>"/></div>
          <div class="form-group"><label>Phone</label><input name="phone" class="form-control" value="<?php echo val($branding,'phone'); ?>"/></div>
          <div class="form-group"><label>Website</label><input name="website" class="form-control" placeholder="https://" value="<?php echo val($branding,'website'); ?>"/></div>
          <div class="form-group"><label>GSTIN</label><input name="gstin" class="form-control" value="<?php echo val($branding,'gstin'); ?>"/></div>
        </div>

        <div class="grid" style="margin-top:20px;">
          <div class="form-group"><label>Address Line 1</label><input name="address_line1" class="form-control" value="<?php echo val($branding,'address_line1'); ?>"/></div>
          <div class="form-group"><label>Address Line 2</label><input name="address_line2" class="form-control" value="<?php echo val($branding,'address_line2'); ?>"/></div>
          <div class="form-group"><label>City</label><input name="city" class="form-control" value="<?php echo val($branding,'city'); ?>"/></div>
          <div class="form-group"><label>State</label><input name="state" class="form-control" value="<?php echo val($branding,'state'); ?>"/></div>
          <div class="form-group"><label>ZIP</label><input name="zip" class="form-control" value="<?php echo val($branding,'zip'); ?>"/></div>
          <div class="form-group"><label>Country</label><input name="country" class="form-control" value="<?php echo val($branding,'country'); ?>"/></div>
        </div>

        <div style="margin-top:16px; display:flex; gap:10px;">
          <button class="btn btn-primary" type="submit">Save Settings</button>
          <a href="view.php" class="btn btn-secondary">View as Employee</a>
        </div>
      </form>
    </div>

    <div class="grid">
      <div class="logo-card">
        <div class="logo-preview" id="prevLight"><?php if(!empty($branding['logo_light'])) echo '<img src="'.$branding['logo_light'].'" style="max-height:70px;">'; ?></div>
        <div style="display:flex; gap:8px; align-items:center;">
          <input type="file" id="fileLight" accept=".png,.jpg,.jpeg,.svg" />
          <button class="btn btn-secondary" type="button" id="uploadLight">Upload Light Logo</button>
          <button class="btn btn-secondary" type="button" id="delLight">Remove</button>
        </div>
      </div>

      <div class="logo-card">
        <div class="logo-preview" id="prevDark"><?php if(!empty($branding['logo_dark'])) echo '<img src="'.$branding['logo_dark'].'" style="max-height:70px;">'; ?></div>
        <div style="display:flex; gap:8px; align-items:center;">
          <input type="file" id="fileDark" accept=".png,.jpg,.jpeg,.svg" />
          <button class="btn btn-secondary" type="button" id="uploadDark">Upload Dark Logo</button>
          <button class="btn btn-secondary" type="button" id="delDark">Remove</button>
        </div>
      </div>

      <div class="logo-card">
        <div class="logo-preview" id="prevSquare"><?php if(!empty($branding['logo_square'])) echo '<img src="'.$branding['logo_square'].'" style="max-height:70px;">'; ?></div>
        <div style="display:flex; gap:8px; align-items:center;">
          <input type="file" id="fileSquare" accept=".png,.jpg,.jpeg,.svg" />
          <button class="btn btn-secondary" type="button" id="uploadSquare">Upload Square Icon</button>
          <button class="btn btn-secondary" type="button" id="delSquare">Remove</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function count(el, target){ target.textContent = el.value.length + '/' + el.maxLength; }
const tag = document.getElementById('tagline');
const tagC = document.getElementById('tagCount');
const foot = document.getElementById('footer_text');
const footC = document.getElementById('footerCount');
[tag,foot].forEach((el)=> el && el.addEventListener('input', ()=> count(el, el===tag?tagC:footC)));
window.addEventListener('DOMContentLoaded', ()=>{ if(tag) count(tag, tagC); if(foot) count(foot, footC); });

// Save
document.getElementById('brandingForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const res = await fetch('/public/api/settings/branding/update.php', { method:'POST', body: fd });
  const data = await res.json();
  if (data.success) { alert('Saved'); location.reload(); } else { alert(data.error || 'Failed'); }
});

async function upload(type, fileInput, prevEl){
  if (!fileInput.files[0]) return alert('Choose a file');
  const fd = new FormData(); fd.append('type', type); fd.append('file', fileInput.files[0]);
  const res = await fetch('/public/api/settings/branding/upload_logo.php', { method:'POST', body: fd });
  const data = await res.json();
  if (data.success) { prevEl.innerHTML = '<img src="'+data.path+'" style="max-height:70px;">'; }
  else { alert(data.error || 'Upload failed'); }
}
async function del(type, prevEl){
  const fd = new FormData(); fd.append('type', type);
  const res = await fetch('/public/api/settings/branding/delete_logo.php', { method:'POST', body: fd });
  const data = await res.json();
  if (data.success) { prevEl.innerHTML = ''; } else { alert(data.error || 'Delete failed'); }
}

document.getElementById('uploadLight').onclick = ()=> upload('light', document.getElementById('fileLight'), document.getElementById('prevLight'));
document.getElementById('uploadDark').onclick = ()=> upload('dark', document.getElementById('fileDark'), document.getElementById('prevDark'));
document.getElementById('uploadSquare').onclick = ()=> upload('square', document.getElementById('fileSquare'), document.getElementById('prevSquare'));

document.getElementById('delLight').onclick = ()=> del('light', document.getElementById('prevLight'));
document.getElementById('delDark').onclick = ()=> del('dark', document.getElementById('prevDark'));
document.getElementById('delSquare').onclick = ()=> del('square', document.getElementById('prevSquare'));
</script>

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
<?php if($conn) closeConnection($conn); ?>
