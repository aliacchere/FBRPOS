<?php
/**
 * DPS POS FBR Integrated - FBR Queue Processor
 * Background worker for processing queued FBR submissions
 * 
 * This script should be run via cron every few minutes:
 * */5 * * * * /usr/bin/php /path/to/dpspos/cron/fbr_queue_processor.php
 */

// Set time limit for long-running script
set_time_limit(300); // 5 minutes

// Include configuration
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/fbr_engine.php';

// Log start of processing
error_log("FBR Queue Processor: Starting at " . date('Y-m-d H:i:s'));

try {
    // Process the queue
    FBRIntegrationEngine::processQueue();
    
    // Log completion
    error_log("FBR Queue Processor: Completed at " . date('Y-m-d H:i:s'));
    
} catch (Exception $e) {
    // Log error
    error_log("FBR Queue Processor Error: " . $e->getMessage());
    
    // Exit with error code
    exit(1);
}

// Exit successfully
exit(0);
?>