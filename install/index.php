<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DPS POS FBR Integrated - Installation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .step-indicator {
            transition: all 0.3s ease;
        }
        .step-active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: scale(1.1);
        }
        .step-completed {
            background: #10b981;
            color: white;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .requirement-pass {
            color: #10b981;
        }
        .requirement-fail {
            color: #ef4444;
        }
        .loading-spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <div id="app" class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8 fade-in">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full mb-4 shadow-lg">
                <i class="fas fa-cash-register text-3xl text-indigo-600"></i>
            </div>
            <h1 class="text-4xl font-bold text-white mb-2">DPS POS FBR Integrated</h1>
            <p class="text-xl text-white opacity-90">Ultimate PHP SaaS Script for Pakistani Businesses</p>
            <p class="text-white opacity-75 mt-2">Version 2.0.0 - Enterprise Edition</p>
        </div>

        <!-- Installation Steps -->
        <div class="max-w-4xl mx-auto">
            <!-- Step Indicator -->
            <div class="flex justify-center mb-8">
                <div class="flex items-center space-x-4">
                    <div v-for="(step, index) in steps" :key="index" class="flex items-center">
                        <div :class="getStepClass(index)" class="step-indicator w-10 h-10 rounded-full flex items-center justify-center font-bold">
                            {{ index + 1 }}
                        </div>
                        <div v-if="index < steps.length - 1" class="w-16 h-1 bg-white opacity-30"></div>
                    </div>
                </div>
            </div>

            <!-- Installation Form -->
            <div class="glass-effect rounded-2xl p-8 shadow-2xl">
                <!-- Step 1: Requirements Check -->
                <div v-if="currentStep === 0" class="step-content">
                    <h2 class="text-2xl font-bold text-white mb-6 text-center">System Requirements Check</h2>
                    
                    <div class="space-y-4 mb-6">
                        <div v-for="requirement in requirements" :key="requirement.name" 
                             :class="requirement.status ? 'bg-green-500 bg-opacity-20 border-green-400' : 'bg-red-500 bg-opacity-20 border-red-400'"
                             class="flex items-center justify-between p-4 rounded-lg border-l-4">
                            <div class="flex items-center flex-1">
                                <i :class="requirement.status ? 'fas fa-check-circle text-green-400' : 'fas fa-times-circle text-red-400'" 
                                   class="text-xl mr-3"></i>
                                <div>
                                    <span class="text-white font-medium">{{ requirement.name }}</span>
                                    <div v-if="requirement.message" class="text-white opacity-75 text-sm mt-1">
                                        {{ requirement.message }}
                                    </div>
                                    <div v-if="requirement.details" class="text-white opacity-60 text-xs mt-1">
                                        <div v-if="requirement.details.writable" class="text-green-300">
                                            ✓ Writable: {{ requirement.details.writable.join(', ') }}
                                        </div>
                                        <div v-if="requirement.details.non_writable" class="text-red-300">
                                            ✗ Not writable: {{ requirement.details.non_writable.join(', ') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-white text-sm font-medium">{{ requirement.current }}</div>
                                <div class="text-white opacity-75 text-xs">{{ requirement.required }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div v-if="systemInfo" class="mt-8 p-6 bg-white bg-opacity-10 rounded-lg">
                        <h3 class="text-lg font-semibold text-white mb-4">
                            <i class="fas fa-info-circle mr-2"></i>System Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div class="text-white">
                                <span class="opacity-75">PHP Version:</span>
                                <span class="font-medium">{{ systemInfo.php_version }}</span>
                            </div>
                            <div class="text-white">
                                <span class="opacity-75">Server Software:</span>
                                <span class="font-medium">{{ systemInfo.server_software }}</span>
                            </div>
                            <div class="text-white">
                                <span class="opacity-75">Operating System:</span>
                                <span class="font-medium">{{ systemInfo.os }}</span>
                            </div>
                            <div class="text-white">
                                <span class="opacity-75">Architecture:</span>
                                <span class="font-medium">{{ systemInfo.architecture }}</span>
                            </div>
                            <div class="text-white">
                                <span class="opacity-75">Timezone:</span>
                                <span class="font-medium">{{ systemInfo.timezone }}</span>
                            </div>
                            <div class="text-white">
                                <span class="opacity-75">SAPI:</span>
                                <span class="font-medium">{{ systemInfo.sapi }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="requirementsCheckComplete" class="text-center mt-8">
                        <div v-if="allRequirementsMet" class="text-green-400 text-lg font-semibold mb-4">
                            <i class="fas fa-check-circle mr-2"></i>All requirements met! Ready to proceed.
                        </div>
                        <div v-else class="text-red-400 text-lg font-semibold mb-4">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Please fix the requirements above before continuing.
                        </div>
                        <button @click="nextStep" 
                                :disabled="!allRequirementsMet"
                                :class="allRequirementsMet ? 'btn-primary' : 'btn-disabled'"
                                class="px-8 py-3 rounded-lg font-semibold transition-all duration-300">
                            Continue to Database Setup
                        </button>
                    </div>
                </div>

                <!-- Step 2: Database Setup -->
                <div v-if="currentStep === 1" class="step-content">
                    <h2 class="text-2xl font-bold text-white mb-6 text-center">Database Configuration</h2>
                    
                    <!-- Database Connection Test Result -->
                    <div v-if="databaseTestResult" class="mb-6 p-4 rounded-lg" 
                         :class="databaseTestResult.success ? 'bg-green-500 bg-opacity-20 border-green-400' : 'bg-red-500 bg-opacity-20 border-red-400'">
                        <div class="flex items-center">
                            <i :class="databaseTestResult.success ? 'fas fa-check-circle text-green-400' : 'fas fa-times-circle text-red-400'" 
                               class="text-xl mr-3"></i>
                            <div>
                                <div class="text-white font-medium">{{ databaseTestResult.success ? 'Connection Successful!' : 'Connection Failed' }}</div>
                                <div v-if="databaseTestResult.message" class="text-white opacity-75 text-sm mt-1">
                                    {{ databaseTestResult.message }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">
                                Database Host
                                <span class="text-red-400">*</span>
                            </label>
                            <input v-model="database.host" type="text" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="localhost" required>
                            <div class="text-white opacity-60 text-xs mt-1">Usually 'localhost' for local installations</div>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">
                                Database Name
                                <span class="text-red-400">*</span>
                            </label>
                            <input v-model="database.name" type="text" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="dpspos_fbr" required>
                            <div class="text-white opacity-60 text-xs mt-1">Database will be created if it doesn't exist</div>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">
                                Username
                                <span class="text-red-400">*</span>
                            </label>
                            <input v-model="database.username" type="text" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="root" required>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Password</label>
                            <input v-model="database.password" type="password" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="Enter password">
                            <div class="text-white opacity-60 text-xs mt-1">Leave empty if no password is set</div>
                        </div>
                    </div>

                    <!-- Advanced Database Options -->
                    <div class="mt-6 p-4 bg-white bg-opacity-5 rounded-lg">
                        <h3 class="text-lg font-semibold text-white mb-4">
                            <i class="fas fa-cog mr-2"></i>Advanced Options
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-white font-medium mb-2">Port</label>
                                <input v-model="database.port" type="number" 
                                       class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                       placeholder="3306" value="3306">
                            </div>
                            <div>
                                <label class="block text-white font-medium mb-2">Charset</label>
                                <select v-model="database.charset" 
                                        class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                                    <option value="utf8mb4">UTF8MB4 (Recommended)</option>
                                    <option value="utf8">UTF8</option>
                                    <option value="latin1">Latin1</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-8">
                        <button @click="testDatabase" 
                                :disabled="!database.host || !database.name || !database.username"
                                class="btn-secondary mr-4 px-6 py-3 rounded-lg font-semibold transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-plug mr-2"></i>Test Connection
                        </button>
                        <button @click="nextStep" 
                                :disabled="!databaseTestResult || !databaseTestResult.success"
                                :class="databaseTestResult && databaseTestResult.success ? 'btn-primary' : 'btn-disabled'"
                                class="px-8 py-3 rounded-lg font-semibold transition-all duration-300">
                            Continue to Admin Setup
                        </button>
                    </div>
                </div>

                <!-- Step 3: Super Admin Creation -->
                <div v-if="currentStep === 2" class="step-content">
                    <h2 class="text-2xl font-bold text-white mb-6 text-center">Super Admin Account</h2>
                    
                    <!-- Password Strength Indicator -->
                    <div v-if="admin.password" class="mb-6 p-4 bg-white bg-opacity-10 rounded-lg">
                        <h3 class="text-lg font-semibold text-white mb-3">
                            <i class="fas fa-shield-alt mr-2"></i>Password Strength
                        </h3>
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="flex-1 bg-gray-600 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all duration-300" 
                                     :class="passwordStrength.color" 
                                     :style="{ width: passwordStrength.percentage + '%' }"></div>
                            </div>
                            <span class="text-white text-sm font-medium">{{ passwordStrength.text }}</span>
                        </div>
                        <div class="text-white text-xs opacity-75">
                            <div v-for="requirement in passwordRequirements" :key="requirement.text" 
                                 class="flex items-center mt-1">
                                <i :class="requirement.met ? 'fas fa-check text-green-400' : 'fas fa-times text-red-400'" 
                                   class="mr-2"></i>
                                {{ requirement.text }}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">
                                Full Name
                                <span class="text-red-400">*</span>
                            </label>
                            <input v-model="admin.name" type="text" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="John Doe" required>
                            <div class="text-white opacity-60 text-xs mt-1">Your display name in the system</div>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">
                                Email Address
                                <span class="text-red-400">*</span>
                            </label>
                            <input v-model="admin.email" type="email" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="admin@example.com" required>
                            <div class="text-white opacity-60 text-xs mt-1">Used for login and notifications</div>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">
                                Password
                                <span class="text-red-400">*</span>
                            </label>
                            <div class="relative">
                                <input v-model="admin.password" :type="showPassword ? 'text' : 'password'" 
                                       class="w-full px-4 py-3 pr-12 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                       placeholder="Strong password" required>
                                <button type="button" @click="showPassword = !showPassword" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-white opacity-75 hover:opacity-100">
                                    <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">
                                Confirm Password
                                <span class="text-red-400">*</span>
                            </label>
                            <input v-model="admin.password_confirm" type="password" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="Confirm password" required>
                            <div v-if="admin.password_confirm && admin.password !== admin.password_confirm" 
                                 class="text-red-400 text-xs mt-1">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Passwords do not match
                            </div>
                        </div>
                    </div>

                    <!-- Additional Admin Settings -->
                    <div class="mt-6 p-4 bg-white bg-opacity-5 rounded-lg">
                        <h3 class="text-lg font-semibold text-white mb-4">
                            <i class="fas fa-cog mr-2"></i>Additional Settings
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-white font-medium mb-2">Phone Number</label>
                                <input v-model="admin.phone" type="tel" 
                                       class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                       placeholder="+92 300 1234567">
                            </div>
                            <div>
                                <label class="block text-white font-medium mb-2">Company Name</label>
                                <input v-model="admin.company" type="text" 
                                       class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                       placeholder="Your Company Name">
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-8">
                        <button @click="nextStep" 
                                :disabled="!isAdminFormValid"
                                :class="isAdminFormValid ? 'btn-primary' : 'btn-disabled'"
                                class="px-8 py-3 rounded-lg font-semibold transition-all duration-300">
                            Continue to License Verification
                        </button>
                    </div>
                </div>

                <!-- Step 4: License Verification -->
                <div v-if="currentStep === 3" class="step-content">
                    <h2 class="text-2xl font-bold text-white mb-6 text-center">License Verification</h2>
                    
                    <!-- License Information -->
                    <div class="mb-8 p-6 bg-white bg-opacity-10 rounded-lg">
                        <h3 class="text-lg font-semibold text-white mb-4">
                            <i class="fas fa-key mr-2"></i>License Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-white">
                            <div>
                                <span class="opacity-75">Product:</span>
                                <span class="font-medium">DPS POS FBR Integrated</span>
                            </div>
                            <div>
                                <span class="opacity-75">Version:</span>
                                <span class="font-medium">1.0.0</span>
                            </div>
                            <div>
                                <span class="opacity-75">License Type:</span>
                                <span class="font-medium">Commercial</span>
                            </div>
                            <div>
                                <span class="opacity-75">Max Tenants:</span>
                                <span class="font-medium">Unlimited</span>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <p class="text-white opacity-90 mb-4">Enter your license key to activate DPS POS FBR Integrated</p>
                        <div class="max-w-md mx-auto">
                            <input v-model="license.key" type="text" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500 text-center font-mono text-lg tracking-wider" 
                                   placeholder="XXXX-XXXX-XXXX-XXXX" required>
                            <div class="text-white opacity-60 text-xs mt-2">
                                Format: XXXX-XXXX-XXXX-XXXX (16 characters with hyphens)
                            </div>
                        </div>
                    </div>

                    <!-- License Features -->
                    <div class="mb-6 p-4 bg-white bg-opacity-5 rounded-lg">
                        <h3 class="text-lg font-semibold text-white mb-4">
                            <i class="fas fa-star mr-2"></i>What's Included
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-white">
                            <div class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-2"></i>
                                FBR Digital Invoicing Integration
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-2"></i>
                                Multi-tenant SaaS Architecture
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-2"></i>
                                Advanced POS System
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-2"></i>
                                Inventory Management
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-2"></i>
                                HRM & Payroll System
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-2"></i>
                                Advanced Reporting
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-2"></i>
                                Template Editor
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-2"></i>
                                Data Import/Export
                            </div>
                        </div>
                    </div>
                    
                    <!-- License Test Result -->
                    <div v-if="licenseTestResult" class="mb-6 p-4 rounded-lg" 
                         :class="licenseTestResult.success ? 'bg-green-500 bg-opacity-20 border-green-400' : 'bg-red-500 bg-opacity-20 border-red-400'">
                        <div class="flex items-center">
                            <i :class="licenseTestResult.success ? 'fas fa-check-circle text-green-400' : 'fas fa-times-circle text-red-400'" 
                               class="text-xl mr-3"></i>
                            <div>
                                <div class="text-white font-medium">{{ licenseTestResult.success ? 'License Verified!' : 'License Verification Failed' }}</div>
                                <div v-if="licenseTestResult.message" class="text-white opacity-75 text-sm mt-1">
                                    {{ licenseTestResult.message }}
                                </div>
                                <div v-if="licenseTestResult.details" class="text-white opacity-60 text-xs mt-2">
                                    <div v-if="licenseTestResult.details.expires_at">
                                        <strong>Expires:</strong> {{ licenseTestResult.details.expires_at }}
                                    </div>
                                    <div v-if="licenseTestResult.details.max_tenants">
                                        <strong>Max Tenants:</strong> {{ licenseTestResult.details.max_tenants }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button @click="verifyLicense" 
                                :disabled="!license.key || license.key.length < 19"
                                class="btn-secondary mr-4 px-6 py-3 rounded-lg font-semibold transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-search mr-2"></i>Verify License
                        </button>
                        <button @click="nextStep" 
                                :disabled="!licenseTestResult || !licenseTestResult.success"
                                :class="licenseTestResult && licenseTestResult.success ? 'btn-primary' : 'btn-disabled'"
                                class="px-8 py-3 rounded-lg font-semibold transition-all duration-300">
                            Continue to Installation
                        </button>
                    </div>
                </div>

                <!-- Step 5: Finalize Installation -->
                <div v-if="currentStep === 4" class="step-content">
                    <h2 class="text-2xl font-bold text-white mb-6 text-center">Finalize Installation</h2>
                    <div class="text-center">
                        <div v-if="!installationComplete" class="mb-6">
                            <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-2 border-white mb-4"></div>
                            <p class="text-white text-lg mb-4">Installing DPS POS FBR Integrated...</p>
                            <div class="bg-white bg-opacity-20 rounded-lg p-6 max-h-96 overflow-y-auto">
                                <div class="text-left text-sm text-white space-y-3">
                                    <div v-for="log in installationLogs" :key="log.id" class="flex items-center">
                                        <i :class="log.type === 'success' ? 'fas fa-check text-green-400' : 
                                                  log.type === 'error' ? 'fas fa-times text-red-400' : 
                                                  'fas fa-spinner fa-spin text-blue-400'" class="mr-3 text-lg"></i>
                                        <span class="flex-1">{{ log.message }}</span>
                                        <span class="text-xs opacity-60">{{ new Date(log.timestamp || Date.now()).toLocaleTimeString() }}</span>
                                    </div>
                                    <div v-if="installationLogs.length === 0" class="text-center text-white opacity-60">
                                        <i class="fas fa-clock mr-2"></i>Preparing installation...
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center">
                            <div class="text-green-400 text-6xl mb-4">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-white mb-4">Installation Complete!</h3>
                            <p class="text-white opacity-90 mb-6">DPS POS FBR Integrated has been successfully installed and is ready to use.</p>
                            <div class="space-y-4">
                                <a href="../login.php" class="inline-flex items-center px-8 py-3 bg-white text-indigo-600 rounded-lg font-semibold hover:bg-gray-100 transition-all mr-4">
                                    <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
                                </a>
                                <a href="../admin" class="inline-flex items-center px-8 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all">
                                    <i class="fas fa-cog mr-2"></i>Admin Panel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex justify-between pt-6">
                    <button @click="previousStep" v-if="currentStep > 0" 
                            class="px-6 py-3 bg-white bg-opacity-20 text-white rounded-lg hover:bg-opacity-30 transition-all">
                        <i class="fas fa-arrow-left mr-2"></i>Previous
                    </button>
                    <div v-else></div>
                    
                    <button @click="nextStep" v-if="currentStep < 4" 
                            :disabled="!canProceed"
                            class="px-6 py-3 bg-white text-indigo-600 rounded-lg hover:bg-gray-100 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ currentStep === 3 ? 'Install Now' : 'Next' }}<i class="fas fa-arrow-right ml-2"></i>
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
                    currentStep: 0,
                    steps: ['Requirements', 'Database', 'Admin', 'License', 'Install'],
                    requirements: [
                        { name: 'PHP Version', current: '', required: '7.4+', status: false },
                        { name: 'MySQL Extension', current: '', required: 'Required', status: false },
                        { name: 'PDO Extension', current: '', required: 'Required', status: false },
                        { name: 'cURL Extension', current: '', required: 'Required', status: false },
                        { name: 'GD Extension', current: '', required: 'Required', status: false },
                        { name: 'MBString Extension', current: '', required: 'Required', status: false },
                        { name: 'OpenSSL Extension', current: '', required: 'Required', status: false },
                        { name: 'File Permissions', current: '', required: 'Writable', status: false },
                        { name: 'Memory Limit', current: '', required: '128M+', status: false },
                        { name: 'Max Execution Time', current: '', required: '300s+', status: false }
                    ],
                    requirementsCheckComplete: false,
                    systemInfo: null,
                    database: {
                        host: 'localhost',
                        name: 'dpspos_fbr',
                        username: 'root',
                        password: '',
                        port: 3306,
                        charset: 'utf8mb4'
                    },
                    databaseTestResult: null,
                    admin: {
                        name: '',
                        email: '',
                        password: '',
                        password_confirm: '',
                        phone: '',
                        company: ''
                    },
                    showPassword: false,
                    license: {
                        key: ''
                    },
                    licenseTestResult: null,
                    installationComplete: false,
                    installationLogs: []
                }
            },
            computed: {
                allRequirementsMet() {
                    return this.requirements.every(req => req.status);
                },
                canProceed() {
                    switch (this.currentStep) {
                        case 0: return this.allRequirementsMet;
                        case 1: return this.databaseTestResult && this.databaseTestResult.success;
                        case 2: return this.isAdminFormValid;
                        case 3: return this.licenseTestResult && this.licenseTestResult.success;
                        case 4: return true;
                        default: return false;
                    }
                },
                isAdminFormValid() {
                    return this.admin.name && 
                           this.admin.email && 
                           this.admin.password && 
                           this.admin.password === this.admin.password_confirm &&
                           this.passwordStrength.percentage >= 60;
                },
                passwordStrength() {
                    const password = this.admin.password || '';
                    let score = 0;
                    let requirements = [];
                    
                    // Length check
                    if (password.length >= 8) {
                        score += 20;
                        requirements.push({ text: 'At least 8 characters', met: true });
                    } else {
                        requirements.push({ text: 'At least 8 characters', met: false });
                    }
                    
                    // Uppercase check
                    if (/[A-Z]/.test(password)) {
                        score += 20;
                        requirements.push({ text: 'Contains uppercase letter', met: true });
                    } else {
                        requirements.push({ text: 'Contains uppercase letter', met: false });
                    }
                    
                    // Lowercase check
                    if (/[a-z]/.test(password)) {
                        score += 20;
                        requirements.push({ text: 'Contains lowercase letter', met: true });
                    } else {
                        requirements.push({ text: 'Contains lowercase letter', met: false });
                    }
                    
                    // Number check
                    if (/\d/.test(password)) {
                        score += 20;
                        requirements.push({ text: 'Contains number', met: true });
                    } else {
                        requirements.push({ text: 'Contains number', met: false });
                    }
                    
                    // Special character check
                    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                        score += 20;
                        requirements.push({ text: 'Contains special character', met: true });
                    } else {
                        requirements.push({ text: 'Contains special character', met: false });
                    }
                    
                    let color = 'bg-red-500';
                    let text = 'Weak';
                    
                    if (score >= 80) {
                        color = 'bg-green-500';
                        text = 'Strong';
                    } else if (score >= 60) {
                        color = 'bg-yellow-500';
                        text = 'Medium';
                    }
                    
                    return {
                        percentage: score,
                        color: color,
                        text: text,
                        requirements: requirements
                    };
                },
                passwordRequirements() {
                    return this.passwordStrength.requirements;
                }
            },
            mounted() {
                this.checkRequirements();
            },
            methods: {
                async checkRequirements() {
                    try {
                        this.addInstallationLog('info', 'Checking system requirements...');
                        const response = await fetch('check_requirements.php');
                        const data = await response.json();
                        
                        if (data.success) {
                            // Update requirements with detailed information
                            this.requirements = data.requirements.map(req => ({
                                name: req.name,
                                current: req.current,
                                required: req.required,
                                status: req.status,
                                message: req.message || '',
                                details: req.details || null
                            }));
                            
                            this.requirementsCheckComplete = true;
                            
                            if (data.overall_status) {
                                this.addInstallationLog('success', 'All system requirements met!');
                            } else {
                                this.addInstallationLog('error', 'Some requirements are not met. Please check the details above.');
                            }
                            
                // Store system information
                if (data.system_info) {
                    this.systemInfo = data.system_info;
                    console.log('System Info:', data.system_info);
                }
                        } else {
                            this.addInstallationLog('error', 'Failed to check requirements: ' + (data.error || 'Unknown error'));
                        }
                    } catch (error) {
                        console.error('Error checking requirements:', error);
                        this.addInstallationLog('error', 'Error checking requirements: ' + error.message);
                    }
                },
                
                async testDatabase() {
                    try {
                        const response = await fetch('test_database.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(this.database)
                        });
                        const data = await response.json();
                        this.databaseTestResult = data;
                    } catch (error) {
                        this.databaseTestResult = {
                            success: false,
                            message: 'Database connection failed: ' + error.message
                        };
                    }
                },
                
                async verifyLicense() {
                    try {
                        const response = await fetch('verify_license.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ key: this.license.key })
                        });
                        const data = await response.json();
                        this.licenseTestResult = data;
                    } catch (error) {
                        this.licenseTestResult = {
                            success: false,
                            message: 'License verification failed: ' + error.message
                        };
                    }
                },
                
                async nextStep() {
                    if (this.currentStep === 1) {
                        await this.testDatabase();
                        if (!this.databaseTestResult.success) return;
                    }
                    
                    if (this.currentStep === 3) {
                        await this.verifyLicense();
                        if (!this.licenseTestResult.success) return;
                        // Move to installation step
                        this.currentStep = 4;
                        // Start installation
                        await this.installSystem();
                        return;
                    }
                    
                    this.currentStep++;
                },
                
                previousStep() {
                    if (this.currentStep > 0) {
                        this.currentStep--;
                    }
                },
                
                getStepClass(index) {
                    if (index < this.currentStep) return 'step-completed';
                    if (index === this.currentStep) return 'step-active';
                    return 'bg-white opacity-30';
                },
                
                async installSystem() {
                    this.addInstallationLog('info', 'Starting installation process...');
                    
                    // Add small delay for better UX
                    await this.delay(500);
                    
                    const installationData = {
                        database: this.database,
                        admin: this.admin,
                        license: this.license
                    };
                    
                    try {
                        this.addInstallationLog('info', 'Preparing installation data...');
                        await this.delay(300);
                        
                        this.addInstallationLog('info', 'Connecting to database...');
                        await this.delay(500);
                        
                        const response = await fetch('install.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(installationData)
                        });
                        
                        this.addInstallationLog('info', 'Processing installation request...');
                        await this.delay(500);
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.addInstallationLog('success', 'Database tables created successfully');
                            await this.delay(300);
                            
                            this.addInstallationLog('success', 'Super admin account created');
                            await this.delay(300);
                            
                            this.addInstallationLog('success', 'Configuration files generated');
                            await this.delay(300);
                            
                            this.addInstallationLog('success', 'File permissions set');
                            await this.delay(300);
                            
                            this.addInstallationLog('success', 'Installation completed successfully!');
                            
                            // Wait a moment to show all logs
                            setTimeout(() => {
                                this.installationComplete = true;
                            }, 1000);
                        } else {
                            this.addInstallationLog('error', 'Installation failed: ' + (data.message || 'Unknown error'));
                        }
                    } catch (error) {
                        this.addInstallationLog('error', 'Installation error: ' + error.message);
                    }
                },
                
                delay(ms) {
                    return new Promise(resolve => setTimeout(resolve, ms));
                },
                
                addInstallationLog(type, message) {
                    this.installationLogs.push({
                        id: Date.now(),
                        type: type,
                        message: message,
                        timestamp: Date.now()
                    });
                }
            }
        }).mount('#app');
    </script>
</body>
</html>