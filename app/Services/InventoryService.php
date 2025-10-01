<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Get inventory statistics
     */
    public function getInventoryStats($tenantId)
    {
        $stats = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->selectRaw('
                COUNT(*) as total_products,
                SUM(stock_quantity) as total_stock_value,
                SUM(stock_quantity * cost_price) as total_inventory_value,
                SUM(CASE WHEN stock_quantity <= min_stock_level THEN 1 ELSE 0 END) as low_stock_count,
                SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count
            ')
            ->first();

        // Get recent movements count
        $recentMovements = StockMovement::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Get low stock products count
        $lowStockProducts = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereRaw('stock_quantity <= min_stock_level')
            ->count();

        return [
            'total_products' => $stats->total_products ?? 0,
            'total_stock_value' => $stats->total_stock_value ?? 0,
            'total_inventory_value' => $stats->total_inventory_value ?? 0,
            'low_stock_count' => $stats->low_stock_count ?? 0,
            'out_of_stock_count' => $stats->out_of_stock_count ?? 0,
            'recent_movements' => $recentMovements,
            'low_stock_products' => $lowStockProducts
        ];
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts($tenantId, $limit = 10)
    {
        return Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereRaw('stock_quantity <= min_stock_level')
            ->with('category')
            ->orderBy('stock_quantity', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent stock movements
     */
    public function getRecentMovements($tenantId, $limit = 10)
    {
        return StockMovement::where('tenant_id', $tenantId)
            ->with(['product'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get stock movement history for a product
     */
    public function getProductMovementHistory($productId, $limit = 50)
    {
        return StockMovement::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get inventory valuation
     */
    public function getInventoryValuation($tenantId, $method = 'fifo')
    {
        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $totalValue = 0;
        $valuationDetails = [];

        foreach ($products as $product) {
            $value = $this->calculateProductValue($product, $method);
            $totalValue += $value;
            
            $valuationDetails[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $product->stock_quantity,
                'unit_cost' => $product->cost_price,
                'total_value' => $value
            ];
        }

        return [
            'total_value' => $totalValue,
            'details' => $valuationDetails
        ];
    }

    /**
     * Calculate product value using specified method
     */
    private function calculateProductValue(Product $product, $method)
    {
        switch ($method) {
            case 'fifo':
                return $this->calculateFIFOValue($product);
            case 'lifo':
                return $this->calculateLIFOValue($product);
            case 'average':
                return $this->calculateAverageValue($product);
            case 'standard':
            default:
                return $product->stock_quantity * $product->cost_price;
        }
    }

    /**
     * Calculate FIFO value
     */
    private function calculateFIFOValue(Product $product)
    {
        // Get stock movements in chronological order
        $movements = StockMovement::where('product_id', $product->id)
            ->where('movement_type', 'in')
            ->orderBy('created_at', 'asc')
            ->get();

        $remainingQuantity = $product->stock_quantity;
        $totalValue = 0;

        foreach ($movements as $movement) {
            if ($remainingQuantity <= 0) break;

            $quantityToUse = min($remainingQuantity, $movement->quantity);
            $unitCost = $this->getUnitCostFromMovement($movement);
            $totalValue += $quantityToUse * $unitCost;
            $remainingQuantity -= $quantityToUse;
        }

        return $totalValue;
    }

    /**
     * Calculate LIFO value
     */
    private function calculateLIFOValue(Product $product)
    {
        // Get stock movements in reverse chronological order
        $movements = StockMovement::where('product_id', $product->id)
            ->where('movement_type', 'in')
            ->orderBy('created_at', 'desc')
            ->get();

        $remainingQuantity = $product->stock_quantity;
        $totalValue = 0;

        foreach ($movements as $movement) {
            if ($remainingQuantity <= 0) break;

            $quantityToUse = min($remainingQuantity, $movement->quantity);
            $unitCost = $this->getUnitCostFromMovement($movement);
            $totalValue += $quantityToUse * $unitCost;
            $remainingQuantity -= $quantityToUse;
        }

        return $totalValue;
    }

    /**
     * Calculate average value
     */
    private function calculateAverageValue(Product $product)
    {
        $totalCost = 0;
        $totalQuantity = 0;

        $movements = StockMovement::where('product_id', $product->id)
            ->where('movement_type', 'in')
            ->get();

        foreach ($movements as $movement) {
            $unitCost = $this->getUnitCostFromMovement($movement);
            $totalCost += $movement->quantity * $unitCost;
            $totalQuantity += $movement->quantity;
        }

        if ($totalQuantity > 0) {
            $averageCost = $totalCost / $totalQuantity;
            return $product->stock_quantity * $averageCost;
        }

        return $product->stock_quantity * $product->cost_price;
    }

    /**
     * Get unit cost from movement
     */
    private function getUnitCostFromMovement(StockMovement $movement)
    {
        // This would need to be enhanced to store unit cost in movements
        // For now, return the product's current cost price
        return $movement->product->cost_price;
    }

    /**
     * Get stock turnover analysis
     */
    public function getStockTurnoverAnalysis($tenantId, $period = 30)
    {
        $startDate = now()->subDays($period);
        
        // Get products with sales in the period
        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('saleItems.sale', function($query) use ($startDate) {
                $query->where('sale_date', '>=', $startDate);
            })
            ->with(['saleItems' => function($query) use ($startDate) {
                $query->whereHas('sale', function($q) use ($startDate) {
                    $q->where('sale_date', '>=', $startDate);
                });
            }])
            ->get();

        $turnoverAnalysis = [];

        foreach ($products as $product) {
            $totalSold = $product->saleItems->sum('quantity');
            $averageStock = $this->getAverageStock($product->id, $period);
            $turnoverRate = $averageStock > 0 ? $totalSold / $averageStock : 0;
            $daysToSell = $turnoverRate > 0 ? $period / $turnoverRate : 0;

            $turnoverAnalysis[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'total_sold' => $totalSold,
                'average_stock' => $averageStock,
                'turnover_rate' => round($turnoverRate, 2),
                'days_to_sell' => round($daysToSell, 1)
            ];
        }

        // Sort by turnover rate descending
        usort($turnoverAnalysis, function($a, $b) {
            return $b['turnover_rate'] <=> $a['turnover_rate'];
        });

        return $turnoverAnalysis;
    }

    /**
     * Get average stock for a product over a period
     */
    private function getAverageStock($productId, $period)
    {
        $startDate = now()->subDays($period);
        
        // Get stock movements in the period
        $movements = StockMovement::where('product_id', $productId)
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($movements->isEmpty()) {
            return Product::find($productId)->stock_quantity;
        }

        $totalStockDays = 0;
        $currentStock = Product::find($productId)->stock_quantity;
        $lastDate = now();

        // Calculate weighted average
        foreach ($movements->reverse() as $movement) {
            $daysDiff = $lastDate->diffInDays($movement->created_at);
            $totalStockDays += $currentStock * $daysDiff;
            
            if ($movement->movement_type === 'in') {
                $currentStock -= $movement->quantity;
            } else {
                $currentStock += $movement->quantity;
            }
            
            $lastDate = $movement->created_at;
        }

        // Add remaining days
        $daysDiff = $lastDate->diffInDays($startDate);
        $totalStockDays += $currentStock * $daysDiff;

        return $totalStockDays / $period;
    }

    /**
     * Get reorder recommendations
     */
    public function getReorderRecommendations($tenantId)
    {
        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('reorder_level')
            ->whereRaw('stock_quantity <= reorder_level')
            ->with('category')
            ->get();

        $recommendations = [];

        foreach ($products as $product) {
            $turnoverAnalysis = $this->getStockTurnoverAnalysis($tenantId, 30);
            $productTurnover = collect($turnoverAnalysis)
                ->firstWhere('product_id', $product->id);

            $recommendedQuantity = $this->calculateReorderQuantity($product, $productTurnover);

            $recommendations[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'current_stock' => $product->stock_quantity,
                'reorder_level' => $product->reorder_level,
                'recommended_quantity' => $recommendedQuantity,
                'urgency' => $this->calculateUrgency($product, $productTurnover)
            ];
        }

        // Sort by urgency
        usort($recommendations, function($a, $b) {
            return $b['urgency'] <=> $a['urgency'];
        });

        return $recommendations;
    }

    /**
     * Calculate reorder quantity
     */
    private function calculateReorderQuantity(Product $product, $turnoverAnalysis)
    {
        if (!$turnoverAnalysis) {
            return $product->reorder_level * 2; // Default: 2x reorder level
        }

        $daysToSell = $turnoverAnalysis['days_to_sell'];
        $safetyStock = $product->reorder_level;
        $leadTime = 7; // Assume 7 days lead time
        
        $requiredStock = ($daysToSell + $leadTime) * ($turnoverAnalysis['total_sold'] / 30);
        $recommendedQuantity = max($requiredStock - $product->stock_quantity, $safetyStock);

        return round($recommendedQuantity);
    }

    /**
     * Calculate urgency level
     */
    private function calculateUrgency(Product $product, $turnoverAnalysis)
    {
        $stockRatio = $product->stock_quantity / $product->reorder_level;
        
        if ($stockRatio <= 0.5) return 'critical';
        if ($stockRatio <= 0.8) return 'high';
        if ($stockRatio <= 1.0) return 'medium';
        
        return 'low';
    }

    /**
     * Get inventory aging report
     */
    public function getInventoryAgingReport($tenantId)
    {
        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->get();

        $agingReport = [];

        foreach ($products as $product) {
            $lastMovement = StockMovement::where('product_id', $product->id)
                ->where('movement_type', 'in')
                ->orderBy('created_at', 'desc')
                ->first();

            $daysInStock = $lastMovement 
                ? now()->diffInDays($lastMovement->created_at)
                : 999; // Very old if no movement found

            $agingCategory = $this->getAgingCategory($daysInStock);

            $agingReport[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $product->stock_quantity,
                'value' => $product->stock_quantity * $product->cost_price,
                'days_in_stock' => $daysInStock,
                'aging_category' => $agingCategory,
                'last_movement' => $lastMovement ? $lastMovement->created_at : null
            ];
        }

        // Sort by days in stock descending
        usort($agingReport, function($a, $b) {
            return $b['days_in_stock'] <=> $a['days_in_stock'];
        });

        return $agingReport;
    }

    /**
     * Get aging category
     */
    private function getAgingCategory($daysInStock)
    {
        if ($daysInStock <= 30) return 'fresh';
        if ($daysInStock <= 60) return 'recent';
        if ($daysInStock <= 90) return 'aging';
        if ($daysInStock <= 180) return 'old';
        
        return 'very_old';
    }
}