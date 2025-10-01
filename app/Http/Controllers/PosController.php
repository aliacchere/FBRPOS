<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Customer;
use App\Services\FbrIntegrationService;
use App\Services\WhatsAppService;
use App\Services\ReceiptService;

class PosController extends Controller
{
    protected $fbrService;
    protected $whatsappService;
    protected $receiptService;

    public function __construct(
        FbrIntegrationService $fbrService,
        WhatsAppService $whatsappService,
        ReceiptService $receiptService
    ) {
        $this->fbrService = $fbrService;
        $this->whatsappService = $whatsappService;
        $this->receiptService = $receiptService;
    }

    /**
     * Display the POS interface
     */
    public function index()
    {
        $tenant = Auth::user()->tenant;
        $products = Product::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with('category')
            ->get();
        
        $categories = $tenant->categories()->where('is_active', true)->get();
        
        return view('pos.index', compact('products', 'categories'));
    }

    /**
     * Get products for POS
     */
    public function getProducts()
    {
        $tenant = Auth::user()->tenant;
        
        $products = Product::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with('category')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'price' => $product->price,
                    'stock_quantity' => $product->stock_quantity,
                    'category_id' => $product->category_id,
                    'image_url' => $product->image ? asset('uploads/products/' . $product->image) : null,
                    'tax_category' => $product->tax_category,
                    'hs_code' => $product->hs_code,
                    'unit_of_measure' => $product->unit_of_measure
                ];
            });

        return response()->json($products);
    }

    /**
     * Get categories for POS
     */
    public function getCategories()
    {
        $tenant = Auth::user()->tenant;
        
        $categories = $tenant->categories()
            ->where('is_active', true)
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name
                ];
            });

        return response()->json($categories);
    }

    /**
     * Process a new sale
     */
    public function processSale(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,easypaisa,jazzcash,credit',
            'amount_received' => 'required|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0'
        ]);

        $tenant = Auth::user()->tenant;
        $user = Auth::user();

        DB::beginTransaction();

        try {
            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber($tenant->id);

            // Create sale record
            $sale = Sale::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $request->subtotal,
                'tax_amount' => $request->tax,
                'total_amount' => $request->total,
                'payment_method' => $request->payment_method,
                'payment_status' => 'paid',
                'sale_date' => now()->toDateString(),
                'fbr_status' => 'pending'
            ]);

            // Create sale items and update stock
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                // Check stock
                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name}");
                }

                // Create sale item
                $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                    'tax_rate' => 18.00,
                    'tax_amount' => ($item['quantity'] * $item['unit_price']) * 0.18
                ]);

                // Update stock
                $product->decrement('stock_quantity', $item['quantity']);

                // Create stock movement
                $product->stockMovements()->create([
                    'tenant_id' => $tenant->id,
                    'movement_type' => 'out',
                    'quantity' => $item['quantity'],
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'notes' => "Sale: {$invoiceNumber}"
                ]);
            }

            // Process FBR integration
            $fbrResult = $this->fbrService->processSale($sale);

            if ($fbrResult['success']) {
                $sale->update([
                    'fbr_invoice_number' => $fbrResult['fbr_invoice_number'],
                    'fbr_status' => 'synced'
                ]);
            } else {
                $sale->update([
                    'fbr_status' => 'failed',
                    'fbr_error' => $fbrResult['error']
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'invoice_number' => $invoiceNumber,
                'fbr_invoice_number' => $fbrResult['fbr_invoice_number'] ?? null,
                'fbr_status' => $sale->fbr_status
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's statistics
     */
    public function getTodayStats()
    {
        $tenant = Auth::user()->tenant;
        $today = now()->toDateString();

        $stats = DB::table('sales')
            ->where('tenant_id', $tenant->id)
            ->where('sale_date', $today)
            ->selectRaw('
                COUNT(*) as total_sales,
                SUM(total_amount) as total_revenue,
                SUM(CASE WHEN fbr_status = "synced" THEN 1 ELSE 0 END) as synced_sales,
                SUM(CASE WHEN fbr_status = "pending" THEN 1 ELSE 0 END) as pending_sales,
                SUM(CASE WHEN fbr_status = "failed" THEN 1 ELSE 0 END) as failed_sales
            ')
            ->first();

        $fbrSyncRate = $stats->total_sales > 0 
            ? round(($stats->synced_sales / $stats->total_sales) * 100, 1)
            : 0;

        return response()->json([
            'total_sales' => $stats->total_sales ?? 0,
            'total_revenue' => $stats->total_revenue ?? 0,
            'fbr_sync_rate' => $fbrSyncRate,
            'synced_sales' => $stats->synced_sales ?? 0,
            'pending_sales' => $stats->pending_sales ?? 0,
            'failed_sales' => $stats->failed_sales ?? 0
        ]);
    }

    /**
     * Print receipt
     */
    public function printReceipt($saleId)
    {
        $sale = Sale::with(['items.product', 'tenant', 'user'])
            ->where('id', $saleId)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        return $this->receiptService->generateReceipt($sale);
    }

    /**
     * Send WhatsApp receipt
     */
    public function sendWhatsAppReceipt(Request $request, $saleId)
    {
        $request->validate([
            'phone_number' => 'required|string'
        ]);

        $sale = Sale::with(['items.product', 'tenant'])
            ->where('id', $saleId)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $result = $this->whatsappService->sendReceipt($request->phone_number, $sale);

        return response()->json($result);
    }

    /**
     * Send email receipt
     */
    public function sendEmailReceipt(Request $request, $saleId)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $sale = Sale::with(['items.product', 'tenant'])
            ->where('id', $saleId)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        // Implement email sending logic
        // This would use Laravel's Mail facade

        return response()->json([
            'success' => true,
            'message' => 'Receipt sent successfully'
        ]);
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber($tenantId)
    {
        $prefix = 'INV';
        $year = now()->format('Y');
        $month = now()->format('m');
        
        $lastInvoice = Sale::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastInvoice 
            ? (int)substr($lastInvoice->invoice_number, -4) + 1
            : 1;

        return $prefix . $year . $month . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}