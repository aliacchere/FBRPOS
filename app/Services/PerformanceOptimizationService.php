<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PerformanceOptimizationService
{
    /**
     * Optimize database queries
     */
    public function optimizeDatabaseQueries()
    {
        $optimizations = [];
        
        // Analyze slow queries
        $slowQueries = $this->analyzeSlowQueries();
        if (!empty($slowQueries)) {
            $optimizations['slow_queries'] = $slowQueries;
        }
        
        // Check for missing indexes
        $missingIndexes = $this->checkMissingIndexes();
        if (!empty($missingIndexes)) {
            $optimizations['missing_indexes'] = $missingIndexes;
        }
        
        // Optimize table structures
        $tableOptimizations = $this->optimizeTableStructures();
        if (!empty($tableOptimizations)) {
            $optimizations['table_optimizations'] = $tableOptimizations;
        }
        
        return $optimizations;
    }

    /**
     * Optimize cache performance
     */
    public function optimizeCachePerformance()
    {
        $optimizations = [];
        
        // Clear expired cache entries
        $clearedEntries = $this->clearExpiredCacheEntries();
        $optimizations['cleared_entries'] = $clearedEntries;
        
        // Optimize cache configuration
        $cacheConfig = $this->optimizeCacheConfiguration();
        $optimizations['cache_config'] = $cacheConfig;
        
        // Warm up frequently accessed data
        $this->warmUpCache();
        
        return $optimizations;
    }

    /**
     * Optimize file storage
     */
    public function optimizeFileStorage()
    {
        $optimizations = [];
        
        // Clean up temporary files
        $cleanedFiles = $this->cleanupTemporaryFiles();
        $optimizations['cleaned_files'] = $cleanedFiles;
        
        // Optimize image files
        $optimizedImages = $this->optimizeImageFiles();
        $optimizations['optimized_images'] = $optimizedImages;
        
        // Compress old files
        $compressedFiles = $this->compressOldFiles();
        $optimizations['compressed_files'] = $compressedFiles;
        
        return $optimizations;
    }

    /**
     * Monitor system performance
     */
    public function monitorSystemPerformance()
    {
        $metrics = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'cpu_usage' => $this->getCpuUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'database_connections' => $this->getDatabaseConnections(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'response_time' => $this->getAverageResponseTime()
        ];
        
        // Log performance metrics
        Log::info('System Performance Metrics', $metrics);
        
        return $metrics;
    }

    /**
     * Generate performance report
     */
    public function generatePerformanceReport()
    {
        $report = [
            'timestamp' => now()->toISOString(),
            'database' => $this->optimizeDatabaseQueries(),
            'cache' => $this->optimizeCachePerformance(),
            'storage' => $this->optimizeFileStorage(),
            'metrics' => $this->monitorSystemPerformance(),
            'recommendations' => $this->getPerformanceRecommendations()
        ];
        
        return $report;
    }

    /**
     * Analyze slow queries
     */
    private function analyzeSlowQueries()
    {
        $slowQueries = [];
        
        // Get slow query log (if enabled)
        $slowQueryLog = config('database.slow_query_log');
        if ($slowQueryLog && file_exists($slowQueryLog)) {
            $queries = file_get_contents($slowQueryLog);
            $lines = explode("\n", $queries);
            
            foreach ($lines as $line) {
                if (strpos($line, 'Query_time:') !== false) {
                    $slowQueries[] = $line;
                }
            }
        }
        
        return $slowQueries;
    }

    /**
     * Check for missing indexes
     */
    private function checkMissingIndexes()
    {
        $missingIndexes = [];
        
        // Check common tables for missing indexes
        $tables = ['sales', 'products', 'customers', 'inventory_movements'];
        
        foreach ($tables as $table) {
            $indexes = DB::select("SHOW INDEX FROM {$table}");
            $indexColumns = array_column($indexes, 'Column_name');
            
            // Check for common missing indexes
            $commonColumns = ['tenant_id', 'created_at', 'updated_at'];
            foreach ($commonColumns as $column) {
                if (!in_array($column, $indexColumns)) {
                    $missingIndexes[] = "Missing index on {$table}.{$column}";
                }
            }
        }
        
        return $missingIndexes;
    }

    /**
     * Optimize table structures
     */
    private function optimizeTableStructures()
    {
        $optimizations = [];
        
        // Analyze tables
        $tables = ['sales', 'products', 'customers', 'inventory_movements'];
        
        foreach ($tables as $table) {
            $result = DB::select("ANALYZE TABLE {$table}");
            $optimizations[] = "Analyzed table {$table}";
        }
        
        return $optimizations;
    }

    /**
     * Clear expired cache entries
     */
    private function clearExpiredCacheEntries()
    {
        $clearedCount = 0;
        
        // Clear expired cache entries
        if (config('cache.default') === 'redis') {
            $redis = Redis::connection();
            $keys = $redis->keys('*');
            
            foreach ($keys as $key) {
                $ttl = $redis->ttl($key);
                if ($ttl === -1) { // No expiration set
                    $redis->expire($key, 3600); // Set 1 hour expiration
                    $clearedCount++;
                }
            }
        }
        
        return $clearedCount;
    }

    /**
     * Optimize cache configuration
     */
    private function optimizeCacheConfiguration()
    {
        $config = [
            'default_ttl' => 3600,
            'max_memory' => '256MB',
            'compression' => true
        ];
        
        return $config;
    }

    /**
     * Warm up cache
     */
    private function warmUpCache()
    {
        // Cache frequently accessed data
        $this->cacheFrequentlyAccessedData();
    }

    /**
     * Cache frequently accessed data
     */
    private function cacheFrequentlyAccessedData()
    {
        // Cache system settings
        Cache::remember('system_settings', 3600, function () {
            return DB::table('system_settings')->get();
        });
        
        // Cache tax rates
        Cache::remember('tax_rates', 3600, function () {
            return DB::table('tax_settings')->where('is_active', true)->get();
        });
        
        // Cache product categories
        Cache::remember('product_categories', 3600, function () {
            return DB::table('categories')->where('is_active', true)->get();
        });
    }

    /**
     * Clean up temporary files
     */
    private function cleanupTemporaryFiles()
    {
        $cleanedCount = 0;
        $tempDirectories = [
            storage_path('app/temp'),
            storage_path('app/exports'),
            storage_path('app/backups')
        ];
        
        foreach ($tempDirectories as $directory) {
            if (file_exists($directory)) {
                $files = glob($directory . '/*');
                $cutoffTime = now()->subDays(7)->timestamp;
                
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < $cutoffTime) {
                        unlink($file);
                        $cleanedCount++;
                    }
                }
            }
        }
        
        return $cleanedCount;
    }

    /**
     * Optimize image files
     */
    private function optimizeImageFiles()
    {
        $optimizedCount = 0;
        $imageDirectories = [
            public_path('uploads/products'),
            public_path('uploads/customers'),
            public_path('uploads/employees')
        ];
        
        foreach ($imageDirectories as $directory) {
            if (file_exists($directory)) {
                $images = glob($directory . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
                
                foreach ($images as $image) {
                    // Compress image if it's too large
                    $fileSize = filesize($image);
                    if ($fileSize > 1024 * 1024) { // 1MB
                        $this->compressImage($image);
                        $optimizedCount++;
                    }
                }
            }
        }
        
        return $optimizedCount;
    }

    /**
     * Compress image
     */
    private function compressImage($imagePath)
    {
        $imageInfo = getimagesize($imagePath);
        $mimeType = $imageInfo['mime'];
        
        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($imagePath);
                imagejpeg($image, $imagePath, 85);
                break;
            case 'image/png':
                $image = imagecreatefrompng($imagePath);
                imagepng($image, $imagePath, 8);
                break;
        }
        
        if (isset($image)) {
            imagedestroy($image);
        }
    }

    /**
     * Compress old files
     */
    private function compressOldFiles()
    {
        $compressedCount = 0;
        $oldFiles = glob(storage_path('app/exports/*'));
        $cutoffTime = now()->subDays(30)->timestamp;
        
        foreach ($oldFiles as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                $this->compressFile($file);
                $compressedCount++;
            }
        }
        
        return $compressedCount;
    }

    /**
     * Compress file
     */
    private function compressFile($filePath)
    {
        $compressedPath = $filePath . '.gz';
        $fp = gzopen($compressedPath, 'w9');
        gzwrite($fp, file_get_contents($filePath));
        gzclose($fp);
        
        // Replace original with compressed version
        unlink($filePath);
        rename($compressedPath, $filePath);
    }

    /**
     * Get CPU usage
     */
    private function getCpuUsage()
    {
        $load = sys_getloadavg();
        return $load[0] ?? 0;
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage()
    {
        $bytes = disk_free_space(storage_path());
        $totalBytes = disk_total_space(storage_path());
        
        return [
            'free' => $bytes,
            'total' => $totalBytes,
            'used' => $totalBytes - $bytes,
            'percentage' => (($totalBytes - $bytes) / $totalBytes) * 100
        ];
    }

    /**
     * Get database connections
     */
    private function getDatabaseConnections()
    {
        $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'");
        return $connections[0]->Value ?? 0;
    }

    /**
     * Get cache hit rate
     */
    private function getCacheHitRate()
    {
        if (config('cache.default') === 'redis') {
            $redis = Redis::connection();
            $info = $redis->info();
            
            $hits = $info['keyspace_hits'] ?? 0;
            $misses = $info['keyspace_misses'] ?? 0;
            $total = $hits + $misses;
            
            return $total > 0 ? ($hits / $total) * 100 : 0;
        }
        
        return 0;
    }

    /**
     * Get average response time
     */
    private function getAverageResponseTime()
    {
        // This would typically be calculated from application logs
        // For now, return a placeholder
        return 0.5; // seconds
    }

    /**
     * Get performance recommendations
     */
    private function getPerformanceRecommendations()
    {
        $recommendations = [];
        
        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        
        if ($memoryUsage > ($memoryLimitBytes * 0.8)) {
            $recommendations[] = 'High memory usage detected. Consider increasing memory limit or optimizing queries.';
        }
        
        // Check disk usage
        $diskUsage = $this->getDiskUsage();
        if ($diskUsage['percentage'] > 80) {
            $recommendations[] = 'High disk usage detected. Consider cleaning up old files or increasing storage.';
        }
        
        // Check database connections
        $connections = $this->getDatabaseConnections();
        if ($connections > 50) {
            $recommendations[] = 'High number of database connections. Consider connection pooling.';
        }
        
        return $recommendations;
    }

    /**
     * Convert memory limit to bytes
     */
    private function convertToBytes($memoryLimit)
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $memoryLimit = (int) $memoryLimit;
        
        switch ($last) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
        }
        
        return $memoryLimit;
    }
}