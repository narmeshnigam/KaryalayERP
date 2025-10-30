<?php
/**
 * Contacts Module - View Contact Details
 * Display full contact profile with quick actions
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'contacts', 'view');

// Check if tables exist
if (!contacts_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=contacts');
    exit;
}

// Get contact ID
$contact_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($contact_id <= 0) {
    header('Location: index.php');
    exit;
}

// Get contact details
$contact = get_contact_by_id($conn, $contact_id, $CURRENT_USER_ID);

if (!$contact) {
    $_SESSION['flash_error'] = 'Contact not found or access denied';
    header('Location: index.php');
    exit;
}

// Check edit permission
$can_edit = can_edit_contact($conn, $contact_id, $CURRENT_USER_ID);

$page_title = $contact['name'] . ' - Contacts - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1;">
                    <h1 style="margin: 0 0 8px 0;">📇 Contact Details</h1>
                    <p style="color: #6c757d; margin: 0;">View complete contact information</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php if ($can_edit): ?>
                        <a href="edit.php?id=<?php echo $contact_id; ?>" class="btn btn-primary">✏️ Edit</a>
                        <form method="POST" action="delete.php" style="display: inline;" 
                              onsubmit="return confirm('Are you sure you want to delete this contact?');">
                            <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
                            <button type="submit" class="btn btn-danger">🗑️ Delete</button>
                        </form>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary">← Back</a>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
            
            <!-- Main Content -->
            <div>
                <!-- Contact Header Card -->
                <div class="card" style="margin-bottom: 24px;">
                    <div style="display: flex; align-items: start; gap: 24px;">
                        <!-- Avatar -->
                        <div style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #003581 0%, #0066cc 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 36px; flex-shrink: 0;">
                            <?php echo get_contact_initials($contact['name']); ?>
                        </div>
                        
                        <!-- Header Info -->
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                <h2 style="margin: 0; color: #1b2a57; font-size: 28px;">
                                    <?php echo htmlspecialchars($contact['name']); ?>
                                </h2>
                                <span style="background: rgba(0,53,129,0.1); color: #003581; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600;">
                                    <?php echo get_contact_type_icon($contact['contact_type']); ?> <?php echo $contact['contact_type']; ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($contact['designation'])): ?>
                                <div style="font-size: 16px; color: #6c757d; margin-bottom: 6px;">
                                    💼 <?php echo htmlspecialchars($contact['designation']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($contact['organization'])): ?>
                                <div style="font-size: 16px; color: #003581; font-weight: 600; margin-bottom: 12px;">
                                    🏢 <?php echo htmlspecialchars($contact['organization']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Quick Actions -->
                            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px;">
                                <?php if (!empty($contact['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($contact['phone']); ?>" 
                                       class="btn btn-primary" style="font-size: 14px;">
                                        📞 Call
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($contact['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" 
                                       class="btn btn-primary" style="font-size: 14px;">
                                        ✉️ Email
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($contact['whatsapp'])): ?>
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $contact['whatsapp']); ?>" 
                                       target="_blank" class="btn" style="background: #25D366; color: #fff; font-size: 14px;">
                                        💬 WhatsApp
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($contact['linkedin'])): ?>
                                    <a href="<?php echo htmlspecialchars($contact['linkedin']); ?>" 
                                       target="_blank" class="btn" style="background: #0077B5; color: #fff; font-size: 14px;">
                                        🔗 LinkedIn
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="card" style="margin-bottom: 24px;">
                    <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                        📞 Contact Information
                    </h3>
                    
                    <div style="display: grid; gap: 16px;">
                        <?php if (!empty($contact['phone'])): ?>
                            <div style="display: flex; align-items: start; gap: 12px;">
                                <div style="width: 140px; color: #6c757d; font-weight: 600; flex-shrink: 0;">
                                    📞 Primary Phone:
                                </div>
                                <div style="color: #1b2a57;">
                                    <a href="tel:<?php echo htmlspecialchars($contact['phone']); ?>" style="color: #003581; text-decoration: none;">
                                        <?php echo htmlspecialchars($contact['phone']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($contact['alt_phone'])): ?>
                            <div style="display: flex; align-items: start; gap: 12px;">
                                <div style="width: 140px; color: #6c757d; font-weight: 600; flex-shrink: 0;">
                                    📱 Alternate Phone:
                                </div>
                                <div style="color: #1b2a57;">
                                    <a href="tel:<?php echo htmlspecialchars($contact['alt_phone']); ?>" style="color: #003581; text-decoration: none;">
                                        <?php echo htmlspecialchars($contact['alt_phone']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($contact['email'])): ?>
                            <div style="display: flex; align-items: start; gap: 12px;">
                                <div style="width: 140px; color: #6c757d; font-weight: 600; flex-shrink: 0;">
                                    ✉️ Email:
                                </div>
                                <div style="color: #1b2a57;">
                                    <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" style="color: #003581; text-decoration: none;">
                                        <?php echo htmlspecialchars($contact['email']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($contact['whatsapp'])): ?>
                            <div style="display: flex; align-items: start; gap: 12px;">
                                <div style="width: 140px; color: #6c757d; font-weight: 600; flex-shrink: 0;">
                                    💬 WhatsApp:
                                </div>
                                <div style="color: #1b2a57;">
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $contact['whatsapp']); ?>" 
                                       target="_blank" style="color: #25D366; text-decoration: none;">
                                        <?php echo htmlspecialchars($contact['whatsapp']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($contact['linkedin'])): ?>
                            <div style="display: flex; align-items: start; gap: 12px;">
                                <div style="width: 140px; color: #6c757d; font-weight: 600; flex-shrink: 0;">
                                    🔗 LinkedIn:
                                </div>
                                <div style="color: #1b2a57; word-break: break-all;">
                                    <a href="<?php echo htmlspecialchars($contact['linkedin']); ?>" 
                                       target="_blank" style="color: #0077B5; text-decoration: none;">
                                        <?php echo htmlspecialchars($contact['linkedin']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($contact['address'])): ?>
                            <div style="display: flex; align-items: start; gap: 12px;">
                                <div style="width: 140px; color: #6c757d; font-weight: 600; flex-shrink: 0;">
                                    📍 Address:
                                </div>
                                <div style="color: #1b2a57;">
                                    <?php echo nl2br(htmlspecialchars($contact['address'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Notes -->
                <?php if (!empty($contact['notes'])): ?>
                    <div class="card">
                        <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                            📝 Notes
                        </h3>
                        <div style="color: #495057; line-height: 1.6; white-space: pre-wrap;">
                            <?php echo nl2br(htmlspecialchars($contact['notes'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div>
                <!-- Tags -->
                <?php if (!empty($contact['tags'])): ?>
                    <div class="card" style="margin-bottom: 24px;">
                        <h3 style="margin: 0 0 16px 0; color: #1b2a57; font-size: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">
                            🏷️ Tags
                        </h3>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                            <?php 
                            $tags = array_filter(array_map('trim', explode(',', $contact['tags'])));
                            foreach ($tags as $tag): 
                            ?>
                                <a href="index.php?tag=<?php echo urlencode($tag); ?>" 
                                   style="background: #e3f2fd; color: #1976d2; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block;">
                                    #<?php echo htmlspecialchars($tag); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Visibility & Sharing -->
                <div class="card" style="margin-bottom: 24px;">
                    <h3 style="margin: 0 0 16px 0; color: #1b2a57; font-size: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">
                        👁️ Visibility
                    </h3>
                    
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 12px;">
                        <div style="font-size: 24px; text-align: center; margin-bottom: 8px;">
                            <?php echo get_share_scope_icon($contact['share_scope']); ?>
                        </div>
                        <div style="text-align: center; font-weight: 600; color: #1b2a57; font-size: 15px;">
                            <?php echo $contact['share_scope']; ?>
                        </div>
                        <div style="text-align: center; font-size: 12px; color: #6c757d; margin-top: 4px;">
                            <?php 
                            $scope_desc = [
                                'Private' => 'Only visible to you',
                                'Team' => 'Visible to team members',
                                'Organization' => 'Visible to everyone'
                            ];
                            echo $scope_desc[$contact['share_scope']] ?? '';
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Entity Linking -->
                <?php if (!empty($contact['linked_entity_type'])): ?>
                    <div class="card" style="margin-bottom: 24px;">
                        <h3 style="margin: 0 0 16px 0; color: #1b2a57; font-size: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">
                            🔗 Linked To
                        </h3>
                        
                        <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 24px;">
                                <?php 
                                $entity_icons = [
                                    'Client' => '👤',
                                    'Project' => '📁',
                                    'Lead' => '🎯',
                                    'Other' => '📌'
                                ];
                                echo $entity_icons[$contact['linked_entity_type']] ?? '📌';
                                ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #1b2a57; font-size: 14px;">
                                    <?php echo htmlspecialchars($contact['linked_entity_type']); ?>
                                </div>
                                <div style="font-size: 12px; color: #6c757d;">
                                    ID: <?php echo $contact['linked_entity_id']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Metadata -->
                <div class="card">
                    <h3 style="margin: 0 0 16px 0; color: #1b2a57; font-size: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">
                        ℹ️ Metadata
                    </h3>
                    
                    <div style="display: grid; gap: 12px; font-size: 13px;">
                        <div>
                            <div style="color: #6c757d; margin-bottom: 4px; font-weight: 600;">Created By:</div>
                            <div style="color: #1b2a57;">
                                👤 <?php echo htmlspecialchars($contact['created_by_username']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <div style="color: #6c757d; margin-bottom: 4px; font-weight: 600;">Created On:</div>
                            <div style="color: #1b2a57;">
                                📅 <?php echo date('M d, Y h:i A', strtotime($contact['created_at'])); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($contact['updated_at'])): ?>
                            <div>
                                <div style="color: #6c757d; margin-bottom: 4px; font-weight: 600;">Last Updated:</div>
                                <div style="color: #1b2a57;">
                                    🔄 <?php echo date('M d, Y h:i A', strtotime($contact['updated_at'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <div style="color: #6c757d; margin-bottom: 4px; font-weight: 600;">Contact ID:</div>
                            <div style="color: #1b2a57; font-family: monospace;">
                                #<?php echo $contact['id']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>

    </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
