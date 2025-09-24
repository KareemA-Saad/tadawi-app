<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class DonationUpdateRequest extends FormRequest
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
            'verified' => 'nullable|boolean',
            'status' => 'nullable|in:proposed,under_review,approved,rejected,collected',
            'location' => 'nullable|string',
            'contact_info' => 'nullable|string',
            'medicine_id' => 'nullable|exists:medicines,id',
            'quantity' => 'required_with:medicine_id|integer|min:1',
            'expiry_date' => 'nullable|date|after:today',
            'batch_num' => 'nullable|string|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'verified.required' => 'The verification status is required.',
            'verified.boolean' => 'The verification status must be a boolean (true/false).',
            'status.in' => 'The selected status is invalid. Valid values are: proposed, under_review, approved, rejected, collected.',
            'location.string' => 'The location must be a string.',
            'contact_info.string' => 'The contact info must be a string.',
            'medicine_id.exists' => 'The selected medicine does not exist.',
            'quantity.required_with' => 'The quantity is required when adding a medicine.',
            'quantity.integer' => 'The quantity must be an integer.',
            'quantity.min' => 'The quantity must be at least 1.',
            'expiry_date.date' => 'The expiry date must be a valid date.',
            'expiry_date.after' => 'The expiry date must be after today.',
            'batch_num.string' => 'The batch number must be a string.',
            'batch_num.max' => 'The batch number may not be greater than 100 characters.',
        ];
    }
}
