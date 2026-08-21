<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_inventory_resources(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_user_can_register_and_receive_a_jwt(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Ana Estoque',
            'email' => 'ana@example.com',
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
        ])->assertCreated()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user' => ['id', 'name', 'email']]);
    }

    public function test_movement_updates_stock_and_rejects_negative_stock(): void
    {
        $category = Category::create(['name' => 'Papelaria']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Caderno',
            'sku' => 'CAD-001',
            'price' => 20,
            'quantity' => 4,
        ]);
        $user = User::factory()->create();
        $headers = ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];

        $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 2,
            'note' => 'Venda balcão',
        ], $headers)->assertCreated()->assertJsonPath('type', 'out');

        $this->assertSame(2, $product->fresh()->quantity);

        $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 3,
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertSame(2, $product->fresh()->quantity);
    }

    public function test_product_update_does_not_change_quantity(): void
    {
        $category = Category::create(['name' => 'Eletrônicos']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cabo USB',
            'sku' => 'USB-001',
            'price' => 15,
            'quantity' => 10,
        ]);
        $user = User::factory()->create();
        $headers = ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];

        $this->putJson("/api/products/{$product->id}", [
            'category_id' => $category->id,
            'name' => 'Cabo USB-C',
            'sku' => 'USB-001',
            'price' => 18,
            'quantity' => 999,
        ], $headers)->assertOk();

        $this->assertSame(10, $product->fresh()->quantity);
    }
}
