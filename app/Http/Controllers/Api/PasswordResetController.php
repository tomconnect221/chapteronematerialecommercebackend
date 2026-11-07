<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\Customer;
use App\Models\Rider;
use App\Models\Subadmin;
use App\Models\User;
use App\Support\FrontendUrl;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class PasswordResetController extends Controller
{
    private const ROLES = ['admin', 'customer', 'subadmin', 'rider'];

    public function sendResetLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|string|in:' . implode(',', self::ROLES),
        ]);

        $model = $this->modelForRole($validated['role']);
        $user = $model::where('email', $validated['email'])->first();

        if (!$user) {
            return $this->resetLinkResponse();
        }

        $token = Str::random(64);
        $resetLink = FrontendUrl::base() . '/password/reset?' . http_build_query([
            'token' => $token,
            'email' => $user->email,
            'role' => $validated['role'],
        ]);

        DB::table('password_resets')->updateOrInsert(
            ['email' => $user->email, 'role' => $validated['role']],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        try {
            Mail::to($user->email)->send(new PasswordResetMail($resetLink));
        } catch (Throwable $exception) {
            Log::error('Password reset email failed to send', [
                'email' => $user->email,
                'role' => $validated['role'],
                'error' => $exception->getMessage(),
            ]);
            DB::table('password_resets')
                ->where('email', $user->email)
                ->where('role', $validated['role'])
                ->delete();

            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset link. Please try again later.',
            ], 500);
        }

        return $this->resetLinkResponse();
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
            'email' => 'required|email|max:255',
            'token' => 'required|string|size:64',
            'role' => 'required|string|in:' . implode(',', self::ROLES),
        ]);

        $model = $this->modelForRole($validated['role']);
        $user = $model::where('email', $validated['email'])->first();
        $record = DB::table('password_resets')
            ->where('email', $validated['email'])
            ->where('role', $validated['role'])
            ->first();

        if (!$user || !$record || !Hash::check($validated['token'], $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired password reset link. Please request a new one.',
            ], 400);
        }

        if (
            !$record->created_at ||
            Carbon::parse($record->created_at)->addMinutes(60)->isPast()
        ) {
            DB::table('password_resets')
                ->where('email', $validated['email'])
                ->where('role', $validated['role'])
                ->delete();

            return response()->json([
                'success' => false,
                'message' => 'Password reset link has expired. Please request a new one.',
            ], 400);
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->password = Hash::make($validated['password']);
            $user->save();

            DB::table('password_resets')
                ->where('email', $validated['email'])
                ->where('role', $validated['role'])
                ->delete();
        });

        Log::info('Password reset successful', [
            'user_id' => $user->getKey(),
            'user_type' => get_class($user),
            'email' => $user->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    private function modelForRole(string $role): string
    {
        return match ($role) {
            'admin' => User::class,
            'customer' => Customer::class,
            'subadmin' => Subadmin::class,
            'rider' => Rider::class,
        };
    }

    private function resetLinkResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'If the email exists in our system, a password reset link has been sent.',
        ]);
    }
}
