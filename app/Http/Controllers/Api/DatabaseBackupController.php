<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class DatabaseBackupController extends Controller
{
    private const FORMAT = 'chapterone-database-backup';
    private const VERSION = 1;
    private const RESTORE_CONFIRMATION = 'RESTORE DATABASE';
    private const CLEAR_CONFIRMATION = 'CLEAR DATABASE';
    private const PRESERVED_TABLES = ['settings', 'oauth_clients', 'oauth_personal_access_clients'];

    public function download(Request $request)
    {
        $tables = [];
        foreach ($this->applicationTables() as $table) {
            $tables[$table] = [
                'columns' => DB::connection()->getSchemaBuilder()->getColumnListing($table),
                'rows' => DB::table($table)->get()->map(static fn ($row) => (array) $row)->all(),
            ];
        }

        try {
            $contents = json_encode([
                'format' => self::FORMAT,
                'version' => self::VERSION,
                'created_at' => now()->toIso8601String(),
                'tables' => $tables,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::error('Database backup could not be encoded', ['exception' => $exception]);

            return response()->json([
                'success' => false,
                'message' => 'The database backup could not be generated.',
            ], 500);
        }

        $filename = 'chapterone-database-backup-' . now()->format('Ymd-His') . '.json';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filename,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]
        );
    }

    public function restore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'backup' => 'required|file|max:102400',
            'confirmation' => ['required', 'in:' . self::RESTORE_CONFIRMATION],
        ]);

        try {
            $backup = json_decode($validated['backup']->get(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ValidationException::withMessages([
                'backup' => 'The selected file is not valid JSON.',
            ]);
        }

        if (
            !is_array($backup)
            || ($backup['format'] ?? null) !== self::FORMAT
            || ($backup['version'] ?? null) !== self::VERSION
            || !is_array($backup['tables'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'backup' => 'This file is not a supported Chapter One database backup.',
            ]);
        }

        $tableData = $this->validateBackupTables($backup['tables']);
        $schema = DB::connection()->getSchemaBuilder();
        $restoredRows = [];

        $schema->disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($tableData, &$restoredRows): void {
                foreach ($tableData as $table => $entry) {
                    DB::table($table)->delete();
                }

                foreach ($tableData as $table => $entry) {
                    foreach (array_chunk($entry['rows'], 250) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                    $restoredRows[$table] = count($entry['rows']);
                }
            });
        } catch (Throwable $exception) {
            Log::error('Database restore failed', [
                'admin_id' => $request->user()->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The database restore failed. No partial changes were kept.',
            ], 500);
        } finally {
            $schema->enableForeignKeyConstraints();
        }

        Log::warning('Database restore completed', [
            'admin_id' => $request->user()->id,
            'restored_rows' => $restoredRows,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Database data restored successfully.',
            'data' => [
                'restored_rows' => $restoredRows,
            ],
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:' . self::CLEAR_CONFIRMATION],
        ]);

        $admin = $request->user();
        $tables = $this->applicationTables();
        if (!in_array('users', $tables, true) || !in_array('settings', $tables, true)) {
            Log::error('Database clear refused because required protected tables are missing', [
                'admin_id' => $admin->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Database clear was stopped because protected account or settings data could not be verified.',
            ], 500);
        }

        $activeToken = $admin->currentAccessToken();
        $activeTokenId = $activeToken && method_exists($activeToken, 'getKey')
            ? $activeToken->getKey()
            : null;
        $schema = DB::connection()->getSchemaBuilder();
        $clearedRows = [];

        $schema->disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($tables, $admin, $activeTokenId, &$clearedRows): void {
                foreach ($tables as $table) {
                    if (in_array($table, self::PRESERVED_TABLES, true)) {
                        continue;
                    }

                    $query = DB::table($table);
                    if ($table === 'users') {
                        $query->where('id', '!=', $admin->getAuthIdentifier());
                    } elseif ($table === 'oauth_access_tokens' && $activeTokenId !== null) {
                        $query->where('id', '!=', $activeTokenId);
                    }

                    $clearedRows[$table] = $query->delete();
                }
            });
        } catch (Throwable $exception) {
            Log::error('Database clear failed', [
                'admin_id' => $admin->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Database clear failed. No partial changes were kept.',
            ], 500);
        } finally {
            $schema->enableForeignKeyConstraints();
        }

        Log::warning('Database data cleared by admin', [
            'admin_id' => $admin->id,
            'cleared_rows' => $clearedRows,
            'preserved_tables' => self::PRESERVED_TABLES,
            'preserved_admin_id' => $admin->getAuthIdentifier(),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Database data cleared. The current admin account and store settings were preserved.',
            'data' => [
                'cleared_rows' => $clearedRows,
                'preserved_admin_id' => $admin->getAuthIdentifier(),
                'preserved_tables' => self::PRESERVED_TABLES,
            ],
        ]);
    }

    private function applicationTables(): array
    {
        $connection = DB::connection();
        $prefix = $connection->getTablePrefix();
        $migrationTable = $prefix . config('database.migrations.table', 'migrations');
        $tables = $connection->getSchemaBuilder()->getTableListing(null, false);

        $tables = array_map(
            static fn (string $table): string => str_starts_with($table, $prefix)
                ? substr($table, strlen($prefix))
                : $table,
            $tables
        );
        $tables = array_values(array_filter($tables, static fn (string $table): bool => $table !== substr($migrationTable, strlen($prefix))));
        sort($tables);

        return $tables;
    }

    private function validateBackupTables(array $backupTables): array
    {
        $currentTables = $this->applicationTables();
        $providedTables = array_keys($backupTables);
        sort($providedTables);

        if ($providedTables !== $currentTables) {
            throw ValidationException::withMessages([
                'backup' => 'The backup tables do not match the current database schema.',
            ]);
        }

        $validatedTables = [];
        foreach ($currentTables as $table) {
            $entry = $backupTables[$table] ?? null;
            $currentColumns = DB::connection()->getSchemaBuilder()->getColumnListing($table);

            if (
                !is_array($entry)
                || !is_array($entry['columns'] ?? null)
                || array_values($entry['columns']) !== $currentColumns
                || !is_array($entry['rows'] ?? null)
            ) {
                throw ValidationException::withMessages([
                    'backup' => "The backup schema for table {$table} does not match this database.",
                ]);
            }

            $expectedColumns = $currentColumns;
            sort($expectedColumns);
            foreach ($entry['rows'] as $row) {
                if (!is_array($row)) {
                    throw ValidationException::withMessages([
                        'backup' => "The backup contains invalid data for table {$table}.",
                    ]);
                }

                $rowColumns = array_keys($row);
                sort($rowColumns);
                if ($rowColumns !== $expectedColumns) {
                    throw ValidationException::withMessages([
                        'backup' => "The backup columns for table {$table} do not match this database.",
                    ]);
                }
            }

            $validatedTables[$table] = $entry;
        }

        return $validatedTables;
    }
}
