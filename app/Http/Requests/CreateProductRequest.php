<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class CreateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0|max:999999.99',
            'cost_price' => 'nullable|numeric|min:0|max:999999.99',
            'sale_price' => 'nullable|numeric|min:0|max:999999.99',
            'discount_type' => 'nullable|string|in:percent,fixed',
            'discount_value' => 'nullable|numeric|min:0|max:999999.99',
            'stock_quantity' => 'required|integer|min:0|max:999999',
            'min_stock_level' => 'nullable|integer|min:0|max:999999',
            'sku' => 'required|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'weight' => 'nullable|numeric|min:0|max:999.99',
            'dimensions' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'size' => 'nullable|string|max:50',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'warranty_period' => 'nullable|integer|min:0|max:120',
            'is_featured' => 'nullable|boolean',
            'is_ad' => 'nullable|boolean',
            'ad_discount_type' => 'nullable|string|in:percent,fixed',
            'ad_discount_value' => 'nullable|numeric|min:0|max:999999.99',
            'is_digital' => 'nullable|boolean',
            'requires_shipping' => 'nullable|boolean',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'category_id' => 'required|uuid|exists:categories,id',
            'branch_id' => 'required|uuid|exists:branches,id',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:50',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'meta_keywords' => 'nullable|string|max:255',
            'variants' => 'nullable|array|max:100',
            'variants.*.id' => 'sometimes|nullable|uuid|exists:product_variants,id',
            'variants.*.color' => 'nullable|string|max:60',
            'variants.*.size' => 'nullable|string|max:50',
            'variants.*.dimensions' => 'nullable|string|max:100',
            'variants.*.price' => 'required_with:variants|numeric|min:0|max:999999.99',
            'variants.*.stock_quantity' => 'required_with:variants|integer|min:0|max:999999',
        ];

        $variants = $this->input('variants', []);
        foreach (is_array($variants) ? $variants : [] as $index => $variant) {
            $uniqueSku = Rule::unique('product_variants', 'sku');
            if (!empty($variant['id'])) {
                $uniqueSku->ignore($variant['id']);
            }
            $rules["variants.$index.sku"] = ['nullable', 'string', 'max:100', $uniqueSku];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $variants = $this->input('variants');
        if (is_string($variants)) {
            $decoded = json_decode($variants, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge(['variants' => $decoded]);
            }
        }
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'name.max' => 'Product name cannot exceed 255 characters.',
            'description.max' => 'Product description cannot exceed 2000 characters.',
            'short_description.max' => 'Short description cannot exceed 500 characters.',
            'price.required' => 'Product price is required.',
            'price.numeric' => 'Price must be a valid number.',
            'price.min' => 'Price cannot be negative.',
            'price.max' => 'Price cannot exceed 999,999.99.',
            'cost_price.numeric' => 'Cost price must be a valid number.',
            'cost_price.min' => 'Cost price cannot be negative.',
            'sale_price.numeric' => 'Sale price must be a valid number.',
            'sale_price.min' => 'Sale price cannot be negative.',
            'stock_quantity.required' => 'Stock quantity is required.',
            'stock_quantity.integer' => 'Stock quantity must be a whole number.',
            'stock_quantity.min' => 'Stock quantity cannot be negative.',
            'sku.required' => 'SKU is required.',
            'sku.unique' => 'This SKU is already in use.',
            'sku.max' => 'SKU cannot exceed 100 characters.',
            'barcode.unique' => 'This barcode is already in use.',
            'barcode.max' => 'Barcode cannot exceed 100 characters.',
            'weight.numeric' => 'Weight must be a valid number.',
            'weight.min' => 'Weight cannot be negative.',
            'dimensions.max' => 'Dimensions cannot exceed 100 characters.',
            'color.max' => 'Color cannot exceed 50 characters.',
            'size.max' => 'Size cannot exceed 50 characters.',
            'brand.max' => 'Brand cannot exceed 100 characters.',
            'model.max' => 'Model cannot exceed 100 characters.',
            'warranty_period.integer' => 'Warranty period must be a whole number.',
            'warranty_period.max' => 'Warranty period cannot exceed 120 months.',
            'tax_rate.numeric' => 'Tax rate must be a valid number.',
            'tax_rate.max' => 'Tax rate cannot exceed 100%.',
            'image.max' => 'The image may not be greater than 2 MB.',
            'images.max' => 'Cannot upload more than 10 images.',
            'images.*.max' => 'Each additional image may not be greater than 2 MB.',
            'category_id.required' => 'Product category is required.',
            'category_id.exists' => 'Selected category does not exist.',
            'branch_id.required' => 'Branch assignment is required.',
            'branch_id.exists' => 'Selected branch does not exist.',
            'tags.max' => 'Cannot add more than 20 tags.',
            'tags.*.max' => 'Each tag cannot exceed 50 characters.',
            'meta_title.max' => 'Meta title cannot exceed 60 characters.',
            'meta_description.max' => 'Meta description cannot exceed 160 characters.',
            'meta_keywords.max' => 'Meta keywords cannot exceed 255 characters.',
            'variants.max' => 'A product cannot have more than 100 variants.',
            'variants.*.price.required_with' => 'Each variant must have a price.',
            'variants.*.stock_quantity.required_with' => 'Each variant must have a stock quantity.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new ValidationException($validator, response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()->messages(),
        ], 422));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $seen = [];
            $variants = $this->input('variants', []);
            foreach (is_array($variants) ? $variants : [] as $index => $variant) {
                if (!is_array($variant)) {
                    continue;
                }

                $options = array_map(
                    fn ($value) => mb_strtolower(trim((string) ($value ?? ''))),
                    [$variant['color'] ?? '', $variant['size'] ?? '', $variant['dimensions'] ?? '']
                );
                if ($options === ['', '', '']) {
                    $validator->errors()->add("variants.$index.color", 'Add a color, size, or dimension to identify this variant.');
                    continue;
                }

                $key = implode('|', $options);
                if (isset($seen[$key])) {
                    $validator->errors()->add("variants.$index.color", 'This color, size, and dimension combination is duplicated.');
                }
                $seen[$key] = true;
            }
        });
    }
}
