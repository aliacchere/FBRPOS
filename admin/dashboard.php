<?php
/**
 * DPS POS FBR Integrated - Super Admin Dashboard
 */

session_start();
require_once '../config/database.php';
require_once '../config/app.php';
require_once '../includes/functions.php';

// Check authentication and role
require_login();
require_role('super_admin');

// Get dashboard statistics
$stats = [
    'total_tenants' => db_fetch("SELECT COUNT(*) as count FROM tenants")['count'],
    'active_tenants' => db_fetch("SELECT COUNT(*) as count FROM tenants WHERE is_active = 1")['count'],
    'total_users' => db_fetch("SELECT COUNT(*) as count FROM users WHERE role != 'super_admin'")['count'],
    'total_sales' => db_fetch("SELECT COUNT(*) as count FROM sales")['count'],
    'total_revenue' => db_fetch("SELECT SUM(total_amount) as total FROM sales")['total'] ?? 0,
    'fbr_synced_sales' => db_fetch("SELECT COUNT(*) as count FROM sales WHERE fbr_status = 'synced'")['count'],
    'pending_fbr_sales' => db_fetch("SELECT COUNT(*) as count FROM sales WHERE fbr_status = 'pending'")['count']
];

// Get recent tenants
$recent_tenants = db_fetch_all("
    SELECT t.*, u.name as admin_name, u.email as admin_email 
    FROM tenants t 
    LEFT JOIN users u ON t.id = u.tenant_id AND u.role = 'tenant_admin'
    ORDER BY t.created_at DESC 
    LIMIT 10
");

// Get recent sales
$recent_sales = db_fetch_all("
    SELECT s.*, t.business_name, u.name as cashier_name
    FROM sales s
    LEFT JOIN tenants t ON s.tenant_id = t.id
    LEFT JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
    LIMIT 10
");

// Get FBR sync status
$fbr_status = db_fetch_all("
    SELECT 
        t.business_name,
        COUNT(s.id) as total_sales,
        SUM(CASE WHEN s.fbr_status = 'synced' THEN 1 ELSE 0 END) as synced_sales,
        SUM(CASE WHEN s.fbr_status = 'pending' THEN 1 ELSE 0 END) as pending_sales,
        SUM(CASE WHEN s.fbr_status = 'failed' THEN 1 ELSE 0 END) as failed_sales
    FROM tenants t
    LEFT JOIN sales s ON t.id = s.tenant_id
    GROUP BY t.id, t.business_name
    ORDER BY total_sales DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - DPS POS FBR Integrated</title>
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
                        <span class="text-xl font-bold text-white">DPS POS FBR</span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-white">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
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
            <h1 class="text-3xl font-bold text-white mb-2">Super Admin Dashboard</h1>
            <p class="text-white opacity-90">Platform overview and tenant management</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-building text-3xl text-blue-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Total Tenants</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['total_tenants']); ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-3xl text-green-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Active Tenants</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['active_tenants']); ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-users text-3xl text-purple-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Total Users</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['total_users']); ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-rupee-sign text-3xl text-yellow-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Total Revenue</p>
                        <p class="text-2xl font-bold text-white"><?php echo format_currency($stats['total_revenue']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- FBR Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-sync text-3xl text-green-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">FBR Synced Sales</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['fbr_synced_sales']); ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-3xl text-yellow-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Pending FBR Sync</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['pending_fbr_sales']); ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-xl p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shopping-cart text-3xl text-blue-400"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-white opacity-75 text-sm">Total Sales</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['total_sales']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Tenants -->
            <div class="glass-effect rounded-xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white">Recent Tenants</h3>
                    <a href="tenants.php" class="text-white opacity-75 hover:opacity-100 transition-opacity">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="space-y-4">
                    <?php foreach ($recent_tenants as $tenant): ?>
                    <div class="flex items-center justify-between p-4 bg-white bg-opacity-10 rounded-lg">
                        <div>
                            <p class="text-white font-semibold"><?php echo htmlspecialchars($tenant['business_name']); ?></p>
                            <p class="text-white opacity-75 text-sm"><?php echo htmlspecialchars($tenant['admin_name']); ?></p>
                            <p class="text-white opacity-50 text-xs"><?php echo format_date($tenant['created_at']); ?></p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $tenant['is_active'] ? 'bg-green-500 text-white' : 'bg-red-500 text-white'; ?>">
                                <?php echo $tenant['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                            <button onclick="impersonateTenant(<?php echo $tenant['id']; ?>)" 
                                    class="text-white opacity-75 hover:opacity-100 transition-opacity">
                                <i class="fas fa-user-secret"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

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
                            <p class="text-white opacity-75 text-sm"><?php echo htmlspecialchars($sale['business_name']); ?></p>
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
        </div>

        <!-- FBR Status Overview -->
        <div class="mt-8">
            <div class="glass-effect rounded-xl p-6">
                <h3 class="text-xl font-bold text-white mb-6">FBR Sync Status by Tenant</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-white">
                        <thead>
                            <tr class="border-b border-white border-opacity-20">
                                <th class="text-left py-3 px-4">Business Name</th>
                                <th class="text-center py-3 px-4">Total Sales</th>
                                <th class="text-center py-3 px-4">Synced</th>
                                <th class="text-center py-3 px-4">Pending</th>
                                <th class="text-center py-3 px-4">Failed</th>
                                <th class="text-center py-3 px-4">Sync Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fbr_status as $status): ?>
                            <tr class="border-b border-white border-opacity-10">
                                <td class="py-3 px-4"><?php echo htmlspecialchars($status['business_name']); ?></td>
                                <td class="text-center py-3 px-4"><?php echo number_format($status['total_sales']); ?></td>
                                <td class="text-center py-3 px-4">
                                    <span class="text-green-400"><?php echo number_format($status['synced_sales']); ?></span>
                                </td>
                                <td class="text-center py-3 px-4">
                                    <span class="text-yellow-400"><?php echo number_format($status['pending_sales']); ?></span>
                                </td>
                                <td class="text-center py-3 px-4">
                                    <span class="text-red-400"><?php echo number_format($status['failed_sales']); ?></span>
                                </td>
                                <td class="text-center py-3 px-4">
                                    <?php 
                                    $sync_rate = $status['total_sales'] > 0 ? 
                                        round(($status['synced_sales'] / $status['total_sales']) * 100, 1) : 0;
                                    $color = $sync_rate >= 90 ? 'text-green-400' : ($sync_rate >= 70 ? 'text-yellow-400' : 'text-red-400');
                                    ?>
                                    <span class="<?php echo $color; ?>"><?php echo $sync_rate; ?>%</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function impersonateTenant(tenantId) {
            if (confirm('Are you sure you want to impersonate this tenant? This will log you in as their admin account.')) {
                // This would be implemented with proper security measures
                window.location.href = `impersonate.php?tenant_id=${tenantId}`;
            }
        }

        // Auto-refresh dashboard every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>