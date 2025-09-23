<?php

namespace App\Services;

use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Carbon\Carbon;

class AuthService
{
    /**
     * Register a new user with email verification.
     */
    public function registerWithEmail(array $data): array
    {
        // Check if user already exists
        $existingUser = User::where('email', $data['email'])->first();
        
        if ($existingUser) {
            if ($existingUser->isVerified()) {
                throw ValidationException::withMessages([
                    'email' => ['User already exists and is verified. Please login instead.']
                ]);
            }
            // User exists but not verified, resend OTP
            $this->sendOtpEmail($data['email']);
            return [
                'user' => $existingUser,
                'requires_verification' => true,
                'message' => 'OTP sent to your email for verification.'
            ];
        }

        // Create new user
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone_number' => $data['phone_number'] ?? null,
            'role' => null, // No default role, user will choose later
            'status' => 'pending',
        ]);

        // Send OTP
        $this->sendOtpEmail($user->email);

        return [
            'user' => $user,
            'requires_verification' => true,
            'message' => 'Registration successful. OTP sent to your email.'
        ];
    }

    /**
     * Login user with email and password.
     */
    public function loginWithEmail(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.']
            ]);
        }

        if (!$user->isVerified()) {
            $this->sendOtpEmail($user->email);
            return [
                'user' => $user,
                'requires_verification' => true,
                'message' => 'Please verify your email. OTP sent.'
            ];
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'requires_verification' => false,
            'message' => 'Login successful.'
        ];
    }

    /**
     * Handle Google OAuth callback.
     */
    public function handleGoogleCallback(): array
    {
        try {
            /** @var \Laravel\Socialite\Two\GoogleProvider $googleProvider */
            $googleProvider = Socialite::driver('google');
            $googleUser = $googleProvider->stateless()->user();
            
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // User exists, update Google ID if not set
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleUser->getId()]);
                }
                
                // Mark as verified if not already
                if (!$user->isVerified()) {
                    $user->update(['email_verified_at' => Carbon::now()]);
                }
            } else {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->getName() ?: 'Google User',
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'role' => null, // No default role, user will choose later
                    'status' => 'active',
                    'email_verified_at' => Carbon::now(),
                    'profile_picture_path' => $googleUser->getAvatar(),
                    // password is null for Google users
                    // phone_number is null for Google users - can be collected later
                ]);
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
                'requires_verification' => false,
                'needs_profile_completion' => $user->needsProfileCompletion(),
                'message' => 'Google authentication successful.'
            ];

        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'google' => ['Google authentication failed. Please try again.']
            ]);
        }
    }

    /**
     * Verify OTP and activate user account.
     */
    public function verifyOtp(string $otpCode): array
    {
        // Find the most recent valid OTP for verification
        $otp = Otp::where('type', 'verification')
                  ->where('used', false)
                  ->where('expires_at', '>', Carbon::now())
                  ->orderBy('created_at', 'desc')
                  ->first();

        if (!$otp || !$otp->verifyOtp($otpCode)) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired OTP code.']
            ]);
        }

        // Mark OTP as used
        $otp->markAsUsed();

        // Verify user email
        $user = User::where('email', $otp->email)->first();
        if ($user && !$user->isVerified()) {
            $user->update([
                'email_verified_at' => Carbon::now(),
                'status' => 'active'
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'message' => 'Email verified successfully.'
        ];
    }

    /**
     * Resend OTP to user's email.
     */
    public function resendOtp(string $email): array
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.']
            ]);
        }

        if ($user->isVerified()) {
            throw ValidationException::withMessages([
                'email' => ['Email is already verified.']
            ]);
        }

        $this->sendOtpEmail($email);

        return ['message' => 'OTP sent successfully.'];
    }

    /**
     * Update user role and create appropriate profile.
     */
    public function updateUserRole(int $userId, string $role, array $profileData = []): User
    {
        $user = User::findOrFail($userId);
        
        $user->update(['role' => $role]);

        // Create appropriate profile based on role (only if doesn't exist)
        switch ($role) {
            case 'patient':
                $user->patientProfile()->firstOrCreate($profileData);
                break;
            case 'doctor':
                $user->doctorProfile()->firstOrCreate($profileData);
                break;
            case 'pharmacy':
                $user->pharmacyProfile()->firstOrCreate($profileData);
                break;
        }

        return $user->load(['patientProfile', 'doctorProfile', 'pharmacyProfile']);
    }

    /**
     * Send password reset OTP to user's email.
     */
    public function sendPasswordResetOtp(string $email): array
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found with this email address.']
            ]);
        }

        if (!$user->isVerified()) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email first before resetting password.']
            ]);
        }

        $this->sendPasswordResetOtpEmail($email);

        return ['message' => 'Password reset OTP sent to your email.'];
    }

    /**
     * Verify password reset OTP and reset password.
     */
    public function resetPasswordWithOtp(string $otpCode, string $newPassword): array
    {
        // Find the most recent valid OTP for password reset
        $otp = Otp::where('type', 'password_reset')
                  ->where('used', false)
                  ->where('expires_at', '>', Carbon::now())
                  ->orderBy('created_at', 'desc')
                  ->first();

        if (!$otp || !$otp->verifyOtp($otpCode)) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired OTP code.']
            ]);
        }

        // Mark OTP as used
        $otp->markAsUsed();

        // Update user password
        $user = User::where('email', $otp->email)->first();
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.']
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword)
        ]);

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        return [
            'message' => 'Password reset successfully. Please login with your new password.'
        ];
    }

    /**
     * Send OTP email to user.
     */
    private function sendOtpEmail(string $email): void
    {
        try {
            $otpRecord = Otp::generateOtp($email, 'verification');
            $otpCode = $otpRecord->getPlainOtp();
            
            // Send email with OTP
            Mail::send('emails.otp', ['otp' => $otpCode], function ($message) use ($email) {
                $message->to($email);
                $message->subject('Tadawi - Email Verification Code');
            });

            // In development, log the OTP for testing
            if (app()->environment('local')) {
                Log::info("OTP for {$email}: {$otpCode}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to send OTP to {$email}: " . $e->getMessage());
            throw ValidationException::withMessages([
                'email' => ['Failed to send OTP. Please try again.']
            ]);
        }
    }

    /**
     * Send password reset OTP email to user.
     */
    private function sendPasswordResetOtpEmail(string $email): void
    {
        try {
            $otpRecord = Otp::generateOtp($email, 'password_reset');
            $otpCode = $otpRecord->getPlainOtp();
            
            // Send email with OTP
            Mail::send('emails.password-reset-otp', ['otp' => $otpCode], function ($message) use ($email) {
                $message->to($email);
                $message->subject('Tadawi - Password Reset Code');
            });

            // In development, log the OTP for testing
            if (app()->environment('local')) {
                Log::info("Password Reset OTP for {$email}: {$otpCode}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to send password reset OTP to {$email}: " . $e->getMessage());
            throw ValidationException::withMessages([
                'email' => ['Failed to send password reset OTP. Please try again.']
            ]);
        }
    }
}
