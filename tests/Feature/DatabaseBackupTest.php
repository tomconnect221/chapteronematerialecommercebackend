<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_backup_is_only_available_to_admins(): void
    {
        $this->getJson('/api/admin/database/backup')->assertUnauthorized();

        Passport::actingAs(User::factory()->create(['role' => 'subadmin']));
        $this->getJson('/api/admin/database/backup')->assertForbidden();

        Passport::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/database/backup')->assertOk();
    }

    public function test_restore_requires_admin_and_confirmation(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 'subadmin']));

        $this->postJson('/api/admin/database/restore', [
            'confirmation' => 'RESTORE DATABASE',
        ])->assertForbidden();
    }

    public function test_admin_can_restore_a_schema_compatible_backup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Passport::actingAs($admin);

        $tables = [];
        $migrationTable = DB::connection()->getTablePrefix() . config('database.migrations.table', 'migrations');
        foreach (DB::connection()->getSchemaBuilder()->getTableListing(null, false) as $physicalTable) {
            $prefix = DB::connection()->getTablePrefix();
            $table = str_starts_with($physicalTable, $prefix)
                ? substr($physicalTable, strlen($prefix))
                : $physicalTable;
            if ($table === substr($migrationTable, strlen($prefix))) {
                continue;
            }

            $tables[$table] = [
                'columns' => Schema::getColumnListing($table),
                'rows' => DB::table($table)->get()->map(static fn ($row) => (array) $row)->all(),
            ];
        }
        ksort($tables);

        $backup = json_encode([
            'format' => 'chapterone-database-backup',
            'version' => 1,
            'created_at' => now()->toIso8601String(),
            'tables' => $tables,
        ], JSON_THROW_ON_ERROR);

        $response = $this->post('/api/admin/database/restore', [
            'backup' => \Illuminate\Http\UploadedFile::fake()->createWithContent('backup.json', $backup),
            'confirmation' => 'RESTORE DATABASE',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_restore_rejects_an_incompatible_backup_without_changing_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Passport::actingAs($admin);

        $response = $this->postJson('/api/admin/database/restore', [
            'backup' => \Illuminate\Http\UploadedFile::fake()->createWithContent('backup.json', json_encode([
                'format' => 'chapterone-database-backup',
                'version' => 1,
                'tables' => [],
            ], JSON_THROW_ON_ERROR)),
            'confirmation' => 'RESTORE DATABASE',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_clear_removes_database_data_but_preserves_current_admin_and_store_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create(['role' => 'subadmin']);
        Passport::actingAs($admin);

        foreach ([
            'site_name' => 'Chapter One',
            'store_description' => 'Independent bookseller',
            'paystack_secret_key' => 'test-secret-value',
        ] as $key => $value) {
            DB::table('settings')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('sessions')->insert([
            'id' => 'old-session',
            'user_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => 'stale-session-data',
            'last_activity' => 1,
        ]);

        $this->postJson('/api/admin/database/clear', [
            'confirmation' => 'CLEAR DATABASE',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.preserved_admin_id', $admin->id);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseMissing('users', ['id' => $otherUser->id]);
        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => 'Chapter One']);
        $this->assertDatabaseHas('settings', ['key' => 'store_description', 'value' => 'Independent bookseller']);
        $this->assertDatabaseHas('settings', ['key' => 'paystack_secret_key', 'value' => 'test-secret-value']);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
    }

    public function test_clear_is_admin_only_and_requires_exact_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Passport::actingAs($admin);

        $this->postJson('/api/admin/database/clear', [
            'confirmation' => 'clear database',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);

        Passport::actingAs(User::factory()->create(['role' => 'subadmin']));
        $this->postJson('/api/admin/database/clear', [
            'confirmation' => 'CLEAR DATABASE',
        ])->assertForbidden();
    }
}
