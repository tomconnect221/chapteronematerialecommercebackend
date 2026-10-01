<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterCustomerRequest;
use App\Mail\PasswordResetMail;
use App\Mail\WelcomeEmail;
use App\Models\Customer;
use App\Models\Rider;
use App\Models\Subadmin;
use App\Models\User;
use App\Services\MailConfigService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        // Select model based on role
        $model = match ($credentials['role']) {
            'admin' => User::class,
            'subadmin' => Subadmin::class,
            'customer' => Customer::class,
            'rider' => Rider::class,
            default => null,
        };

        // Check if a user with the given email exists in the correct model
        $user = $model::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Auth passed: create Passport access token
        $token = $user->createToken(env('API_TOKEN_KEY', 'API Token'))->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
            ]
        ]);
    }

    /**
     * Register customer
     */
    public function register(RegisterCustomerRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Validate and prepare data
            $data = $request->validated();
            $data['password'] = Hash::make($data['password']);

            // Create customer record
            $customer = Customer::create($data);

            // Send welcome email
            try {
                // Ensure mail config is loaded
                app(MailConfigService::class)->loadFromDatabase();
                Mail::to($customer->email)->send(new WelcomeEmail($customer));
            } catch (\Exception $e) {
                Log::warning('Welcome email failed to send', [
                    'customer_id' => $customer->id,
                    'email' => $customer->email,
                    'error' => $e->getMessage()
                ]);
                // Don't fail registration if email fails
            }

            // Optionally auto-login the customer
            Auth::login($customer);

            // Generate Passport token
            $token = $customer->createToken(env('API_TOKEN_KEY', 'API Token'))->accessToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer registered successfully',
                'data' => [
                    'user' => $customer,
                    'token' => $token,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Customer registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again later.',
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user && $user->token()) {
                $user->token()->revoke();
            }

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }

    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|string|in:admin,customer,subadmin,rider',
        ]);

        try {
            // Determine model
            $model = match ($request->role) {
                'admin' => User::class,
                'subadmin' => Subadmin::class,
                'customer' => Customer::class,
                'rider' => Rider::class,
                default => null,
            };

            if (!$model) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Invalid role specified'
                ], 400);
            }

            $user = $model::where('email', $request->email)->first();
            if (!$user) {
                // For security, don't reveal if email exists or not
                return response()->json([
                    'success' => true,
                    'message' => 'If the email exists in our system, a password reset link has been sent.'
                ]);
            }

            // Generate secure token
            $token = Str::random(64);
            $hashedToken = Hash::make($token);
            
            // Store token with expiration (60 minutes)
            DB::table('password_resets')->updateOrInsert(
                ['email' => $user->email],
                [
                    'email' => $user->email,
                    'token' => $hashedToken,
                    'created_at' => now()
                ]
            );

            // Create secure reset link
            $resetLink = url("/password-reset?token={$token}&email=" . urlencode($user->email) . "&role={$request->role}");
            
            // Send email
            try {
                // Ensure mail config is loaded
                app(MailConfigService::class)->loadFromDatabase();
                Mail::to($user->email)->send(new PasswordResetMail($resetLink));
            } catch (\Exception $e) {
                Log::error('Password reset email failed to send', [
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => 'If the email exists in our system, a password reset link has been sent.'
            ]);

        } catch (\Exception $e) {
            Log::error('Password reset link failed', [
                'email' => $request->email,
                'role' => $request->role,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset link. Please try again later.'
            ], 500);
        }
    }

    /**
     * Reset password (both authenticated and via link)
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
            'email' => 'sometimes|required|email|max:255',
            'token' => 'sometimes|required|string|size:64',
            'role' => 'sometimes|required|string|in:admin,customer,subadmin,rider',
        ]);

        try {
            // Try to get authenticated user from any guard
            $user = $this->getAuthenticatedUser($request);

            if (!$user) {
                // Use token + email + role for password reset
                if (!$request->filled(['email', 'token', 'role'])) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Missing required parameters for password reset'
                    ], 400);
                }

                $model = match ($request->role) {
                    'admin' => User::class,
                    'subadmin' => Subadmin::class,
                    'customer' => Customer::class,
                    'rider' => Rider::class,
                    default => null,
                };

                if (!$model) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Invalid role specified'
                    ], 400);
                }

                $user = $model::where('email', $request->email)->first();
                if (!$user) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'User not found'
                    ], 404);
                }

                // Verify token and check expiration
                $record = DB::table('password_resets')->where('email', $request->email)->first();
                if (!$record) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'No password reset request found for this email'
                    ], 400);
                }

                if (!Hash::check($request->token, $record->token)) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Invalid reset token'
                    ], 400);
                }

                if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
                    // Clean up expired token
                    DB::table('password_resets')->where('email', $request->email)->delete();
                    return response()->json([
                        'success' => false, 
                        'message' => 'Password reset token has expired. Please request a new one.'
                    ], 400);
                }
            }

            // Update password
            $user->password = Hash::make($request->password);
            $user->save();

            // Clean up token if password reset was via link
            if (!$request->user()) {
                DB::table('password_resets')->where('email', $request->email)->delete();
            }

            // Log successful password reset
            Log::info('Password reset successful', [
                'user_id' => $user->id,
                'user_type' => get_class($user),
                'email' => $user->email
            ]);

            return response()->json([
                'success' => true, 
                'message' => 'Password updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Password reset failed', [
                'email' => $request->email ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get authenticated user from any guard
     */
    protected function getAuthenticatedUser(Request $request)
    {
        // Try all possible guards in order
        $guards = ['api', 'customer-api', 'subadmin-api', 'rider-api'];
        
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return Auth::guard($guard)->user();
            }
        }
        
        // Fallback to request->user() which uses the default guard
        return $request->user();
    }
}
