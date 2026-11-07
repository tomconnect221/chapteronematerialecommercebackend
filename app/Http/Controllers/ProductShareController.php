<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ProductShareController extends Controller
{
    public function show(Request $request, string $id): Response
    {
        $product = Product::with('category')->findOrFail($id);
        $frontendBaseUrl = rtrim(
            config('app.frontend_url') ?: $request->getSchemeAndHttpHost(),
            '/'
        );
        $productUrl = $frontendBaseUrl . '/product/' . rawurlencode($product->id);
        $imagePath = $product->image;
        $publicBaseUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/');
        $imageUrl = $imagePath && filter_var($imagePath, FILTER_VALIDATE_URL)
            ? $imagePath
            : $publicBaseUrl . '/' . ltrim($imagePath ?: 'favicon.ico', '/');
        $rawDescription = $product->meta_description
            ?: $product->short_description
            ?: $product->description
            ?: 'View this product at Chapter One.';
        $description = html_entity_decode((string) $rawDescription, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = preg_replace('/\s+/u', ' ', trim(strip_tags($description))) ?? '';

        return response()->view('share.product', [
            'title' => $product->meta_title ?: $product->name,
            'description' => Str::limit($description, 300, ''),
            'imageUrl' => $imageUrl,
            'shareUrl' => route('products.share', ['id' => $product->id], true),
            'productUrl' => $productUrl,
            'price' => $product->sale_price ?: $product->price,
            'category' => $product->category?->name,
        ])->header('Cache-Control', 'public, max-age=300');
    }
}