<?php

namespace Tests\Unit;

use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateProductRequestTest extends TestCase
{
    public function test_image_is_optional_when_creating_a_product(): void
    {
        $validator = Validator::make([], (new CreateProductRequest())->rules());

        $this->assertArrayNotHasKey('image', $validator->errors()->toArray());
    }

    public function test_supplied_image_still_must_be_an_image(): void
    {
        $validator = Validator::make(
            ['image' => 'not-an-image'],
            (new CreateProductRequest())->rules()
        );

        $this->assertArrayHasKey('image', $validator->errors()->toArray());
    }

    public function test_variants_require_price_and_stock(): void
    {
        $payload = [
            'variants' => [[
                'color' => 'Sky blue',
                'size' => 'A4',
                'dimensions' => '20 x 30 cm',
            ]],
        ];
        $validator = $this->makeProductValidator($payload);

        $this->assertArrayHasKey('variants.0.price', $validator->errors()->toArray());
        $this->assertArrayHasKey('variants.0.stock_quantity', $validator->errors()->toArray());
    }

    public function test_duplicate_variant_combinations_are_rejected(): void
    {
        $payload = [
            'variants' => [
                ['color' => 'Sky blue', 'size' => 'A4', 'dimensions' => '20 x 30 cm', 'price' => 10, 'stock_quantity' => 2],
                ['color' => ' sky blue ', 'size' => 'a4', 'dimensions' => '20 x 30 CM', 'price' => 12, 'stock_quantity' => 1],
            ],
        ];
        $validator = $this->makeProductValidator($payload);

        $this->assertArrayHasKey('variants.1.color', $validator->errors()->toArray());
    }

    public function test_distinct_text_variants_are_valid(): void
    {
        $payload = [
            'variants' => [
                ['color' => 'Sky blue', 'size' => 'A4', 'dimensions' => '20 x 30 cm', 'price' => 10, 'stock_quantity' => 2],
                ['color' => 'Charcoal', 'size' => 'A3', 'dimensions' => '30 x 40 cm', 'price' => 12, 'stock_quantity' => 1],
            ],
        ];
        $validator = $this->makeProductValidator($payload);

        $errors = $validator->errors()->toArray();
        $this->assertArrayNotHasKey('variants.0.color', $errors);
        $this->assertArrayNotHasKey('variants.1.color', $errors);
    }

    public function test_create_product_validation_response_uses_exact_error_messages(): void
    {
        $request = new class extends CreateProductRequest {
            public function invokeFailedValidation($validator): void
            {
                $this->failedValidation($validator);
            }
        };

        $validator = Validator::make([
            'images' => ['too-long'],
            'category_id' => 'not-a-uuid',
            'branch_id' => 'not-a-uuid',
            'sku' => str_repeat('x', 101),
        ], $request->rules(), $request->messages());

        try {
            $request->invokeFailedValidation($validator);
            $this->fail('Expected validation exception to be thrown.');
        } catch (ValidationException $exception) {
            $payload = json_decode($exception->response->getContent(), true);

            $this->assertSame(false, $payload['success']);
            $this->assertSame('Validation failed', $payload['message']);
            $this->assertArrayHasKey('images.0', $payload['errors']);
            $this->assertIsArray($payload['errors']['images.0']);
            $this->assertNotEmpty($payload['errors']['images.0'][0]);
            $this->assertArrayNotHasKey('status', $payload);
        }
    }

    public function test_update_product_validation_response_uses_exact_error_messages(): void
    {
        $request = new class extends UpdateProductRequest {
            public function invokeFailedValidation($validator): void
            {
                $this->failedValidation($validator);
            }
        };

        $validator = Validator::make([
            'images' => ['too-long'],
            'category_id' => 'not-a-uuid',
        ], $request->rules(), $request->messages());

        try {
            $request->invokeFailedValidation($validator);
            $this->fail('Expected validation exception to be thrown.');
        } catch (ValidationException $exception) {
            $payload = json_decode($exception->response->getContent(), true);

            $this->assertSame(false, $payload['success']);
            $this->assertSame('Validation failed', $payload['message']);
            $this->assertArrayHasKey('images.0', $payload['errors']);
            $this->assertIsArray($payload['errors']['images.0']);
            $this->assertNotEmpty($payload['errors']['images.0'][0]);
            $this->assertArrayNotHasKey('status', $payload);
        }
    }

    private function makeProductValidator(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        $request = new CreateProductRequest();
        $request->merge($payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());
        $request->withValidator($validator);

        return $validator;
    }
}