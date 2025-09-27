<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize()
    {
        // Require authenticated user
        return auth()->check();
    }

    public function rules()
    {
        return [
            'medicine_id' => 'required|integer|exists:medicines,id',
            'pharmacy_id' => 'required|integer|exists:pharmacy_profiles,id',
            'quantity' => 'required|integer|min:1',
        ];
    }

    public function messages()
    {
        return [
            'quantity.min' => 'Quantity must be greater than 0',
            'medicine_id.integer' => 'Medicine ID must be a valid number',
            'pharmacy_id.integer' => 'Pharmacy ID must be a valid number',
        ];
    }

    // Stock validation is now handled in CartController with proper locking
}
