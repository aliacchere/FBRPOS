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
        case 'get_settings':
            $stmt = $pdo->prepare("
                SELECT setting_key, setting_value, setting_type 
                FROM system_settings 
                WHERE tenant_id = ? OR tenant_id IS NULL
                ORDER BY tenant_id DESC, setting_key
            ");
            $stmt->execute([$user['tenant_id']]);
            $settings = $stmt->fetchAll();
            
            $settingsArray = [];
            foreach ($settings as $setting) {
                $value = $setting['setting_value'];
                if ($setting['setting_type'] === 'json') {
                    $value = json_decode($value, true);
                } elseif ($setting['setting_type'] === 'number') {
                    $value = (float) $value;
                } elseif ($setting['setting_type'] === 'boolean') {
                    $value = $value === '1' || $value === 'true';
                }
                $settingsArray[$setting['setting_key']] = $value;
            }
            
            echo json_encode(['success' => true, 'settings' => $settingsArray]);
            exit;
            
        case 'save_settings':
            $settings = $_POST['settings'];
            
            try {
                $pdo->beginTransaction();
                
                foreach ($settings as $key => $value) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                        $type = 'json';
                    } elseif (is_bool($value)) {
                        $value = $value ? '1' : '0';
                        $type = 'boolean';
                    } elseif (is_numeric($value)) {
                        $type = 'number';
                    } else {
                        $type = 'string';
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_type, updated_at)
                        VALUES (?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                    ");
                    $stmt->execute([$user['tenant_id'], $key, $value, $type]);
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
            } catch (Exception $e) {
                $pdo->rollBack();
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
    <title>Settings - DPS POS FBR Integrated</title>
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
        .setting-card:hover {
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
                        <i class="fas fa-cog text-white text-2xl mr-3"></i>
                        <h1 class="text-xl font-bold text-white">System Settings</h1>
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
                    <button @click="activeTab = 'general'" 
                            :class="activeTab === 'general' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-cog mr-2"></i>General
                    </button>
                    <button @click="activeTab = 'pos'" 
                            :class="activeTab === 'pos' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-cash-register mr-2"></i>POS Settings
                    </button>
                    <button @click="activeTab = 'tax'" 
                            :class="activeTab === 'tax' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-percentage mr-2"></i>Tax Settings
                    </button>
                    <button @click="activeTab = 'receipt'" 
                            :class="activeTab === 'receipt' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-receipt mr-2"></i>Receipt
                    </button>
                    <button @click="activeTab = 'fbr'" 
                            :class="activeTab === 'fbr' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-file-invoice mr-2"></i>FBR Integration
                    </button>
                    <button @click="activeTab = 'email'" 
                            :class="activeTab === 'email' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-envelope mr-2"></i>Email
                    </button>
                    <button @click="activeTab = 'backup'" 
                            :class="activeTab === 'backup' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-database mr-2"></i>Backup
                    </button>
                    <button @click="activeTab = 'security'" 
                            :class="activeTab === 'security' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-shield-alt mr-2"></i>Security
                    </button>
                </div>
            </div>

            <!-- General Settings -->
            <div v-if="activeTab === 'general'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">General Settings</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">Business Name</label>
                            <input v-model="settings.business_name" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Business Address</label>
                            <input v-model="settings.business_address" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Phone Number</label>
                            <input v-model="settings.business_phone" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Email Address</label>
                            <input v-model="settings.business_email" type="email" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Currency</label>
                            <select v-model="settings.currency" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="PKR">Pakistani Rupee (PKR)</option>
                                <option value="USD">US Dollar (USD)</option>
                                <option value="EUR">Euro (EUR)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Language</label>
                            <select v-model="settings.language" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="en">English</option>
                                <option value="ur">اردو (Urdu)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Timezone</label>
                            <select v-model="settings.timezone" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="Asia/Karachi">Asia/Karachi</option>
                                <option value="UTC">UTC</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Date Format</label>
                            <select v-model="settings.date_format" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="Y-m-d">YYYY-MM-DD</option>
                                <option value="d-m-Y">DD-MM-YYYY</option>
                                <option value="m/d/Y">MM/DD/YYYY</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- POS Settings -->
            <div v-if="activeTab === 'pos'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">POS Settings</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">Default Payment Method</label>
                            <select v-model="settings.default_payment_method" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="mobile">Mobile Payment</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Auto Print Receipt</label>
                            <select v-model="settings.auto_print_receipt" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Show Product Images</label>
                            <select v-model="settings.show_product_images" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Barcode Scanner</label>
                            <select v-model="settings.enable_barcode_scanner" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Quick Keys</label>
                            <select v-model="settings.enable_quick_keys" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Customer Display</label>
                            <select v-model="settings.enable_customer_display" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Receipt Printer</label>
                            <input v-model="settings.receipt_printer" type="text" 
                                   placeholder="Printer name or IP address"
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Cash Drawer</label>
                            <input v-model="settings.cash_drawer" type="text" 
                                   placeholder="Cash drawer device"
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tax Settings -->
            <div v-if="activeTab === 'tax'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">Tax Settings</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">Default Tax Rate (%)</label>
                            <input v-model="settings.default_tax_rate" type="number" step="0.01" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Tax Inclusive Pricing</label>
                            <select v-model="settings.tax_inclusive_pricing" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Show Tax on Receipt</label>
                            <select v-model="settings.show_tax_on_receipt" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Tax Registration Number</label>
                            <input v-model="settings.tax_registration_number" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Receipt Settings -->
            <div v-if="activeTab === 'receipt'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">Receipt Settings</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">Receipt Header</label>
                            <textarea v-model="settings.receipt_header" 
                                      class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                      rows="3"></textarea>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Receipt Footer</label>
                            <textarea v-model="settings.receipt_footer" 
                                      class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                      rows="3"></textarea>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Receipt Width (mm)</label>
                            <input v-model="settings.receipt_width" type="number" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Receipt Font Size</label>
                            <select v-model="settings.receipt_font_size" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="small">Small</option>
                                <option value="medium">Medium</option>
                                <option value="large">Large</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Show QR Code</label>
                            <select v-model="settings.show_qr_code" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">QR Code Type</label>
                            <select v-model="settings.qr_code_type" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="fbr">FBR Official</option>
                                <option value="pos">POS Verification</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FBR Integration -->
            <div v-if="activeTab === 'fbr'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">FBR Integration Settings</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">FBR Bearer Token</label>
                            <input v-model="settings.fbr_bearer_token" type="password" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Environment</label>
                            <select v-model="settings.fbr_environment" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="sandbox">Sandbox</option>
                                <option value="production">Production</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Auto Submit to FBR</label>
                            <select v-model="settings.auto_submit_fbr" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">FBR Timeout (seconds)</label>
                            <input v-model="settings.fbr_timeout" type="number" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email Settings -->
            <div v-if="activeTab === 'email'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">Email Settings</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">SMTP Host</label>
                            <input v-model="settings.smtp_host" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">SMTP Port</label>
                            <input v-model="settings.smtp_port" type="number" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">SMTP Username</label>
                            <input v-model="settings.smtp_username" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">SMTP Password</label>
                            <input v-model="settings.smtp_password" type="password" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">SMTP Encryption</label>
                            <select v-model="settings.smtp_encryption" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="none">None</option>
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">From Email</label>
                            <input v-model="settings.from_email" type="email" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Backup Settings -->
            <div v-if="activeTab === 'backup'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">Backup & Restore</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">Auto Backup</label>
                            <select v-model="settings.auto_backup" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Backup Frequency</label>
                            <select v-model="settings.backup_frequency" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Backup Retention (days)</label>
                            <input v-model="settings.backup_retention" type="number" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Backup Location</label>
                            <input v-model="settings.backup_location" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    
                    <div class="mt-6 flex space-x-4">
                        <button @click="createBackup" 
                                class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg transition-all">
                            <i class="fas fa-download mr-2"></i>Create Backup Now
                        </button>
                        <button @click="restoreBackup" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition-all">
                            <i class="fas fa-upload mr-2"></i>Restore Backup
                        </button>
                    </div>
                </div>
            </div>

            <!-- Security Settings -->
            <div v-if="activeTab === 'security'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">Security Settings</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">Session Timeout (minutes)</label>
                            <input v-model="settings.session_timeout" type="number" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Max Login Attempts</label>
                            <input v-model="settings.max_login_attempts" type="number" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Password Expiry (days)</label>
                            <input v-model="settings.password_expiry" type="number" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Two Factor Authentication</label>
                            <select v-model="settings.two_factor_auth" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">IP Whitelist</label>
                            <textarea v-model="settings.ip_whitelist" 
                                      placeholder="Enter IP addresses separated by commas"
                                      class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                      rows="3"></textarea>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Audit Logging</label>
                            <select v-model="settings.audit_logging" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="mt-8 flex justify-end">
                <button @click="saveSettings" 
                        class="bg-green-500 hover:bg-green-600 text-white px-8 py-3 rounded-lg font-semibold transition-all">
                    <i class="fas fa-save mr-2"></i>Save All Settings
                </button>
            </div>
        </div>
    </div>

    <script>
        const { createApp } = Vue;
        
        createApp({
            data() {
                return {
                    activeTab: 'general',
                    settings: {
                        business_name: 'DPS POS FBR Integrated',
                        business_address: '',
                        business_phone: '',
                        business_email: '',
                        currency: 'PKR',
                        language: 'en',
                        timezone: 'Asia/Karachi',
                        date_format: 'Y-m-d',
                        default_payment_method: 'cash',
                        auto_print_receipt: '1',
                        show_product_images: '1',
                        enable_barcode_scanner: '1',
                        enable_quick_keys: '1',
                        enable_customer_display: '1',
                        receipt_printer: '',
                        cash_drawer: '',
                        default_tax_rate: 16,
                        tax_inclusive_pricing: '0',
                        show_tax_on_receipt: '1',
                        tax_registration_number: '',
                        receipt_header: 'Thank you for your business!',
                        receipt_footer: 'Visit us again soon!',
                        receipt_width: 80,
                        receipt_font_size: 'medium',
                        show_qr_code: '1',
                        qr_code_type: 'fbr',
                        fbr_bearer_token: '',
                        fbr_environment: 'sandbox',
                        auto_submit_fbr: '1',
                        fbr_timeout: 30,
                        smtp_host: '',
                        smtp_port: 587,
                        smtp_username: '',
                        smtp_password: '',
                        smtp_encryption: 'tls',
                        from_email: '',
                        auto_backup: '1',
                        backup_frequency: 'daily',
                        backup_retention: 30,
                        backup_location: '/backups/',
                        session_timeout: 120,
                        max_login_attempts: 5,
                        password_expiry: 90,
                        two_factor_auth: '0',
                        ip_whitelist: '',
                        audit_logging: '1'
                    }
                }
            },
            mounted() {
                this.loadSettings();
            },
            methods: {
                async loadSettings() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=get_settings'
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.settings = { ...this.settings, ...data.settings };
                        }
                    } catch (error) {
                        console.error('Error loading settings:', error);
                    }
                },
                
                async saveSettings() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=save_settings&settings=${encodeURIComponent(JSON.stringify(this.settings))}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            alert('Settings saved successfully!');
                        } else {
                            alert('Error saving settings: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error saving settings:', error);
                        alert('Error saving settings');
                    }
                },
                
                createBackup() {
                    alert('Backup functionality will be implemented in the next update!');
                },
                
                restoreBackup() {
                    alert('Restore functionality will be implemented in the next update!');
                }
            }
        }).mount('#app');
    </script>
</body>
</html>