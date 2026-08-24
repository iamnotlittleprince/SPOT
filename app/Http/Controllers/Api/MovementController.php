<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Inventory\Actions\RegisterStockMovement;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;

class MovementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            StockMovement::with(['product:id,name,sku', 'user:id,name'])->latest()->limit(100)->get()
        );
    }

    public function store(StoreStockMovementRequest $request, RegisterStockMovement $action): JsonResponse
    {
        $movement = $action->execute($request->validated(), $request->user());

        return response()->json($movement->load(['product', 'user']), 201);
    }

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'total_products' => Product::count(),
            'total_categories' => Category::count(),
            'total_stock_units' => (int) Product::sum('quantity'),
            'total_stock_value' => (float) Product::query()->selectRaw('COALESCE(SUM(quantity * price), 0) as value')->value('value'),
            'recent_movements' => StockMovement::with('product:id,name')->latest()->limit(5)->get(),
        ]);
    }
}
