<?php
/**
 * DPS POS FBR Integrated - Process Sale
 */

session_start();
require_once '../config/database.php';
require_once '../config/app.php';
require_once '../includes/functions.php';
require_once '../includes/fbr_engine.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check authentication and role
if (!is_logged_in() || !in_array($_SESSION['user_role'], ['tenant_admin', 'cashier'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

try {
    // Validate required fields
    if (empty($data['items']) || !is_array($data['items'])) {
        throw new Exception('No items in cart');
    }

    if (empty($data['total']) || $data['total'] <= 0) {
        throw new Exception('Invalid total amount');
    }

    // Get tenant information
    $tenant = get_current_tenant();
    if (!$tenant) {
        throw new Exception('Tenant not found');
    }

    // Start database transaction
    $pdo->beginTransaction();

    // Generate invoice number
    $invoice_number = generate_invoice_number($tenant['id']);

    // Create sale record
    $sale_data = [
        'tenant_id' => $tenant['id'],
        'user_id' => $_SESSION['user_id'],
        'customer_id' => null, // Will be set if customer exists
        'invoice_number' => $invoice_number,
        'reference_number' => '',
        'fbr_invoice_number' => null,
        'fbr_status' => 'pending',
        'fbr_error' => null,
        'subtotal' => $data['subtotal'],
        'tax_amount' => $data['tax'],
        'discount_amount' => 0,
        'total_amount' => $data['total'],
        'payment_method' => $data['payment_method'],
        'payment_status' => 'paid',
        'notes' => $data['notes'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $sale_id = db_insert('sales', $sale_data);

    if (!$sale_id) {
        throw new Exception('Failed to create sale record');
    }

    // Process sale items
    foreach ($data['items'] as $item) {
        // Validate product
        $product = db_fetch("SELECT * FROM products WHERE id = ? AND tenant_id = ?", [$item['productId'], $tenant['id']]);
        
        if (!$product) {
            throw new Exception('Product not found: ' . $item['name']);
        }

        // Check stock
        if ($product['stock_quantity'] < $item['quantity']) {
            throw new Exception('Insufficient stock for: ' . $item['name']);
        }

        // Calculate tax based on product category
        $tax_category = get_tax_categories()[$product['tax_category']];
        $item_tax = $item['price'] * $item['quantity'] * ($tax_category['rate'] / 100);

        // Create sale item record
        $sale_item_data = [
            'sale_id' => $sale_id,
            'product_id' => $item['productId'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['price'],
            'total_price' => $item['price'] * $item['quantity'],
            'tax_rate' => $tax_category['rate'],
            'tax_amount' => $item_tax,
            'created_at' => date('Y-m-d H:i:s')
        ];

        if (!db_insert('sale_items', $sale_item_data)) {
            throw new Exception('Failed to create sale item record');
        }

        // Update stock
        $new_stock = $product['stock_quantity'] - $item['quantity'];
        db_update('products', 
            ['stock_quantity' => $new_stock, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$item['productId']]
        );

        // Create stock movement record
        db_insert('stock_movements', [
            'tenant_id' => $tenant['id'],
            'product_id' => $item['productId'],
            'movement_type' => 'out',
            'quantity' => $item['quantity'],
            'reference_type' => 'sale',
            'reference_id' => $sale_id,
            'notes' => 'Sale: ' . $invoice_number,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    // Try FBR integration
    $fbr_engine = new FBRIntegrationEngine($tenant['id']);
    
    if ($fbr_engine->isConfigured()) {
        $fbr_result = $fbr_engine->processSale([
            'sale_id' => $sale_id,
            'created_at' => $sale_data['created_at'],
            'customer_name' => $data['customer_name'],
            'customer_ntn' => null,
            'customer_province' => $tenant['province'],
            'customer_address' => 'N/A',
            'reference_number' => ''
        ]);

        if ($fbr_result['success']) {
            // Update sale with FBR invoice number
            db_update('sales',
                [
                    'fbr_invoice_number' => $fbr_result['fbr_invoice_number'],
                    'fbr_status' => 'synced',
                    'fbr_error' => null
                ],
                'id = ?',
                [$sale_id]
            );
        } else {
            // Update sale with FBR error
            db_update('sales',
                [
                    'fbr_status' => 'failed',
                    'fbr_error' => $fbr_result['error']
                ],
                'id = ?',
                [$sale_id]
            );
        }
    }

    // Log the sale
    db_insert('audit_logs', [
        'tenant_id' => $tenant['id'],
        'user_id' => $_SESSION['user_id'],
        'action' => 'sale_created',
        'table_name' => 'sales',
        'record_id' => $sale_id,
        'new_values' => json_encode($sale_data),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'sale_id' => $sale_id,
        'invoice_number' => $invoice_number,
        'fbr_status' => $fbr_result['success'] ?? false,
        'fbr_invoice_number' => $fbr_result['fbr_invoice_number'] ?? null
    ]);

} catch (Exception $e) {
    // Rollback transaction
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Sale Processing Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>