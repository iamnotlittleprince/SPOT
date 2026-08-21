<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            StockMovement::with(['product:id,name,sku', 'user:id,name'])->latest()->limit(100)->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'type' => ['required', 'in:in,out'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $movement = DB::transaction(function () use ($data) {
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);
            if ($data['type'] === 'out' && $product->quantity < $data['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => 'Estoque insuficiente para essa saída.',
                ]);
            }

            $product->quantity += $data['type'] === 'in' ? $data['quantity'] : -$data['quantity'];
            $product->save();

            return StockMovement::create([
                ...$data,
                'user_id' => Auth::guard('api')->id(),
            ]);
        });

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
