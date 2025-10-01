<?php
require_once 'includes/auth.php';

// Handle login form submission
$error = '';
$success = '';

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        try {
            $pdo = getDatabaseConnection();
            $auth = new Auth($pdo);
            $result = $auth->login($email, $password);
            
            if ($result['success']) {
                header('Location: /admin/');
                exit;
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = 'Login failed: ' . $e->getMessage();
        }
    }
}

// Check if already logged in
try {
    $pdo = getDatabaseConnection();
    $auth = new Auth($pdo);
    if ($auth->isLoggedIn()) {
        header('Location: /admin/');
        exit;
    }
} catch (Exception $e) {
    // Database connection failed, show login form
}
?>

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
            
            <?php if ($error): ?>
                <div class="bg-red-500 bg-opacity-20 border border-red-400 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-400 mr-3"></i>
                        <span class="text-red-200"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-500 bg-opacity-20 border border-green-400 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-400 mr-3"></i>
                        <span class="text-green-200"><?php echo htmlspecialchars($success); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-white font-medium mb-2">Email Address</label>
                    <input type="email" 
                           name="email"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500" 
                           placeholder="admin@example.com" required>
                </div>
                
                <div>
                    <label class="block text-white font-medium mb-2">Password</label>
                    <input type="password" 
                           name="password"
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
                    Use the admin credentials you created during installation.
                </p>
            </div>
        </div>
    </div>
</body>
</html>