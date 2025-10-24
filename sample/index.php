<?php
/**
 * Karyalay ERP - UI Component Library
 * 
 * This page displays all reusable UI components available for the ERP system.
 * Components are organized by category with examples and usage instructions.
 * 
 * Theme: Purple Gradient (#667eea to #764ba2)
 */

$page_title = "UI Component Library - Karyalay ERP";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: #f8f9fa;
        }
        .library-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .library-header {
            text-align: center;
            padding: 40px 20px;
            background: #003581;
            color: white;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        .library-header h1 {
            font-size: 36px;
            margin-bottom: 10px;
        }
        .library-header p {
            font-size: 16px;
            opacity: 0.9;
        }
        .component-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .section-title {
            font-size: 28px;
            font-weight: 700;
            color: #003581;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #faa718;
        }
        .component-demo {
            margin-bottom: 30px;
        }
        .demo-title {
            font-size: 18px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .demo-code {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin-top: 10px;
            overflow-x: auto;
        }
        .toc {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        .toc h2 {
            color: #003581;
            margin-bottom: 20px;
            font-size: 24px;
        }
        .toc-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
        }
        .toc-item {
            display: block;
            padding: 10px 15px;
            color: #495057;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            font-weight: 500;
        }
        .toc-item:hover {
            background: #003581;
            color: white;
            transform: translateX(5px);
        }
        .color-palette {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .color-box {
            padding: 30px 15px;
            border-radius: 8px;
            text-align: center;
            color: white;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .example-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
    </style>
</head>
<body>
    <div class="library-container">
        <!-- Header -->
        <div class="library-header">
            <h1>🎨 UI Component Library</h1>
            <p>Comprehensive collection of reusable UI components for Karyalay ERP</p>
            <p style="font-size: 14px; margin-top: 10px;">Theme: Blue & Orange (#003581, #faa718, #ffffff)</p>
        </div>

        <!-- Table of Contents -->
        <div class="toc">
            <h2>📋 Component Index</h2>
            <div class="toc-list">
                <a href="#colors" class="toc-item">🎨 Color Palette</a>
                <a href="#buttons" class="toc-item">🔘 Buttons</a>
                <a href="#forms" class="toc-item">📝 Form Elements</a>
                <a href="#tables" class="toc-item">📊 Tables</a>
                <a href="#cards" class="toc-item">🃏 Cards</a>
                <a href="#alerts" class="toc-item">⚠️ Alerts</a>
                <a href="#badges" class="toc-item">🏷️ Badges</a>
                <a href="#modals" class="toc-item">🪟 Modals</a>
                <a href="#breadcrumbs" class="toc-item">🗺️ Breadcrumbs</a>
                <a href="#pagination" class="toc-item">📄 Pagination</a>
                <a href="#progress" class="toc-item">📈 Progress Bars</a>
                <a href="#tabs" class="toc-item">📑 Tabs</a>
                <a href="#dropdowns" class="toc-item">⬇️ Dropdowns</a>
                <a href="#tooltips" class="toc-item">💬 Tooltips</a>
                <a href="#spinners" class="toc-item">⏳ Spinners</a>
                <a href="#lists" class="toc-item">📝 List Groups</a>
                <a href="#stats" class="toc-item">📊 Stats Cards</a>
                <a href="#utilities" class="toc-item">🛠️ Utility Classes</a>
            </div>
        </div>

        <!-- 1. COLOR PALETTE -->
        <div id="colors" class="component-section">
            <h2 class="section-title">🎨 Color Palette</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Brand Colors</h3>
                <div class="color-palette">
                    <div class="color-box" style="background: #003581;">
                        Primary Blue<br>#003581
                    </div>
                    <div class="color-box" style="background: #faa718;">
                        Accent Orange<br>#faa718
                    </div>
                    <div class="color-box" style="background: #ffffff; color: #333; border: 2px solid #ddd;">
                        White<br>#ffffff
                    </div>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Semantic Colors</h3>
                <div class="color-palette">
                    <div class="color-box" style="background: #28a745;">
                        Success<br>#28a745
                    </div>
                    <div class="color-box" style="background: #dc3545;">
                        Danger<br>#dc3545
                    </div>
                    <div class="color-box" style="background: #ffc107; color: #333;">
                        Warning<br>#ffc107
                    </div>
                    <div class="color-box" style="background: #17a2b8;">
                        Info<br>#17a2b8
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. BUTTONS -->
        <div id="buttons" class="component-section">
            <h2 class="section-title">🔘 Buttons</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Button Variants</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <button class="btn btn-primary">Primary</button>
                    <button class="btn btn-accent">Accent</button>
                    <button class="btn btn-secondary">Secondary</button>
                    <button class="btn btn-success">Success</button>
                    <button class="btn btn-danger">Danger</button>
                    <button class="btn btn-warning">Warning</button>
                    <button class="btn btn-info">Info</button>
                    <button class="btn btn-light">Light</button>
                    <button class="btn btn-outline-primary">Outline Primary</button>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Button Sizes</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                    <button class="btn btn-primary btn-sm">Small</button>
                    <button class="btn btn-primary">Default</button>
                    <button class="btn btn-primary btn-lg">Large</button>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Button States</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <button class="btn btn-primary">Normal</button>
                    <button class="btn btn-primary" disabled>Disabled</button>
                    <button class="btn btn-primary btn-block">Block Button</button>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Icon Buttons</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <button class="btn btn-primary btn-icon">+</button>
                    <button class="btn btn-danger btn-icon">×</button>
                    <button class="btn btn-success btn-icon">✓</button>
                    <button class="btn btn-info btn-icon">ℹ</button>
                </div>
            </div>
        </div>

        <!-- 3. FORM ELEMENTS -->
        <div id="forms" class="component-section">
            <h2 class="section-title">📝 Form Elements</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Text Inputs</h3>
                <div class="form-group">
                    <label class="form-label required">Username</label>
                    <input type="text" class="form-control" placeholder="Enter username">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" placeholder="Enter email">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" placeholder="Enter password">
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Input Sizes</h3>
                <input type="text" class="form-control form-control-sm mb-2" placeholder="Small input">
                <input type="text" class="form-control mb-2" placeholder="Default input">
                <input type="text" class="form-control form-control-lg" placeholder="Large input">
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Input States</h3>
                <input type="text" class="form-control mb-2" placeholder="Normal">
                <input type="text" class="form-control is-valid mb-2" placeholder="Valid input">
                <div class="valid-feedback">Looks good!</div>
                <input type="text" class="form-control is-invalid mb-2" placeholder="Invalid input">
                <div class="invalid-feedback">Please provide a valid input.</div>
                <input type="text" class="form-control mb-2" placeholder="Disabled" disabled>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Textarea</h3>
                <textarea class="form-control" placeholder="Enter description..."></textarea>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Select Dropdown</h3>
                <select class="form-control">
                    <option>Select an option</option>
                    <option>Option 1</option>
                    <option>Option 2</option>
                    <option>Option 3</option>
                </select>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Checkboxes & Radio Buttons</h3>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="check1">
                    <label class="form-check-label" for="check1">Checkbox option 1</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="check2">
                    <label class="form-check-label" for="check2">Checkbox option 2</label>
                </div>
                <hr style="margin: 15px 0;">
                <div class="form-check">
                    <input type="radio" class="form-check-input" name="radio" id="radio1">
                    <label class="form-check-label" for="radio1">Radio option 1</label>
                </div>
                <div class="form-check">
                    <input type="radio" class="form-check-input" name="radio" id="radio2">
                    <label class="form-check-label" for="radio2">Radio option 2</label>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Input Groups</h3>
                <div class="input-group mb-2">
                    <span class="input-group-text">@</span>
                    <input type="text" class="form-control" placeholder="Username">
                </div>
                <div class="input-group mb-2">
                    <input type="text" class="form-control" placeholder="Amount">
                    <span class="input-group-text">₹</span>
                </div>
                <div class="input-group">
                    <span class="input-group-text">https://</span>
                    <input type="text" class="form-control" placeholder="website.com">
                </div>
            </div>
        </div>

        <!-- 4. TABLES -->
        <div id="tables" class="component-section">
            <h2 class="section-title">📊 Tables</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Default Table</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>John Doe</td>
                                <td>john@example.com</td>
                                <td><span class="badge badge-primary">Admin</span></td>
                                <td><span class="badge badge-success">Active</span></td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Jane Smith</td>
                                <td>jane@example.com</td>
                                <td><span class="badge badge-info">User</span></td>
                                <td><span class="badge badge-success">Active</span></td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>Bob Johnson</td>
                                <td>bob@example.com</td>
                                <td><span class="badge badge-warning">Manager</span></td>
                                <td><span class="badge badge-danger">Inactive</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Striped Table</h3>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Product A</td><td>₹1,299</td><td>50</td></tr>
                        <tr><td>Product B</td><td>₹2,499</td><td>30</td></tr>
                        <tr><td>Product C</td><td>₹999</td><td>100</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 5. CARDS -->
        <div id="cards" class="component-section">
            <h2 class="section-title">🃏 Cards</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Basic Cards</h3>
                <div class="example-grid">
                    <div class="card">
                        <div class="card-header">Card Header</div>
                        <div class="card-body">
                            <h4 class="card-title">Card Title</h4>
                            <p class="card-text">This is a basic card with header, body, and footer.</p>
                        </div>
                        <div class="card-footer">Card Footer</div>
                    </div>

                    <div class="card card-primary">
                        <div class="card-body">
                            <h4 class="card-title">Primary Card</h4>
                            <p class="card-text">Card with primary border styling.</p>
                        </div>
                    </div>

                    <div class="card card-success">
                        <div class="card-body">
                            <h4 class="card-title">Success Card</h4>
                            <p class="card-text">Card with success border styling.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. ALERTS -->
        <div id="alerts" class="component-section">
            <h2 class="section-title">⚠️ Alerts</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Alert Types</h3>
                <div class="alert alert-success">
                    <span class="alert-icon">✓</span>
                    <span>Success! Your operation completed successfully.</span>
                </div>
                <div class="alert alert-danger">
                    <span class="alert-icon">✗</span>
                    <span>Error! Something went wrong.</span>
                </div>
                <div class="alert alert-warning">
                    <span class="alert-icon">⚠</span>
                    <span>Warning! Please review your input.</span>
                </div>
                <div class="alert alert-info">
                    <span class="alert-icon">ℹ</span>
                    <span>Info: Here's some helpful information.</span>
                </div>
                <div class="alert alert-primary">
                    <span class="alert-icon">💡</span>
                    <span>Primary alert with custom styling.</span>
                </div>
                <div class="alert alert-accent">
                    <span class="alert-icon">⭐</span>
                    <span>Accent alert highlighting important information.</span>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Dismissible Alert</h3>
                <div class="alert alert-success alert-dismissible">
                    <span class="alert-icon">✓</span>
                    <span>This alert can be dismissed!</span>
                    <button class="alert-close" onclick="this.parentElement.style.display='none'">×</button>
                </div>
            </div>
        </div>

        <!-- 7. BADGES -->
        <div id="badges" class="component-section">
            <h2 class="section-title">🏷️ Badges</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Badge Variants</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                    <span class="badge badge-primary">Primary</span>
                    <span class="badge badge-accent">Accent</span>
                    <span class="badge badge-success">Success</span>
                    <span class="badge badge-danger">Danger</span>
                    <span class="badge badge-warning">Warning</span>
                    <span class="badge badge-info">Info</span>
                    <span class="badge badge-light">Light</span>
                    <span class="badge badge-dark">Dark</span>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Pill Badges</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                    <span class="badge badge-primary badge-pill">Primary Pill</span>
                    <span class="badge badge-success badge-pill">Success Pill</span>
                    <span class="badge badge-danger badge-pill">Danger Pill</span>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Badges in Context</h3>
                <h4>Inbox <span class="badge badge-danger badge-pill">5</span></h4>
                <p>Status: <span class="badge badge-success">Active</span></p>
                <p>Role: <span class="badge badge-primary">Administrator</span></p>
            </div>
        </div>

        <!-- 8. MODALS -->
        <div id="modals" class="component-section">
            <h2 class="section-title">🪟 Modals</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Modal Example</h3>
                <button class="btn btn-primary" onclick="document.getElementById('modal-demo').style.display='flex'">
                    Open Modal
                </button>
                
                <div id="modal-demo" class="modal-overlay" style="display: none;">
                    <div class="modal">
                        <div class="modal-header">
                            <h3 class="modal-title">Modal Title</h3>
                            <button class="modal-close" onclick="document.getElementById('modal-demo').style.display='none'">×</button>
                        </div>
                        <div class="modal-body">
                            <p>This is a modal dialog. You can put any content here.</p>
                            <div class="form-group">
                                <label class="form-label">Input Field</label>
                                <input type="text" class="form-control" placeholder="Enter something">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-light" onclick="document.getElementById('modal-demo').style.display='none'">Cancel</button>
                            <button class="btn btn-primary">Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 9. BREADCRUMBS -->
        <div id="breadcrumbs" class="component-section">
            <h2 class="section-title">🗺️ Breadcrumbs</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Navigation Breadcrumbs</h3>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Library</a></li>
                        <li class="breadcrumb-item active">Data</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- 10. PAGINATION -->
        <div id="pagination" class="component-section">
            <h2 class="section-title">📄 Pagination</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Pagination Example</h3>
                <ul class="pagination">
                    <li class="page-item disabled">
                        <a class="page-link" href="#">Previous</a>
                    </li>
                    <li class="page-item active">
                        <a class="page-link" href="#">1</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">2</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">3</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">Next</a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- 11. PROGRESS BARS -->
        <div id="progress" class="component-section">
            <h2 class="section-title">📈 Progress Bars</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Basic Progress</h3>
                <div class="progress mb-3">
                    <div class="progress-bar" style="width: 25%">25%</div>
                </div>
                <div class="progress mb-3">
                    <div class="progress-bar" style="width: 50%">50%</div>
                </div>
                <div class="progress mb-3">
                    <div class="progress-bar" style="width: 75%">75%</div>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Colored Progress Bars</h3>
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-success" style="width: 60%">Success 60%</div>
                </div>
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-warning" style="width: 40%">Warning 40%</div>
                </div>
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-danger" style="width: 80%">Danger 80%</div>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Striped & Animated</h3>
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-striped" style="width: 65%">Striped 65%</div>
                </div>
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 75%">Animated 75%</div>
                </div>
            </div>
        </div>

        <!-- 12. TABS -->
        <div id="tabs" class="component-section">
            <h2 class="section-title">📑 Tabs</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Tab Navigation</h3>
                <ul class="nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" onclick="showTab(event, 'tab1')">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="showTab(event, 'tab2')">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="showTab(event, 'tab3')">Settings</a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div id="tab1" class="tab-pane active">
                        <h4>Home Content</h4>
                        <p>This is the home tab content.</p>
                    </div>
                    <div id="tab2" class="tab-pane">
                        <h4>Profile Content</h4>
                        <p>This is the profile tab content.</p>
                    </div>
                    <div id="tab3" class="tab-pane">
                        <h4>Settings Content</h4>
                        <p>This is the settings tab content.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 13. DROPDOWNS -->
        <div id="dropdowns" class="component-section">
            <h2 class="section-title">⬇️ Dropdowns</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Dropdown Menu</h3>
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" onclick="toggleDropdown('dropdown1')">
                        Dropdown Menu
                    </button>
                    <div id="dropdown1" class="dropdown-menu">
                        <div class="dropdown-header">Header</div>
                        <a class="dropdown-item" href="#">Action</a>
                        <a class="dropdown-item" href="#">Another action</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#">Separated link</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 14. TOOLTIPS -->
        <div id="tooltips" class="component-section">
            <h2 class="section-title">💬 Tooltips</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Hover Tooltips</h3>
                <div style="display: flex; gap: 20px;">
                    <div class="tooltip-container">
                        <button class="btn btn-primary">Hover Me</button>
                        <span class="tooltip-text">This is a tooltip!</span>
                    </div>
                    <div class="tooltip-container">
                        <span class="badge badge-info">Info Badge</span>
                        <span class="tooltip-text">More information here</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 15. SPINNERS -->
        <div id="spinners" class="component-section">
            <h2 class="section-title">⏳ Spinners & Loaders</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Spinner Sizes</h3>
                <div style="display: flex; gap: 30px; align-items: center;">
                    <div class="spinner spinner-sm"></div>
                    <div class="spinner"></div>
                    <div class="spinner spinner-lg"></div>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Dots Loader</h3>
                <div class="dots-loader">
                    <div class="dot"></div>
                    <div class="dot"></div>
                    <div class="dot"></div>
                </div>
            </div>
        </div>

        <!-- 16. LIST GROUPS -->
        <div id="lists" class="component-section">
            <h2 class="section-title">📝 List Groups</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Basic List Group</h3>
                <div style="max-width: 400px;">
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action active">Active item</a>
                        <a href="#" class="list-group-item list-group-item-action">Second item</a>
                        <a href="#" class="list-group-item list-group-item-action">Third item</a>
                        <a href="#" class="list-group-item list-group-item-action">Fourth item</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 17. STATS CARDS -->
        <div id="stats" class="component-section">
            <h2 class="section-title">📊 Statistics Cards</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Metric Cards</h3>
                <div class="example-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-content">
                            <div class="stat-label">Total Users</div>
                            <div class="stat-value">2,543</div>
                            <div class="stat-change positive">↑ 12.5%</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: #faa718;">💰</div>
                        <div class="stat-content">
                            <div class="stat-label">Revenue</div>
                            <div class="stat-value">₹45.2K</div>
                            <div class="stat-change positive">↑ 8.3%</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: #dc3545;">📉</div>
                        <div class="stat-content">
                            <div class="stat-label">Sales</div>
                            <div class="stat-value">1,234</div>
                            <div class="stat-change negative">↓ 3.2%</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: #28a745;">⭐</div>
                        <div class="stat-content">
                            <div class="stat-label">Rating</div>
                            <div class="stat-value">4.8/5</div>
                            <div class="stat-change positive">↑ 0.2</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 18. UTILITY CLASSES -->
        <div id="utilities" class="component-section">
            <h2 class="section-title">🛠️ Utility Classes</h2>
            
            <div class="component-demo">
                <h3 class="demo-title">Spacing Utilities</h3>
                <div class="card" style="max-width: 600px;">
                    <div class="card-body">
                        <p class="m-0">No margin (m-0)</p>
                        <p class="mt-3">Top margin 3 (mt-3)</p>
                        <p class="mb-3">Bottom margin 3 (mb-3)</p>
                        <div class="p-3 bg-light">Padding 3 (p-3)</div>
                    </div>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Text Utilities</h3>
                <p class="text-left">Left aligned text</p>
                <p class="text-center">Center aligned text</p>
                <p class="text-right">Right aligned text</p>
                <p class="text-primary">Primary colored text</p>
                <p class="text-success">Success colored text</p>
                <p class="text-danger">Danger colored text</p>
                <p class="text-muted">Muted text</p>
                <p class="fw-bold">Bold text</p>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Display & Flex</h3>
                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded mb-3">
                    <span>Flex container</span>
                    <button class="btn btn-primary btn-sm">Button</button>
                </div>
                <div class="d-flex gap-3">
                    <div class="p-3 bg-primary rounded">Item 1</div>
                    <div class="p-3 bg-accent rounded">Item 2</div>
                    <div class="p-3 bg-success rounded">Item 3</div>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Grid Utilities</h3>
                <div class="d-grid grid-3 gap-3">
                    <div class="p-4 bg-light rounded text-center">Grid 1</div>
                    <div class="p-4 bg-light rounded text-center">Grid 2</div>
                    <div class="p-4 bg-light rounded text-center">Grid 3</div>
                </div>
            </div>

            <div class="component-demo">
                <h3 class="demo-title">Shadow & Border Utilities</h3>
                <div style="display: flex; gap: 20px;">
                    <div class="p-3 shadow-sm border rounded">Shadow SM</div>
                    <div class="p-3 shadow border rounded">Shadow</div>
                    <div class="p-3 shadow-lg border rounded-lg">Shadow LG</div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; padding: 30px; color: #6c757d;">
            <p style="margin: 0; font-size: 14px;">
                &copy; <?php echo date('Y'); ?> Karyalay ERP - UI Component Library
            </p>
            <p style="margin: 5px 0 0 0; font-size: 12px;">
                All components are ready to use. Simply copy the HTML and apply CSS classes.
            </p>
        </div>
    </div>

    <script>
        // Tab switching function
        function showTab(event, tabId) {
            event.preventDefault();
            
            // Hide all tab panes
            const panes = document.querySelectorAll('.tab-pane');
            panes.forEach(pane => pane.classList.remove('active'));
            
            // Remove active class from all links
            const links = event.target.parentElement.parentElement.querySelectorAll('.nav-link');
            links.forEach(link => link.classList.remove('active'));
            
            // Show selected tab and mark link as active
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }

        // Dropdown toggle function
        function toggleDropdown(id) {
            const dropdown = document.getElementById(id);
            dropdown.classList.toggle('show');
            
            // Close dropdown when clicking outside
            window.onclick = function(event) {
                if (!event.target.matches('.dropdown-toggle')) {
                    const dropdowns = document.querySelectorAll('.dropdown-menu');
                    dropdowns.forEach(dd => dd.classList.remove('show'));
                }
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('modal-demo');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
