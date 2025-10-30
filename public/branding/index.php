<?php
/**
 * Branding Settings - Admin Interface
 * Manage logos, organization details, and branding elements
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'branding_settings', 'edit_all');

// Check if module is set up
if (!branding_table_exists($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$page_title = 'Branding Settings - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get current settings
$settings = branding_get_settings($conn);
if (!$settings) {
    // Create default record if doesn't exist
    $default_name = 'Karyalay ERP';
    $default_footer = '© ' . date('Y') . ' Karyalay ERP. All rights reserved.';
    mysqli_query($conn, "INSERT INTO branding_settings (org_name, footer_text) VALUES ('$default_name', '$default_footer')");
    $settings = branding_get_settings($conn);
}
?>

<style>
  .logo-preview { min-height:140px; display:flex; align-items:center; justify-content:center; border:2px dashed #cbd5e0; border-radius:8px; padding:20px; margin:16px 0; background:#fff; }
  .logo-preview img { max-width:100%; max-height:120px; object-fit:contain; }
  .logo-preview.empty { color:#a0aec0; font-size:14px; }
  .logo-preview.dark-bg { background:#1b2a57; }
  .upload-btn { cursor:pointer; }
  .char-count { font-size:12px; color:#6c757d; margin-top:4px; }
  .char-count.warn { color:#faa718; }
  .char-count.error { color:#dc3545; }
</style>

<div class="main-wrapper">
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <h1>🏢 Branding & Organization Settings</h1>
          <p>Manage your organization's visual identity and business information</p>
        </div>
        <div>
          <a href="view.php" class="btn btn-accent">
            👁️ Preview Settings
          </a>
        </div>
      </div>
    </div>

    <form id="brandingForm">
      <!-- Logo Assets Section -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          🎨 Logo Assets
        </h3>
        <p style="color:#6c757d;margin-bottom:24px;font-size:14px;">Upload logos for different use cases. Recommended formats: PNG, SVG (max 2MB each)</p>
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
          <!-- Login Page Logo -->
          <div>
            <label style="font-weight:600;margin-bottom:8px;display:block;">Logo for Login Page</label>
            <p style="font-size:13px;color:#6c757d;margin:0 0 12px 0;">Used on light/white backgrounds in login interface</p>
            
            <div class="logo-preview" id="preview-login_page_logo">
              <?php if (!empty($settings['login_page_logo'])): ?>
                <img src="../../<?php echo htmlspecialchars($settings['login_page_logo']); ?>" alt="Login Logo">
              <?php else: ?>
                <div class="empty">No logo uploaded</div>
              <?php endif; ?>
            </div>
            
            <input type="file" id="file-login_page_logo" accept="image/png,image/jpeg,image/jpg,image/svg+xml" style="display:none;">
            <button type="button" class="btn btn-primary btn-sm upload-btn" onclick="document.getElementById('file-login_page_logo').click();">
              📤 Upload Logo
            </button>
            <?php if (!empty($settings['login_page_logo'])): ?>
              <button type="button" class="btn btn-secondary btn-sm" onclick="deleteLogo('login_page_logo')" style="margin-left:8px;">
                🗑️ Remove
              </button>
            <?php endif; ?>
          </div>

          <!-- Sidebar Header Logo -->
          <div>
            <label style="font-weight:600;margin-bottom:8px;display:block;">Logo for Sidebar Header (Expanded)</label>
            <p style="font-size:13px;color:#6c757d;margin:0 0 12px 0;">Used when sidebar is expanded, on dark background</p>
            
            <div class="logo-preview dark-bg" id="preview-sidebar_header_full_logo">
              <?php if (!empty($settings['sidebar_header_full_logo'])): ?>
                <img src="../../<?php echo htmlspecialchars($settings['sidebar_header_full_logo']); ?>" alt="Sidebar Logo">
              <?php else: ?>
                <div class="empty" style="color:#cbd5e0;">No logo uploaded</div>
              <?php endif; ?>
            </div>
            
            <input type="file" id="file-sidebar_header_full_logo" accept="image/png,image/jpeg,image/jpg,image/svg+xml" style="display:none;">
            <button type="button" class="btn btn-primary btn-sm upload-btn" onclick="document.getElementById('file-sidebar_header_full_logo').click();">
              📤 Upload Logo
            </button>
            <?php if (!empty($settings['sidebar_header_full_logo'])): ?>
              <button type="button" class="btn btn-secondary btn-sm" onclick="deleteLogo('sidebar_header_full_logo')" style="margin-left:8px;">
                🗑️ Remove
              </button>
            <?php endif; ?>
          </div>

          <!-- Favicon -->
          <div>
            <label style="font-weight:600;margin-bottom:8px;display:block;">Favicon (Light Background)</label>
            <p style="font-size:13px;color:#6c757d;margin:0 0 12px 0;">Square icon (256x256px) used as browser favicon</p>
            
            <div class="logo-preview" id="preview-favicon">
              <?php if (!empty($settings['favicon'])): ?>
                <img src="../../<?php echo htmlspecialchars($settings['favicon']); ?>" alt="Favicon">
              <?php else: ?>
                <div class="empty">No icon uploaded</div>
              <?php endif; ?>
            </div>
            
            <input type="file" id="file-favicon" accept="image/png,image/jpeg,image/jpg,image/svg+xml" style="display:none;">
            <button type="button" class="btn btn-primary btn-sm upload-btn" onclick="document.getElementById('file-favicon').click();">
              📤 Upload Icon
            </button>
            <?php if (!empty($settings['favicon'])): ?>
              <button type="button" class="btn btn-secondary btn-sm" onclick="deleteLogo('favicon')" style="margin-left:8px;">
                🗑️ Remove
              </button>
            <?php endif; ?>
          </div>

          <!-- Sidebar Square Logo -->
          <div>
            <label style="font-weight:600;margin-bottom:8px;display:block;">Sidebar Square Logo (Collapsed)</label>
            <p style="font-size:13px;color:#6c757d;margin:0 0 12px 0;">Square icon (256x256px) used when sidebar is collapsed on dark</p>
            
            <div class="logo-preview dark-bg" id="preview-sidebar_square_logo">
              <?php if (!empty($settings['sidebar_square_logo'])): ?>
                <img src="../../<?php echo htmlspecialchars($settings['sidebar_square_logo']); ?>" alt="Sidebar Square Icon">
              <?php else: ?>
                <div class="empty" style="color:#cbd5e0;">No icon uploaded</div>
              <?php endif; ?>
            </div>
            
            <input type="file" id="file-sidebar_square_logo" accept="image/png,image/jpeg,image/jpg,image/svg+xml" style="display:none;">
            <button type="button" class="btn btn-primary btn-sm upload-btn" onclick="document.getElementById('file-sidebar_square_logo').click();">
              📤 Upload Icon
            </button>
            <?php if (!empty($settings['sidebar_square_logo'])): ?>
              <button type="button" class="btn btn-secondary btn-sm" onclick="deleteLogo('sidebar_square_logo')" style="margin-left:8px;">
                🗑️ Remove
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Organization Information Section -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          🏢 Organization Information
        </h3>
      <!-- Organization Information Section -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          🏢 Organization Information
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
          <div class="form-group">
            <label>Organization Name <span style="color: #dc3545;">*</span></label>
            <input type="text" id="org_name" name="org_name" class="form-control" 
                   value="<?php echo htmlspecialchars($settings['org_name'] ?? ''); ?>" required>
          </div>

          <div class="form-group">
            <label>Legal Name <span style="font-size:12px;color:#6c757d;">(if different)</span></label>
            <input type="text" id="legal_name" name="legal_name" class="form-control" 
                   value="<?php echo htmlspecialchars($settings['legal_name'] ?? ''); ?>">
          </div>

          <div class="form-group">
            <label>GSTIN / Business Registration</label>
            <input type="text" id="gstin" name="gstin" class="form-control" 
                   value="<?php echo htmlspecialchars($settings['gstin'] ?? ''); ?>">
          </div>

          <div class="form-group" style="grid-column: 1 / -1;">
            <label>Tagline / Slogan</label>
            <input type="text" id="tagline" name="tagline" class="form-control" maxlength="100"
                   value="<?php echo htmlspecialchars($settings['tagline'] ?? ''); ?>" 
                   oninput="updateCharCount('tagline', 100)">
            <div class="char-count" id="count-tagline">0 / 100 characters</div>
          </div>
        </div>
      </div>

      <!-- Address Information Section -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          📍 Address Information
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
          <div class="form-group" style="grid-column: 1 / -1;">
            <label>Address Line 1</label>
            <input type="text" id="address_line1" name="address_line1" class="form-control" 
                   value="<?php echo htmlspecialchars($settings['address_line1'] ?? ''); ?>">
          </div>

          <div class="form-group" style="grid-column: 1 / -1;">
            <label>Address Line 2</label>
            <input type="text" id="address_line2" name="address_line2" class="form-control" 
                   value="<?php echo htmlspecialchars($settings['address_line2'] ?? ''); ?>">
          </div>

          <div class="form-group">
            <label>City</label>
            <input type="text" id="city" name="city" class="form-control" 
                   value="<?php echo htmlspecialchars($settings['city'] ?? ''); ?>">
          </div>

          <div class="form-group">
            <label>State / Province</label>
            <input type="text" id="state" name="state" class="form-control" 
                   value="<?php echo htmlspecialchars($settings['state'] ?? ''); ?>">
          </div>

          <div class="form-group">
            <label>ZIP / Postal Code</label>
            <input type="text" id="zip" name="zip" class="form-control" 
                   value="<?php echo htmlspecialchars($settings['zip'] ?? ''); ?>">
          </div>

          <div class="form-group">
            <label>Country</label>
            <input type="text" id="country" name="country" class="form-control" 
                   value="<?php echo htmlspecialchars($settings['country'] ?? ''); ?>">
          </div>
        </div>
      </div>

      <!-- Contact Information Section -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          📞 Contact Information
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
          <div class="form-group">
            <label>Email</label>
            <input type="email" id="email" name="email" class="form-control" 
                   value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>">
          </div>

          <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" id="phone" name="phone" class="form-control" 
                   value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>">
          </div>

          <div class="form-group">
            <label>Website URL</label>
            <input type="url" id="website" name="website" class="form-control" 
                   placeholder="https://example.com"
                   value="<?php echo htmlspecialchars($settings['website'] ?? ''); ?>">
          </div>
        </div>
      </div>

      <!-- Branding Elements Section -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          ✨ Branding Elements
        </h3>
        
        <div class="form-group">
          <label>Footer Text</label>
          <textarea id="footer_text" name="footer_text" class="form-control" rows="2" maxlength="150"
                    oninput="updateCharCount('footer_text', 150)"><?php echo htmlspecialchars($settings['footer_text'] ?? ''); ?></textarea>
          <div class="char-count" id="count-footer_text">0 / 150 characters</div>
        </div>
      </div>

      <!-- Submit Buttons -->
      <div style="text-align: center; padding: 20px 0;">
        <button type="submit" class="btn" style="padding: 15px 60px; font-size: 16px;">
          💾 Save Settings
        </button>
        <a href="view.php" class="btn btn-accent" style="padding: 15px 60px; font-size: 16px; margin-left: 15px; text-decoration: none;">
          👁️ Preview
        </a>
      </div>
    </form>

  </div>
</div>

<script>
// Character count updates
function updateCharCount(fieldId, max) {
  const field = document.getElementById(fieldId);
  const counter = document.getElementById('count-' + fieldId);
  const len = field.value.length;
  counter.textContent = len + ' / ' + max + ' characters';
  counter.classList.remove('warn', 'error');
  if (len > max * 0.9) counter.classList.add('warn');
  if (len >= max) counter.classList.add('error');
}

// Initialize character counts
['tagline', 'footer_text'].forEach(id => {
  const field = document.getElementById(id);
  if (field && field.value) {
    updateCharCount(id, id === 'tagline' ? 100 : 150);
  }
});

// Logo upload handlers
['login_page_logo', 'sidebar_header_full_logo', 'favicon', 'sidebar_square_logo'].forEach(type => {
  const input = document.getElementById('file-' + type);
  if (!input) return;
  
  input.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Validate file size
    if (file.size > 2 * 1024 * 1024) {
      alert('File size must not exceed 2MB');
      input.value = '';
      return;
    }
    
    // Upload file
    const formData = new FormData();
    formData.append('logo', file);
    formData.append('type', type);
    
    fetch('../api/branding/upload.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        // Update preview
        const preview = document.getElementById('preview-' + type);
        preview.innerHTML = '<img src="' + data.url + '" alt="' + type + ' Logo">';
        alert(data.message);
        location.reload();
      } else {
        alert('Upload failed: ' + (data.error || 'Unknown error'));
      }
    })
    .catch(err => {
      alert('Upload error: ' + err.message);
    });
    
    input.value = '';
  });
});

// Logo deletion
function deleteLogo(type) {
  if (!confirm('Are you sure you want to delete this logo?')) return;
  
  fetch('../api/branding/delete.php?type=' + type, {
    method: 'POST'
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert(data.message);
      location.reload();
    } else {
      alert('Delete failed: ' + (data.error || 'Unknown error'));
    }
  })
  .catch(err => {
    alert('Delete error: ' + err.message);
  });
}

// Form submission
document.getElementById('brandingForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  
  fetch('../api/branding/update.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert(data.message || 'Settings updated successfully');
      location.reload();
    } else {
      const errors = data.errors || [data.error || 'Unknown error'];
      alert('Update failed:\n' + errors.join('\n'));
    }
  })
  .catch(err => {
    alert('Error: ' + err.message);
  });
});
</script>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
