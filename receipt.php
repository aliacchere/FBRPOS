<?php
/**
 * DPS POS FBR Integrated - Receipt Display
 * Public receipt verification page
 */

require_once 'config/database.php';
require_once 'config/app.php';
require_once 'includes/functions.php';

// Get sale ID from URL
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$sale_id) {
    http_response_code(404);
    die('Receipt not found');
}

// Get sale information
$sale = db_fetch("
    SELECT s.*, t.business_name, t.address, t.phone, t.email, t.ntn, u.name as cashier_name
    FROM sales s
    LEFT JOIN tenants t ON s.tenant_id = t.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
", [$sale_id]);

if (!$sale) {
    http_response_code(404);
    die('Receipt not found');
}

// Get sale items
$sale_items = db_fetch_all("
    SELECT si.*, p.name as product_name, p.sku
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
    ORDER BY si.id
", [$sale_id]);

// Get tenant information
$tenant = [
    'business_name' => $sale['business_name'],
    'address' => $sale['address'],
    'phone' => $sale['phone'],
    'email' => $sale['email'],
    'ntn' => $sale['ntn']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo htmlspecialchars($sale['invoice_number']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
        .print-only { display: none; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-2xl mx-auto bg-white shadow-lg">
        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($tenant['business_name']); ?></h1>
                    <p class="text-indigo-200">Digital Receipt</p>
                </div>
                <div class="text-right">
                    <div class="text-3xl mb-2">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <p class="text-sm text-indigo-200">Verified by DPS POS</p>
                </div>
            </div>
        </div>

        <!-- Receipt Content -->
        <div class="p-6">
            <!-- Invoice Details -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Invoice Details</h2>
                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">
                        <?php echo $sale['fbr_status'] === 'synced' ? 'FBR Verified' : 'Internal Receipt'; ?>
                    </span>
                </div>
                
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">Invoice #:</span>
                        <span class="font-semibold"><?php echo htmlspecialchars($sale['invoice_number']); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Date:</span>
                        <span class="font-semibold"><?php echo format_datetime($sale['created_at']); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Cashier:</span>
                        <span class="font-semibold"><?php echo htmlspecialchars($sale['cashier_name']); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Payment:</span>
                        <span class="font-semibold"><?php echo ucfirst($sale['payment_method']); ?></span>
                    </div>
                </div>

                <?php if ($sale['fbr_invoice_number']): ?>
                <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-shield-alt text-green-600 mr-2"></i>
                        <span class="text-green-800 font-semibold">FBR Verified Invoice</span>
                    </div>
                    <p class="text-green-700 text-sm mt-1">
                        FBR Invoice #: <?php echo htmlspecialchars($sale['fbr_invoice_number']); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Items -->
            <div class="mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Items</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2">Item</th>
                                <th class="text-center py-2">Qty</th>
                                <th class="text-right py-2">Price</th>
                                <th class="text-right py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sale_items as $item): ?>
                            <tr class="border-b border-gray-100">
                                <td class="py-2">
                                    <div class="font-semibold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <?php if ($item['sku']): ?>
                                    <div class="text-gray-500 text-xs">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center py-2"><?php echo $item['quantity']; ?></td>
                                <td class="text-right py-2"><?php echo format_currency($item['unit_price']); ?></td>
                                <td class="text-right py-2 font-semibold"><?php echo format_currency($item['total_price']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Totals -->
            <div class="border-t border-gray-200 pt-4">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal:</span>
                        <span><?php echo format_currency($sale['subtotal']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tax (18%):</span>
                        <span><?php echo format_currency($sale['tax_amount']); ?></span>
                    </div>
                    <?php if ($sale['discount_amount'] > 0): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Discount:</span>
                        <span class="text-red-600">-<?php echo format_currency($sale['discount_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-lg font-bold border-t border-gray-200 pt-2">
                        <span>Total:</span>
                        <span><?php echo format_currency($sale['total_amount']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Business Information -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Business Information</h3>
                <div class="text-sm text-gray-600 space-y-1">
                    <div><strong>Business:</strong> <?php echo htmlspecialchars($tenant['business_name']); ?></div>
                    <?php if ($tenant['address']): ?>
                    <div><strong>Address:</strong> <?php echo htmlspecialchars($tenant['address']); ?></div>
                    <?php endif; ?>
                    <?php if ($tenant['phone']): ?>
                    <div><strong>Phone:</strong> <?php echo htmlspecialchars($tenant['phone']); ?></div>
                    <?php endif; ?>
                    <?php if ($tenant['email']): ?>
                    <div><strong>Email:</strong> <?php echo htmlspecialchars($tenant['email']); ?></div>
                    <?php endif; ?>
                    <?php if ($tenant['ntn']): ?>
                    <div><strong>NTN:</strong> <?php echo htmlspecialchars($tenant['ntn']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- QR Code -->
            <div class="mt-8 text-center">
                <div class="inline-block p-4 bg-gray-50 rounded-lg">
                    <div id="qrcode" class="mb-2"></div>
                    <p class="text-xs text-gray-500">Scan to verify receipt</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center text-sm text-gray-500">
                <p>Thank you for your business!</p>
                <p class="mt-2">This receipt was generated by DPS POS FBR Integrated</p>
                <p class="mt-1">Generated on: <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        </div>

        <!-- Print Button -->
        <div class="no-print p-6 bg-gray-50 text-center">
            <button onclick="window.print()" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                <i class="fas fa-print mr-2"></i>Print Receipt
            </button>
        </div>
    </div>

    <!-- QR Code Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        // Generate QR code
        const qrData = {
            invoice: '<?php echo $sale['invoice_number']; ?>',
            date: '<?php echo date('Y-m-d', strtotime($sale['created_at'])); ?>',
            total: '<?php echo $sale['total_amount']; ?>',
            fbr: '<?php echo $sale['fbr_invoice_number'] ?: ''; ?>',
            business: '<?php echo htmlspecialchars($tenant['business_name']); ?>'
        };

        QRCode.toCanvas(document.getElementById('qrcode'), JSON.stringify(qrData), {
            width: 150,
            margin: 2,
            color: {
                dark: '#000000',
                light: '#FFFFFF'
            }
        }, function (error) {
            if (error) console.error(error);
        });
    </script>
</body>
</html>