<?php
/**
 * DPS POS FBR Integrated - Application Configuration
 */

// Application Settings
define('APP_NAME', 'DPS POS FBR Integrated');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/dpspos'); // Change this to your domain
define('APP_TIMEZONE', 'Asia/Karachi');

// FBR API Configuration
define('FBR_SANDBOX_BASE_URL', 'https://gw.fbr.gov.pk/di_data/v1/di');
define('FBR_PRODUCTION_BASE_URL', 'https://gw.fbr.gov.pk/di_data/v1/di');
define('FBR_REFERENCE_BASE_URL', 'https://gw.fbr.gov.pk/pdi/v1');

// FBR API Endpoints
define('FBR_VALIDATE_ENDPOINT_SB', '/validateinvoicedata_sb');
define('FBR_POST_ENDPOINT_SB', '/postinvoicedata_sb');
define('FBR_VALIDATE_ENDPOINT_PROD', '/validateinvoicedata');
define('FBR_POST_ENDPOINT_PROD', '/postinvoicedata');

// Reference API Endpoints
define('FBR_PROVINCES_ENDPOINT', '/provinces');
define('FBR_DOC_TYPES_ENDPOINT', '/doctypecode');
define('FBR_HS_CODES_ENDPOINT', '/itemdesccode');
define('FBR_UOM_ENDPOINT', '/uom');
define('FBR_TRANS_TYPES_ENDPOINT', '/transtypecode');

// Currency and Locale
define('CURRENCY_SYMBOL', 'Rs.');
define('CURRENCY_CODE', 'PKR');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');
define('DISPLAY_DATETIME_FORMAT', 'd/m/Y H:i:s');

// File Upload Settings
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Pagination
define('RECORDS_PER_PAGE', 20);

// Security
define('ENCRYPTION_KEY', 'your-32-character-secret-key-here'); // Change this!
define('SESSION_TIMEOUT', 3600); // 1 hour

// WhatsApp Integration
define('WHATSAPP_API_URL', 'https://api.whatsapp.com/send');
define('WHATSAPP_BUSINESS_NUMBER', ''); // Set your WhatsApp Business number

// QR Code Settings
define('QR_CODE_SIZE', 200);
define('QR_CODE_MARGIN', 10);

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set(APP_TIMEZONE);
?>