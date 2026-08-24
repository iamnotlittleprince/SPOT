<?php

namespace App\Domain\Inventory\Actions;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegisterStockMovement
{
    /** @param array{product_id:int, type:string, quantity:int, note?:string|null} $data */
    public function execute(array $data, User $actor): StockMovement
    {
        return DB::transaction(function () use ($data, $actor): StockMovement {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);

            if ($data['type'] === 'out' && $product->quantity < $data['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => 'Estoque insuficiente para essa saída.',
                ]);
            }

            $product->increment('quantity', $data['type'] === 'in' ? $data['quantity'] : -$data['quantity']);

            return StockMovement::create([
                ...$data,
                'user_id' => $actor->getKey(),
            ]);
        }, attempts: 3);
    }
}
