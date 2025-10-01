<?php
/**
 * DPS POS FBR Integrated - FBR Integration Hub
 * Tenant Admin interface for FBR configuration and management
 */

session_start();
require_once '../config/database.php';
require_once '../config/app.php';
require_once '../includes/functions.php';
require_once '../includes/fbr_engine.php';

// Check authentication and role
require_login();
require_role(['tenant_admin']);

// Get tenant information
$tenant = get_current_tenant();
if (!$tenant) {
    header('Location: ../login.php?error=Tenant not found');
    exit;
}

// Get FBR configuration
$fbr_config = get_fbr_config($tenant['id']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_config':
                $bearer_token = sanitize_input($_POST['bearer_token']);
                $sandbox_mode = isset($_POST['sandbox_mode']) ? 1 : 0;
                
                if (empty($bearer_token)) {
                    $error = 'Bearer token is required';
                } else {
                    if ($fbr_config) {
                        // Update existing config
                        db_update('fbr_config',
                            [
                                'bearer_token' => $bearer_token,
                                'sandbox_mode' => $sandbox_mode,
                                'updated_at' => date('Y-m-d H:i:s')
                            ],
                            'tenant_id = ?',
                            [$tenant['id']]
                        );
                    } else {
                        // Create new config
                        db_insert('fbr_config', [
                            'tenant_id' => $tenant['id'],
                            'bearer_token' => $bearer_token,
                            'sandbox_mode' => $sandbox_mode,
                            'is_active' => 1,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                    
                    $success = 'FBR configuration saved successfully';
                    $fbr_config = get_fbr_config($tenant['id']); // Refresh config
                }
                break;
                
            case 'test_connection':
                if (!$fbr_config) {
                    $error = 'Please configure FBR settings first';
                } else {
                    $fbr_engine = new FBRIntegrationEngine($tenant['id']);
                    $test_result = $fbr_engine->getReferenceData('provinces');
                    
                    if ($test_result['success']) {
                        $success = 'FBR connection test successful!';
                    } else {
                        $error = 'FBR connection test failed: ' . $test_result['error'];
                    }
                }
                break;
                
            case 'sync_reference_data':
                if (!$fbr_config) {
                    $error = 'Please configure FBR settings first';
                } else {
                    $fbr_engine = new FBRIntegrationEngine($tenant['id']);
                    
                    // Sync provinces
                    $provinces_result = $fbr_engine->getReferenceData('provinces');
                    if ($provinces_result['success'] && isset($provinces_result['data']['data'])) {
                        foreach ($provinces_result['data']['data'] as $province) {
                            db_insert('provinces', [
                                'name' => $province['name'],
                                'code' => $province['code'],
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    }
                    
                    // Sync units of measure
                    $uom_result = $fbr_engine->getReferenceData('uom');
                    if ($uom_result['success'] && isset($uom_result['data']['data'])) {
                        foreach ($uom_result['data']['data'] as $uom) {
                            db_insert('units_of_measure', [
                                'name' => $uom['name'],
                                'code' => $uom['code'],
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    }
                    
                    $success = 'Reference data synced successfully';
                }
                break;
        }
    }
}

// Get FBR sync statistics
$fbr_stats = db_fetch("
    SELECT 
        COUNT(*) as total_sales,
        SUM(CASE WHEN fbr_status = 'synced' THEN 1 ELSE 0 END) as synced_sales,
        SUM(CASE WHEN fbr_status = 'pending' THEN 1 ELSE 0 END) as pending_sales,
        SUM(CASE WHEN fbr_status = 'failed' THEN 1 ELSE 0 END) as failed_sales
    FROM sales 
    WHERE tenant_id = ?
", [$tenant['id']]);

$sync_rate = $fbr_stats['total_sales'] > 0 ? 
    round(($fbr_stats['synced_sales'] / $fbr_stats['total_sales']) * 100, 1) : 0;

// Get recent FBR activities
$recent_activities = db_fetch_all("
    SELECT s.*, u.name as cashier_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.tenant_id = ?
    ORDER BY s.created_at DESC
    LIMIT 10
", [$tenant['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FBR Integration Hub - <?php echo htmlspecialchars($tenant['business_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .fade-in {
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white bg-opacity-10 backdrop-blur-md border-b border-white border-opacity-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-cash-register text-2xl text-white mr-3"></i>
                        <span class="text-xl font-bold text-white"><?php echo htmlspecialchars($tenant['business_name']); ?></span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                    </a>
                    <a href="pos/sales.php" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-shopping-cart mr-1"></i>POS
                    </a>
                    <span class="text-white"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../auth/logout.php" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">FBR Integration Hub</h1>
            <p class="text-white opacity-90">Manage your FBR Digital Invoicing integration</p>
        </div>

        <!-- Alerts -->
        <?php if (isset($success)): ?>
        <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="bg-red-500 bg-opacity-20 border border-red-500 text-red-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- FBR Status Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-sync text-3xl text-blue-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Sync Rate</p>
                        <p class="text-2xl font-bold text-white"><?php echo $sync_rate; ?>%</p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-3xl text-green-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Synced Sales</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($fbr_stats['synced_sales']); ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-3xl text-yellow-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Pending</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($fbr_stats['pending_sales']); ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-3xl text-red-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Failed</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($fbr_stats['failed_sales']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- FBR Configuration -->
            <div class="glass-effect rounded-xl p-6">
                <h3 class="text-xl font-bold text-white mb-6">FBR Configuration</h3>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="save_config">
                    
                    <div>
                        <label class="block text-white font-medium mb-2">
                            <i class="fas fa-key mr-2"></i>FBR Bearer Token
                        </label>
                        <input type="text" name="bearer_token" 
                               value="<?php echo $fbr_config ? htmlspecialchars($fbr_config['bearer_token']) : ''; ?>"
                               class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500"
                               placeholder="Enter your FBR Bearer Token" required>
                        <p class="text-white opacity-75 text-sm mt-2">
                            Get your Bearer Token from the FBR Digital Invoicing portal
                        </p>
                    </div>

                    <div>
                        <label class="flex items-center text-white">
                            <input type="checkbox" name="sandbox_mode" 
                                   <?php echo $fbr_config && $fbr_config['sandbox_mode'] ? 'checked' : ''; ?>
                                   class="mr-3 rounded">
                            <span>Sandbox Mode (for testing)</span>
                        </label>
                        <p class="text-white opacity-75 text-sm mt-2">
                            Enable this for testing. Disable for live production.
                        </p>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" 
                                class="flex-1 bg-white text-indigo-600 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-all">
                            <i class="fas fa-save mr-2"></i>Save Configuration
                        </button>
                    </div>
                </form>

                <div class="mt-6 pt-6 border-t border-white border-opacity-20">
                    <h4 class="text-white font-semibold mb-4">Quick Actions</h4>
                    <div class="space-y-3">
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="test_connection">
                            <button type="submit" 
                                    class="w-full bg-blue-500 text-white py-2 rounded-lg font-semibold hover:bg-blue-600 transition-all">
                                <i class="fas fa-plug mr-2"></i>Test FBR Connection
                            </button>
                        </form>
                        
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="sync_reference_data">
                            <button type="submit" 
                                    class="w-full bg-green-500 text-white py-2 rounded-lg font-semibold hover:bg-green-600 transition-all">
                                <i class="fas fa-sync mr-2"></i>Sync Reference Data
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- FBR Status & Information -->
            <div class="glass-effect rounded-xl p-6">
                <h3 class="text-xl font-bold text-white mb-6">FBR Status</h3>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-white bg-opacity-10 rounded-lg">
                        <span class="text-white font-medium">Configuration Status</span>
                        <span class="px-3 py-1 rounded-full text-sm <?php echo $fbr_config ? 'bg-green-500 text-white' : 'bg-red-500 text-white'; ?>">
                            <?php echo $fbr_config ? 'Configured' : 'Not Configured'; ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-white bg-opacity-10 rounded-lg">
                        <span class="text-white font-medium">Current Mode</span>
                        <span class="px-3 py-1 rounded-full text-sm <?php echo $fbr_config && $fbr_config['sandbox_mode'] ? 'bg-yellow-500 text-white' : 'bg-blue-500 text-white'; ?>">
                            <?php echo $fbr_config && $fbr_config['sandbox_mode'] ? 'Sandbox' : 'Production'; ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-white bg-opacity-10 rounded-lg">
                        <span class="text-white font-medium">Last Sync</span>
                        <span class="text-white opacity-75">
                            <?php echo $fbr_config && $fbr_config['last_sync'] ? format_datetime($fbr_config['last_sync']) : 'Never'; ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-white bg-opacity-10 rounded-lg">
                        <span class="text-white font-medium">Total Sales</span>
                        <span class="text-white font-bold"><?php echo number_format($fbr_stats['total_sales']); ?></span>
                    </div>
                </div>

                <div class="mt-6">
                    <h4 class="text-white font-semibold mb-4">FBR Integration Features</h4>
                    <ul class="space-y-2 text-white opacity-75">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-400 mr-2"></i>
                            Automatic invoice validation
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-400 mr-2"></i>
                            Offline queuing for failed syncs
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-400 mr-2"></i>
                            Error translation to plain English
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-400 mr-2"></i>
                            Support for all FBR sale types
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-400 mr-2"></i>
                            QR code generation
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Recent FBR Activities -->
        <div class="mt-8">
            <div class="glass-effect rounded-xl p-6">
                <h3 class="text-xl font-bold text-white mb-6">Recent FBR Activities</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-white">
                        <thead>
                            <tr class="border-b border-white border-opacity-20">
                                <th class="text-left py-3 px-4">Invoice #</th>
                                <th class="text-left py-3 px-4">Cashier</th>
                                <th class="text-right py-3 px-4">Amount</th>
                                <th class="text-center py-3 px-4">FBR Status</th>
                                <th class="text-left py-3 px-4">FBR Invoice #</th>
                                <th class="text-left py-3 px-4">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activities as $sale): ?>
                            <tr class="border-b border-white border-opacity-10">
                                <td class="py-3 px-4 font-semibold"><?php echo htmlspecialchars($sale['invoice_number']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($sale['cashier_name']); ?></td>
                                <td class="py-3 px-4 text-right font-bold"><?php echo format_currency($sale['total_amount']); ?></td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-1 text-xs rounded-full <?php 
                                        echo $sale['fbr_status'] === 'synced' ? 'bg-green-500 text-white' : 
                                            ($sale['fbr_status'] === 'pending' ? 'bg-yellow-500 text-white' : 'bg-red-500 text-white'); 
                                    ?>">
                                        <?php echo ucfirst($sale['fbr_status']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    <?php echo $sale['fbr_invoice_number'] ? htmlspecialchars($sale['fbr_invoice_number']) : '-'; ?>
                                </td>
                                <td class="py-3 px-4 text-sm opacity-75"><?php echo format_datetime($sale['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh page every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>