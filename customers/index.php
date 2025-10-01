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
        case 'get_customers':
            $page = $_POST['page'] ?? 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $stmt = $pdo->prepare("
                SELECT * FROM customers 
                WHERE tenant_id = ? AND is_active = 1
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user['tenant_id'], $limit, $offset]);
            $customers = $stmt->fetchAll();
            
            // Get total count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE tenant_id = ? AND is_active = 1");
            $stmt->execute([$user['tenant_id']]);
            $total = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true, 
                'customers' => $customers,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $limit)
            ]);
            exit;
            
        case 'add_customer':
            $name = $_POST['name'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $address = $_POST['address'];
            $credit_limit = $_POST['credit_limit'];
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO customers (tenant_id, name, email, phone, address, credit_limit, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$user['tenant_id'], $name, $email, $phone, $address, $credit_limit]);
                
                echo json_encode(['success' => true, 'message' => 'Customer added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_customer':
            $id = $_POST['id'];
            $name = $_POST['name'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $address = $_POST['address'];
            $credit_limit = $_POST['credit_limit'];
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE customers 
                    SET name = ?, email = ?, phone = ?, address = ?, credit_limit = ?, updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$name, $email, $phone, $address, $credit_limit, $id, $user['tenant_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_customer':
            $id = $_POST['id'];
            
            try {
                $stmt = $pdo->prepare("UPDATE customers SET is_active = 0 WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $user['tenant_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_customer_sales':
            $customerId = $_POST['customer_id'];
            
            $stmt = $pdo->prepare("
                SELECT s.*, u.name as cashier_name
                FROM sales s
                LEFT JOIN users u ON s.cashier_id = u.id
                WHERE s.tenant_id = ? AND s.customer_id = ?
                ORDER BY s.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$user['tenant_id'], $customerId]);
            $sales = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'sales' => $sales]);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - DPS POS FBR Integrated</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js"></script>
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
        .customer-card:hover {
            transform: translateY(-2px);
            transition: all 0.3s ease;
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
                        <i class="fas fa-users text-white text-2xl mr-3"></i>
                        <h1 class="text-xl font-bold text-white">Customer Management</h1>
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
            <div class="glass-effect rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-white">Customers</h3>
                    <button @click="showAddCustomer = true" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-all">
                        <i class="fas fa-plus mr-2"></i>Add Customer
                    </button>
                </div>
                
                <!-- Search -->
                <div class="mb-6">
                    <input v-model="searchQuery" @input="searchCustomers" 
                           type="text" 
                           placeholder="Search customers..." 
                           class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                </div>
                
                <!-- Customers Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div v-for="customer in filteredCustomers" :key="customer.id" 
                         class="customer-card bg-white bg-opacity-10 rounded-lg p-4">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="text-white font-medium">{{ customer.name }}</h4>
                            <div class="flex space-x-1">
                                <button @click="viewCustomer(customer)" class="text-blue-400 hover:text-blue-300">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button @click="editCustomer(customer)" class="text-yellow-400 hover:text-yellow-300">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button @click="deleteCustomer(customer.id)" class="text-red-400 hover:text-red-300">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div v-if="customer.email" class="text-white opacity-75 text-sm mb-1">
                            <i class="fas fa-envelope mr-1"></i>{{ customer.email }}
                        </div>
                        <div v-if="customer.phone" class="text-white opacity-75 text-sm mb-1">
                            <i class="fas fa-phone mr-1"></i>{{ customer.phone }}
                        </div>
                        <div class="text-green-400 font-bold text-sm">
                            Credit: Rs. {{ formatPrice(customer.credit_limit - customer.credit_used) }}
                        </div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="flex justify-center mt-6">
                    <button @click="loadCustomers(currentPage - 1)" 
                            :disabled="currentPage <= 1"
                            class="px-4 py-2 bg-white bg-opacity-20 text-white rounded-lg mr-2 disabled:opacity-50">
                        Previous
                    </button>
                    <span class="text-white px-4 py-2">{{ currentPage }} of {{ totalPages }}</span>
                    <button @click="loadCustomers(currentPage + 1)" 
                            :disabled="currentPage >= totalPages"
                            class="px-4 py-2 bg-white bg-opacity-20 text-white rounded-lg ml-2 disabled:opacity-50">
                        Next
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Add Customer Modal -->
        <div v-if="showAddCustomer" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="glass-effect rounded-lg p-8 max-w-md w-full mx-4">
                <h3 class="text-xl font-semibold text-white mb-6">Add Customer</h3>
                
                <form @submit.prevent="addCustomer" class="space-y-4">
                    <div>
                        <label class="block text-white font-medium mb-2">Customer Name</label>
                        <input v-model="customerForm.name" type="text" required 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Email</label>
                        <input v-model="customerForm.email" type="email" 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Phone</label>
                        <input v-model="customerForm.phone" type="text" 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Address</label>
                        <textarea v-model="customerForm.address" 
                                  class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                  rows="3"></textarea>
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Credit Limit</label>
                        <input v-model="customerForm.credit_limit" type="number" step="0.01" 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="button" @click="showAddCustomer = false" 
                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-2 rounded-lg transition-all">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg transition-all">
                            Add Customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Customer Details Modal -->
        <div v-if="showCustomerDetails" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="glass-effect rounded-lg p-8 max-w-2xl w-full mx-4 max-h-96 overflow-y-auto">
                <h3 class="text-xl font-semibold text-white mb-6">Customer Details</h3>
                
                <div v-if="selectedCustomer" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-white font-medium mb-2">Name</label>
                            <div class="text-white">{{ selectedCustomer.name }}</div>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Email</label>
                            <div class="text-white">{{ selectedCustomer.email || 'N/A' }}</div>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Phone</label>
                            <div class="text-white">{{ selectedCustomer.phone || 'N/A' }}</div>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Credit Limit</label>
                            <div class="text-white">Rs. {{ formatPrice(selectedCustomer.credit_limit) }}</div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-white font-medium mb-2">Address</label>
                        <div class="text-white">{{ selectedCustomer.address || 'N/A' }}</div>
                    </div>
                    
                    <!-- Recent Sales -->
                    <div class="mt-6">
                        <h4 class="text-lg font-semibold text-white mb-4">Recent Sales</h4>
                        <div v-if="customerSales.length > 0" class="space-y-2">
                            <div v-for="sale in customerSales" :key="sale.id" 
                                 class="bg-white bg-opacity-10 rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="text-white font-medium">{{ sale.invoice_number }}</div>
                                        <div class="text-white opacity-75 text-sm">{{ sale.created_at }}</div>
                                    </div>
                                    <div class="text-green-400 font-bold">Rs. {{ formatPrice(sale.total_amount) }}</div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-white opacity-75 text-center py-4">
                            No sales found for this customer
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end mt-6">
                    <button @click="showCustomerDetails = false" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-all">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const { createApp } = Vue;
        
        createApp({
            data() {
                return {
                    customers: [],
                    filteredCustomers: [],
                    searchQuery: '',
                    currentPage: 1,
                    totalPages: 1,
                    showAddCustomer: false,
                    showCustomerDetails: false,
                    selectedCustomer: null,
                    customerSales: [],
                    customerForm: {
                        name: '',
                        email: '',
                        phone: '',
                        address: '',
                        credit_limit: 0
                    }
                }
            },
            mounted() {
                this.loadCustomers();
            },
            methods: {
                async loadCustomers(page = 1) {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=get_customers&page=${page}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.customers = data.customers;
                            this.filteredCustomers = data.customers;
                            this.currentPage = data.page;
                            this.totalPages = data.total_pages;
                        }
                    } catch (error) {
                        console.error('Error loading customers:', error);
                    }
                },
                
                searchCustomers() {
                    if (this.searchQuery) {
                        this.filteredCustomers = this.customers.filter(c => 
                            c.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                            (c.email && c.email.toLowerCase().includes(this.searchQuery.toLowerCase())) ||
                            (c.phone && c.phone.includes(this.searchQuery))
                        );
                    } else {
                        this.filteredCustomers = this.customers;
                    }
                },
                
                async addCustomer() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=add_customer&${new URLSearchParams(this.customerForm)}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            alert('Customer added successfully!');
                            this.showAddCustomer = false;
                            this.customerForm = {
                                name: '', email: '', phone: '', address: '', credit_limit: 0
                            };
                            this.loadCustomers();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error adding customer:', error);
                        alert('Error adding customer');
                    }
                },
                
                async viewCustomer(customer) {
                    this.selectedCustomer = customer;
                    this.showCustomerDetails = true;
                    
                    // Load customer sales
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=get_customer_sales&customer_id=${customer.id}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.customerSales = data.sales;
                        }
                    } catch (error) {
                        console.error('Error loading customer sales:', error);
                    }
                },
                
                editCustomer(customer) {
                    // TODO: Implement edit functionality
                    alert('Edit functionality coming soon!');
                },
                
                async deleteCustomer(id) {
                    if (confirm('Are you sure you want to delete this customer?')) {
                        try {
                            const response = await fetch('', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=delete_customer&id=${id}`
                            });
                            const data = await response.json();
                            if (data.success) {
                                alert('Customer deleted successfully!');
                                this.loadCustomers();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Error deleting customer:', error);
                            alert('Error deleting customer');
                        }
                    }
                },
                
                formatPrice(price) {
                    return parseFloat(price).toFixed(2);
                }
            }
        }).mount('#app');
    </script>
</body>
</html>