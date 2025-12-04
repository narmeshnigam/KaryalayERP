<?php
/**
 * Demo Data Population Script
 * Populates database with realistic Indian sample data for ~30 employees over 2 months
 * 
 * Usage: Run this script from browser or CLI after setting up all tables
 * Warning: This will insert sample data - use only on demo/test databases
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

$conn = createConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Demo Data Population</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #003581; border-bottom: 3px solid #003581; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; }
        .section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #003581; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: white; padding: 15px; border-radius: 4px; border-left: 4px solid #003581; }
        .stat-number { font-size: 24px; font-weight: bold; color: #003581; }
        .stat-label { color: #6c757d; font-size: 14px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🎯 Demo Data Population Script</h1>
        <p>Populating database with realistic Indian sample data for demo purposes...</p>
";

$stats = [
    'employees' => 0,
    'attendance' => 0,
    'clients' => 0,
    'projects' => 0,
    'leads' => 0,
    'contacts' => 0,
    'catalog_items' => 0,
    'quotations' => 0,
    'invoices' => 0,
    'payments' => 0,
    'reimbursements' => 0,
    'payroll_records' => 0,
    'documents' => 0,
    'visitors' => 0,
    'notebook_notes' => 0,
    'assets' => 0,
];

$errors = [];
$user_id = 1; // Default admin user ID

// Start transaction
mysqli_begin_transaction($conn);

try {
    
    // ============================================
    // 1. EMPLOYEES (30 employees)
    // ============================================
    echo "<div class='section'><h3>👥 Populating Employees...</h3>";
    
    $employees_data = [
        ['Rajesh', 'Kumar', 'rajesh.kumar@techvista.in', '9876543210', 'IT', 'Senior Developer', 75000, 'Active'],
        ['Priya', 'Sharma', 'priya.sharma@techvista.in', '9876543211', 'IT', 'Full Stack Developer', 65000, 'Active'],
        ['Amit', 'Patel', 'amit.patel@techvista.in', '9876543212', 'Sales', 'Sales Manager', 80000, 'Active'],
        ['Sneha', 'Reddy', 'sneha.reddy@techvista.in', '9876543213', 'Marketing', 'Marketing Manager', 70000, 'Active'],
        ['Vikram', 'Singh', 'vikram.singh@techvista.in', '9876543214', 'IT', 'DevOps Engineer', 72000, 'Active'],
        ['Ananya', 'Gupta', 'ananya.gupta@techvista.in', '9876543215', 'HR', 'HR Manager', 68000, 'Active'],
        ['Rohan', 'Verma', 'rohan.verma@techvista.in', '9876543216', 'IT', 'Frontend Developer', 58000, 'Active'],
        ['Kavita', 'Desai', 'kavita.desai@techvista.in', '9876543217', 'Finance', 'Accountant', 55000, 'Active'],
        ['Sanjay', 'Menon', 'sanjay.menon@techvista.in', '9876543218', 'Sales', 'Sales Executive', 45000, 'Active'],
        ['Deepika', 'Iyer', 'deepika.iyer@techvista.in', '9876543219', 'IT', 'Backend Developer', 62000, 'Active'],
        ['Arjun', 'Nair', 'arjun.nair@techvista.in', '9876543220', 'IT', 'UI/UX Designer', 55000, 'Active'],
        ['Pooja', 'Joshi', 'pooja.joshi@techvista.in', '9876543221', 'Marketing', 'Content Writer', 42000, 'Active'],
        ['Karthik', 'Rao', 'karthik.rao@techvista.in', '9876543222', 'IT', 'QA Engineer', 52000, 'Active'],
        ['Neha', 'Agarwal', 'neha.agarwal@techvista.in', '9876543223', 'Sales', 'Business Development', 48000, 'Active'],
        ['Manish', 'Pandey', 'manish.pandey@techvista.in', '9876543224', 'IT', 'System Administrator', 60000, 'Active'],
        ['Ritika', 'Chopra', 'ritika.chopra@techvista.in', '9876543225', 'HR', 'HR Executive', 38000, 'Active'],
        ['Aditya', 'Malhotra', 'aditya.malhotra@techvista.in', '9876543226', 'IT', 'Mobile Developer', 68000, 'Active'],
        ['Simran', 'Kaur', 'simran.kaur@techvista.in', '9876543227', 'Marketing', 'SEO Specialist', 45000, 'Active'],
        ['Rahul', 'Bhatia', 'rahul.bhatia@techvista.in', '9876543228', 'Sales', 'Sales Executive', 46000, 'Active'],
        ['Divya', 'Pillai', 'divya.pillai@techvista.in', '9876543229', 'Finance', 'Finance Manager', 85000, 'Active'],
        ['Nikhil', 'Shetty', 'nikhil.shetty@techvista.in', '9876543230', 'IT', 'Data Analyst', 58000, 'Active'],
        ['Ankita', 'Mehta', 'ankita.mehta@techvista.in', '9876543231', 'Marketing', 'Social Media Manager', 48000, 'Active'],
        ['Varun', 'Kohli', 'varun.kohli@techvista.in', '9876543232', 'IT', 'Cloud Architect', 95000, 'Active'],
        ['Ishita', 'Bansal', 'ishita.bansal@techvista.in', '9876543233', 'Sales', 'Account Manager', 52000, 'Active'],
        ['Gaurav', 'Saxena', 'gaurav.saxena@techvista.in', '9876543234', 'IT', 'Product Manager', 90000, 'Active'],
        ['Tanvi', 'Shah', 'tanvi.shah@techvista.in', '9876543235', 'HR', 'Recruiter', 40000, 'Active'],
        ['Harsh', 'Trivedi', 'harsh.trivedi@techvista.in', '9876543236', 'IT', 'Security Analyst', 72000, 'Active'],
        ['Aarti', 'Mishra', 'aarti.mishra@techvista.in', '9876543237', 'Finance', 'Financial Analyst', 62000, 'Active'],
        ['Suresh', 'Raman', 'suresh.raman@techvista.in', '9876543238', 'IT', 'Technical Lead', 88000, 'Active'],
        ['Megha', 'Kulkarni', 'megha.kulkarni@techvista.in', '9876543239', 'Marketing', 'Marketing Executive', 43000, 'Active'],
    ];
    
    $employee_ids = [];
    foreach ($employees_data as $index => $emp) {
        $emp_code = 'EMP' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);
        $doj = date('Y-m-d', strtotime('-' . rand(180, 720) . ' days'));
        
        $stmt = $conn->prepare("INSERT INTO employees (employee_code, first_name, last_name, official_email, mobile_number, 
                                 department, designation, basic_salary, status, date_of_joining, created_by, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssssdssi", $emp_code, $emp[0], $emp[1], $emp[2], $emp[3], $emp[4], $emp[5], $emp[6], $emp[7], $doj, $user_id);
        
        if ($stmt->execute()) {
            $employee_ids[] = $conn->insert_id;
            $stats['employees']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['employees']} employees</div>";
    echo "</div>";
    
    // ============================================
    // 2. ATTENDANCE (2 months for all employees)
    // ============================================
    echo "<div class='section'><h3>📅 Populating Attendance Records...</h3>";
    
    $start_date = strtotime('-60 days');
    $end_date = strtotime('now');
    $statuses = ['Present', 'Present', 'Present', 'Present', 'Absent', 'Half Day', 'Work From Home'];
    
    for ($date = $start_date; $date <= $end_date; $date = strtotime('+1 day', $date)) {
        $day_of_week = date('N', $date);
        if ($day_of_week >= 6) continue; // Skip weekends
        
        $date_str = date('Y-m-d', $date);
        
        foreach ($employee_ids as $emp_id) {
            $status = $statuses[array_rand($statuses)];
            $check_in = date('H:i:s', strtotime('09:' . rand(0, 30) . ':00'));
            $check_out = $status === 'Present' ? date('H:i:s', strtotime('18:' . rand(0, 59) . ':00')) : NULL;
            
            $stmt = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, status, check_in_time, check_out_time, created_by) 
                                     VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssi", $emp_id, $date_str, $status, $check_in, $check_out, $user_id);
            
            if ($stmt->execute()) {
                $stats['attendance']++;
            }
            $stmt->close();
        }
    }
    
    echo "<div class='success'>✅ Inserted {$stats['attendance']} attendance records</div>";
    echo "</div>";
    
    // ============================================
    // 3. CLIENTS (15 clients)
    // ============================================
    echo "<div class='section'><h3>🏢 Populating Clients...</h3>";
    
    $clients_data = [
        ['Infosys Limited', 'infosys@infosys.com', '080-28520261', 'Bengaluru', 'Active', 'Rajiv Mehta', 'Technology'],
        ['TCS Pvt Ltd', 'contact@tcs.com', '022-67780000', 'Mumbai', 'Active', 'Sunita Desai', 'IT Services'],
        ['Wipro Technologies', 'info@wipro.com', '080-28440011', 'Bengaluru', 'Active', 'Arun Kumar', 'Technology'],
        ['Reliance Industries', 'contact@ril.com', '022-35555000', 'Mumbai', 'Active', 'Priya Sharma', 'Conglomerate'],
        ['HDFC Bank', 'info@hdfcbank.com', '022-66521000', 'Mumbai', 'Active', 'Amit Patel', 'Banking'],
        ['Flipkart India', 'support@flipkart.com', '080-33885656', 'Bengaluru', 'Active', 'Vikram Singh', 'E-commerce'],
        ['Zomato Ltd', 'business@zomato.com', '0124-4616065', 'Gurugram', 'Active', 'Sneha Reddy', 'Food Tech'],
        ['Paytm Payments', 'business@paytm.com', '0120-4880880', 'Noida', 'Active', 'Rohan Verma', 'Fintech'],
        ['Ola Cabs', 'support@olacabs.com', '080-68727272', 'Bengaluru', 'Active', 'Kavita Desai', 'Transportation'],
        ['BigBasket', 'care@bigbasket.com', '080-67786666', 'Bengaluru', 'Active', 'Sanjay Menon', 'E-commerce'],
        ['Swiggy Food', 'support@swiggy.in', '080-68179999', 'Bengaluru', 'Active', 'Deepika Iyer', 'Food Delivery'],
        ['ICICI Bank', 'info@icicibank.com', '022-26531414', 'Mumbai', 'Active', 'Arjun Nair', 'Banking'],
        ['Tech Mahindra', 'info@techmahindra.com', '020-66015000', 'Pune', 'Active', 'Pooja Joshi', 'IT Services'],
        ['HCL Technologies', 'contact@hcl.com', '0120-4528000', 'Noida', 'Active', 'Karthik Rao', 'Technology'],
        ['Bharti Airtel', 'care@airtel.in', '011-46663344', 'New Delhi', 'Active', 'Neha Agarwal', 'Telecom'],
    ];
    
    $client_ids = [];
    foreach ($clients_data as $client) {
        $stmt = $conn->prepare("INSERT INTO clients (name, email, phone, address, status, contact_person, industry, created_by, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssssi", $client[0], $client[1], $client[2], $client[3], $client[4], $client[5], $client[6], $user_id);
        
        if ($stmt->execute()) {
            $client_ids[] = $conn->insert_id;
            $stats['clients']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['clients']} clients</div>";
    echo "</div>";
    
    // ============================================
    // 4. CONTACTS (20 contacts)
    // ============================================
    echo "<div class='section'><h3>📞 Populating Contacts...</h3>";
    
    $contacts_data = [
        ['Ravi Kumar', 'ravi.kumar@infosys.com', '9988776655', 'CTO', 'Technology'],
        ['Meera Nair', 'meera.nair@tcs.com', '9988776656', 'Project Manager', 'IT Services'],
        ['Sunil Joshi', 'sunil.joshi@wipro.com', '9988776657', 'VP Engineering', 'Technology'],
        ['Anita Deshmukh', 'anita.d@ril.com', '9988776658', 'Business Head', 'Business'],
        ['Prakash Reddy', 'prakash.reddy@hdfcbank.com', '9988776659', 'Branch Manager', 'Banking'],
        ['Lakshmi Iyer', 'lakshmi.iyer@flipkart.com', '9988776660', 'Category Head', 'E-commerce'],
        ['Vijay Malhotra', 'vijay.m@zomato.com', '9988776661', 'Regional Manager', 'Food Tech'],
        ['Seema Kapoor', 'seema.kapoor@paytm.com', '9988776662', 'Partnership Manager', 'Fintech'],
        ['Ajay Sharma', 'ajay.sharma@olacabs.com', '9988776663', 'Operations Head', 'Transportation'],
        ['Nisha Patel', 'nisha.patel@bigbasket.com', '9988776664', 'Supply Chain Head', 'E-commerce'],
        ['Ramesh Gupta', 'ramesh.gupta@swiggy.in', '9988776665', 'City Head', 'Food Delivery'],
        ['Divya Rao', 'divya.rao@icicibank.com', '9988776666', 'Relationship Manager', 'Banking'],
        ['Sandeep Verma', 'sandeep.v@techmahindra.com', '9988776667', 'Delivery Manager', 'IT Services'],
        ['Priyanka Singh', 'priyanka.singh@hcl.com', '9988776668', 'Solution Architect', 'Technology'],
        ['Abhishek Mishra', 'abhishek.m@airtel.in', '9988776669', 'Enterprise Sales', 'Telecom'],
        ['Rekha Pillai', 'rekha.pillai@infosys.com', '9988776670', 'HR Head', 'HR'],
        ['Manoj Kulkarni', 'manoj.k@tcs.com', '9988776671', 'Quality Manager', 'IT Services'],
        ['Swati Bansal', 'swati.bansal@wipro.com', '9988776672', 'Marketing Head', 'Technology'],
        ['Avinash Reddy', 'avinash.reddy@ril.com', '9988776673', 'Finance Manager', 'Finance'],
        ['Sunita Sharma', 'sunita.sharma@hdfcbank.com', '9988776674', 'Credit Manager', 'Banking'],
    ];
    
    foreach ($contacts_data as $contact) {
        $stmt = $conn->prepare("INSERT INTO contacts (name, email, phone, designation, category, created_by, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssi", $contact[0], $contact[1], $contact[2], $contact[3], $contact[4], $user_id);
        
        if ($stmt->execute()) {
            $stats['contacts']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['contacts']} contacts</div>";
    echo "</div>";
    
    // ============================================
    // 5. CRM LEADS (25 leads)
    // ============================================
    echo "<div class='section'><h3>🎯 Populating CRM Leads...</h3>";
    
    $lead_sources = ['Website', 'Referral', 'Cold Call', 'Social Media', 'Trade Show', 'Email Campaign'];
    $lead_statuses = ['New', 'Contacted', 'Qualified', 'Proposal Sent', 'Negotiation', 'Closed Won', 'Closed Lost'];
    
    $companies = [
        'Tech Solutions India', 'Digital Innovations Pvt Ltd', 'Cloud Services India', 'Mobile Apps Co',
        'E-commerce Solutions', 'Fintech Startups', 'Healthcare IT', 'Edutech Platform',
        'Logistics Tech', 'Real Estate Portal', 'Travel Booking System', 'Food Delivery Startup',
        'Gaming Studio', 'AI Research Lab', 'IoT Solutions', 'Blockchain Ventures',
        'SaaS Products India', 'Digital Marketing Agency', 'Web Development Co', 'Consulting Firm India',
        'Manufacturing ERP', 'Retail Management Systems', 'HR Tech Solutions', 'Supply Chain Tech',
        'Analytics Platform'
    ];
    
    foreach ($companies as $index => $company) {
        $lead_name = 'Lead Contact ' . ($index + 1);
        $email = strtolower(str_replace(' ', '', $lead_name)) . '@' . strtolower(str_replace(' ', '', $company)) . '.com';
        $phone = '98' . rand(10000000, 99999999);
        $source = $lead_sources[array_rand($lead_sources)];
        $status = $lead_statuses[array_rand($lead_statuses)];
        $value = rand(100000, 5000000);
        $assigned_to = $employee_ids[array_rand($employee_ids)];
        
        $stmt = $conn->prepare("INSERT INTO crm_leads (lead_name, company_name, email, phone, source, status, 
                                 estimated_value, assigned_to, created_by, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssiii", $lead_name, $company, $email, $phone, $source, $status, $value, $assigned_to, $user_id);
        
        if ($stmt->execute()) {
            $stats['leads']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['leads']} CRM leads</div>";
    echo "</div>";
    
    // ============================================
    // 6. PROJECTS (10 projects)
    // ============================================
    echo "<div class='section'><h3>📊 Populating Projects...</h3>";
    
    $projects_data = [
        ['E-commerce Platform Development', 'PRJ-2024-001', 'In Progress', '2024-09-01', '2025-01-31', 2500000],
        ['Mobile App Development', 'PRJ-2024-002', 'In Progress', '2024-09-15', '2024-12-31', 1800000],
        ['CRM System Integration', 'PRJ-2024-003', 'Completed', '2024-07-01', '2024-10-31', 1200000],
        ['Website Redesign Project', 'PRJ-2024-004', 'In Progress', '2024-10-01', '2025-02-28', 800000],
        ['Cloud Migration Services', 'PRJ-2024-005', 'Planning', '2024-11-01', '2025-03-31', 3000000],
        ['ERP Implementation', 'PRJ-2024-006', 'In Progress', '2024-08-01', '2025-01-31', 4500000],
        ['Digital Marketing Campaign', 'PRJ-2024-007', 'Completed', '2024-08-15', '2024-10-15', 500000],
        ['Data Analytics Platform', 'PRJ-2024-008', 'In Progress', '2024-09-20', '2024-12-31', 2200000],
        ['Security Audit Project', 'PRJ-2024-009', 'Planning', '2024-11-15', '2025-01-15', 600000],
        ['API Development Services', 'PRJ-2024-010', 'In Progress', '2024-10-01', '2025-01-31', 1500000],
    ];
    
    $project_ids = [];
    foreach ($projects_data as $project) {
        $client_id = $client_ids[array_rand($client_ids)];
        $manager_id = $employee_ids[array_rand($employee_ids)];
        
        $stmt = $conn->prepare("INSERT INTO projects (title, project_code, status, start_date, end_date, 
                                 budget, client_id, manager_id, created_by, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssdiiii", $project[0], $project[1], $project[2], $project[3], $project[4], $project[5], $client_id, $manager_id, $user_id);
        
        if ($stmt->execute()) {
            $project_ids[] = $conn->insert_id;
            $stats['projects']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['projects']} projects</div>";
    echo "</div>";
    
    // ============================================
    // 7. CATALOG ITEMS (30 items)
    // ============================================
    echo "<div class='section'><h3>📦 Populating Catalog Items...</h3>";
    
    $catalog_items = [
        ['Web Development Service', 'WEB-DEV-001', 'Services', 50000, 100],
        ['Mobile App Development', 'APP-DEV-001', 'Services', 75000, 100],
        ['UI/UX Design Service', 'DESIGN-001', 'Services', 30000, 100],
        ['Cloud Hosting Package', 'CLOUD-001', 'Services', 15000, 100],
        ['SEO Optimization', 'SEO-001', 'Services', 25000, 100],
        ['Digital Marketing', 'DM-001', 'Services', 40000, 100],
        ['Software License - Basic', 'LIC-BASIC-001', 'Products', 5000, 50],
        ['Software License - Pro', 'LIC-PRO-001', 'Products', 12000, 50],
        ['Software License - Enterprise', 'LIC-ENT-001', 'Products', 25000, 20],
        ['Technical Support - Monthly', 'SUP-MON-001', 'Services', 8000, 100],
        ['Technical Support - Annual', 'SUP-ANN-001', 'Services', 80000, 100],
        ['Database Management', 'DB-MGT-001', 'Services', 35000, 100],
        ['API Integration Service', 'API-INT-001', 'Services', 45000, 100],
        ['Security Audit Service', 'SEC-AUD-001', 'Services', 60000, 100],
        ['Training Program - Basic', 'TRN-BAS-001', 'Services', 20000, 100],
        ['Training Program - Advanced', 'TRN-ADV-001', 'Services', 35000, 100],
        ['Consulting Hours', 'CONS-HRS-001', 'Services', 5000, 500],
        ['Project Management', 'PM-001', 'Services', 55000, 100],
        ['Quality Assurance', 'QA-001', 'Services', 30000, 100],
        ['DevOps Services', 'DEVOPS-001', 'Services', 65000, 100],
        ['Content Writing', 'CONT-WRT-001', 'Services', 15000, 100],
        ['Video Production', 'VID-PRD-001', 'Services', 50000, 100],
        ['Graphic Design', 'GFX-001', 'Services', 20000, 100],
        ['Social Media Management', 'SMM-001', 'Services', 30000, 100],
        ['Email Marketing', 'EMAIL-001', 'Services', 18000, 100],
        ['Server Setup', 'SRV-SET-001', 'Services', 40000, 100],
        ['Data Migration', 'DATA-MIG-001', 'Services', 55000, 100],
        ['Backup Services', 'BACKUP-001', 'Services', 12000, 100],
        ['Maintenance Contract', 'MAINT-001', 'Services', 75000, 100],
        ['Custom Development', 'CUST-DEV-001', 'Services', 100000, 100],
    ];
    
    foreach ($catalog_items as $item) {
        $stmt = $conn->prepare("INSERT INTO items_master (name, sku, category, base_price, current_stock, 
                                 status, created_by, created_at) 
                                 VALUES (?, ?, ?, ?, ?, 'Active', ?, NOW())");
        $stmt->bind_param("sssdii", $item[0], $item[1], $item[2], $item[3], $item[4], $user_id);
        
        if ($stmt->execute()) {
            $stats['catalog_items']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['catalog_items']} catalog items</div>";
    echo "</div>";
    
    // ============================================
    // 8. QUOTATIONS (20 quotations)
    // ============================================
    echo "<div class='section'><h3>📋 Populating Quotations...</h3>";
    
    $quotation_statuses = ['Draft', 'Sent', 'Accepted', 'Rejected'];
    
    for ($i = 1; $i <= 20; $i++) {
        $quotation_no = 'QT-2024-' . str_pad($i, 4, '0', STR_PAD_LEFT);
        $title = 'Quotation for ' . $projects_data[array_rand($projects_data)][0];
        $client_id = $client_ids[array_rand($client_ids)];
        $status = $quotation_statuses[array_rand($quotation_statuses)];
        $date = date('Y-m-d', strtotime('-' . rand(1, 60) . ' days'));
        $valid_until = date('Y-m-d', strtotime($date . ' +30 days'));
        $total = rand(50000, 500000);
        
        $stmt = $conn->prepare("INSERT INTO quotations (quotation_no, title, client_id, quotation_date, 
                                 valid_until, total_amount, status, created_by, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssissdsi", $quotation_no, $title, $client_id, $date, $valid_until, $total, $status, $user_id);
        
        if ($stmt->execute()) {
            $stats['quotations']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['quotations']} quotations</div>";
    echo "</div>";
    
    // ============================================
    // 9. INVOICES (25 invoices)
    // ============================================
    echo "<div class='section'><h3>🧾 Populating Invoices...</h3>";
    
    $invoice_statuses = ['Draft', 'Sent', 'Paid', 'Overdue', 'Cancelled'];
    
    $invoice_ids = [];
    for ($i = 1; $i <= 25; $i++) {
        $invoice_no = 'INV-2024-' . str_pad($i, 4, '0', STR_PAD_LEFT);
        $client_id = $client_ids[array_rand($client_ids)];
        $status = $invoice_statuses[array_rand($invoice_statuses)];
        $issue_date = date('Y-m-d', strtotime('-' . rand(1, 60) . ' days'));
        $due_date = date('Y-m-d', strtotime($issue_date . ' +30 days'));
        $total = rand(50000, 1000000);
        
        $stmt = $conn->prepare("INSERT INTO invoices (invoice_no, client_id, issue_date, due_date, 
                                 total_amount, status, created_by, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sissdsi", $invoice_no, $client_id, $issue_date, $due_date, $total, $status, $user_id);
        
        if ($stmt->execute()) {
            $invoice_ids[] = $conn->insert_id;
            $stats['invoices']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['invoices']} invoices</div>";
    echo "</div>";
    
    // ============================================
    // 10. PAYMENTS (20 payments)
    // ============================================
    echo "<div class='section'><h3>💳 Populating Payments...</h3>";
    
    $payment_modes = ['Bank Transfer', 'Cheque', 'UPI', 'NEFT', 'RTGS', 'Cash'];
    
    foreach ($invoice_ids as $index => $invoice_id) {
        if ($index >= 20) break; // Only 20 payments
        
        $client_id = $client_ids[array_rand($client_ids)];
        $payment_date = date('Y-m-d', strtotime('-' . rand(1, 50) . ' days'));
        $amount = rand(50000, 800000);
        $payment_mode = $payment_modes[array_rand($payment_modes)];
        $reference_no = 'PAY-' . date('Ymd', strtotime($payment_date)) . '-' . rand(1000, 9999);
        
        $stmt = $conn->prepare("INSERT INTO payments (client_id, payment_date, amount_received, 
                                 payment_mode, reference_no, created_by, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isdssi", $client_id, $payment_date, $amount, $payment_mode, $reference_no, $user_id);
        
        if ($stmt->execute()) {
            $stats['payments']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['payments']} payments</div>";
    echo "</div>";
    
    // ============================================
    // 11. REIMBURSEMENTS (40 reimbursements)
    // ============================================
    echo "<div class='section'><h3>💸 Populating Reimbursements...</h3>";
    
    $reimb_categories = ['Travel', 'Food', 'Accommodation', 'Fuel', 'Office Supplies', 'Client Entertainment'];
    $reimb_statuses = ['Pending', 'Approved', 'Rejected', 'Paid'];
    
    for ($i = 0; $i < 40; $i++) {
        $employee_id = $employee_ids[array_rand($employee_ids)];
        $expense_date = date('Y-m-d', strtotime('-' . rand(1, 60) . ' days'));
        $category = $reimb_categories[array_rand($reimb_categories)];
        $amount = rand(500, 15000);
        $description = 'Reimbursement for ' . $category . ' expenses';
        $status = $reimb_statuses[array_rand($reimb_statuses)];
        
        $stmt = $conn->prepare("INSERT INTO reimbursements (employee_id, expense_date, category, amount, 
                                 description, status, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issdss", $employee_id, $expense_date, $category, $amount, $description, $status);
        
        if ($stmt->execute()) {
            $stats['reimbursements']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['reimbursements']} reimbursements</div>";
    echo "</div>";
    
    // ============================================
    // 12. PAYROLL (2 months for all employees)
    // ============================================
    echo "<div class='section'><h3>💵 Populating Payroll Records...</h3>";
    
    $months = [
        date('Y-m', strtotime('-2 months')),
        date('Y-m', strtotime('-1 month')),
    ];
    
    foreach ($months as $month) {
        // Create payroll master
        $stmt = $conn->prepare("INSERT INTO payroll_master (month, total_employees, total_amount, status, 
                                 created_by, created_at) 
                                 VALUES (?, ?, 0, 'Paid', ?, NOW())");
        $total_employees = count($employee_ids);
        $stmt->bind_param("sii", $month, $total_employees, $user_id);
        $stmt->execute();
        $payroll_id = $conn->insert_id;
        $stmt->close();
        
        $month_total = 0;
        
        // Create payroll records for each employee
        foreach ($employee_ids as $emp_id) {
            // Get employee salary
            $result = $conn->query("SELECT basic_salary FROM employees WHERE id = $emp_id");
            $emp = $result->fetch_assoc();
            $base_salary = $emp['basic_salary'];
            
            $allowances = $base_salary * 0.30; // 30% allowances
            $deductions = $base_salary * 0.12; // 12% deductions (PF, etc.)
            $net_pay = $base_salary + $allowances - $deductions;
            $month_total += $net_pay;
            
            $stmt = $conn->prepare("INSERT INTO payroll_records (payroll_id, employee_id, base_salary, 
                                     attendance_days, total_days, allowances, deductions, net_pay, created_at) 
                                     VALUES (?, ?, ?, 22, 22, ?, ?, ?, NOW())");
            $stmt->bind_param("iidddd", $payroll_id, $emp_id, $base_salary, $allowances, $deductions, $net_pay);
            
            if ($stmt->execute()) {
                $stats['payroll_records']++;
            }
            $stmt->close();
        }
        
        // Update payroll master total
        $conn->query("UPDATE payroll_master SET total_amount = $month_total WHERE id = $payroll_id");
    }
    
    echo "<div class='success'>✅ Inserted {$stats['payroll_records']} payroll records</div>";
    echo "</div>";
    
    // ============================================
    // 13. DOCUMENTS (15 documents)
    // ============================================
    echo "<div class='section'><h3>📄 Populating Documents...</h3>";
    
    $doc_types = ['Contract', 'Invoice', 'Report', 'Proposal', 'Agreement', 'Policy'];
    
    for ($i = 1; $i <= 15; $i++) {
        $title = 'Document ' . $i . ' - ' . $doc_types[array_rand($doc_types)];
        $doc_type = $doc_types[array_rand($doc_types)];
        $file_path = '/uploads/documents/sample_doc_' . $i . '.pdf';
        $uploaded_by = $employee_ids[array_rand($employee_ids)];
        $tags = implode(',', array_slice($doc_types, 0, rand(2, 4)));
        
        $stmt = $conn->prepare("INSERT INTO documents (title, doc_type, file_path, uploaded_by, tags, created_at) 
                                 VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssis", $title, $doc_type, $file_path, $uploaded_by, $tags);
        
        if ($stmt->execute()) {
            $stats['documents']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['documents']} documents</div>";
    echo "</div>";
    
    // ============================================
    // 14. VISITOR LOGS (30 visitors)
    // ============================================
    echo "<div class='section'><h3>👤 Populating Visitor Logs...</h3>";
    
    $purposes = ['Meeting', 'Interview', 'Delivery', 'Inspection', 'Consultation', 'Training'];
    
    for ($i = 0; $i < 30; $i++) {
        $visitor_name = 'Visitor ' . ($i + 1);
        $phone = '98' . rand(10000000, 99999999);
        $purpose = $purposes[array_rand($purposes)];
        $employee_id = $employee_ids[array_rand($employee_ids)];
        $check_in = date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days ' . rand(9, 17) . ':' . rand(0, 59) . ':00'));
        $check_out = date('Y-m-d H:i:s', strtotime($check_in . ' +' . rand(1, 4) . ' hours'));
        
        $stmt = $conn->prepare("INSERT INTO visitor_logs (visitor_name, phone, purpose, employee_id, 
                                 check_in_time, check_out_time, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssiss", $visitor_name, $phone, $purpose, $employee_id, $check_in, $check_out);
        
        if ($stmt->execute()) {
            $stats['visitors']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['visitors']} visitor logs</div>";
    echo "</div>";
    
    // ============================================
    // 15. NOTEBOOK NOTES (20 notes)
    // ============================================
    echo "<div class='section'><h3>📓 Populating Notebook Notes...</h3>";
    
    $note_titles = [
        'Project Meeting Notes', 'Client Requirements', 'Technical Specifications',
        'Design Guidelines', 'Development Roadmap', 'Testing Strategy',
        'Deployment Plan', 'Performance Optimization', 'Security Checklist',
        'Code Review Notes', 'Sprint Planning', 'Retrospective Notes',
        'Budget Analysis', 'Resource Allocation', 'Risk Assessment',
        'Quality Metrics', 'Team Feedback', 'Process Improvements',
        'Knowledge Base Article', 'Training Material'
    ];
    
    foreach ($note_titles as $title) {
        $content = 'This is sample content for ' . $title . '. Contains detailed information and insights.';
        $created_by = $employee_ids[array_rand($employee_ids)];
        $tags = 'project,meeting,notes,documentation';
        $share_scope = ['Private', 'Team', 'Organization'][array_rand(['Private', 'Team', 'Organization'])];
        
        $stmt = $conn->prepare("INSERT INTO notebook_notes (title, content, created_by, tags, share_scope, created_at) 
                                 VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssiss", $title, $content, $created_by, $tags, $share_scope);
        
        if ($stmt->execute()) {
            $stats['notebook_notes']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['notebook_notes']} notebook notes</div>";
    echo "</div>";
    
    // ============================================
    // 16. ASSETS (20 assets)
    // ============================================
    echo "<div class='section'><h3>🧰 Populating Assets...</h3>";
    
    $assets_data = [
        ['Dell Laptop - Latitude 5520', 'ASSET-LAP-001', 'IT', 'Laptop', 'Dell', 'Latitude 5520', 'Available'],
        ['HP Laptop - EliteBook 840', 'ASSET-LAP-002', 'IT', 'Laptop', 'HP', 'EliteBook 840', 'In Use'],
        ['Lenovo ThinkPad T14', 'ASSET-LAP-003', 'IT', 'Laptop', 'Lenovo', 'ThinkPad T14', 'Available'],
        ['MacBook Pro 14"', 'ASSET-LAP-004', 'IT', 'Laptop', 'Apple', 'MacBook Pro', 'In Use'],
        ['Dell Monitor 27"', 'ASSET-MON-001', 'IT', 'Monitor', 'Dell', 'U2720Q', 'Available'],
        ['LG Monitor 24"', 'ASSET-MON-002', 'IT', 'Monitor', 'LG', '24UD58', 'In Use'],
        ['Office Desk - Executive', 'ASSET-FURN-001', 'Furniture', 'Desk', 'Godrej', 'Executive Desk', 'Available'],
        ['Office Chair - Ergonomic', 'ASSET-FURN-002', 'Furniture', 'Chair', 'Featherlite', 'Ergo Pro', 'In Use'],
        ['Conference Table', 'ASSET-FURN-003', 'Furniture', 'Table', 'Durian', 'Conference 10-Seater', 'Available'],
        ['Projector - Epson', 'ASSET-PROJ-001', 'IT', 'Projector', 'Epson', 'EB-X41', 'Available'],
        ['Printer - HP LaserJet', 'ASSET-PRINT-001', 'IT', 'Printer', 'HP', 'LaserJet Pro M404dn', 'In Use'],
        ['Scanner - Canon', 'ASSET-SCAN-001', 'IT', 'Scanner', 'Canon', 'DR-M260', 'Available'],
        ['UPS - APC 1KVA', 'ASSET-UPS-001', 'IT', 'UPS', 'APC', 'BR1000G-IN', 'In Use'],
        ['Server - Dell PowerEdge', 'ASSET-SRV-001', 'IT', 'Server', 'Dell', 'PowerEdge R740', 'In Use'],
        ['Network Switch - Cisco', 'ASSET-NET-001', 'IT', 'Switch', 'Cisco', 'SG350-28', 'In Use'],
        ['Whiteboard - Magnetic', 'ASSET-OFF-001', 'Furniture', 'Whiteboard', 'Generic', '6x4 ft', 'Available'],
        ['Air Conditioner - 2 Ton', 'ASSET-AC-001', 'Other', 'AC', 'Voltas', '2 Ton Split', 'In Use'],
        ['Water Dispenser', 'ASSET-DISP-001', 'Other', 'Dispenser', 'Kent', 'Hot & Cold', 'In Use'],
        ['Coffee Machine', 'ASSET-COFFEE-001', 'Other', 'Coffee Maker', 'Nespresso', 'Lattissima Pro', 'Available'],
        ['Security Camera', 'ASSET-CAM-001', 'IT', 'Camera', 'Hikvision', 'DS-2CD2143G0-I', 'In Use'],
    ];
    
    foreach ($assets_data as $asset) {
        $purchase_date = date('Y-m-d', strtotime('-' . rand(180, 1095) . ' days'));
        $purchase_cost = rand(10000, 150000);
        
        $stmt = $conn->prepare("INSERT INTO assets_master (asset_code, name, category, type, make, model, 
                                 status, purchase_date, purchase_cost, created_by, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssssdi", $asset[1], $asset[0], $asset[2], $asset[3], $asset[4], $asset[5], 
                          $asset[6], $purchase_date, $purchase_cost, $user_id);
        
        if ($stmt->execute()) {
            $stats['assets']++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>✅ Inserted {$stats['assets']} assets</div>";
    echo "</div>";
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo "<div class='section'><h3>📊 Summary Statistics</h3>";
    echo "<div class='stats'>";
    foreach ($stats as $key => $value) {
        $label = ucwords(str_replace('_', ' ', $key));
        echo "<div class='stat-card'>
                <div class='stat-number'>$value</div>
                <div class='stat-label'>$label</div>
              </div>";
    }
    echo "</div></div>";
    
    echo "<div class='success' style='margin-top: 20px;'>
            <h3>✅ Demo Data Population Completed Successfully!</h3>
            <p>The database has been populated with realistic Indian sample data representing 2 months of operations with 30 employees.</p>
            <p>You can now use the system for demo purposes with fully functional data across all modules.</p>
          </div>";
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "<div class='error'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
    echo "<div class='error'>Transaction has been rolled back. No data was inserted.</div>";
}

closeConnection($conn);

echo "<div style='margin-top: 30px; text-align: center;'>
        <a href='../public/index.php' style='display: inline-block; padding: 12px 24px; background: #003581; color: white; text-decoration: none; border-radius: 4px;'>
            Go to Dashboard
        </a>
      </div>";

echo "</div></body></html>";
?>
