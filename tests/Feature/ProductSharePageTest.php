<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductSharePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_product_share_page_contains_crawler_metadata_and_frontend_link(): void
    {
        config(['app.frontend_url' => 'https://store.example']);

        $branchId = (string) Str::uuid();
        $categoryId = (string) Str::uuid();
        $productId = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'name' => 'Main branch',
            'address' => 'Test address',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('categories')->insert([
            'id' => $categoryId,
            'name' => 'Stationery',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            'id' => $productId,
            'name' => 'Sky Blue Notebook',
            'description' => '<p>A notebook for daily notes.</p>',
            'short_description' => 'A notebook for daily notes.',
            'price' => 1500,
            'sku' => 'SHARE-TEST-001',
            'image' => '/storage/products/sky-blue-notebook.jpg',
            'category_id' => $categoryId,
            'branch_id' => $branchId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('products.share', ['id' => $productId]));

        $response->assertOk()
            ->assertSee('property="og:title" content="Sky Blue Notebook"', false)
            ->assertSee('property="og:description" content="A notebook for daily notes."', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('storage/products/sky-blue-notebook.jpg', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('https://store.example/product/' . $productId, false);
    }
}