<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuthRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $method = $this->method();
        $route = $this->route()->getName();

        // Registration validation
        if ($method === 'POST' && $this->is('api/v1/auth/register')) {
            return [
                'name' => [
                    'required',
                    'string',
                    'min:2',
                    'max:255',
                    'regex:/^[a-zA-Z\s]+$/'
                ],
                'email' => [
                    'required',
                    'string',
                    'email:rfc,dns',
                    'max:255',
                    'unique:users,email'
                ],
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
                ],
                'password_confirmation' => [
                    'required',
                    'same:password'
                ],
                'phone_number' => [
                    'nullable',
                    'string',
                    'regex:/^\+20[0-9]{10}$/',
                    'unique:users,phone_number'
                ]
            ];
        }

        // Login validation
        if ($method === 'POST' && $this->is('api/v1/auth/login')) {
            return [
                'email' => [
                    'required',
                    'string',
                    'email:rfc,dns'
                ],
                'password' => [
                    'required',
                    'string'
                ]
            ];
        }

        // OTP verification validation
        if ($method === 'POST' && $this->is('api/v1/auth/verify-otp')) {
            return [
                'otp' => [
                    'required',
                    'string',
                    'size:6',
                    'regex:/^[0-9]{6}$/'
                ],
                'email' => [
                    'required',
                    'string',
                    'email:rfc,dns'
                ]
            ];
        }

        // Resend OTP validation
        if ($method === 'POST' && $this->is('api/v1/auth/resend-otp')) {
            return [
                'email' => [
                    'required',
                    'string',
                    'email:rfc,dns'
                ]
            ];
        }

        // Update role validation
        if ($method === 'POST' && $this->is('api/v1/auth/update-role')) {
            return [
                'role' => [
                    'required',
                    'string',
                    Rule::in(['patient', 'doctor', 'pharmacy'])
                ],
                'profile_data' => [
                    'sometimes',
                    'array'
                ]
            ];
        }

        // Send password reset OTP validation
        if ($method === 'POST' && $this->is('api/v1/auth/send-password-reset-otp')) {
            return [
                'email' => [
                    'required',
                    'string',
                    'email:rfc,dns'
                ]
            ];
        }

        // Reset password validation
        if ($method === 'POST' && $this->is('api/v1/auth/reset-password')) {
            return [
                'otp' => [
                    'required',
                    'string',
                    'size:6',
                    'regex:/^[0-9]{6}$/'
                ],
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
                ],
                'password_confirmation' => [
                    'required',
                    'same:password'
                ]
            ];
        }

        return [];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            // Name validation messages
            'name.required' => 'Name is required.',
            'name.regex' => 'Name can only contain letters and spaces.',
            'name.min' => 'Name must be at least 2 characters.',
            'name.max' => 'Name cannot exceed 255 characters.',

            // Email validation messages
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'We couldn’t complete your registration with this email.',
            'email.max' => 'Email cannot exceed 255 characters.',

            // Password validation messages
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',

            // Password confirmation messages
            'password_confirmation.required' => 'Password confirmation is required.',
            'password_confirmation.same' => 'Password confirmation does not match.',

            // Phone number validation messages
            'phone_number.regex' => 'Phone number must be in Egyptian format: +20 followed by 10 digits.',
            'phone_number.unique' => 'This phone number is already registered.',

            // OTP validation messages
            'otp.required' => 'OTP code is required.',
            'otp.size' => 'OTP code must be exactly 6 digits.',
            'otp.regex' => 'OTP code must contain only numbers.',

            // Role validation messages
            'role.required' => 'Role is required.',
            'role.in' => 'Role must be one of: patient, doctor, pharmacy.',

            // Profile data messages
            'profile_data.array' => 'Profile data must be an array.'
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
            'password' => 'password',
            'password_confirmation' => 'password confirmation',
            'phone_number' => 'phone number',
            'otp' => 'verification code',
            'role' => 'user role',
            'profile_data' => 'profile information'
        ];
    }
}
