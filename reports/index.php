<?php
require_once '../includes/auth.php';

// Check if user is logged in
try {
    $pdo = getDatabaseConnection();
    $auth = new Auth($pdo);
    $auth->requireLogin();
    $user = $auth->getCurrentUser();
} catch (Exception $e) {
    header('Location: /login.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_sales_report':
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            $reportType = $_POST['report_type'] ?? 'daily';
            
            try {
                $query = "
                    SELECT 
                        DATE(s.created_at) as sale_date,
                        COUNT(*) as total_sales,
                        SUM(s.total_amount) as total_revenue,
                        SUM(s.tax_amount) as total_tax,
                        SUM(s.discount_amount) as total_discount,
                        AVG(s.total_amount) as average_sale
                    FROM sales s
                    WHERE s.tenant_id = ? 
                    AND s.created_at >= ? 
                    AND s.created_at <= ?
                    GROUP BY DATE(s.created_at)
                    ORDER BY sale_date DESC
                ";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user['tenant_id'], $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                $salesData = $stmt->fetchAll();
                
                // Get top products
                $productQuery = "
                    SELECT 
                        p.name,
                        p.sku,
                        SUM(si.quantity) as total_quantity,
                        SUM(si.total_price) as total_revenue
                    FROM sale_items si
                    JOIN products p ON si.product_id = p.id
                    JOIN sales s ON si.sale_id = s.id
                    WHERE s.tenant_id = ? 
                    AND s.created_at >= ? 
                    AND s.created_at <= ?
                    GROUP BY p.id, p.name, p.sku
                    ORDER BY total_quantity DESC
                    LIMIT 10
                ";
                
                $stmt = $pdo->prepare($productQuery);
                $stmt->execute([$user['tenant_id'], $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                $topProducts = $stmt->fetchAll();
                
                // Get summary stats
                $summaryQuery = "
                    SELECT 
                        COUNT(*) as total_sales,
                        SUM(total_amount) as total_revenue,
                        SUM(tax_amount) as total_tax,
                        SUM(discount_amount) as total_discount,
                        AVG(total_amount) as average_sale,
                        MIN(total_amount) as min_sale,
                        MAX(total_amount) as max_sale
                    FROM sales
                    WHERE tenant_id = ? 
                    AND created_at >= ? 
                    AND created_at <= ?
                ";
                
                $stmt = $pdo->prepare($summaryQuery);
                $stmt->execute([$user['tenant_id'], $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                $summary = $stmt->fetch();
                
                echo json_encode([
                    'success' => true,
                    'sales_data' => $salesData,
                    'top_products' => $topProducts,
                    'summary' => $summary
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_inventory_report':
            $lowStock = $_POST['low_stock'] ?? false;
            
            try {
                $query = "
                    SELECT 
                        p.name,
                        p.sku,
                        p.stock_quantity,
                        p.min_stock_level,
                        p.price,
                        p.cost,
                        (p.stock_quantity * p.price) as total_value,
                        c.name as category_name
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.tenant_id = ? AND p.is_active = 1
                ";
                
                if ($lowStock) {
                    $query .= " AND p.stock_quantity <= p.min_stock_level";
                }
                
                $query .= " ORDER BY p.stock_quantity ASC";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user['tenant_id']]);
                $inventoryData = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'inventory_data' => $inventoryData]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_customer_report':
            try {
                $query = "
                    SELECT 
                        c.name,
                        c.email,
                        c.phone,
                        COUNT(s.id) as total_sales,
                        SUM(s.total_amount) as total_spent,
                        AVG(s.total_amount) as average_sale,
                        MAX(s.created_at) as last_purchase
                    FROM customers c
                    LEFT JOIN sales s ON c.id = s.customer_id
                    WHERE c.tenant_id = ? AND c.is_active = 1
                    GROUP BY c.id, c.name, c.email, c.phone
                    ORDER BY total_spent DESC
                ";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user['tenant_id']]);
                $customerData = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'customer_data' => $customerData]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - DPS POS FBR Integrated</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body>
    <div id="app" class="min-h-screen">
        <!-- Header -->
        <div class="bg-white bg-opacity-10 backdrop-blur-md border-b border-white border-opacity-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center">
                        <i class="fas fa-chart-bar text-white text-2xl mr-3"></i>
                        <h1 class="text-xl font-bold text-white">Reports & Analytics</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-white opacity-75">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
                        <a href="/admin/" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-all">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Admin
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Tabs -->
            <div class="mb-8">
                <div class="flex space-x-1 bg-white bg-opacity-10 rounded-lg p-1">
                    <button @click="activeTab = 'sales'" 
                            :class="activeTab === 'sales' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-1 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-chart-line mr-2"></i>Sales Reports
                    </button>
                    <button @click="activeTab = 'inventory'" 
                            :class="activeTab === 'inventory' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-1 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-boxes mr-2"></i>Inventory Reports
                    </button>
                    <button @click="activeTab = 'customers'" 
                            :class="activeTab === 'customers' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-1 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-users mr-2"></i>Customer Reports
                    </button>
                </div>
            </div>

            <!-- Sales Reports Tab -->
            <div v-if="activeTab === 'sales'">
                <div class="glass-effect rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-white">Sales Reports</h3>
                        <div class="flex space-x-4">
                            <input v-model="dateRange.start" type="date" 
                                   class="px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                            <input v-model="dateRange.end" type="date" 
                                   class="px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                            <button @click="loadSalesReport" 
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-all">
                                <i class="fas fa-search mr-2"></i>Generate Report
                            </button>
                        </div>
                    </div>
                    
                    <!-- Summary Cards -->
                    <div v-if="salesSummary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white bg-opacity-10 rounded-lg p-4 text-center">
                            <div class="text-2xl text-white font-bold">{{ salesSummary.total_sales || 0 }}</div>
                            <div class="text-white opacity-75 text-sm">Total Sales</div>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded-lg p-4 text-center">
                            <div class="text-2xl text-white font-bold">Rs. {{ formatPrice(salesSummary.total_revenue || 0) }}</div>
                            <div class="text-white opacity-75 text-sm">Total Revenue</div>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded-lg p-4 text-center">
                            <div class="text-2xl text-white font-bold">Rs. {{ formatPrice(salesSummary.average_sale || 0) }}</div>
                            <div class="text-white opacity-75 text-sm">Average Sale</div>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded-lg p-4 text-center">
                            <div class="text-2xl text-white font-bold">Rs. {{ formatPrice(salesSummary.total_tax || 0) }}</div>
                            <div class="text-white opacity-75 text-sm">Total Tax</div>
                        </div>
                    </div>
                    
                    <!-- Sales Chart -->
                    <div v-if="salesData.length > 0" class="mb-8">
                        <h4 class="text-lg font-semibold text-white mb-4">Sales Trend</h4>
                        <div class="glass-effect rounded-lg p-4">
                            <div class="chart-container">
                                <canvas ref="salesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top Products -->
                    <div v-if="topProducts.length > 0" class="mb-8">
                        <h4 class="text-lg font-semibold text-white mb-4">Top Selling Products</h4>
                        <div class="glass-effect rounded-lg p-4">
                            <div class="overflow-x-auto">
                                <table class="w-full text-white">
                                    <thead>
                                        <tr class="border-b border-white border-opacity-20">
                                            <th class="text-left py-2">Product</th>
                                            <th class="text-left py-2">SKU</th>
                                            <th class="text-right py-2">Quantity Sold</th>
                                            <th class="text-right py-2">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="product in topProducts" :key="product.sku" 
                                            class="border-b border-white border-opacity-10">
                                            <td class="py-2">{{ product.name }}</td>
                                            <td class="py-2">{{ product.sku }}</td>
                                            <td class="text-right py-2">{{ product.total_quantity }}</td>
                                            <td class="text-right py-2">Rs. {{ formatPrice(product.total_revenue) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Reports Tab -->
            <div v-if="activeTab === 'inventory'">
                <div class="glass-effect rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-white">Inventory Reports</h3>
                        <div class="flex space-x-4">
                            <label class="flex items-center text-white">
                                <input v-model="showLowStock" type="checkbox" class="mr-2">
                                Show Low Stock Only
                            </label>
                            <button @click="loadInventoryReport" 
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-all">
                                <i class="fas fa-search mr-2"></i>Generate Report
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="inventoryData.length > 0" class="overflow-x-auto">
                        <table class="w-full text-white">
                            <thead>
                                <tr class="border-b border-white border-opacity-20">
                                    <th class="text-left py-2">Product</th>
                                    <th class="text-left py-2">SKU</th>
                                    <th class="text-left py-2">Category</th>
                                    <th class="text-right py-2">Stock</th>
                                    <th class="text-right py-2">Min Level</th>
                                    <th class="text-right py-2">Value</th>
                                    <th class="text-center py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in inventoryData" :key="item.sku" 
                                    class="border-b border-white border-opacity-10">
                                    <td class="py-2">{{ item.name }}</td>
                                    <td class="py-2">{{ item.sku }}</td>
                                    <td class="py-2">{{ item.category_name || 'N/A' }}</td>
                                    <td class="text-right py-2">{{ item.stock_quantity }}</td>
                                    <td class="text-right py-2">{{ item.min_stock_level }}</td>
                                    <td class="text-right py-2">Rs. {{ formatPrice(item.total_value) }}</td>
                                    <td class="text-center py-2">
                                        <span :class="item.stock_quantity <= item.min_stock_level ? 'text-red-400' : 'text-green-400'"
                                              class="font-bold">
                                            {{ item.stock_quantity <= item.min_stock_level ? 'Low Stock' : 'In Stock' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Customer Reports Tab -->
            <div v-if="activeTab === 'customers'">
                <div class="glass-effect rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-white">Customer Reports</h3>
                        <button @click="loadCustomerReport" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-all">
                            <i class="fas fa-search mr-2"></i>Generate Report
                        </button>
                    </div>
                    
                    <div v-if="customerData.length > 0" class="overflow-x-auto">
                        <table class="w-full text-white">
                            <thead>
                                <tr class="border-b border-white border-opacity-20">
                                    <th class="text-left py-2">Customer</th>
                                    <th class="text-left py-2">Email</th>
                                    <th class="text-left py-2">Phone</th>
                                    <th class="text-right py-2">Total Sales</th>
                                    <th class="text-right py-2">Total Spent</th>
                                    <th class="text-right py-2">Avg Sale</th>
                                    <th class="text-left py-2">Last Purchase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="customer in customerData" :key="customer.name" 
                                    class="border-b border-white border-opacity-10">
                                    <td class="py-2">{{ customer.name }}</td>
                                    <td class="py-2">{{ customer.email || 'N/A' }}</td>
                                    <td class="py-2">{{ customer.phone || 'N/A' }}</td>
                                    <td class="text-right py-2">{{ customer.total_sales || 0 }}</td>
                                    <td class="text-right py-2">Rs. {{ formatPrice(customer.total_spent || 0) }}</td>
                                    <td class="text-right py-2">Rs. {{ formatPrice(customer.average_sale || 0) }}</td>
                                    <td class="py-2">{{ customer.last_purchase ? formatDate(customer.last_purchase) : 'Never' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const { createApp } = Vue;
        
        createApp({
            data() {
                return {
                    activeTab: 'sales',
                    dateRange: {
                        start: new Date().toISOString().split('T')[0],
                        end: new Date().toISOString().split('T')[0]
                    },
                    salesData: [],
                    salesSummary: null,
                    topProducts: [],
                    inventoryData: [],
                    customerData: [],
                    showLowStock: false,
                    salesChart: null
                }
            },
            mounted() {
                this.loadSalesReport();
            },
            methods: {
                async loadSalesReport() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=get_sales_report&start_date=${this.dateRange.start}&end_date=${this.dateRange.end}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.salesData = data.sales_data;
                            this.salesSummary = data.summary;
                            this.topProducts = data.top_products;
                            this.createSalesChart();
                        }
                    } catch (error) {
                        console.error('Error loading sales report:', error);
                    }
                },
                
                async loadInventoryReport() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=get_inventory_report&low_stock=${this.showLowStock}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.inventoryData = data.inventory_data;
                        }
                    } catch (error) {
                        console.error('Error loading inventory report:', error);
                    }
                },
                
                async loadCustomerReport() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=get_customer_report'
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.customerData = data.customer_data;
                        }
                    } catch (error) {
                        console.error('Error loading customer report:', error);
                    }
                },
                
                createSalesChart() {
                    if (this.salesChart) {
                        this.salesChart.destroy();
                    }
                    
                    const ctx = this.$refs.salesChart.getContext('2d');
                    this.salesChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: this.salesData.map(item => item.sale_date),
                            datasets: [{
                                label: 'Revenue',
                                data: this.salesData.map(item => item.total_revenue),
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    labels: {
                                        color: 'white'
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        color: 'white'
                                    },
                                    grid: {
                                        color: 'rgba(255, 255, 255, 0.1)'
                                    }
                                },
                                y: {
                                    ticks: {
                                        color: 'white'
                                    },
                                    grid: {
                                        color: 'rgba(255, 255, 255, 0.1)'
                                    }
                                }
                            }
                        }
                    });
                },
                
                formatPrice(price) {
                    return parseFloat(price).toFixed(2);
                },
                
                formatDate(dateString) {
                    return new Date(dateString).toLocaleDateString();
                }
            }
        }).mount('#app');
    </script>
</body>
</html>