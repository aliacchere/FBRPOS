<?php
/**
 * DPS POS FBR Integrated - Tenant Admin Dashboard
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

// Get dashboard statistics
$stats = [
    'total_sales' => db_fetch("SELECT COUNT(*) as count FROM sales WHERE tenant_id = ?", [$tenant['id']])['count'],
    'today_sales' => db_fetch("SELECT COUNT(*) as count FROM sales WHERE tenant_id = ? AND DATE(created_at) = CURDATE()", [$tenant['id']])['count'],
    'total_revenue' => db_fetch("SELECT SUM(total_amount) as total FROM sales WHERE tenant_id = ?", [$tenant['id']])['total'] ?? 0,
    'today_revenue' => db_fetch("SELECT SUM(total_amount) as total FROM sales WHERE tenant_id = ? AND DATE(created_at) = CURDATE()", [$tenant['id']])['total'] ?? 0,
    'fbr_synced' => db_fetch("SELECT COUNT(*) as count FROM sales WHERE tenant_id = ? AND fbr_status = 'synced'", [$tenant['id']])['count'],
    'fbr_pending' => db_fetch("SELECT COUNT(*) as count FROM sales WHERE tenant_id = ? AND fbr_status = 'pending'", [$tenant['id']])['count'],
    'fbr_failed' => db_fetch("SELECT COUNT(*) as count FROM sales WHERE tenant_id = ? AND fbr_status = 'failed'", [$tenant['id']])['count'],
    'total_products' => db_fetch("SELECT COUNT(*) as count FROM products WHERE tenant_id = ? AND is_active = 1", [$tenant['id']])['count'],
    'low_stock_products' => db_fetch("SELECT COUNT(*) as count FROM products WHERE tenant_id = ? AND stock_quantity <= min_stock_level AND is_active = 1", [$tenant['id']])['count']
];

// Get recent sales
$recent_sales = db_fetch_all("
    SELECT s.*, u.name as cashier_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.tenant_id = ?
    ORDER BY s.created_at DESC
    LIMIT 10
", [$tenant['id']]);

// Get low stock products
$low_stock_products = db_fetch_all("
    SELECT * FROM products 
    WHERE tenant_id = ? AND stock_quantity <= min_stock_level AND is_active = 1
    ORDER BY stock_quantity ASC
    LIMIT 5
", [$tenant['id']]);

// Get FBR sync status
$fbr_sync_status = db_fetch("
    SELECT 
        COUNT(*) as total_sales,
        SUM(CASE WHEN fbr_status = 'synced' THEN 1 ELSE 0 END) as synced_sales,
        SUM(CASE WHEN fbr_status = 'pending' THEN 1 ELSE 0 END) as pending_sales,
        SUM(CASE WHEN fbr_status = 'failed' THEN 1 ELSE 0 END) as failed_sales
    FROM sales 
    WHERE tenant_id = ?
", [$tenant['id']]);

$sync_rate = $fbr_sync_status['total_sales'] > 0 ? 
    round(($fbr_sync_status['synced_sales'] / $fbr_sync_status['total_sales']) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($tenant['business_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <a href="pos/sales.php" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-shopping-cart mr-1"></i>POS
                    </a>
                    <a href="products.php" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-box mr-1"></i>Products
                    </a>
                    <a href="fbr-hub.php" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-cogs mr-1"></i>FBR Hub
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
            <h1 class="text-3xl font-bold text-white mb-2">Dashboard</h1>
            <p class="text-white opacity-90">Welcome back to your business dashboard</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shopping-cart text-3xl text-blue-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Today's Sales</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['today_sales']); ?></p>
                        <p class="text-white opacity-50 text-xs">Total: <?php echo number_format($stats['total_sales']); ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-rupee-sign text-3xl text-green-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Today's Revenue</p>
                        <p class="text-2xl font-bold text-white"><?php echo format_currency($stats['today_revenue']); ?></p>
                        <p class="text-white opacity-50 text-xs">Total: <?php echo format_currency($stats['total_revenue']); ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-sync text-3xl text-purple-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">FBR Sync Rate</p>
                        <p class="text-2xl font-bold text-white"><?php echo $sync_rate; ?>%</p>
                        <p class="text-white opacity-50 text-xs"><?php echo number_format($stats['fbr_synced']); ?> synced</p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-box text-3xl text-yellow-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Products</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['total_products']); ?></p>
                        <p class="text-white opacity-50 text-xs"><?php echo number_format($stats['low_stock_products']); ?> low stock</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- FBR Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-3xl text-green-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">FBR Synced</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['fbr_synced']); ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-3xl text-yellow-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Pending Sync</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['fbr_pending']); ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-3xl text-red-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Failed Sync</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['fbr_failed']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Sales -->
            <div class="glass-effect rounded-xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white">Recent Sales</h3>
                    <a href="sales.php" class="text-white opacity-75 hover:opacity-100 transition-opacity">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="space-y-4">
                    <?php foreach ($recent_sales as $sale): ?>
                    <div class="flex items-center justify-between p-4 bg-white bg-opacity-10 rounded-lg">
                        <div>
                            <p class="text-white font-semibold"><?php echo htmlspecialchars($sale['invoice_number']); ?></p>
                            <p class="text-white opacity-75 text-sm"><?php echo htmlspecialchars($sale['cashier_name']); ?></p>
                            <p class="text-white opacity-50 text-xs"><?php echo format_datetime($sale['created_at']); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-white font-bold"><?php echo format_currency($sale['total_amount']); ?></p>
                            <span class="px-2 py-1 text-xs rounded-full <?php 
                                echo $sale['fbr_status'] === 'synced' ? 'bg-green-500 text-white' : 
                                    ($sale['fbr_status'] === 'pending' ? 'bg-yellow-500 text-white' : 'bg-red-500 text-white'); 
                            ?>">
                                <?php echo ucfirst($sale['fbr_status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Low Stock Products -->
            <div class="glass-effect rounded-xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white">Low Stock Alert</h3>
                    <a href="products.php" class="text-white opacity-75 hover:opacity-100 transition-opacity">
                        Manage <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="space-y-4">
                    <?php if (empty($low_stock_products)): ?>
                    <div class="text-center text-white opacity-75 py-8">
                        <i class="fas fa-check-circle text-4xl mb-2"></i>
                        <p>All products are well stocked!</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($low_stock_products as $product): ?>
                    <div class="flex items-center justify-between p-4 bg-white bg-opacity-10 rounded-lg">
                        <div>
                            <p class="text-white font-semibold"><?php echo htmlspecialchars($product['name']); ?></p>
                            <p class="text-white opacity-75 text-sm">SKU: <?php echo htmlspecialchars($product['sku']); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-red-400 font-bold"><?php echo $product['stock_quantity']; ?></p>
                            <p class="text-white opacity-50 text-xs">Min: <?php echo $product['min_stock_level']; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- FBR Configuration Status -->
        <div class="mt-8">
            <div class="glass-effect rounded-xl p-6">
                <h3 class="text-xl font-bold text-white mb-6">FBR Integration Status</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-white font-medium">Configuration Status</span>
                            <span class="px-3 py-1 rounded-full text-sm <?php echo $fbr_config ? 'bg-green-500 text-white' : 'bg-red-500 text-white'; ?>">
                                <?php echo $fbr_config ? 'Configured' : 'Not Configured'; ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-white font-medium">Mode</span>
                            <span class="px-3 py-1 rounded-full text-sm <?php echo $fbr_config && $fbr_config['sandbox_mode'] ? 'bg-yellow-500 text-white' : 'bg-blue-500 text-white'; ?>">
                                <?php echo $fbr_config && $fbr_config['sandbox_mode'] ? 'Sandbox' : 'Production'; ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-white font-medium">Last Sync</span>
                            <span class="text-white opacity-75">
                                <?php echo $fbr_config && $fbr_config['last_sync'] ? format_datetime($fbr_config['last_sync']) : 'Never'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-center">
                        <a href="fbr-hub.php" class="inline-flex items-center px-6 py-3 bg-white text-indigo-600 rounded-lg font-semibold hover:bg-gray-100 transition-all">
                            <i class="fas fa-cogs mr-2"></i>
                            <?php echo $fbr_config ? 'Manage FBR Settings' : 'Configure FBR Integration'; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh dashboard every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>