<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user.
     */
    public function register(AuthRequest $request)
    {

        try {
            $result = $this->authService->registerWithEmail($request->only([
                'name', 'email', 'password', 'phone_number'
            ]));

            return response()->json($result, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Login user with email and password.
     */
    public function login(AuthRequest $request)
    {

        try {
            $result = $this->authService->loginWithEmail($request->only(['email', 'password']));
            return response()->json($result, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Authentication failed',
                'errors' => $e->errors()
            ], 401);
        }
    }

    /**
     * Redirect to Google OAuth.
     */
    public function googleRedirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback.
     */
    public function googleCallback()
    {
        try {
            $result = $this->authService->handleGoogleCallback();

            // In a SPA, you might want to redirect to frontend with token
            // return redirect()->to(config('app.frontend_url') . '/auth/callback?token=' . $result['token']);

            return response()->json($result, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Google authentication failed',
                'errors' => $e->errors()
            ], 401);
        }
    }

    /**
     * Verify OTP code.
     */
    public function verifyOtp(AuthRequest $request)
    {
        try {
            $result = $this->authService->verifyOtp($request->otp, $request->email);
            return response()->json($result, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'OTP verification failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Resend OTP code.
     */
    public function resendOtp(AuthRequest $request)
    {
        try {
            $result = $this->authService->resendOtp($request->email);
            return response()->json($result, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Failed to resend OTP',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Update user role and create profile.
     */
    public function updateRole(AuthRequest $request)
    {

        try {
            $user = $this->authService->updateUserRole(
                $request->user()->id,
                $request->role,
                $request->profile_data ?? []
            );

            return response()->json([
                'user' => $user,
                'message' => 'Role updated successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current authenticated user.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        // If user is not verified, return limited information
        if (!$user->isVerified()) {
            return response()->json([
                'user' => $user->only(['id', 'name', 'email', 'email_verified_at']),
                'is_verified' => false,
                'needs_verification' => true,
                'message' => 'Please verify your email to access full features.'
            ], 200);
        }

        return response()->json([
            'user' => $user->load(['patientProfile', 'doctorProfile', 'pharmacyProfile']),
            'is_verified' => true,
            'needs_profile_completion' => $user->needsProfileCompletion()
        ]);
    }

    /**
     * Send password reset OTP to user's email.
     */
    public function sendPasswordResetOtp(AuthRequest $request)
    {
        try {
            $result = $this->authService->sendPasswordResetOtp($request->email);
            return response()->json($result, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Failed to send password reset OTP',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Reset password using OTP verification.
     */
    public function resetPassword(AuthRequest $request)
    {
        try {
            $result = $this->authService->resetPasswordWithOtp(
                $request->otp,
                $request->password
            );
            return response()->json($result, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Password reset failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Logout user (revoke current token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.'
        ]);
    }
}
