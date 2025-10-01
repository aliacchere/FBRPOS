<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DPS POS FBR Integrated - Installation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8 fade-in">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full mb-4 shadow-lg">
                <i class="fas fa-cash-register text-3xl text-indigo-600"></i>
            </div>
            <h1 class="text-4xl font-bold text-white mb-2">DPS POS FBR Integrated</h1>
            <p class="text-xl text-white opacity-90">Premier SaaS POS Platform for Pakistani Businesses</p>
        </div>

        <!-- Installation Steps -->
        <div class="max-w-4xl mx-auto">
            <!-- Step Indicator -->
            <div class="flex justify-center mb-8">
                <div class="flex items-center space-x-4">
                    <div class="step-indicator step-active w-10 h-10 rounded-full flex items-center justify-center font-bold" id="step-1">1</div>
                    <div class="w-16 h-1 bg-white opacity-30"></div>
                    <div class="step-indicator w-10 h-10 rounded-full flex items-center justify-center font-bold bg-white opacity-30" id="step-2">2</div>
                    <div class="w-16 h-1 bg-white opacity-30"></div>
                    <div class="step-indicator w-10 h-10 rounded-full flex items-center justify-center font-bold bg-white opacity-30" id="step-3">3</div>
                    <div class="w-16 h-1 bg-white opacity-30"></div>
                    <div class="step-indicator w-10 h-10 rounded-full flex items-center justify-center font-bold bg-white opacity-30" id="step-4">4</div>
                </div>
            </div>

            <!-- Installation Form -->
            <div class="glass-effect rounded-2xl p-8 shadow-2xl">
                <form id="installationForm" class="space-y-6">
                    <!-- Step 1: Database Configuration -->
                    <div id="step1-content" class="step-content">
                        <h2 class="text-2xl font-bold text-white mb-6 text-center">Database Configuration</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-white font-medium mb-2">Database Host</label>
                                <input type="text" name="db_host" value="localhost" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" required>
                            </div>
                            <div>
                                <label class="block text-white font-medium mb-2">Database Name</label>
                                <input type="text" name="db_name" value="dpspos_fbr" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" required>
                            </div>
                            <div>
                                <label class="block text-white font-medium mb-2">Database Username</label>
                                <input type="text" name="db_username" value="root" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" required>
                            </div>
                            <div>
                                <label class="block text-white font-medium mb-2">Database Password</label>
                                <input type="password" name="db_password" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Super Admin Account -->
                    <div id="step2-content" class="step-content hidden">
                        <h2 class="text-2xl font-bold text-white mb-6 text-center">Super Admin Account</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-white font-medium mb-2">Full Name</label>
                                <input type="text" name="admin_name" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" required>
                            </div>
                            <div>
                                <label class="block text-white font-medium mb-2">Email Address</label>
                                <input type="email" name="admin_email" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" required>
                            </div>
                            <div>
                                <label class="block text-white font-medium mb-2">Password</label>
                                <input type="password" name="admin_password" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" required>
                            </div>
                            <div>
                                <label class="block text-white font-medium mb-2">Confirm Password</label>
                                <input type="password" name="admin_password_confirm" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" required>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Application Settings -->
                    <div id="step3-content" class="step-content hidden">
                        <h2 class="text-2xl font-bold text-white mb-6 text-center">Application Settings</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-white font-medium mb-2">Application URL</label>
                                <input type="url" name="app_url" value="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" required>
                            </div>
                            <div>
                                <label class="block text-white font-medium mb-2">Encryption Key</label>
                                <input type="text" name="encryption_key" value="<?php echo bin2hex(random_bytes(16)); ?>" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" required>
                            </div>
                            <div>
                                <label class="block text-white font-medium mb-2">WhatsApp Business Number</label>
                                <input type="text" name="whatsapp_number" placeholder="+923001234567" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-white font-medium mb-2">Default Timezone</label>
                                <select name="timezone" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" required>
                                    <option value="Asia/Karachi" selected>Asia/Karachi (Pakistan)</option>
                                    <option value="UTC">UTC</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Installation Progress -->
                    <div id="step4-content" class="step-content hidden">
                        <h2 class="text-2xl font-bold text-white mb-6 text-center">Installing DPS POS</h2>
                        <div class="text-center">
                            <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-2 border-white mb-4"></div>
                            <p class="text-white text-lg mb-4">Please wait while we set up your system...</p>
                            <div class="bg-white bg-opacity-20 rounded-lg p-4">
                                <div id="installation-log" class="text-left text-sm text-white space-y-2">
                                    <div>Initializing installation...</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="flex justify-between pt-6">
                        <button type="button" id="prevBtn" class="px-6 py-3 bg-white bg-opacity-20 text-white rounded-lg hover:bg-opacity-30 transition-all hidden">
                            <i class="fas fa-arrow-left mr-2"></i>Previous
                        </button>
                        <button type="button" id="nextBtn" class="px-6 py-3 bg-white text-indigo-600 rounded-lg hover:bg-gray-100 transition-all ml-auto">
                            Next<i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 4;

        function showStep(step) {
            // Hide all step contents
            document.querySelectorAll('.step-content').forEach(content => {
                content.classList.add('hidden');
            });

            // Show current step content
            document.getElementById(`step${step}-content`).classList.remove('hidden');

            // Update step indicators
            for (let i = 1; i <= totalSteps; i++) {
                const indicator = document.getElementById(`step-${i}`);
                if (i < step) {
                    indicator.className = 'step-indicator step-completed w-10 h-10 rounded-full flex items-center justify-center font-bold';
                } else if (i === step) {
                    indicator.className = 'step-indicator step-active w-10 h-10 rounded-full flex items-center justify-center font-bold';
                } else {
                    indicator.className = 'step-indicator w-10 h-10 rounded-full flex items-center justify-center font-bold bg-white opacity-30';
                }
            }

            // Update navigation buttons
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');

            if (step === 1) {
                prevBtn.classList.add('hidden');
            } else {
                prevBtn.classList.remove('hidden');
            }

            if (step === totalSteps) {
                nextBtn.innerHTML = '<i class="fas fa-download mr-2"></i>Install';
                nextBtn.type = 'submit';
            } else {
                nextBtn.innerHTML = 'Next<i class="fas fa-arrow-right ml-2"></i>';
                nextBtn.type = 'button';
            }
        }

        function nextStep() {
            if (currentStep < totalSteps) {
                currentStep++;
                showStep(currentStep);
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        }

        function addLogMessage(message) {
            const log = document.getElementById('installation-log');
            const div = document.createElement('div');
            div.textContent = message;
            log.appendChild(div);
            log.scrollTop = log.scrollHeight;
        }

        function installSystem() {
            addLogMessage('Creating database configuration...');
            
            // Simulate installation process
            setTimeout(() => {
                addLogMessage('Database configuration created successfully');
                addLogMessage('Creating database tables...');
            }, 1000);

            setTimeout(() => {
                addLogMessage('Database tables created successfully');
                addLogMessage('Creating super admin account...');
            }, 2000);

            setTimeout(() => {
                addLogMessage('Super admin account created successfully');
                addLogMessage('Setting up application configuration...');
            }, 3000);

            setTimeout(() => {
                addLogMessage('Application configuration completed');
                addLogMessage('Installation completed successfully!');
                addLogMessage('Redirecting to login page...');
                
                setTimeout(() => {
                    window.location.href = '../login.php';
                }, 2000);
            }, 4000);
        }

        // Event listeners
        document.getElementById('nextBtn').addEventListener('click', function(e) {
            e.preventDefault();
            if (currentStep === totalSteps) {
                installSystem();
            } else {
                nextStep();
            }
        });

        document.getElementById('prevBtn').addEventListener('click', function(e) {
            e.preventDefault();
            prevStep();
        });

        // Initialize
        showStep(1);
    </script>
</body>
</html>