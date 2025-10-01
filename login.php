<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DPS POS FBR Integrated</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md">
        <div class="glass-effect rounded-2xl p-8 shadow-2xl">
            <div class="text-center mb-8">
                <div class="text-4xl text-white mb-4">
                    <i class="fas fa-cash-register"></i>
                </div>
                <h1 class="text-2xl font-bold text-white">DPS POS FBR Integrated</h1>
                <p class="text-white opacity-75 mt-2">Login to your account</p>
            </div>
            
            <form class="space-y-6">
                <div>
                    <label class="block text-white font-medium mb-2">Email Address</label>
                    <input type="email" 
                           class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                           placeholder="admin@example.com" required>
                </div>
                
                <div>
                    <label class="block text-white font-medium mb-2">Password</label>
                    <input type="password" 
                           class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                           placeholder="Enter your password" required>
                </div>
                
                <button type="submit" 
                        class="w-full bg-white text-indigo-600 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-all">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-white opacity-75 text-sm">
                    <i class="fas fa-info-circle mr-1"></i>
                    Installation completed successfully! You can now login with your admin credentials.
                </p>
            </div>
        </div>
    </div>
</body>
</html>