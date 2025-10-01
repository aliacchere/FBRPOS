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
        case 'get_products':
            $page = $_POST['page'] ?? 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name, s.name as supplier_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                WHERE p.tenant_id = ?
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user['tenant_id'], $limit, $offset]);
            $products = $stmt->fetchAll();
            
            // Get total count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = ?");
            $stmt->execute([$user['tenant_id']]);
            $total = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true, 
                'products' => $products,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $limit)
            ]);
            exit;
            
        case 'add_product':
            $name = $_POST['name'];
            $sku = $_POST['sku'];
            $barcode = $_POST['barcode'];
            $price = $_POST['price'];
            $cost = $_POST['cost'];
            $stock_quantity = $_POST['stock_quantity'];
            $min_stock_level = $_POST['min_stock_level'];
            $category_id = $_POST['category_id'];
            $supplier_id = $_POST['supplier_id'];
            $description = $_POST['description'];
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO products (tenant_id, name, sku, barcode, price, cost, stock_quantity, min_stock_level, category_id, supplier_id, description, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $user['tenant_id'], $name, $sku, $barcode, $price, $cost, 
                    $stock_quantity, $min_stock_level, $category_id, $supplier_id, $description
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Product added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_product':
            $id = $_POST['id'];
            $name = $_POST['name'];
            $sku = $_POST['sku'];
            $barcode = $_POST['barcode'];
            $price = $_POST['price'];
            $cost = $_POST['cost'];
            $stock_quantity = $_POST['stock_quantity'];
            $min_stock_level = $_POST['min_stock_level'];
            $category_id = $_POST['category_id'];
            $supplier_id = $_POST['supplier_id'];
            $description = $_POST['description'];
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET name = ?, sku = ?, barcode = ?, price = ?, cost = ?, stock_quantity = ?, 
                        min_stock_level = ?, category_id = ?, supplier_id = ?, description = ?, updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $name, $sku, $barcode, $price, $cost, $stock_quantity, 
                    $min_stock_level, $category_id, $supplier_id, $description, $id, $user['tenant_id']
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_product':
            $id = $_POST['id'];
            
            try {
                $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $user['tenant_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_categories':
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE tenant_id = ? ORDER BY name");
            $stmt->execute([$user['tenant_id']]);
            $categories = $stmt->fetchAll();
            echo json_encode(['success' => true, 'categories' => $categories]);
            exit;
            
        case 'add_category':
            $name = $_POST['name'];
            $description = $_POST['description'];
            
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (tenant_id, name, description, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$user['tenant_id'], $name, $description]);
                
                echo json_encode(['success' => true, 'message' => 'Category added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_suppliers':
            $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE tenant_id = ? ORDER BY name");
            $stmt->execute([$user['tenant_id']]);
            $suppliers = $stmt->fetchAll();
            echo json_encode(['success' => true, 'suppliers' => $suppliers]);
            exit;
            
        case 'add_supplier':
            $name = $_POST['name'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $address = $_POST['address'];
            
            try {
                $stmt = $pdo->prepare("INSERT INTO suppliers (tenant_id, name, email, phone, address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$user['tenant_id'], $name, $email, $phone, $address]);
                
                echo json_encode(['success' => true, 'message' => 'Supplier added successfully']);
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
    <title>Inventory Management - DPS POS FBR Integrated</title>
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
        .product-card:hover {
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
                        <i class="fas fa-boxes text-white text-2xl mr-3"></i>
                        <h1 class="text-xl font-bold text-white">Inventory Management</h1>
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
                    <button @click="activeTab = 'products'" 
                            :class="activeTab === 'products' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-1 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-boxes mr-2"></i>Products
                    </button>
                    <button @click="activeTab = 'categories'" 
                            :class="activeTab === 'categories' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-1 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-list mr-2"></i>Categories
                    </button>
                    <button @click="activeTab = 'suppliers'" 
                            :class="activeTab === 'suppliers' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-1 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-truck mr-2"></i>Suppliers
                    </button>
                    <button @click="activeTab = 'reports'" 
                            :class="activeTab === 'reports' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-1 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-chart-bar mr-2"></i>Reports
                    </button>
                </div>
            </div>

            <!-- Products Tab -->
            <div v-if="activeTab === 'products'">
                <div class="glass-effect rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-white">Products</h3>
                        <button @click="showAddProduct = true" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-all">
                            <i class="fas fa-plus mr-2"></i>Add Product
                        </button>
                    </div>
                    
                    <!-- Search and Filters -->
                    <div class="flex space-x-4 mb-6">
                        <div class="flex-1">
                            <input v-model="searchQuery" @input="searchProducts" 
                                   type="text" 
                                   placeholder="Search products..." 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <select v-model="selectedCategory" @change="filterProducts" 
                                class="px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                            <option value="">All Categories</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                {{ category.name }}
                            </option>
                        </select>
                    </div>
                    
                    <!-- Products Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <div v-for="product in filteredProducts" :key="product.id" 
                             class="product-card bg-white bg-opacity-10 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="text-white font-medium">{{ product.name }}</h4>
                                <div class="flex space-x-1">
                                    <button @click="editProduct(product)" class="text-blue-400 hover:text-blue-300">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button @click="deleteProduct(product.id)" class="text-red-400 hover:text-red-300">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="text-white opacity-75 text-sm mb-2">{{ product.sku }}</div>
                            <div class="text-green-400 font-bold mb-2">Rs. {{ formatPrice(product.price) }}</div>
                            <div class="text-white opacity-60 text-xs mb-2">Stock: {{ product.stock_quantity }}</div>
                            <div class="text-white opacity-60 text-xs">Cost: Rs. {{ formatPrice(product.cost) }}</div>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="flex justify-center mt-6">
                        <button @click="loadProducts(currentPage - 1)" 
                                :disabled="currentPage <= 1"
                                class="px-4 py-2 bg-white bg-opacity-20 text-white rounded-lg mr-2 disabled:opacity-50">
                            Previous
                        </button>
                        <span class="text-white px-4 py-2">{{ currentPage }} of {{ totalPages }}</span>
                        <button @click="loadProducts(currentPage + 1)" 
                                :disabled="currentPage >= totalPages"
                                class="px-4 py-2 bg-white bg-opacity-20 text-white rounded-lg ml-2 disabled:opacity-50">
                            Next
                        </button>
                    </div>
                </div>
            </div>

            <!-- Categories Tab -->
            <div v-if="activeTab === 'categories'">
                <div class="glass-effect rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-white">Categories</h3>
                        <button @click="showAddCategory = true" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-all">
                            <i class="fas fa-plus mr-2"></i>Add Category
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="category in categories" :key="category.id" 
                             class="bg-white bg-opacity-10 rounded-lg p-4">
                            <h4 class="text-white font-medium mb-2">{{ category.name }}</h4>
                            <p class="text-white opacity-75 text-sm">{{ category.description || 'No description' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suppliers Tab -->
            <div v-if="activeTab === 'suppliers'">
                <div class="glass-effect rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-white">Suppliers</h3>
                        <button @click="showAddSupplier = true" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-all">
                            <i class="fas fa-plus mr-2"></i>Add Supplier
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="supplier in suppliers" :key="supplier.id" 
                             class="bg-white bg-opacity-10 rounded-lg p-4">
                            <h4 class="text-white font-medium mb-2">{{ supplier.name }}</h4>
                            <p class="text-white opacity-75 text-sm">{{ supplier.email }}</p>
                            <p class="text-white opacity-75 text-sm">{{ supplier.phone }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports Tab -->
            <div v-if="activeTab === 'reports'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">Inventory Reports</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white bg-opacity-10 rounded-lg p-4 text-center">
                            <div class="text-2xl text-white font-bold">{{ totalProducts }}</div>
                            <div class="text-white opacity-75 text-sm">Total Products</div>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded-lg p-4 text-center">
                            <div class="text-2xl text-white font-bold">{{ lowStockProducts }}</div>
                            <div class="text-white opacity-75 text-sm">Low Stock</div>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded-lg p-4 text-center">
                            <div class="text-2xl text-white font-bold">Rs. {{ formatPrice(totalValue) }}</div>
                            <div class="text-white opacity-75 text-sm">Total Value</div>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded-lg p-4 text-center">
                            <div class="text-2xl text-white font-bold">{{ totalCategories }}</div>
                            <div class="text-white opacity-75 text-sm">Categories</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Product Modal -->
        <div v-if="showAddProduct" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="glass-effect rounded-lg p-8 max-w-2xl w-full mx-4 max-h-96 overflow-y-auto">
                <h3 class="text-xl font-semibold text-white mb-6">Add Product</h3>
                
                <form @submit.prevent="addProduct" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-white font-medium mb-2">Product Name</label>
                            <input v-model="productForm.name" type="text" required 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">SKU</label>
                            <input v-model="productForm.sku" type="text" required 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Barcode</label>
                            <input v-model="productForm.barcode" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Price</label>
                            <input v-model="productForm.price" type="number" step="0.01" required 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Cost</label>
                            <input v-model="productForm.cost" type="number" step="0.01" required 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Stock Quantity</label>
                            <input v-model="productForm.stock_quantity" type="number" required 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Min Stock Level</label>
                            <input v-model="productForm.min_stock_level" type="number" required 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Category</label>
                            <select v-model="productForm.category_id" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select Category</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-white font-medium mb-2">Description</label>
                        <textarea v-model="productForm.description" 
                                  class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                  rows="3"></textarea>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="button" @click="showAddProduct = false" 
                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-2 rounded-lg transition-all">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg transition-all">
                            Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Category Modal -->
        <div v-if="showAddCategory" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="glass-effect rounded-lg p-8 max-w-md w-full mx-4">
                <h3 class="text-xl font-semibold text-white mb-6">Add Category</h3>
                
                <form @submit.prevent="addCategory" class="space-y-4">
                    <div>
                        <label class="block text-white font-medium mb-2">Category Name</label>
                        <input v-model="categoryForm.name" type="text" required 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Description</label>
                        <textarea v-model="categoryForm.description" 
                                  class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                  rows="3"></textarea>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="button" @click="showAddCategory = false" 
                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-2 rounded-lg transition-all">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg transition-all">
                            Add Category
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Supplier Modal -->
        <div v-if="showAddSupplier" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="glass-effect rounded-lg p-8 max-w-md w-full mx-4">
                <h3 class="text-xl font-semibold text-white mb-6">Add Supplier</h3>
                
                <form @submit.prevent="addSupplier" class="space-y-4">
                    <div>
                        <label class="block text-white font-medium mb-2">Supplier Name</label>
                        <input v-model="supplierForm.name" type="text" required 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Email</label>
                        <input v-model="supplierForm.email" type="email" 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Phone</label>
                        <input v-model="supplierForm.phone" type="text" 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Address</label>
                        <textarea v-model="supplierForm.address" 
                                  class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                  rows="3"></textarea>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="button" @click="showAddSupplier = false" 
                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-2 rounded-lg transition-all">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg transition-all">
                            Add Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const { createApp } = Vue;
        
        createApp({
            data() {
                return {
                    activeTab: 'products',
                    products: [],
                    filteredProducts: [],
                    categories: [],
                    suppliers: [],
                    searchQuery: '',
                    selectedCategory: '',
                    currentPage: 1,
                    totalPages: 1,
                    showAddProduct: false,
                    showAddCategory: false,
                    showAddSupplier: false,
                    productForm: {
                        name: '',
                        sku: '',
                        barcode: '',
                        price: '',
                        cost: '',
                        stock_quantity: '',
                        min_stock_level: '',
                        category_id: '',
                        supplier_id: '',
                        description: ''
                    },
                    categoryForm: {
                        name: '',
                        description: ''
                    },
                    supplierForm: {
                        name: '',
                        email: '',
                        phone: '',
                        address: ''
                    }
                }
            },
            computed: {
                totalProducts() {
                    return this.products.length;
                },
                lowStockProducts() {
                    return this.products.filter(p => p.stock_quantity <= p.min_stock_level).length;
                },
                totalValue() {
                    return this.products.reduce((sum, p) => sum + (p.price * p.stock_quantity), 0);
                },
                totalCategories() {
                    return this.categories.length;
                }
            },
            mounted() {
                this.loadProducts();
                this.loadCategories();
                this.loadSuppliers();
            },
            methods: {
                async loadProducts(page = 1) {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=get_products&page=${page}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.products = data.products;
                            this.filteredProducts = data.products;
                            this.currentPage = data.page;
                            this.totalPages = data.total_pages;
                        }
                    } catch (error) {
                        console.error('Error loading products:', error);
                    }
                },
                
                async loadCategories() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=get_categories'
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.categories = data.categories;
                        }
                    } catch (error) {
                        console.error('Error loading categories:', error);
                    }
                },
                
                async loadSuppliers() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=get_suppliers'
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.suppliers = data.suppliers;
                        }
                    } catch (error) {
                        console.error('Error loading suppliers:', error);
                    }
                },
                
                searchProducts() {
                    if (this.searchQuery) {
                        this.filteredProducts = this.products.filter(p => 
                            p.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                            p.sku.toLowerCase().includes(this.searchQuery.toLowerCase())
                        );
                    } else {
                        this.filteredProducts = this.products;
                    }
                },
                
                filterProducts() {
                    if (this.selectedCategory) {
                        this.filteredProducts = this.products.filter(p => p.category_id == this.selectedCategory);
                    } else {
                        this.filteredProducts = this.products;
                    }
                },
                
                async addProduct() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=add_product&${new URLSearchParams(this.productForm)}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            alert('Product added successfully!');
                            this.showAddProduct = false;
                            this.productForm = {
                                name: '', sku: '', barcode: '', price: '', cost: '',
                                stock_quantity: '', min_stock_level: '', category_id: '',
                                supplier_id: '', description: ''
                            };
                            this.loadProducts();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error adding product:', error);
                        alert('Error adding product');
                    }
                },
                
                async addCategory() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=add_category&${new URLSearchParams(this.categoryForm)}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            alert('Category added successfully!');
                            this.showAddCategory = false;
                            this.categoryForm = { name: '', description: '' };
                            this.loadCategories();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error adding category:', error);
                        alert('Error adding category');
                    }
                },
                
                async addSupplier() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=add_supplier&${new URLSearchParams(this.supplierForm)}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            alert('Supplier added successfully!');
                            this.showAddSupplier = false;
                            this.supplierForm = { name: '', email: '', phone: '', address: '' };
                            this.loadSuppliers();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error adding supplier:', error);
                        alert('Error adding supplier');
                    }
                },
                
                editProduct(product) {
                    // TODO: Implement edit functionality
                    alert('Edit functionality coming soon!');
                },
                
                async deleteProduct(id) {
                    if (confirm('Are you sure you want to delete this product?')) {
                        try {
                            const response = await fetch('', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=delete_product&id=${id}`
                            });
                            const data = await response.json();
                            if (data.success) {
                                alert('Product deleted successfully!');
                                this.loadProducts();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Error deleting product:', error);
                            alert('Error deleting product');
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