<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductVariantService
{
    public function sync(Product $product, array $variants): void
    {
        DB::transaction(function () use ($product, $variants): void {
            $retainedIds = [];

            foreach ($variants as $attributes) {
                $id = $attributes['id'] ?? null;
                unset($attributes['id']);
                $attributes = array_map(
                    fn ($value) => is_string($value) && trim($value) === '' ? null : $value,
                    $attributes
                );

                if ($id) {
                    $variant = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->find($id);
                    if (!$variant) {
                        throw ValidationException::withMessages([
                            'variants' => ['A selected variant does not belong to this product.'],
                        ]);
                    }
                    $variant->fill($attributes)->save();
                } else {
                    $variant = new ProductVariant($attributes);
                    $variant->product_id = $product->id;
                    $variant->save();
                }

                $retainedIds[] = $variant->id;
            }

            $variantsToDelete = $product->variants();
            if ($retainedIds) {
                $variantsToDelete->whereNotIn('id', $retainedIds);
            }
            $variantsToDelete->delete();

            $product->load('variants');
            if ($product->variants->isNotEmpty()) {
                $product->price = $product->variants->min('price');
                $product->stock_quantity = $product->variants->sum('stock_quantity');
                $product->save();
            }
        });
    }
}