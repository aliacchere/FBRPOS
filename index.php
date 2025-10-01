<?php
/**
 * DPS POS FBR Integrated - Main Entry Point
 * A Premier Multi-Tenant SaaS POS Platform for Pakistani Businesses
 */

// Check if installation is required
if (!file_exists('config/database.php')) {
    header('Location: install/');
    exit;
}

// Start session
session_start();

// Include configuration
require_once 'config/database.php';
require_once 'config/app.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Route based on user role
$user_role = $_SESSION['user_role'];
$tenant_id = $_SESSION['tenant_id'];

switch ($user_role) {
    case 'super_admin':
        header('Location: admin/dashboard.php');
        break;
    case 'tenant_admin':
        header('Location: tenant/dashboard.php');
        break;
    case 'cashier':
        header('Location: pos/sales.php');
        break;
    default:
        header('Location: login.php');
        break;
}
?>