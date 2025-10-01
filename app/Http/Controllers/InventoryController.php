<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\InventoryService;
use App\Services\NotificationService;

class InventoryController extends Controller
{
    protected $inventoryService;
    protected $notificationService;

    public function __construct(InventoryService $inventoryService, NotificationService $notificationService)
    {
        $this->inventoryService = $inventoryService;
        $this->notificationService = $notificationService;
    }

    /**
     * Display inventory dashboard
     */
    public function index()
    {
        $tenant = Auth::user()->tenant;
        
        $stats = $this->inventoryService->getInventoryStats($tenant->id);
        $lowStockProducts = $this->inventoryService->getLowStockProducts($tenant->id);
        $recentMovements = $this->inventoryService->getRecentMovements($tenant->id);
        
        return view('inventory.dashboard', compact('stats', 'lowStockProducts', 'recentMovements'));
    }

    /**
     * Display products list
     */
    public function products()
    {
        $tenant = Auth::user()->tenant;
        $products = Product::where('tenant_id', $tenant->id)
            ->with('category')
            ->paginate(20);
        
        $categories = $tenant->categories()->where('is_active', true)->get();
        
        return view('inventory.products', compact('products', 'categories'));
    }

    /**
     * Store a new product
     */
    public function storeProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'category_id' => 'nullable|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_category' => 'required|in:standard_rate,third_schedule,reduced_rate,exempt,steel',
            'hs_code' => 'required|string|max:20',
            'unit_of_measure' => 'required|string|max:50',
            'stock_quantity' => 'required|numeric|min:0',
            'min_stock_level' => 'required|numeric|min:0',
            'max_stock_level' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $tenant = Auth::user()->tenant;
        
        $productData = $request->all();
        $productData['tenant_id'] = $tenant->id;
        
        // Handle image upload
        if ($request->hasFile('image')) {
            $productData['image'] = $this->uploadProductImage($request->file('image'));
        }

        $product = Product::create($productData);

        // Create initial stock movement
        StockMovement::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'movement_type' => 'in',
            'quantity' => $product->stock_quantity,
            'reference_type' => 'adjustment',
            'notes' => 'Initial stock',
            'created_at' => now()
        ]);

        return redirect()->route('inventory.products')
            ->with('success', 'Product created successfully');
    }

    /**
     * Update a product
     */
    public function updateProduct(Request $request, Product $product)
    {
        $this->authorize('update', $product);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|max:100|unique:products,barcode,' . $product->id,
            'category_id' => 'nullable|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_category' => 'required|in:standard_rate,third_schedule,reduced_rate,exempt,steel',
            'hs_code' => 'required|string|max:20',
            'unit_of_measure' => 'required|string|max:50',
            'min_stock_level' => 'required|numeric|min:0',
            'max_stock_level' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $productData = $request->all();
        
        // Handle image upload
        if ($request->hasFile('image')) {
            $productData['image'] = $this->uploadProductImage($request->file('image'));
        }

        $product->update($productData);

        return redirect()->route('inventory.products')
            ->with('success', 'Product updated successfully');
    }

    /**
     * Display purchase orders
     */
    public function purchaseOrders()
    {
        $tenant = Auth::user()->tenant;
        $purchaseOrders = PurchaseOrder::where('tenant_id', $tenant->id)
            ->with(['supplier', 'user', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        $suppliers = $tenant->suppliers()->where('is_active', true)->get();
        
        return view('inventory.purchase-orders', compact('purchaseOrders', 'suppliers'));
    }

    /**
     * Create a new purchase order
     */
    public function createPurchaseOrder(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'expected_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0'
        ]);

        $tenant = Auth::user()->tenant;
        
        DB::beginTransaction();
        
        try {
            // Generate PO number
            $poNumber = $this->generatePONumber($tenant->id);
            
            // Calculate totals
            $subtotal = 0;
            $taxAmount = 0;
            
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $itemTotal;
                $taxAmount += $itemTotal * 0.18; // 18% tax
            }
            
            $totalAmount = $subtotal + $taxAmount;
            
            // Create purchase order
            $purchaseOrder = PurchaseOrder::create([
                'tenant_id' => $tenant->id,
                'supplier_id' => $request->supplier_id,
                'user_id' => Auth::id(),
                'po_number' => $poNumber,
                'status' => 'draft',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
                'expected_date' => $request->expected_date
            ]);
            
            // Create purchase order items
            foreach ($request->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price']
                ]);
            }
            
            DB::commit();
            
            return redirect()->route('inventory.purchase-orders')
                ->with('success', 'Purchase order created successfully');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create purchase order: ' . $e->getMessage());
        }
    }

    /**
     * Receive purchase order items
     */
    public function receivePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', $purchaseOrder);
        
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:purchase_order_items,id',
            'items.*.received_quantity' => 'required|numeric|min:0'
        ]);

        $tenant = Auth::user()->tenant;
        
        DB::beginTransaction();
        
        try {
            foreach ($request->items as $itemData) {
                $item = PurchaseOrderItem::find($itemData['id']);
                $receivedQty = $itemData['received_quantity'];
                
                if ($receivedQty > 0) {
                    // Update received quantity
                    $item->increment('received_quantity', $receivedQty);
                    
                    // Update product stock
                    $product = $item->product;
                    $product->increment('stock_quantity', $receivedQty);
                    
                    // Create stock movement
                    StockMovement::create([
                        'tenant_id' => $tenant->id,
                        'product_id' => $product->id,
                        'movement_type' => 'in',
                        'quantity' => $receivedQty,
                        'reference_type' => 'purchase',
                        'reference_id' => $purchaseOrder->id,
                        'notes' => "Purchase Order: {$purchaseOrder->po_number}",
                        'created_at' => now()
                    ]);
                }
            }
            
            // Check if all items are fully received
            $allReceived = $purchaseOrder->items()->whereRaw('received_quantity < quantity')->count() === 0;
            
            if ($allReceived) {
                $purchaseOrder->update([
                    'status' => 'received',
                    'received_date' => now()
                ]);
            }
            
            DB::commit();
            
            return redirect()->route('inventory.purchase-orders')
                ->with('success', 'Purchase order items received successfully');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'Failed to receive items: ' . $e->getMessage());
        }
    }

    /**
     * Stock adjustment
     */
    public function adjustStock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'adjustment_type' => 'required|in:increase,decrease',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255'
        ]);

        $tenant = Auth::user()->tenant;
        $product = Product::findOrFail($request->product_id);
        
        // Verify product belongs to tenant
        if ($product->tenant_id !== $tenant->id) {
            return redirect()->back()
                ->with('error', 'Product not found');
        }

        $adjustmentQty = $request->quantity;
        if ($request->adjustment_type === 'decrease') {
            $adjustmentQty = -$adjustmentQty;
        }

        DB::beginTransaction();
        
        try {
            // Update stock quantity
            $product->increment('stock_quantity', $adjustmentQty);
            
            // Create stock movement
            StockMovement::create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'movement_type' => $request->adjustment_type === 'increase' ? 'in' : 'out',
                'quantity' => abs($adjustmentQty),
                'reference_type' => 'adjustment',
                'notes' => $request->reason,
                'created_at' => now()
            ]);
            
            DB::commit();
            
            return redirect()->route('inventory.products')
                ->with('success', 'Stock adjusted successfully');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'Failed to adjust stock: ' . $e->getMessage());
        }
    }

    /**
     * Stock transfer between locations
     */
    public function transferStock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_location' => 'required|string|max:255',
            'to_location' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:255'
        ]);

        $tenant = Auth::user()->tenant;
        $product = Product::findOrFail($request->product_id);
        
        // Verify product belongs to tenant
        if ($product->tenant_id !== $tenant->id) {
            return redirect()->back()
                ->with('error', 'Product not found');
        }

        // Check if sufficient stock
        if ($product->stock_quantity < $request->quantity) {
            return redirect()->back()
                ->with('error', 'Insufficient stock for transfer');
        }

        DB::beginTransaction();
        
        try {
            // Update stock quantity
            $product->decrement('stock_quantity', $request->quantity);
            
            // Create stock movement
            StockMovement::create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'movement_type' => 'transfer',
                'quantity' => $request->quantity,
                'reference_type' => 'transfer',
                'notes' => "Transfer from {$request->from_location} to {$request->to_location}. {$request->notes}",
                'created_at' => now()
            ]);
            
            DB::commit();
            
            return redirect()->route('inventory.products')
                ->with('success', 'Stock transferred successfully');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'Failed to transfer stock: ' . $e->getMessage());
        }
    }

    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts()
    {
        $tenant = Auth::user()->tenant;
        $lowStockProducts = $this->inventoryService->getLowStockProducts($tenant->id);
        
        return response()->json($lowStockProducts);
    }

    /**
     * Send low stock notifications
     */
    public function sendLowStockNotifications()
    {
        $tenant = Auth::user()->tenant;
        $lowStockProducts = $this->inventoryService->getLowStockProducts($tenant->id);
        
        if ($lowStockProducts->count() > 0) {
            $this->notificationService->sendLowStockAlert($tenant->id, $lowStockProducts);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Low stock notifications sent'
        ]);
    }

    /**
     * Generate PO number
     */
    private function generatePONumber($tenantId)
    {
        $prefix = 'PO';
        $year = now()->format('Y');
        $month = now()->format('m');
        
        $lastPO = PurchaseOrder::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastPO 
            ? (int)substr($lastPO->po_number, -4) + 1
            : 1;

        return $prefix . $year . $month . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Upload product image
     */
    private function uploadProductImage($file)
    {
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move(public_path('uploads/products'), $filename);
        return $filename;
    }
}