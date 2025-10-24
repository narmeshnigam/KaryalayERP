<?php
/**
 * Header Include File with Sidebar Support
 * 
 * Contains common HTML head elements
 * Used across all pages for consistent UI
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include config for APP_NAME
require_once __DIR__ . '/../config/config.php';

// Get page title from variable or use default
$page_title = isset($page_title) ? $page_title : APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <style>
        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
            min-height: 100vh;
            color: #1b2a57;
        }
        
        /* Main Content Container */
        .main-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Container Styles */
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }
        
        .container-large {
            max-width: 100%;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #003581;
            box-shadow: 0 0 0 3px rgba(0, 53, 129, 0.1);
        }
        
        /* Button Styles */
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #003581;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #004aad;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 53, 129, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-accent {
            background: #faa718;
            color: white;
        }
        
        .btn-accent:hover {
            background: #ffc04d;
        }
        
        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #003581;
            margin-bottom: 15px;
        }
        
        /* Text Styles */
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #6c757d;
        }
        
        .mt-20 {
            margin-top: 20px;
        }
        
        .mb-20 {
            margin-bottom: 20px;
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 20px 30px;
            margin: -30px -30px 30px -30px;
            border-bottom: 2px solid #e9ecef;
            border-radius: 0;
        }

        .page-header h1 {
            color: #003581;
            font-size: 28px;
            margin: 0;
        }

        .page-header p {
            color: #6c757d;
            margin: 5px 0 0 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
