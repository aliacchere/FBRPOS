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
        case 'create_backup':
            try {
                $backupId = 'backup_' . date('Y_m_d_H_i_s');
                $backupDir = '../storage/backups/';
                
                // Create backup directory if it doesn't exist
                if (!file_exists($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                
                // Create database backup
                $dbBackupFile = $backupDir . $backupId . '_database.sql';
                $this->createDatabaseBackup($dbBackupFile);
                
                // Create files backup
                $filesBackupFile = $backupDir . $backupId . '_files.zip';
                $this->createFilesBackup($filesBackupFile);
                
                // Create complete backup
                $completeBackupFile = $backupDir . $backupId . '_complete.zip';
                $this->createCompleteBackup($completeBackupFile, $dbBackupFile, $filesBackupFile);
                
                // Clean up individual files
                unlink($dbBackupFile);
                unlink($filesBackupFile);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Backup created successfully',
                    'backup_file' => $completeBackupFile,
                    'backup_id' => $backupId
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'list_backups':
            $backupDir = '../storage/backups/';
            $backups = [];
            
            if (is_dir($backupDir)) {
                $files = scandir($backupDir);
                foreach ($files as $file) {
                    if (strpos($file, '_complete.zip') !== false) {
                        $filePath = $backupDir . $file;
                        $backups[] = [
                            'filename' => $file,
                            'size' => filesize($filePath),
                            'created' => filemtime($filePath),
                            'path' => $filePath
                        ];
                    }
                }
                
                // Sort by creation time (newest first)
                usort($backups, function($a, $b) {
                    return $b['created'] - $a['created'];
                });
            }
            
            echo json_encode(['success' => true, 'backups' => $backups]);
            exit;
            
        case 'restore_backup':
            $backupFile = $_POST['backup_file'];
            
            try {
                if (!file_exists($backupFile)) {
                    throw new Exception('Backup file not found');
                }
                
                // Extract backup
                $extractDir = '../storage/temp_restore/';
                if (!file_exists($extractDir)) {
                    mkdir($extractDir, 0755, true);
                }
                
                $zip = new ZipArchive();
                if ($zip->open($backupFile) === TRUE) {
                    $zip->extractTo($extractDir);
                    $zip->close();
                } else {
                    throw new Exception('Failed to extract backup file');
                }
                
                // Restore database
                $dbFile = $extractDir . 'database.sql';
                if (file_exists($dbFile)) {
                    $this->restoreDatabase($dbFile);
                }
                
                // Restore files
                $filesDir = $extractDir . 'files/';
                if (is_dir($filesDir)) {
                    $this->restoreFiles($filesDir);
                }
                
                // Clean up
                $this->deleteDirectory($extractDir);
                
                echo json_encode(['success' => true, 'message' => 'Backup restored successfully']);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_backup':
            $backupFile = $_POST['backup_file'];
            
            try {
                if (file_exists($backupFile)) {
                    unlink($backupFile);
                    echo json_encode(['success' => true, 'message' => 'Backup deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Backup file not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Helper functions
function createDatabaseBackup($file) {
    global $pdo;
    
    $tables = [
        'tenants', 'users', 'products', 'categories', 'suppliers', 'customers',
        'sales', 'sale_items', 'fbr_integration_settings', 'system_settings'
    ];
    
    $sql = "-- DPS POS FBR Integrated Database Backup\n";
    $sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($tables as $table) {
        // Get table structure
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        $sql .= $createTable['Create Table'] . ";\n\n";
        
        // Get table data
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $sql .= "INSERT INTO `$table` VALUES\n";
            $values = [];
            foreach ($rows as $row) {
                $rowValues = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $rowValues[] = 'NULL';
                    } else {
                        $rowValues[] = "'" . addslashes($value) . "'";
                    }
                }
                $values[] = '(' . implode(',', $rowValues) . ')';
            }
            $sql .= implode(",\n", $values) . ";\n\n";
        }
    }
    
    file_put_contents($file, $sql);
}

function createFilesBackup($file) {
    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE) === TRUE) {
        $this->addDirectoryToZip($zip, '../storage/', 'storage/');
        $this->addDirectoryToZip($zip, '../public/uploads/', 'uploads/');
        $zip->close();
    }
}

function createCompleteBackup($file, $dbFile, $filesFile) {
    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($dbFile, 'database.sql');
        $zip->addFile($filesFile, 'files.zip');
        $zip->close();
    }
}

function addDirectoryToZip($zip, $dir, $zipDir) {
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $filePath = $dir . $file;
                if (is_dir($filePath)) {
                    $this->addDirectoryToZip($zip, $filePath . '/', $zipDir . $file . '/');
                } else {
                    $zip->addFile($filePath, $zipDir . $file);
                }
            }
        }
    }
}

function restoreDatabase($file) {
    global $pdo;
    
    $sql = file_get_contents($file);
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
}

function restoreFiles($dir) {
    // Copy files from backup to their original locations
    $this->copyDirectory($dir . 'storage/', '../storage/');
    $this->copyDirectory($dir . 'uploads/', '../public/uploads/');
}

function copyDirectory($src, $dst) {
    if (is_dir($src)) {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $srcFile = $src . $file;
                $dstFile = $dst . $file;
                
                if (is_dir($srcFile)) {
                    $this->copyDirectory($srcFile . '/', $dstFile . '/');
                } else {
                    copy($srcFile, $dstFile);
                }
            }
        }
    }
}

function deleteDirectory($dir) {
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $filePath = $dir . $file;
                if (is_dir($filePath)) {
                    $this->deleteDirectory($filePath . '/');
                } else {
                    unlink($filePath);
                }
            }
        }
        rmdir($dir);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - DPS POS FBR Integrated</title>
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
        .backup-card:hover {
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
                        <i class="fas fa-database text-white text-2xl mr-3"></i>
                        <h1 class="text-xl font-bold text-white">Backup & Restore</h1>
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
            <!-- Quick Actions -->
            <div class="mb-8">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-6">Quick Actions</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <button @click="createBackup" 
                                :disabled="isCreatingBackup"
                                class="bg-green-500 hover:bg-green-600 disabled:opacity-50 text-white px-6 py-4 rounded-lg transition-all">
                            <i class="fas fa-download text-2xl mb-2"></i>
                            <div class="font-semibold">Create Backup</div>
                            <div class="text-sm opacity-75">Backup database and files</div>
                        </button>
                        
                        <button @click="loadBackups" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-4 rounded-lg transition-all">
                            <i class="fas fa-list text-2xl mb-2"></i>
                            <div class="font-semibold">View Backups</div>
                            <div class="text-sm opacity-75">List all available backups</div>
                        </button>
                        
                        <button @click="showRestoreModal = true" 
                                class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-4 rounded-lg transition-all">
                            <i class="fas fa-upload text-2xl mb-2"></i>
                            <div class="font-semibold">Restore Backup</div>
                            <div class="text-sm opacity-75">Restore from backup file</div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Backup List -->
            <div class="glass-effect rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-white">Available Backups</h3>
                    <button @click="loadBackups" 
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-all">
                        <i class="fas fa-refresh mr-2"></i>Refresh
                    </button>
                </div>
                
                <div v-if="backups.length === 0" class="text-center text-white opacity-75 py-8">
                    <i class="fas fa-database text-4xl mb-4"></i>
                    <div>No backups found</div>
                    <div class="text-sm mt-2">Create your first backup to get started</div>
                </div>
                
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="backup in backups" :key="backup.filename" 
                         class="backup-card bg-white bg-opacity-10 rounded-lg p-4">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="text-white font-medium">{{ backup.filename }}</h4>
                            <div class="flex space-x-1">
                                <button @click="downloadBackup(backup)" 
                                        class="text-blue-400 hover:text-blue-300">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button @click="restoreBackup(backup)" 
                                        class="text-green-400 hover:text-green-300">
                                    <i class="fas fa-upload"></i>
                                </button>
                                <button @click="deleteBackup(backup)" 
                                        class="text-red-400 hover:text-red-300">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="text-white opacity-75 text-sm mb-1">
                            Size: {{ formatFileSize(backup.size) }}
                        </div>
                        <div class="text-white opacity-75 text-sm">
                            Created: {{ formatDate(backup.created) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Restore Modal -->
        <div v-if="showRestoreModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="glass-effect rounded-lg p-8 max-w-md w-full mx-4">
                <h3 class="text-xl font-semibold text-white mb-6">Restore Backup</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-white font-medium mb-2">Select Backup File</label>
                        <input type="file" @change="handleFileSelect" 
                               accept=".zip" 
                               class="w-full px-4 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    
                    <div class="bg-yellow-500 bg-opacity-20 border border-yellow-400 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-400 mr-3"></i>
                            <div class="text-yellow-200 text-sm">
                                <strong>Warning:</strong> This will overwrite all current data. Make sure you have a current backup before proceeding.
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex space-x-4 mt-6">
                    <button @click="showRestoreModal = false" 
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-2 rounded-lg transition-all">
                        Cancel
                    </button>
                    <button @click="confirmRestore" 
                            :disabled="!selectedFile"
                            class="flex-1 bg-red-500 hover:bg-red-600 disabled:opacity-50 text-white py-2 rounded-lg transition-all">
                        Restore Backup
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
                    backups: [],
                    isCreatingBackup: false,
                    showRestoreModal: false,
                    selectedFile: null
                }
            },
            mounted() {
                this.loadBackups();
            },
            methods: {
                async createBackup() {
                    this.isCreatingBackup = true;
                    
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=create_backup'
                        });
                        const data = await response.json();
                        
                        if (data.success) {
                            alert('Backup created successfully!');
                            this.loadBackups();
                        } else {
                            alert('Error creating backup: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error creating backup:', error);
                        alert('Error creating backup');
                    } finally {
                        this.isCreatingBackup = false;
                    }
                },
                
                async loadBackups() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=list_backups'
                        });
                        const data = await response.json();
                        
                        if (data.success) {
                            this.backups = data.backups;
                        }
                    } catch (error) {
                        console.error('Error loading backups:', error);
                    }
                },
                
                downloadBackup(backup) {
                    // Create download link
                    const link = document.createElement('a');
                    link.href = backup.path;
                    link.download = backup.filename;
                    link.click();
                },
                
                async restoreBackup(backup) {
                    if (confirm('Are you sure you want to restore this backup? This will overwrite all current data.')) {
                        try {
                            const response = await fetch('', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=restore_backup&backup_file=${encodeURIComponent(backup.path)}`
                            });
                            const data = await response.json();
                            
                            if (data.success) {
                                alert('Backup restored successfully!');
                                this.loadBackups();
                            } else {
                                alert('Error restoring backup: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Error restoring backup:', error);
                            alert('Error restoring backup');
                        }
                    }
                },
                
                async deleteBackup(backup) {
                    if (confirm('Are you sure you want to delete this backup?')) {
                        try {
                            const response = await fetch('', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=delete_backup&backup_file=${encodeURIComponent(backup.path)}`
                            });
                            const data = await response.json();
                            
                            if (data.success) {
                                alert('Backup deleted successfully!');
                                this.loadBackups();
                            } else {
                                alert('Error deleting backup: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Error deleting backup:', error);
                            alert('Error deleting backup');
                        }
                    }
                },
                
                handleFileSelect(event) {
                    this.selectedFile = event.target.files[0];
                },
                
                async confirmRestore() {
                    if (!this.selectedFile) {
                        alert('Please select a backup file');
                        return;
                    }
                    
                    // This would typically upload the file and then restore
                    alert('File upload and restore functionality will be implemented in the next update!');
                    this.showRestoreModal = false;
                },
                
                formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                },
                
                formatDate(timestamp) {
                    return new Date(timestamp * 1000).toLocaleString();
                }
            }
        }).mount('#app');
    </script>
</body>
</html>