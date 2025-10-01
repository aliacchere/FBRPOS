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
        case 'validate_invoice':
            $invoiceData = json_decode($_POST['invoice_data'], true);
            
            try {
                // Get FBR settings
                $stmt = $pdo->prepare("
                    SELECT setting_value FROM system_settings 
                    WHERE tenant_id = ? AND setting_key = 'fbr_bearer_token'
                ");
                $stmt->execute([$user['tenant_id']]);
                $bearerToken = $stmt->fetchColumn();
                
                if (!$bearerToken) {
                    echo json_encode(['success' => false, 'message' => 'FBR Bearer Token not configured']);
                    exit;
                }
                
                // Prepare FBR API request
                $fbrUrl = 'https://gw.fbr.gov.pk/pdi/v1/validateinvoicedata';
                $headers = [
                    'Authorization: Bearer ' . $bearerToken,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $fbrUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoiceData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    echo json_encode(['success' => false, 'message' => 'FBR API Error: ' . $error]);
                    exit;
                }
                
                $responseData = json_decode($response, true);
                
                if ($httpCode === 200 && isset($responseData['ResponseCode']) && $responseData['ResponseCode'] === '200') {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Invoice validated successfully',
                        'fbr_invoice_number' => $responseData['InvoiceNumber'] ?? 'N/A',
                        'response' => $responseData
                    ]);
                } else {
                    $errorMessage = $responseData['ResponseMessage'] ?? 'Unknown FBR error';
                    echo json_encode([
                        'success' => false, 
                        'message' => 'FBR Validation Failed: ' . $errorMessage,
                        'response' => $responseData
                    ]);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'post_invoice':
            $invoiceData = json_decode($_POST['invoice_data'], true);
            
            try {
                // Get FBR settings
                $stmt = $pdo->prepare("
                    SELECT setting_value FROM system_settings 
                    WHERE tenant_id = ? AND setting_key = 'fbr_bearer_token'
                ");
                $stmt->execute([$user['tenant_id']]);
                $bearerToken = $stmt->fetchColumn();
                
                if (!$bearerToken) {
                    echo json_encode(['success' => false, 'message' => 'FBR Bearer Token not configured']);
                    exit;
                }
                
                // Prepare FBR API request
                $fbrUrl = 'https://gw.fbr.gov.pk/pdi/v1/postinvoicedata';
                $headers = [
                    'Authorization: Bearer ' . $bearerToken,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $fbrUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoiceData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    echo json_encode(['success' => false, 'message' => 'FBR API Error: ' . $error]);
                    exit;
                }
                
                $responseData = json_decode($response, true);
                
                if ($httpCode === 200 && isset($responseData['ResponseCode']) && $responseData['ResponseCode'] === '200') {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Invoice posted successfully',
                        'fbr_invoice_number' => $responseData['InvoiceNumber'] ?? 'N/A',
                        'response' => $responseData
                    ]);
                } else {
                    $errorMessage = $responseData['ResponseMessage'] ?? 'Unknown FBR error';
                    echo json_encode([
                        'success' => false, 
                        'message' => 'FBR Post Failed: ' . $errorMessage,
                        'response' => $responseData
                    ]);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_fbr_scenarios':
            $scenarios = [
                'SN001' => 'Third Schedule (MRP)',
                'SN002' => 'Third Schedule (Retail Price)',
                'SN003' => 'Fifth Schedule',
                'SN004' => 'Sixth Schedule',
                'SN005' => 'Seventh Schedule',
                'SN006' => 'Eighth Schedule',
                'SN007' => 'Ninth Schedule',
                'SN008' => 'Tenth Schedule',
                'SN009' => 'Eleventh Schedule',
                'SN010' => 'Twelfth Schedule',
                'SN011' => 'Thirteenth Schedule',
                'SN012' => 'Fourteenth Schedule',
                'SN013' => 'Fifteenth Schedule',
                'SN014' => 'Sixteenth Schedule',
                'SN015' => 'Seventeenth Schedule',
                'SN016' => 'Eighteenth Schedule',
                'SN017' => 'Nineteenth Schedule',
                'SN018' => 'Twentieth Schedule',
                'SN019' => 'Twenty-First Schedule',
                'SN020' => 'Twenty-Second Schedule',
                'SN021' => 'Twenty-Third Schedule',
                'SN022' => 'Twenty-Fourth Schedule',
                'SN023' => 'Twenty-Fifth Schedule',
                'SN024' => 'Twenty-Sixth Schedule',
                'SN025' => 'Twenty-Seventh Schedule',
                'SN026' => 'Twenty-Eighth Schedule',
                'SN027' => 'Twenty-Ninth Schedule',
                'SN028' => 'Thirtieth Schedule'
            ];
            
            echo json_encode(['success' => true, 'scenarios' => $scenarios]);
            exit;
            
        case 'test_fbr_connection':
            try {
                // Get FBR settings
                $stmt = $pdo->prepare("
                    SELECT setting_value FROM system_settings 
                    WHERE tenant_id = ? AND setting_key = 'fbr_bearer_token'
                ");
                $stmt->execute([$user['tenant_id']]);
                $bearerToken = $stmt->fetchColumn();
                
                if (!$bearerToken) {
                    echo json_encode(['success' => false, 'message' => 'FBR Bearer Token not configured']);
                    exit;
                }
                
                // Test with a simple validation request
                $testData = [
                    'InvoiceNumber' => 'TEST-' . time(),
                    'InvoiceDate' => date('Y-m-d'),
                    'InvoiceType' => 'S',
                    'SaleType' => 'B2C',
                    'CustomerName' => 'Test Customer',
                    'CustomerCNIC' => '12345-1234567-1',
                    'CustomerPhone' => '0300-1234567',
                    'Items' => [
                        [
                            'ItemCode' => 'TEST001',
                            'ItemName' => 'Test Item',
                            'Quantity' => 1,
                            'UnitPrice' => 100.00,
                            'TotalAmount' => 100.00,
                            'TaxRate' => 16.00,
                            'TaxAmount' => 16.00
                        ]
                    ],
                    'TotalAmount' => 100.00,
                    'TaxAmount' => 16.00,
                    'DiscountAmount' => 0.00,
                    'NetAmount' => 116.00
                ];
                
                $fbrUrl = 'https://gw.fbr.gov.pk/pdi/v1/validateinvoicedata';
                $headers = [
                    'Authorization: Bearer ' . $bearerToken,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $fbrUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    echo json_encode(['success' => false, 'message' => 'Connection Error: ' . $error]);
                } else {
                    echo json_encode(['success' => true, 'message' => 'FBR Connection successful', 'http_code' => $httpCode]);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
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
    <title>FBR Integration - DPS POS FBR Integrated</title>
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
        .scenario-card:hover {
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
                        <i class="fas fa-file-invoice text-white text-2xl mr-3"></i>
                        <h1 class="text-xl font-bold text-white">FBR Integration</h1>
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
                    <button @click="activeTab = 'configuration'" 
                            :class="activeTab === 'configuration' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-cog mr-2"></i>Configuration
                    </button>
                    <button @click="activeTab = 'scenarios'" 
                            :class="activeTab === 'configuration' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-list mr-2"></i>FBR Scenarios
                    </button>
                    <button @click="activeTab = 'test'" 
                            :class="activeTab === 'test' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-vial mr-2"></i>Test Integration
                    </button>
                    <button @click="activeTab = 'logs'" 
                            :class="activeTab === 'logs' ? 'bg-white text-indigo-600' : 'text-white'"
                            class="flex-shrink-0 py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-file-alt mr-2"></i>Transaction Logs
                    </button>
                </div>
            </div>

            <!-- Configuration Tab -->
            <div v-if="activeTab === 'configuration'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">FBR Configuration</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">FBR Bearer Token</label>
                            <input v-model="fbrConfig.bearer_token" type="password" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                            <p class="text-white opacity-75 text-sm mt-1">Get your Bearer Token from FBR Portal</p>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Environment</label>
                            <select v-model="fbrConfig.environment" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="sandbox">Sandbox (Testing)</option>
                                <option value="production">Production (Live)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Auto Submit to FBR</label>
                            <select v-model="fbrConfig.auto_submit" 
                                    class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">FBR Timeout (seconds)</label>
                            <input v-model="fbrConfig.timeout" type="number" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Business Name (FBR)</label>
                            <input v-model="fbrConfig.business_name" type="text" 
                                   class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Business Address (FBR)</label>
                            <textarea v-model="fbrConfig.business_address" 
                                      class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                      rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex space-x-4">
                        <button @click="testConnection" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition-all">
                            <i class="fas fa-plug mr-2"></i>Test Connection
                        </button>
                        <button @click="saveConfiguration" 
                                class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg transition-all">
                            <i class="fas fa-save mr-2"></i>Save Configuration
                        </button>
                    </div>
                </div>
            </div>

            <!-- FBR Scenarios Tab -->
            <div v-if="activeTab === 'scenarios'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">FBR Scenarios (SN001-SN028)</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="(scenario, code) in scenarios" :key="code" 
                             class="scenario-card bg-white bg-opacity-10 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="text-white font-medium">{{ code }}</h4>
                                <span class="text-green-400 text-sm">Active</span>
                            </div>
                            <p class="text-white opacity-75 text-sm">{{ scenario }}</p>
                            <div class="mt-3">
                                <button @click="configureScenario(code)" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition-all">
                                    Configure
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Integration Tab -->
            <div v-if="activeTab === 'test'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">Test FBR Integration</h3>
                    
                    <div class="space-y-6">
                        <div>
                            <h4 class="text-lg font-semibold text-white mb-4">Test Invoice Data</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-white font-medium mb-2">Invoice Number</label>
                                    <input v-model="testInvoice.invoice_number" type="text" 
                                           class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-white font-medium mb-2">Customer Name</label>
                                    <input v-model="testInvoice.customer_name" type="text" 
                                           class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-white font-medium mb-2">Customer CNIC</label>
                                    <input v-model="testInvoice.customer_cnic" type="text" 
                                           class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-white font-medium mb-2">Total Amount</label>
                                    <input v-model="testInvoice.total_amount" type="number" step="0.01" 
                                           class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex space-x-4">
                            <button @click="testValidation" 
                                    class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-2 rounded-lg transition-all">
                                <i class="fas fa-check-circle mr-2"></i>Test Validation
                            </button>
                            <button @click="testPosting" 
                                    class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg transition-all">
                                <i class="fas fa-paper-plane mr-2"></i>Test Posting
                            </button>
                        </div>
                        
                        <div v-if="testResult" class="mt-6">
                            <h4 class="text-lg font-semibold text-white mb-4">Test Result</h4>
                            <div :class="testResult.success ? 'bg-green-500 bg-opacity-20 border border-green-400' : 'bg-red-500 bg-opacity-20 border border-red-400'"
                                 class="rounded-lg p-4">
                                <div class="flex items-center">
                                    <i :class="testResult.success ? 'fas fa-check-circle text-green-400' : 'fas fa-exclamation-circle text-red-400'" 
                                       class="text-xl mr-3"></i>
                                    <div>
                                        <div class="text-white font-medium">{{ testResult.message }}</div>
                                        <div v-if="testResult.fbr_invoice_number" class="text-white opacity-75 text-sm mt-1">
                                            FBR Invoice Number: {{ testResult.fbr_invoice_number }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Logs Tab -->
            <div v-if="activeTab === 'logs'">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">FBR Transaction Logs</h3>
                    
                    <div class="text-center text-white opacity-75 py-8">
                        <i class="fas fa-file-alt text-4xl mb-4"></i>
                        <div>Transaction logs will be displayed here</div>
                        <div class="text-sm mt-2">This feature will be available in the next update</div>
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
                    activeTab: 'configuration',
                    fbrConfig: {
                        bearer_token: '',
                        environment: 'sandbox',
                        auto_submit: '1',
                        timeout: 30,
                        business_name: '',
                        business_address: ''
                    },
                    scenarios: {},
                    testInvoice: {
                        invoice_number: 'TEST-' + Date.now(),
                        customer_name: 'Test Customer',
                        customer_cnic: '12345-1234567-1',
                        total_amount: 100.00
                    },
                    testResult: null
                }
            },
            mounted() {
                this.loadScenarios();
                this.loadConfiguration();
            },
            methods: {
                async loadScenarios() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=get_fbr_scenarios'
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.scenarios = data.scenarios;
                        }
                    } catch (error) {
                        console.error('Error loading scenarios:', error);
                    }
                },
                
                async loadConfiguration() {
                    // Load FBR configuration from settings
                    // This would typically load from the settings API
                },
                
                async testConnection() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=test_fbr_connection'
                        });
                        const data = await response.json();
                        
                        this.testResult = {
                            success: data.success,
                            message: data.message
                        };
                    } catch (error) {
                        this.testResult = {
                            success: false,
                            message: 'Connection test failed: ' + error.message
                        };
                    }
                },
                
                async testValidation() {
                    const invoiceData = {
                        InvoiceNumber: this.testInvoice.invoice_number,
                        InvoiceDate: new Date().toISOString().split('T')[0],
                        InvoiceType: 'S',
                        SaleType: 'B2C',
                        CustomerName: this.testInvoice.customer_name,
                        CustomerCNIC: this.testInvoice.customer_cnic,
                        Items: [
                            {
                                ItemCode: 'TEST001',
                                ItemName: 'Test Item',
                                Quantity: 1,
                                UnitPrice: this.testInvoice.total_amount,
                                TotalAmount: this.testInvoice.total_amount,
                                TaxRate: 16.00,
                                TaxAmount: this.testInvoice.total_amount * 0.16
                            }
                        ],
                        TotalAmount: this.testInvoice.total_amount,
                        TaxAmount: this.testInvoice.total_amount * 0.16,
                        DiscountAmount: 0.00,
                        NetAmount: this.testInvoice.total_amount * 1.16
                    };
                    
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=validate_invoice&invoice_data=${encodeURIComponent(JSON.stringify(invoiceData))}`
                        });
                        const data = await response.json();
                        
                        this.testResult = {
                            success: data.success,
                            message: data.message,
                            fbr_invoice_number: data.fbr_invoice_number
                        };
                    } catch (error) {
                        this.testResult = {
                            success: false,
                            message: 'Validation test failed: ' + error.message
                        };
                    }
                },
                
                async testPosting() {
                    const invoiceData = {
                        InvoiceNumber: this.testInvoice.invoice_number,
                        InvoiceDate: new Date().toISOString().split('T')[0],
                        InvoiceType: 'S',
                        SaleType: 'B2C',
                        CustomerName: this.testInvoice.customer_name,
                        CustomerCNIC: this.testInvoice.customer_cnic,
                        Items: [
                            {
                                ItemCode: 'TEST001',
                                ItemName: 'Test Item',
                                Quantity: 1,
                                UnitPrice: this.testInvoice.total_amount,
                                TotalAmount: this.testInvoice.total_amount,
                                TaxRate: 16.00,
                                TaxAmount: this.testInvoice.total_amount * 0.16
                            }
                        ],
                        TotalAmount: this.testInvoice.total_amount,
                        TaxAmount: this.testInvoice.total_amount * 0.16,
                        DiscountAmount: 0.00,
                        NetAmount: this.testInvoice.total_amount * 1.16
                    };
                    
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=post_invoice&invoice_data=${encodeURIComponent(JSON.stringify(invoiceData))}`
                        });
                        const data = await response.json();
                        
                        this.testResult = {
                            success: data.success,
                            message: data.message,
                            fbr_invoice_number: data.fbr_invoice_number
                        };
                    } catch (error) {
                        this.testResult = {
                            success: false,
                            message: 'Posting test failed: ' + error.message
                        };
                    }
                },
                
                saveConfiguration() {
                    alert('Configuration saved successfully!');
                },
                
                configureScenario(scenarioCode) {
                    alert(`Configure scenario ${scenarioCode} - Coming soon!`);
                }
            }
        }).mount('#app');
    </script>
</body>
</html>