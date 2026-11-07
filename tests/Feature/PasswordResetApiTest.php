<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_request_and_complete_a_password_reset(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://store.example']);

        $customerId = (string) Str::uuid();
        DB::table('customers')->insert([
            'id' => $customerId,
            'name' => 'Test Customer',
            'email' => 'customer@example.test',
            'password' => Hash::make('OldPassword123!'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/auth/password/reset-request', [
            'email' => 'customer@example.test',
            'role' => 'customer',
        ])->assertOk()
            ->assertJson(['success' => true]);

        $resetLink = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$resetLink): bool {
            $resetLink = $mail->link;

            return str_starts_with($mail->link, 'https://store.example/password/reset?');
        });

        parse_str((string) parse_url($resetLink, PHP_URL_QUERY), $query);
        $this->assertSame('customer@example.test', $query['email'] ?? null);
        $this->assertSame('customer', $query['role'] ?? null);
        $this->assertSame(64, strlen($query['token'] ?? ''));

        $this->postJson('/api/auth/password/reset', [
            'email' => $query['email'],
            'role' => $query['role'],
            'token' => $query['token'],
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue(Hash::check(
            'NewPassword123!',
            DB::table('customers')->where('id', $customerId)->value('password')
        ));
        $this->assertDatabaseMissing('password_resets', [
            'email' => 'customer@example.test',
        ]);
    }

    public function test_staff_reset_tokens_are_bound_to_the_selected_role(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://store.example']);

        $email = 'staff@example.test';
        $adminId = (string) Str::uuid();
        $subadminId = (string) Str::uuid();
        $riderId = (string) Str::uuid();
        $branchId = (string) Str::uuid();
        DB::table('branches')->insert([
            'id' => $branchId,
            'name' => 'Test Branch',
            'address' => 'Test Address',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->insert([
            'id' => $adminId,
            'name' => 'Test Admin',
            'email' => $email,
            'password' => Hash::make('OldPassword123!'),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('subadmins')->insert([
            'id' => $subadminId,
            'name' => 'Test Subadmin',
            'email' => $email,
            'password' => Hash::make('OldPassword123!'),
            'branch_id' => $branchId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('riders')->insert([
            'id' => $riderId,
            'name' => 'Test Rider',
            'email' => $email,
            'password' => Hash::make('OldPassword123!'),
            'phone' => '08000000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/auth/password/reset-request', [
            'email' => $email,
            'role' => 'admin',
        ])->assertOk()->assertJson(['success' => true]);

        $adminResetLink = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$adminResetLink): bool {
            $isAdminLink = str_contains($mail->link, 'role=admin');
            if ($isAdminLink) {
                $adminResetLink = $mail->link;
            }

            return $isAdminLink;
        });

        $this->postJson('/api/auth/password/reset-request', [
            'email' => $email,
            'role' => 'rider',
        ])->assertOk()->assertJson(['success' => true]);

        $riderResetLink = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$riderResetLink): bool {
            $isRiderLink = str_contains($mail->link, 'role=rider');
            if ($isRiderLink) {
                $riderResetLink = $mail->link;
            }

            return $isRiderLink;
        });

        $this->postJson('/api/auth/password/reset-request', [
            'email' => $email,
            'role' => 'subadmin',
        ])->assertOk()->assertJson(['success' => true]);

        $subadminResetLink = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$subadminResetLink): bool {
            $isSubadminLink = str_contains($mail->link, 'role=subadmin');
            if ($isSubadminLink) {
                $subadminResetLink = $mail->link;
            }

            return $isSubadminLink;
        });

        parse_str((string) parse_url($adminResetLink, PHP_URL_QUERY), $adminQuery);
        parse_str((string) parse_url($riderResetLink, PHP_URL_QUERY), $riderQuery);
        parse_str((string) parse_url($subadminResetLink, PHP_URL_QUERY), $subadminQuery);
        $this->assertNotSame($adminQuery['token'], $riderQuery['token']);
        $this->assertDatabaseHas('password_resets', ['email' => $email, 'role' => 'admin']);
        $this->assertDatabaseHas('password_resets', ['email' => $email, 'role' => 'rider']);
        $this->assertDatabaseHas('password_resets', ['email' => $email, 'role' => 'subadmin']);

        $this->postJson('/api/auth/password/reset', [
            'email' => $email,
            'role' => 'rider',
            'token' => $adminQuery['token'],
            'password' => 'NewAdminPassword123!',
            'password_confirmation' => 'NewAdminPassword123!',
        ])->assertStatus(400);

        $this->postJson('/api/auth/password/reset', [
            'email' => $email,
            'role' => 'admin',
            'token' => $adminQuery['token'],
            'password' => 'NewAdminPassword123!',
            'password_confirmation' => 'NewAdminPassword123!',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertTrue(Hash::check(
            'NewAdminPassword123!',
            DB::table('users')->where('id', $adminId)->value('password')
        ));
        $this->assertDatabaseMissing('password_resets', ['email' => $email, 'role' => 'admin']);
        $this->assertDatabaseHas('password_resets', ['email' => $email, 'role' => 'rider']);
        $this->assertDatabaseHas('password_resets', ['email' => $email, 'role' => 'subadmin']);

        $this->postJson('/api/auth/password/reset', [
            'email' => $email,
            'role' => 'subadmin',
            'token' => $subadminQuery['token'],
            'password' => 'NewSubadminPassword123!',
            'password_confirmation' => 'NewSubadminPassword123!',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertTrue(Hash::check(
            'NewSubadminPassword123!',
            DB::table('subadmins')->where('id', $subadminId)->value('password')
        ));

        $this->postJson('/api/auth/password/reset', [
            'email' => $email,
            'role' => 'rider',
            'token' => $riderQuery['token'],
            'password' => 'NewRiderPassword123!',
            'password_confirmation' => 'NewRiderPassword123!',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertTrue(Hash::check(
            'NewRiderPassword123!',
            DB::table('riders')->where('id', $riderId)->value('password')
        ));
    }
}
