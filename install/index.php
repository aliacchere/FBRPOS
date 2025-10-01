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
                             class="flex items-center justify-between p-4 bg-white bg-opacity-10 rounded-lg">
                            <div class="flex items-center">
                                <i :class="requirement.status ? 'fas fa-check-circle requirement-pass' : 'fas fa-times-circle requirement-fail'" 
                                   class="text-xl mr-3"></i>
                                <span class="text-white font-medium">{{ requirement.name }}</span>
                            </div>
                            <div class="text-right">
                                <div class="text-white text-sm">{{ requirement.current }}</div>
                                <div class="text-white opacity-75 text-xs">{{ requirement.required }}</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="requirementsCheckComplete" class="text-center">
                        <div v-if="allRequirementsMet" class="text-green-400 text-lg font-semibold mb-4">
                            <i class="fas fa-check-circle mr-2"></i>All requirements met! Ready to proceed.
                        </div>
                        <div v-else class="text-red-400 text-lg font-semibold mb-4">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Please fix the requirements above before continuing.
                        </div>
                    </div>
                </div>

                <!-- Step 2: Database Setup -->
                <div v-if="currentStep === 1" class="step-content">
                    <h2 class="text-2xl font-bold text-white mb-6 text-center">Database Configuration</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">Database Host</label>
                            <input v-model="database.host" type="text" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="localhost" required>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Database Name</label>
                            <input v-model="database.name" type="text" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="dpspos_fbr" required>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Database Username</label>
                            <input v-model="database.username" type="text" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="root" required>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Database Password</label>
                            <input v-model="database.password" type="password" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    
                    <div v-if="databaseTestResult" class="mt-4 p-4 rounded-lg" 
                         :class="databaseTestResult.success ? 'bg-green-500 bg-opacity-20 text-green-200' : 'bg-red-500 bg-opacity-20 text-red-200'">
                        <i :class="databaseTestResult.success ? 'fas fa-check-circle' : 'fas fa-times-circle'" class="mr-2"></i>
                        {{ databaseTestResult.message }}
                    </div>
                </div>

                <!-- Step 3: Super Admin Creation -->
                <div v-if="currentStep === 2" class="step-content">
                    <h2 class="text-2xl font-bold text-white mb-6 text-center">Super Admin Account</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-white font-medium mb-2">Full Name</label>
                            <input v-model="admin.name" type="text" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="John Doe" required>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Email Address</label>
                            <input v-model="admin.email" type="email" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="admin@example.com" required>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Password</label>
                            <input v-model="admin.password" type="password" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="Strong password" required>
                        </div>
                        <div>
                            <label class="block text-white font-medium mb-2">Confirm Password</label>
                            <input v-model="admin.password_confirm" type="password" 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="Confirm password" required>
                        </div>
                    </div>
                </div>

                <!-- Step 4: License Verification -->
                <div v-if="currentStep === 3" class="step-content">
                    <h2 class="text-2xl font-bold text-white mb-6 text-center">License Verification</h2>
                    <div class="text-center mb-6">
                        <p class="text-white opacity-90 mb-4">Enter your license key to activate DPS POS FBR Integrated</p>
                        <input v-model="license.key" type="text" 
                               class="w-full max-w-md px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500 text-center font-mono" 
                               placeholder="XXXX-XXXX-XXXX-XXXX" required>
                    </div>
                    
                    <div v-if="licenseTestResult" class="mt-4 p-4 rounded-lg" 
                         :class="licenseTestResult.success ? 'bg-green-500 bg-opacity-20 text-green-200' : 'bg-red-500 bg-opacity-20 text-red-200'">
                        <i :class="licenseTestResult.success ? 'fas fa-check-circle' : 'fas fa-times-circle'" class="mr-2"></i>
                        {{ licenseTestResult.message }}
                    </div>
                </div>

                <!-- Step 5: Finalize Installation -->
                <div v-if="currentStep === 4" class="step-content">
                    <h2 class="text-2xl font-bold text-white mb-6 text-center">Finalize Installation</h2>
                    <div class="text-center">
                        <div v-if="!installationComplete" class="mb-6">
                            <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-2 border-white mb-4"></div>
                            <p class="text-white text-lg mb-4">Installing DPS POS FBR Integrated...</p>
                            <div class="bg-white bg-opacity-20 rounded-lg p-4">
                                <div class="text-left text-sm text-white space-y-2">
                                    <div v-for="log in installationLogs" :key="log.id" class="flex items-center">
                                        <i :class="log.type === 'success' ? 'fas fa-check text-green-400' : 
                                                  log.type === 'error' ? 'fas fa-times text-red-400' : 
                                                  'fas fa-spinner fa-spin text-blue-400'" class="mr-2"></i>
                                        <span>{{ log.message }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center">
                            <div class="text-green-400 text-6xl mb-4">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-white mb-4">Installation Complete!</h3>
                            <p class="text-white opacity-90 mb-6">DPS POS FBR Integrated has been successfully installed.</p>
                            <a href="../login.php" class="inline-flex items-center px-6 py-3 bg-white text-indigo-600 rounded-lg font-semibold hover:bg-gray-100 transition-all">
                                <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
                            </a>
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
                        {{ currentStep === 3 ? 'Install' : 'Next' }}<i class="fas fa-arrow-right ml-2"></i>
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
                    database: {
                        host: 'localhost',
                        name: 'dpspos_fbr',
                        username: 'root',
                        password: ''
                    },
                    databaseTestResult: null,
                    admin: {
                        name: '',
                        email: '',
                        password: '',
                        password_confirm: ''
                    },
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
                        case 2: return this.admin.name && this.admin.email && this.admin.password && 
                                this.admin.password === this.admin.password_confirm;
                        case 3: return this.licenseTestResult && this.licenseTestResult.success;
                        case 4: return true;
                        default: return false;
                    }
                }
            },
            mounted() {
                this.checkRequirements();
            },
            methods: {
                async checkRequirements() {
                    try {
                        const response = await fetch('check_requirements.php');
                        const data = await response.json();
                        
                        this.requirements.forEach((req, index) => {
                            const check = data.requirements[index];
                            req.current = check.current;
                            req.status = check.status;
                        });
                        
                        this.requirementsCheckComplete = true;
                    } catch (error) {
                        console.error('Error checking requirements:', error);
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
                    }
                    
                    if (this.currentStep === 4) {
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
                    const installationData = {
                        database: this.database,
                        admin: this.admin,
                        license: this.license
                    };
                    
                    try {
                        const response = await fetch('install.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(installationData)
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.installationComplete = true;
                        } else {
                            this.addInstallationLog('error', 'Installation failed: ' + data.error);
                        }
                    } catch (error) {
                        this.addInstallationLog('error', 'Installation error: ' + error.message);
                    }
                },
                
                addInstallationLog(type, message) {
                    this.installationLogs.push({
                        id: Date.now(),
                        type: type,
                        message: message
                    });
                }
            }
        }).mount('#app');
    </script>
</body>
</html>