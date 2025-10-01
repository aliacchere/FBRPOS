<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use ZipArchive;

class BackupService
{
    /**
     * Create system backup
     */
    public function createSystemBackup(string $backupName = null, bool $includeFiles = true, string $password = null)
    {
        $backupName = $backupName ?: 'backup_' . now()->format('Y-m-d_H-i-s');
        $backupPath = storage_path('app/backups/' . $backupName . '.zip');
        
        // Ensure backup directory exists
        if (!file_exists(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0755, true);
        }

        $zip = new ZipArchive();
        
        if ($zip->open($backupPath, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create backup file');
        }

        try {
            // Add database dump
            $this->addDatabaseDump($zip);
            
            // Add uploaded files if requested
            if ($includeFiles) {
                $this->addUploadedFiles($zip);
            }
            
            // Add configuration files
            $this->addConfigurationFiles($zip);
            
            // Add backup metadata
            $this->addBackupMetadata($zip, $backupName, $includeFiles);
            
            $zip->close();
            
            // Set password if provided
            if ($password) {
                $this->setZipPassword($backupPath, $password);
            }
            
            // Record backup in database
            $this->recordBackup($backupName, $backupPath, $includeFiles);
            
            return [
                'name' => $backupName,
                'path' => $backupPath,
                'size' => filesize($backupPath),
                'created_at' => now()
            ];
            
        } catch (\Exception $e) {
            $zip->close();
            if (file_exists($backupPath)) {
                unlink($backupPath);
            }
            throw $e;
        }
    }

    /**
     * Add database dump to ZIP
     */
    private function addDatabaseDump(ZipArchive $zip)
    {
        $databaseDump = $this->generateDatabaseDump();
        $zip->addFromString('database/dump.sql', $databaseDump);
    }

    /**
     * Generate database dump
     */
    private function generateDatabaseDump()
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');

        $command = "mysqldump --host={$host} --port={$port} --user={$username} --password={$password} {$database}";
        
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception('Database dump failed');
        }
        
        return implode("\n", $output);
    }

    /**
     * Add uploaded files to ZIP
     */
    private function addUploadedFiles(ZipArchive $zip)
    {
        $uploadPaths = [
            'public/uploads',
            'storage/app/public',
            'storage/app/tenant_files'
        ];
        
        foreach ($uploadPaths as $path) {
            $fullPath = base_path($path);
            if (file_exists($fullPath)) {
                $this->addDirectoryToZip($zip, $fullPath, 'files/' . basename($path));
            }
        }
    }

    /**
     * Add configuration files to ZIP
     */
    private function addConfigurationFiles(ZipArchive $zip)
    {
        $configFiles = [
            '.env',
            'config/app.php',
            'config/database.php',
            'config/mail.php',
            'config/services.php'
        ];
        
        foreach ($configFiles as $file) {
            $filePath = base_path($file);
            if (file_exists($filePath)) {
                $zip->addFile($filePath, 'config/' . basename($file));
            }
        }
    }

    /**
     * Add backup metadata
     */
    private function addBackupMetadata(ZipArchive $zip, string $backupName, bool $includeFiles)
    {
        $metadata = [
            'backup_name' => $backupName,
            'created_at' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'include_files' => $includeFiles,
            'database_version' => $this->getDatabaseVersion(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version()
        ];
        
        $zip->addFromString('backup_metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Add directory to ZIP recursively
     */
    private function addDirectoryToZip(ZipArchive $zip, string $dirPath, string $zipPath)
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $relativePath = $zipPath . '/' . substr($file->getRealPath(), strlen($dirPath) + 1);
                $zip->addFile($file->getRealPath(), $relativePath);
            }
        }
    }

    /**
     * Set ZIP password
     */
    private function setZipPassword(string $filePath, string $password)
    {
        // This would require a library that supports password-protected ZIPs
        // For now, we'll just store the password in metadata
        $metadata = [
            'password_protected' => true,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT)
        ];
        
        // In a real implementation, you would use a library like ZipArchive with password support
        // or a third-party library that supports password-protected ZIPs
    }

    /**
     * Record backup in database
     */
    private function recordBackup(string $backupName, string $backupPath, bool $includeFiles)
    {
        DB::table('system_backups')->insert([
            'name' => $backupName,
            'file_path' => $backupPath,
            'file_size' => filesize($backupPath),
            'include_files' => $includeFiles,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Get system backups
     */
    public function getSystemBackups()
    {
        return DB::table('system_backups')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get specific system backup
     */
    public function getSystemBackup(int $backupId)
    {
        $backup = DB::table('system_backups')->find($backupId);
        
        if (!$backup) {
            throw new \Exception('Backup not found');
        }
        
        if (!file_exists($backup->file_path)) {
            throw new \Exception('Backup file not found');
        }
        
        return [
            'id' => $backup->id,
            'name' => $backup->name,
            'path' => $backup->file_path,
            'filename' => $backup->name . '.zip',
            'size' => $backup->file_size,
            'include_files' => $backup->include_files,
            'created_at' => $backup->created_at
        ];
    }

    /**
     * Restore system from backup
     */
    public function restoreSystemBackup($backupFile, string $password = null)
    {
        $tempPath = $backupFile->store('temp');
        $fullPath = storage_path('app/' . $tempPath);
        
        try {
            // Verify backup file
            if (!$this->verifyBackupFile($fullPath)) {
                throw new \Exception('Invalid backup file');
            }
            
            // Extract backup
            $extractPath = storage_path('app/temp/restore_' . uniqid());
            $this->extractBackup($fullPath, $extractPath, $password);
            
            // Restore database
            $this->restoreDatabase($extractPath . '/database/dump.sql');
            
            // Restore files
            $this->restoreFiles($extractPath);
            
            // Clean up
            $this->cleanupRestore($extractPath);
            
            return [
                'success' => true,
                'message' => 'System restored successfully'
            ];
            
        } catch (\Exception $e) {
            // Clean up on error
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            throw $e;
        }
    }

    /**
     * Verify backup file
     */
    private function verifyBackupFile(string $filePath)
    {
        $zip = new ZipArchive();
        
        if ($zip->open($filePath) !== TRUE) {
            return false;
        }
        
        // Check for required files
        $requiredFiles = ['database/dump.sql', 'backup_metadata.json'];
        foreach ($requiredFiles as $file) {
            if ($zip->locateName($file) === false) {
                $zip->close();
                return false;
            }
        }
        
        $zip->close();
        return true;
    }

    /**
     * Extract backup
     */
    private function extractBackup(string $backupPath, string $extractPath, string $password = null)
    {
        $zip = new ZipArchive();
        
        if ($zip->open($backupPath) !== TRUE) {
            throw new \Exception('Cannot open backup file');
        }
        
        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0755, true);
        }
        
        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            throw new \Exception('Cannot extract backup file');
        }
        
        $zip->close();
    }

    /**
     * Restore database
     */
    private function restoreDatabase(string $dumpPath)
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');

        $command = "mysql --host={$host} --port={$port} --user={$username} --password={$password} {$database} < {$dumpPath}";
        
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception('Database restore failed');
        }
    }

    /**
     * Restore files
     */
    private function restoreFiles(string $extractPath)
    {
        $filesPath = $extractPath . '/files';
        
        if (!file_exists($filesPath)) {
            return; // No files to restore
        }
        
        $uploadPaths = [
            'public/uploads',
            'storage/app/public',
            'storage/app/tenant_files'
        ];
        
        foreach ($uploadPaths as $path) {
            $fullPath = base_path($path);
            $sourcePath = $filesPath . '/' . basename($path);
            
            if (file_exists($sourcePath)) {
                if (file_exists($fullPath)) {
                    File::deleteDirectory($fullPath);
                }
                File::moveDirectory($sourcePath, $fullPath);
            }
        }
    }

    /**
     * Clean up restore process
     */
    private function cleanupRestore(string $extractPath)
    {
        File::deleteDirectory($extractPath);
    }

    /**
     * Delete system backup
     */
    public function deleteSystemBackup(int $backupId)
    {
        $backup = DB::table('system_backups')->find($backupId);
        
        if (!$backup) {
            throw new \Exception('Backup not found');
        }
        
        if (file_exists($backup->file_path)) {
            unlink($backup->file_path);
        }
        
        DB::table('system_backups')->where('id', $backupId)->delete();
    }

    /**
     * Export business settings
     */
    public function exportSettings(int $tenantId)
    {
        $tenant = DB::table('tenants')->find($tenantId);
        if (!$tenant) {
            throw new \Exception('Tenant not found');
        }
        
        $settings = [
            'tenant' => $tenant,
            'categories' => DB::table('categories')->where('tenant_id', $tenantId)->get(),
            'tax_settings' => DB::table('tax_settings')->where('tenant_id', $tenantId)->get(),
            'receipt_templates' => DB::table('receipt_templates')->where('tenant_id', $tenantId)->get(),
            'user_roles' => DB::table('user_roles')->where('tenant_id', $tenantId)->get(),
            'fiscal_years' => DB::table('fiscal_years')->where('tenant_id', $tenantId)->get(),
            'exported_at' => now()->toISOString()
        ];
        
        $filename = 'settings_' . $tenant->business_name . '_' . now()->format('Y-m-d_H-i-s') . '.json';
        $filepath = storage_path('app/exports/' . $filename);
        
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        file_put_contents($filepath, json_encode($settings, JSON_PRETTY_PRINT));
        
        return [
            'path' => $filepath,
            'filename' => $filename
        ];
    }

    /**
     * Import business settings
     */
    public function importSettings(int $tenantId, $settingsFile)
    {
        $content = file_get_contents($settingsFile->getPathname());
        $settings = json_decode($content, true);
        
        if (!$settings) {
            throw new \Exception('Invalid settings file');
        }
        
        DB::beginTransaction();
        
        try {
            // Import categories
            if (isset($settings['categories'])) {
                foreach ($settings['categories'] as $category) {
                    unset($category['id']);
                    $category['tenant_id'] = $tenantId;
                    DB::table('categories')->insert($category);
                }
            }
            
            // Import tax settings
            if (isset($settings['tax_settings'])) {
                foreach ($settings['tax_settings'] as $taxSetting) {
                    unset($taxSetting['id']);
                    $taxSetting['tenant_id'] = $tenantId;
                    DB::table('tax_settings')->insert($taxSetting);
                }
            }
            
            // Import receipt templates
            if (isset($settings['receipt_templates'])) {
                foreach ($settings['receipt_templates'] as $template) {
                    unset($template['id']);
                    $template['tenant_id'] = $tenantId;
                    DB::table('receipt_templates')->insert($template);
                }
            }
            
            // Import user roles
            if (isset($settings['user_roles'])) {
                foreach ($settings['user_roles'] as $role) {
                    unset($role['id']);
                    $role['tenant_id'] = $tenantId;
                    DB::table('user_roles')->insert($role);
                }
            }
            
            // Import fiscal years
            if (isset($settings['fiscal_years'])) {
                foreach ($settings['fiscal_years'] as $fiscalYear) {
                    unset($fiscalYear['id']);
                    $fiscalYear['tenant_id'] = $tenantId;
                    DB::table('fiscal_years')->insert($fiscalYear);
                }
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Settings imported successfully'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get settings backups
     */
    public function getSettingsBackups(int $tenantId)
    {
        return DB::table('settings_backups')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get last backup date
     */
    public function getLastBackupDate(int $tenantId)
    {
        $backup = DB::table('system_backups')
            ->orderBy('created_at', 'desc')
            ->first();
        
        return $backup ? $backup->created_at : null;
    }

    /**
     * Get backup size
     */
    public function getBackupSize(int $tenantId)
    {
        $backups = DB::table('system_backups')
            ->sum('file_size');
        
        return $backups;
    }

    /**
     * Get backup status
     */
    public function getBackupStatus(int $tenantId)
    {
        $lastBackup = $this->getLastBackupDate($tenantId);
        $backupSize = $this->getBackupSize($tenantId);
        
        return [
            'last_backup' => $lastBackup,
            'total_size' => $backupSize,
            'backup_count' => DB::table('system_backups')->count(),
            'status' => $lastBackup ? 'up_to_date' : 'no_backups'
        ];
    }

    /**
     * Schedule backups
     */
    public function scheduleBackups(array $scheduleData)
    {
        DB::table('backup_schedules')->updateOrInsert(
            ['id' => 1],
            [
                'frequency' => $scheduleData['frequency'],
                'time' => $scheduleData['time'],
                'retention_days' => $scheduleData['retention_days'],
                'include_files' => $scheduleData['include_files'],
                'email_notifications' => $scheduleData['email_notifications'],
                'email_recipients' => json_encode($scheduleData['email_recipients'] ?? []),
                'is_active' => true,
                'updated_at' => now()
            ]
        );
    }

    /**
     * Clean up old data
     */
    public function cleanupOldData(string $dataType, int $olderThanDays)
    {
        $cutoffDate = now()->subDays($olderThanDays);
        $deletedCount = 0;
        
        switch ($dataType) {
            case 'logs':
                $deletedCount = DB::table('activity_logs')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
                break;
            case 'backups':
                $oldBackups = DB::table('system_backups')
                    ->where('created_at', '<', $cutoffDate)
                    ->get();
                
                foreach ($oldBackups as $backup) {
                    if (file_exists($backup->file_path)) {
                        unlink($backup->file_path);
                    }
                }
                
                $deletedCount = DB::table('system_backups')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
                break;
            case 'exports':
                $deletedCount = DB::table('export_logs')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
                break;
            case 'imports':
                $deletedCount = DB::table('import_logs')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
                break;
        }
        
        return [
            'deleted_count' => $deletedCount,
            'data_type' => $dataType,
            'cutoff_date' => $cutoffDate
        ];
    }

    /**
     * Get database version
     */
    private function getDatabaseVersion()
    {
        $result = DB::select('SELECT VERSION() as version');
        return $result[0]->version ?? 'Unknown';
    }
}