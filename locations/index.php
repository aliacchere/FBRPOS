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
        case 'get_locations':
            $page = $_POST['page'] ?? 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $stmt = $pdo->prepare("
                SELECT l.*, 
                       (SELECT COUNT(*) FROM users u WHERE u.location_id = l.id) as user_count,
                       (SELECT COUNT(*) FROM products p WHERE p.location_id = l.id) as product_count
                FROM locations l
                WHERE l.tenant_id = ? AND l.is_active = 1
                ORDER BY l.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user['tenant_id'], $limit, $offset]);
            $locations = $stmt->fetchAll();
            
            // Get total count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM locations WHERE tenant_id = ? AND is_active = 1");
            $stmt->execute([$user['tenant_id']]);
            $total = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true, 
                'locations' => $locations,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $limit)
            ]);
            exit;
            
        case 'add_location':
            $name = $_POST['name'];
            $address = $_POST['address'];
            $city = $_POST['city'];
            $state = $_POST['state'];
            $country = $_POST['country'];
            $postal_code = $_POST['postal_code'];
            $phone = $_POST['phone'];
            $email = $_POST['email'];
            $manager_name = $_POST['manager_name'];
            $manager_phone = $_POST['manager_phone'];
            $manager_email = $_POST['manager_email'];
            $is_main = $_POST['is_main'] ?? 0;
            
            try {
                $pdo->beginTransaction();
                
                // If this is set as main location, unset others
                if ($is_main) {
                    $stmt = $pdo->prepare("UPDATE locations SET is_main = 0 WHERE tenant_id = ?");
                    $stmt->execute([$user['tenant_id']]);
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO locations (tenant_id, name, address, city, state, country, postal_code, 
                                         phone, email, manager_name, manager_phone, manager_email, is_main, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $user['tenant_id'], $name, $address, $city, $state, $country, $postal_code,
                    $phone, $email, $manager_name, $manager_phone, $manager_email, $is_main
                ]);
                
                $locationId = $pdo->lastInsertId();
                
                // Create default inventory for this location
                $this->createDefaultInventory($pdo, $locationId, $user['tenant_id']);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Location added successfully', 'location_id' => $locationId]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_location':
            $id = $_POST['id'];
            $name = $_POST['name'];
            $address = $_POST['address'];
            $city = $_POST['city'];
            $state = $_POST['state'];
            $country = $_POST['country'];
            $postal_code = $_POST['postal_code'];
            $phone = $_POST['phone'];
            $email = $_POST['email'];
            $manager_name = $_POST['manager_name'];
            $manager_phone = $_POST['manager_phone'];
            $manager_email = $_POST['manager_email'];
            $is_main = $_POST['is_main'] ?? 0;
            
            try {
                $pdo->beginTransaction();
                
                // If this is set as main location, unset others
                if ($is_main) {
                    $stmt = $pdo->prepare("UPDATE locations SET is_main = 0 WHERE tenant_id = ? AND id != ?");
                    $stmt->execute([$user['tenant_id'], $id]);
                }
                
                $stmt = $pdo->prepare("
                    UPDATE locations 
                    SET name = ?, address = ?, city = ?, state = ?, country = ?, postal_code = ?,
                        phone = ?, email = ?, manager_name = ?, manager_phone = ?, manager_email = ?, 
                        is_main = ?, updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $name, $address, $city, $state, $country, $postal_code,
                    $phone, $email, $manager_name, $manager_phone, $manager_email, $is_main,
                    $id, $user['tenant_id']
                ]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Location updated successfully']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_location':
            $id = $_POST['id'];
            
            try {
                $stmt = $pdo->prepare("UPDATE locations SET is_active = 0 WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $user['tenant_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Location deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_location_inventory':
            $locationId = $_POST['location_id'];
            
            $stmt = $pdo->prepare("
                SELECT p.*, li.stock_quantity, li.min_stock_level, li.reorder_level
                FROM products p
                LEFT JOIN location_inventory li ON p.id = li.product_id AND li.location_id = ?
                WHERE p.tenant_id = ? AND p.is_active = 1
                ORDER BY p.name
            ");
            $stmt->execute([$locationId, $user['tenant_id']]);
            $inventory = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'inventory' => $inventory]);
            exit;
            
        case 'update_location_inventory':
            $locationId = $_POST['location_id'];
            $productId = $_POST['product_id'];
            $stockQuantity = $_POST['stock_quantity'];
            $minStockLevel = $_POST['min_stock_level'];
            $reorderLevel = $_POST['reorder_level'];
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO location_inventory (location_id, product_id, stock_quantity, min_stock_level, reorder_level, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    stock_quantity = VALUES(stock_quantity),
                    min_stock_level = VALUES(min_stock_level),
                    reorder_level = VALUES(reorder_level),
                    updated_at = NOW()
                ");
                $stmt->execute([$locationId, $productId, $stockQuantity, $minStockLevel, $reorderLevel]);
                
                echo json_encode(['success' => true, 'message' => 'Inventory updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'transfer_inventory':
            $fromLocation = $_POST['from_location'];
            $toLocation = $_POST['to_location'];
            $productId = $_POST['product_id'];
            $quantity = $_POST['quantity'];
            $notes = $_POST['notes'];
            
            try {
                $pdo->beginTransaction();
                
                // Check if source location has enough stock
                $stmt = $pdo->prepare("
                    SELECT stock_quantity FROM location_inventory 
                    WHERE location_id = ? AND product_id = ?
                ");
                $stmt->execute([$fromLocation, $productId]);
                $currentStock = $stmt->fetchColumn() ?? 0;
                
                if ($currentStock < $quantity) {
                    throw new Exception('Insufficient stock in source location');
                }
                
                // Update source location
                $stmt = $pdo->prepare("
                    UPDATE location_inventory 
                    SET stock_quantity = stock_quantity - ?, updated_at = NOW()
                    WHERE location_id = ? AND product_id = ?
                ");
                $stmt->execute([$quantity, $fromLocation, $productId]);
                
                // Update destination location
                $stmt = $pdo->prepare("
                    INSERT INTO location_inventory (location_id, product_id, stock_quantity, updated_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    stock_quantity = stock_quantity + VALUES(stock_quantity),
                    updated_at = NOW()
                ");
                $stmt->execute([$toLocation, $productId, $quantity]);
                
                // Record transfer
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_transfers (tenant_id, from_location_id, to_location_id, product_id, quantity, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$user['tenant_id'], $fromLocation, $toLocation, $productId, $quantity, $notes]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Inventory transferred successfully']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

function createDefaultInventory($pdo, $locationId, $tenantId) {
    // Get all products for this tenant
    $stmt = $pdo->prepare("SELECT id FROM products WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Create inventory records for each product
    foreach ($products as $productId) {
        $stmt = $pdo->prepare("
            INSERT INTO location_inventory (location_id, product_id, stock_quantity, min_stock_level, reorder_level, created_at)
            VALUES (?, ?, 0, 0, 0, NOW())
        ");
        $stmt->execute([$locationId, $productId]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Location Management - DPS POS FBR Integrated</title>
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
        .location-card:hover {
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
                        <i class="fas fa-map-marker-alt text-white text-2xl mr-3"></i>
                        <h1 class="text-xl font-bold text-white">Multi-Location Management</h1>
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
                <div class="flex space-x-1 bg-white bg-opacity-10 rounded-lg p-1 overflow-x-auto">
                    <button @click="activeTab = 'locations'" 
                            :class="activeTab === 'locations' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-map-marker-alt mr-2"></i>Locations
                    </button>
                    <button @click="activeTab = 'inventory'" 
                            :class="activeTab === 'inventory' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-boxes mr-2"></i>Inventory
                    </button>
                    <button @click="activeTab = 'transfers'" 
                            :class="activeTab === 'transfers' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-exchange-alt mr-2"></i>Transfers
                    </button>
                    <button @click="activeTab = 'reports'" 
                            :class="activeTab === 'reports' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-chart-bar mr-2"></i>Reports
                    </button>
                </div>
            </div>

            <!-- Locations Tab -->
            <div v-if="activeTab === 'locations'">
                <div class="glass-effect rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-white">Locations</h3>
                        <button @click="showAddLocation = true" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-all">
                            <i class="fas fa-plus mr-2"></i>Add Location
                        </button>
                    </div>
                    
                    <!-- Search -->
                    <div class="mb-6">
                        <input v-model="searchQuery" @input="searchLocations" 
                               type="text" 
                               placeholder="Search locations..." 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    
                    <!-- Locations Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="location in filteredLocations" :key="location.id" 
                             class="location-card bg-white bg-opacity-10 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="text-white font-medium">{{ location.name }}</h4>
                                <div class="flex space-x-1">
                                    <button @click="viewLocation(location)" class="text-blue-400 hover:text-blue-300">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button @click="editLocation(location)" class="text-yellow-400 hover:text-yellow-300">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button @click="deleteLocation(location.id)" class="text-red-400 hover:text-red-300">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="text-white opacity-75 text-sm mb-1">{{ location.address }}</div>
                            <div class="text-white opacity-75 text-sm mb-1">{{ location.city }}, {{ location.state }}</div>
                            <div class="text-white opacity-75 text-sm mb-2">{{ location.phone }}</div>
                            <div class="flex justify-between items-center">
                                <span v-if="location.is_main" class="bg-yellow-500 text-black px-2 py-1 rounded text-xs font-bold">
                                    Main Location
                                </span>
                                <span v-else class="text-white opacity-60 text-xs">
                                    {{ location.user_count }} users, {{ location.product_count }} products
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Tab -->
            <div v-if="activeTab === 'inventory'">
                <div class="glass-effect rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-white">Location Inventory</h3>
                        <div class="flex space-x-4">
                            <select v-model="selectedLocation" @change="loadLocationInventory" 
                                    class="px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select Location</option>
                                <option v-for="location in locations" :key="location.id" :value="location.id">
                                    {{ location.name }}
                                </option>
                            </select>
                            <button @click="showTransferModal = true" 
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-all">
                                <i class="fas fa-exchange-alt mr-2"></i>Transfer Inventory
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="selectedLocation && locationInventory.length > 0" class="overflow-x-auto">
                        <table class="w-full text-white">
                            <thead>
                                <tr class="border-b border-white border-opacity-20">
                                    <th class="text-left py-2">Product</th>
                                    <th class="text-left py-2">SKU</th>
                                    <th class="text-right py-2">Current Stock</th>
                                    <th class="text-right py-2">Min Level</th>
                                    <th class="text-right py-2">Reorder Level</th>
                                    <th class="text-center py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in locationInventory" :key="item.id" 
                                    class="border-b border-white border-opacity-10">
                                    <td class="py-2">{{ item.name }}</td>
                                    <td class="py-2">{{ item.sku }}</td>
                                    <td class="text-right py-2">{{ item.stock_quantity || 0 }}</td>
                                    <td class="text-right py-2">{{ item.min_stock_level || 0 }}</td>
                                    <td class="text-right py-2">{{ item.reorder_level || 0 }}</td>
                                    <td class="text-center py-2">
                                        <button @click="editInventory(item)" 
                                                class="text-blue-400 hover:text-blue-300">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div v-else-if="selectedLocation" class="text-center text-white opacity-75 py-8">
                        <i class="fas fa-boxes text-4xl mb-4"></i>
                        <div>No inventory found for this location</div>
                    </div>
                    
                    <div v-else class="text-center text-white opacity-75 py-8">
                        <i class="fas fa-map-marker-alt text-4xl mb-4"></i>
                        <div>Please select a location to view inventory</div>
                    </div>
                </div>
            </div>

            <!-- Transfers Tab -->
            <div v-if="activeTab === 'transfers'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">Inventory Transfers</h3>
                    
                    <div class="text-center text-white opacity-75 py-8">
                        <i class="fas fa-exchange-alt text-4xl mb-4"></i>
                        <div>Transfer history will be displayed here</div>
                        <div class="text-sm mt-2">This feature will be available in the next update</div>
                    </div>
                </div>
            </div>

            <!-- Reports Tab -->
            <div v-if="activeTab === 'reports'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">Location Reports</h3>
                    
                    <div class="text-center text-white opacity-75 py-8">
                        <i class="fas fa-chart-bar text-4xl mb-4"></i>
                        <div>Location reports will be displayed here</div>
                        <div class="text-sm mt-2">This feature will be available in the next update</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Add Location Modal -->
        <div v-if="showAddLocation" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="glass-effect rounded-lg p-8 max-w-2xl w-full mx-4 max-h-96 overflow-y-auto">
                <h3 class="text-xl font-semibold text-white mb-6">Add Location</h3>
                
                <form @submit.prevent="addLocation" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-white font-medium mb-2">Location Name</label>
                            <input v-model="locationForm.name" type="text" required 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Phone</label>
                            <input v-model="locationForm.phone" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Email</label>
                            <input v-model="locationForm.email" type="email" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">City</label>
                            <input v-model="locationForm.city" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">State</label>
                            <input v-model="locationForm.state" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Postal Code</label>
                            <input v-model="locationForm.postal_code" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-white font-medium mb-2">Address</label>
                        <textarea v-model="locationForm.address" 
                                  class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                  rows="3"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-white font-medium mb-2">Manager Name</label>
                            <input v-model="locationForm.manager_name" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Manager Phone</label>
                            <input v-model="locationForm.manager_phone" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-white font-medium mb-2">Manager Email</label>
                        <input v-model="locationForm.manager_email" type="email" 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    
                    <div>
                        <label class="flex items-center text-white">
                            <input v-model="locationForm.is_main" type="checkbox" class="mr-2">
                            Set as Main Location
                        </label>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="button" @click="showAddLocation = false" 
                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-2 rounded-lg transition-all">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg transition-all">
                            Add Location
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
                    activeTab: 'locations',
                    locations: [],
                    filteredLocations: [],
                    searchQuery: '',
                    currentPage: 1,
                    totalPages: 1,
                    showAddLocation: false,
                    selectedLocation: '',
                    locationInventory: [],
                    locationForm: {
                        name: '',
                        address: '',
                        city: '',
                        state: '',
                        country: 'Pakistan',
                        postal_code: '',
                        phone: '',
                        email: '',
                        manager_name: '',
                        manager_phone: '',
                        manager_email: '',
                        is_main: false
                    }
                }
            },
            mounted() {
                this.loadLocations();
            },
            methods: {
                async loadLocations(page = 1) {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=get_locations&page=${page}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.locations = data.locations;
                            this.filteredLocations = data.locations;
                            this.currentPage = data.page;
                            this.totalPages = data.total_pages;
                        }
                    } catch (error) {
                        console.error('Error loading locations:', error);
                    }
                },
                
                searchLocations() {
                    if (this.searchQuery) {
                        this.filteredLocations = this.locations.filter(l => 
                            l.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                            l.city.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                            l.address.toLowerCase().includes(this.searchQuery.toLowerCase())
                        );
                    } else {
                        this.filteredLocations = this.locations;
                    }
                },
                
                async addLocation() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=add_location&${new URLSearchParams(this.locationForm)}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            alert('Location added successfully!');
                            this.showAddLocation = false;
                            this.locationForm = {
                                name: '', address: '', city: '', state: '', country: 'Pakistan',
                                postal_code: '', phone: '', email: '', manager_name: '',
                                manager_phone: '', manager_email: '', is_main: false
                            };
                            this.loadLocations();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error adding location:', error);
                        alert('Error adding location');
                    }
                },
                
                viewLocation(location) {
                    alert(`View location details for ${location.name} - Coming soon!`);
                },
                
                editLocation(location) {
                    alert(`Edit location ${location.name} - Coming soon!`);
                },
                
                async deleteLocation(id) {
                    if (confirm('Are you sure you want to delete this location?')) {
                        try {
                            const response = await fetch('', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=delete_location&id=${id}`
                            });
                            const data = await response.json();
                            if (data.success) {
                                alert('Location deleted successfully!');
                                this.loadLocations();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Error deleting location:', error);
                            alert('Error deleting location');
                        }
                    }
                },
                
                async loadLocationInventory() {
                    if (!this.selectedLocation) return;
                    
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=get_location_inventory&location_id=${this.selectedLocation}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.locationInventory = data.inventory;
                        }
                    } catch (error) {
                        console.error('Error loading location inventory:', error);
                    }
                },
                
                editInventory(item) {
                    alert(`Edit inventory for ${item.name} - Coming soon!`);
                }
            }
        }).mount('#app');
    </script>
</body>
</html>