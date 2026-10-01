<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiderRequest extends FormRequest
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
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:riders,email,' . $this->route('id'),
            'phone' => 'sometimes|string|max:20',
            'license_number' => 'sometimes|string|max:50',
            'vehicle_type' => 'sometimes|string|max:50',
            'vehicle_number' => 'sometimes|string|max:50',
            'status' => 'sometimes|in:available,busy,offline',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
