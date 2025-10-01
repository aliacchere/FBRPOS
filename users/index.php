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
        case 'get_users':
            $page = $_POST['page'] ?? 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $stmt = $pdo->prepare("
                SELECT u.*, t.name as tenant_name
                FROM users u
                LEFT JOIN tenants t ON u.tenant_id = t.id
                WHERE u.tenant_id = ? OR u.role = 'super_admin'
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user['tenant_id'], $limit, $offset]);
            $users = $stmt->fetchAll();
            
            // Get total count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? OR role = 'super_admin'");
            $stmt->execute([$user['tenant_id']]);
            $total = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true, 
                'users' => $users,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $limit)
            ]);
            exit;
            
        case 'add_user':
            $name = $_POST['name'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            $role = $_POST['role'];
            $phone = $_POST['phone'];
            $is_active = $_POST['is_active'];
            
            try {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (tenant_id, name, email, password, role, phone, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$user['tenant_id'], $name, $email, $hashedPassword, $role, $phone, $is_active]);
                
                echo json_encode(['success' => true, 'message' => 'User added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_user':
            $id = $_POST['id'];
            $name = $_POST['name'];
            $email = $_POST['email'];
            $role = $_POST['role'];
            $phone = $_POST['phone'];
            $is_active = $_POST['is_active'];
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, role = ?, phone = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$name, $email, $role, $phone, $is_active, $id, $user['tenant_id']]);
                
                echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'change_password':
            $id = $_POST['id'];
            $new_password = $_POST['new_password'];
            
            try {
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET password = ?, updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$hashedPassword, $id, $user['tenant_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_user':
            $id = $_POST['id'];
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $user['tenant_id']]);
                
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_roles':
            $roles = [
                ['value' => 'super_admin', 'label' => 'Super Admin', 'description' => 'Full system access'],
                ['value' => 'tenant_admin', 'label' => 'Tenant Admin', 'description' => 'Tenant management access'],
                ['value' => 'manager', 'label' => 'Manager', 'description' => 'Store management access'],
                ['value' => 'cashier', 'label' => 'Cashier', 'description' => 'POS and sales access'],
                ['value' => 'inventory', 'label' => 'Inventory Manager', 'description' => 'Inventory management access'],
                ['value' => 'reports', 'label' => 'Reports Viewer', 'description' => 'Reports and analytics access']
            ];
            
            echo json_encode(['success' => true, 'roles' => $roles]);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - DPS POS FBR Integrated</title>
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
        .user-card:hover {
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
                        <i class="fas fa-user-cog text-white text-2xl mr-3"></i>
                        <h1 class="text-xl font-bold text-white">User Management</h1>
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
                    <h3 class="text-xl font-semibold text-white">Users</h3>
                    <button @click="showAddUser = true" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-all">
                        <i class="fas fa-plus mr-2"></i>Add User
                    </button>
                </div>
                
                <!-- Search and Filters -->
                <div class="flex space-x-4 mb-6">
                    <div class="flex-1">
                        <input v-model="searchQuery" @input="searchUsers" 
                               type="text" 
                               placeholder="Search users..." 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <select v-model="selectedRole" @change="filterUsers" 
                            class="px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        <option value="">All Roles</option>
                        <option v-for="role in roles" :key="role.value" :value="role.value">
                            {{ role.label }}
                        </option>
                    </select>
                </div>
                
                <!-- Users Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div v-for="user in filteredUsers" :key="user.id" 
                         class="user-card bg-white bg-opacity-10 rounded-lg p-4">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="text-white font-medium">{{ user.name }}</h4>
                            <div class="flex space-x-1">
                                <button @click="viewUser(user)" class="text-blue-400 hover:text-blue-300">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button @click="editUser(user)" class="text-yellow-400 hover:text-yellow-300">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button @click="changePassword(user)" class="text-green-400 hover:text-green-300">
                                    <i class="fas fa-key"></i>
                                </button>
                                <button @click="deleteUser(user.id)" class="text-red-400 hover:text-red-300">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="text-white opacity-75 text-sm mb-1">{{ user.email }}</div>
                        <div class="text-white opacity-75 text-sm mb-2">{{ user.phone || 'No phone' }}</div>
                        <div class="flex justify-between items-center">
                            <span :class="getRoleColor(user.role)" 
                                  class="px-2 py-1 rounded text-xs font-medium">
                                {{ getRoleLabel(user.role) }}
                            </span>
                            <span :class="user.is_active ? 'text-green-400' : 'text-red-400'"
                                  class="text-xs font-medium">
                                {{ user.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="flex justify-center mt-6">
                    <button @click="loadUsers(currentPage - 1)" 
                            :disabled="currentPage <= 1"
                            class="px-4 py-2 bg-white bg-opacity-20 text-white rounded-lg mr-2 disabled:opacity-50">
                        Previous
                    </button>
                    <span class="text-white px-4 py-2">{{ currentPage }} of {{ totalPages }}</span>
                    <button @click="loadUsers(currentPage + 1)" 
                            :disabled="currentPage >= totalPages"
                            class="px-4 py-2 bg-white bg-opacity-20 text-white rounded-lg ml-2 disabled:opacity-50">
                        Next
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Add User Modal -->
        <div v-if="showAddUser" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="glass-effect rounded-lg p-8 max-w-md w-full mx-4">
                <h3 class="text-xl font-semibold text-white mb-6">Add User</h3>
                
                <form @submit.prevent="addUser" class="space-y-4">
                    <div>
                        <label class="block text-white font-medium mb-2">Full Name</label>
                        <input v-model="userForm.name" type="text" required 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Email Address</label>
                        <input v-model="userForm.email" type="email" required 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Password</label>
                        <input v-model="userForm.password" type="password" required 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Role</label>
                        <select v-model="userForm.role" required 
                                class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select Role</option>
                            <option v-for="role in roles" :key="role.value" :value="role.value">
                                {{ role.label }} - {{ role.description }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Phone Number</label>
                        <input v-model="userForm.phone" type="text" 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="flex items-center text-white">
                            <input v-model="userForm.is_active" type="checkbox" class="mr-2">
                            Active User
                        </label>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="button" @click="showAddUser = false" 
                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-2 rounded-lg transition-all">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg transition-all">
                            Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password Modal -->
        <div v-if="showChangePassword" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="glass-effect rounded-lg p-8 max-w-md w-full mx-4">
                <h3 class="text-xl font-semibold text-white mb-6">Change Password</h3>
                
                <form @submit.prevent="changeUserPassword" class="space-y-4">
                    <div>
                        <label class="block text-white font-medium mb-2">New Password</label>
                        <input v-model="passwordForm.new_password" type="password" required 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-white font-medium mb-2">Confirm Password</label>
                        <input v-model="passwordForm.confirm_password" type="password" required 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="button" @click="showChangePassword = false" 
                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-2 rounded-lg transition-all">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg transition-all">
                            Change Password
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
                    users: [],
                    filteredUsers: [],
                    roles: [],
                    searchQuery: '',
                    selectedRole: '',
                    currentPage: 1,
                    totalPages: 1,
                    showAddUser: false,
                    showChangePassword: false,
                    selectedUserId: null,
                    userForm: {
                        name: '',
                        email: '',
                        password: '',
                        role: '',
                        phone: '',
                        is_active: true
                    },
                    passwordForm: {
                        new_password: '',
                        confirm_password: ''
                    }
                }
            },
            mounted() {
                this.loadUsers();
                this.loadRoles();
            },
            methods: {
                async loadUsers(page = 1) {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=get_users&page=${page}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.users = data.users;
                            this.filteredUsers = data.users;
                            this.currentPage = data.page;
                            this.totalPages = data.total_pages;
                        }
                    } catch (error) {
                        console.error('Error loading users:', error);
                    }
                },
                
                async loadRoles() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=get_roles'
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.roles = data.roles;
                        }
                    } catch (error) {
                        console.error('Error loading roles:', error);
                    }
                },
                
                searchUsers() {
                    if (this.searchQuery) {
                        this.filteredUsers = this.users.filter(u => 
                            u.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                            u.email.toLowerCase().includes(this.searchQuery.toLowerCase())
                        );
                    } else {
                        this.filteredUsers = this.users;
                    }
                },
                
                filterUsers() {
                    if (this.selectedRole) {
                        this.filteredUsers = this.users.filter(u => u.role === this.selectedRole);
                    } else {
                        this.filteredUsers = this.users;
                    }
                },
                
                async addUser() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=add_user&${new URLSearchParams(this.userForm)}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            alert('User added successfully!');
                            this.showAddUser = false;
                            this.userForm = {
                                name: '', email: '', password: '', role: '', phone: '', is_active: true
                            };
                            this.loadUsers();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error adding user:', error);
                        alert('Error adding user');
                    }
                },
                
                viewUser(user) {
                    alert(`View user details for ${user.name} - Coming soon!`);
                },
                
                editUser(user) {
                    alert(`Edit user ${user.name} - Coming soon!`);
                },
                
                changePassword(user) {
                    this.selectedUserId = user.id;
                    this.showChangePassword = true;
                    this.passwordForm = { new_password: '', confirm_password: '' };
                },
                
                async changeUserPassword() {
                    if (this.passwordForm.new_password !== this.passwordForm.confirm_password) {
                        alert('Passwords do not match!');
                        return;
                    }
                    
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=change_password&id=${this.selectedUserId}&new_password=${this.passwordForm.new_password}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            alert('Password changed successfully!');
                            this.showChangePassword = false;
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error changing password:', error);
                        alert('Error changing password');
                    }
                },
                
                async deleteUser(id) {
                    if (confirm('Are you sure you want to delete this user?')) {
                        try {
                            const response = await fetch('', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=delete_user&id=${id}`
                            });
                            const data = await response.json();
                            if (data.success) {
                                alert('User deleted successfully!');
                                this.loadUsers();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Error deleting user:', error);
                            alert('Error deleting user');
                        }
                    }
                },
                
                getRoleLabel(role) {
                    const roleObj = this.roles.find(r => r.value === role);
                    return roleObj ? roleObj.label : role;
                },
                
                getRoleColor(role) {
                    const colors = {
                        'super_admin': 'bg-red-500 text-white',
                        'tenant_admin': 'bg-purple-500 text-white',
                        'manager': 'bg-blue-500 text-white',
                        'cashier': 'bg-green-500 text-white',
                        'inventory': 'bg-yellow-500 text-black',
                        'reports': 'bg-indigo-500 text-white'
                    };
                    return colors[role] || 'bg-gray-500 text-white';
                }
            }
        }).mount('#app');
    </script>
</body>
</html>