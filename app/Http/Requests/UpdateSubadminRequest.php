<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class UpdateSubadminRequest extends FormRequest
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
        $subadminId = $this->route('id');

        return [
            'name'      => 'sometimes|string|max:255',
            'email'     => 'sometimes|email|unique:subadmins,email,' . $subadminId,
            'password'  => 'sometimes|string|min:6|confirmed',
            'branch_id' => 'sometimes|uuid|exists:branches,id',
            'is_active' => 'sometimes|boolean',
            'permissions' => ['sometimes', 'nullable', 'array'],
            'permissions.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new ValidationException($validator, response()->json([
            'status' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
