<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;


class SystemMaintenanceController extends Controller
{

    public function migrateFresh(Request $request)
    {
        if (!$this->authorizeRequest($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $domain = $this->resolveDomainHint($request);
        [$connection, $database] = $this->databaseSnapshot();
        $sqlitePath = null;
        if ($connection === 'sqlite') {
            $sqlitePath = base_path($database);
            if (!is_file($sqlitePath)) {
                $sqlitePath = database_path(basename($database));
            }
            if (is_file($sqlitePath)) {
                $size = filesize($sqlitePath);
                $mtime = filemtime($sqlitePath);
                \Log::info("[MIGRATE FRESH] SQLite file before migration: $sqlitePath, size=$size, mtime=" . date('c', $mtime));
            } else {
                \Log::warning("[MIGRATE FRESH] SQLite file not found before migration: $sqlitePath");
            }
        }
        [$canConnect, $error] = $this->canConnect($connection);

        if (!$canConnect) {
            return response()->json([
                'message' => 'Database connection failed before migration',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'error' => $error,
            ], 422);
        }

        try {
            $exitCode = \Artisan::call('migrate:fresh', ['--force' => true]);
            $output = trim((string) \Artisan::output());
            if ($connection === 'sqlite' && $sqlitePath && is_file($sqlitePath)) {
                $size = filesize($sqlitePath);
                $mtime = filemtime($sqlitePath);
                \Log::info("[MIGRATE FRESH] SQLite file after migration: $sqlitePath, size=$size, mtime=" . date('c', $mtime));
            }
        } catch (\Throwable $throwable) {
            return response()->json([
                'message' => 'Migrate fresh failed',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'error' => $throwable->getMessage(),
            ], 500);
        }

        if ($exitCode !== 0) {
            return response()->json([
                'message' => 'Migrate fresh command failed',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'exitCode' => $exitCode,
                'output' => $output,
            ], 500);
        }

        return response()->json([
            'message' => 'Migrate fresh completed',
            'domain' => $domain,
            'connection' => $connection,
            'database' => $database,
            'output' => $output,
        ]);
    }
    private function isAbsolutePath(string $path): bool
    {
        $path = trim($path);
        if ($path === '') {
            return false;
        }

        $isWindowsAbsolute =
            strlen($path) >= 3
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '\\' || $path[2] === '/');

        $isUnixAbsolute = str_starts_with($path, '/') || str_starts_with($path, '\\');

        return $isWindowsAbsolute || $isUnixAbsolute;
    }

    private function sqliteTableExists($driver, string $table): bool
    {
        $row = $driver->selectOne(
            "SELECT name FROM sqlite_master WHERE type='table' AND name=? LIMIT 1",
            [$table]
        );

        return $row !== null;
    }

    private function reconcileSqliteCreateTableMigrations(): array
    {
        [$connection, $database] = $this->databaseSnapshot();
        if ($connection !== 'sqlite') {
            return ['inserted' => 0, 'checked' => 0];
        }

        $sqlitePath = $this->normalizeSqlitePath($database);
        if (!is_file($sqlitePath)) {
            return ['inserted' => 0, 'checked' => 0];
        }

        $driver = DB::connection('sqlite');
        if (!$this->sqliteTableExists($driver, 'migrations')) {
            return ['inserted' => 0, 'checked' => 0];
        }

        $migrationFiles = glob(database_path('migrations/*.php')) ?: [];
        sort($migrationFiles);

        $existing = $driver->table('migrations')->pluck('migration')->all();
        $existingSet = array_fill_keys(array_map('strval', $existing), true);
        $batch = max(1, (int) ($driver->table('migrations')->max('batch') ?? 0));

        $inserted = 0;
        $checked = 0;

        foreach ($migrationFiles as $filePath) {
            $migration = pathinfo($filePath, PATHINFO_FILENAME);
            if (isset($existingSet[$migration])) {
                continue;
            }

            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            if (!preg_match("/Schema::create\\s*\\(\\s*['\"]([a-zA-Z0-9_]+)['\"]/", $content, $matches)) {
                continue;
            }

            $checked++;
            $table = $matches[1] ?? '';
            if ($table === '' || !$this->sqliteTableExists($driver, $table)) {
                continue;
            }

            $driver->table('migrations')->insert([
                'migration' => $migration,
                'batch' => $batch,
            ]);
            $existingSet[$migration] = true;
            $inserted++;
        }

        return ['inserted' => $inserted, 'checked' => $checked];
    }

    private function markSqliteCreateMigrationAsRanForTable(string $table): int
    {
        [$connection, $database] = $this->databaseSnapshot();
        if ($connection !== 'sqlite' || $table === '') {
            return 0;
        }

        $sqlitePath = $this->normalizeSqlitePath($database);
        if (!is_file($sqlitePath)) {
            return 0;
        }

        $driver = DB::connection('sqlite');
        if (!$this->sqliteTableExists($driver, 'migrations') || !$this->sqliteTableExists($driver, $table)) {
            return 0;
        }

        $migrationFiles = glob(database_path('migrations/*.php')) ?: [];
        sort($migrationFiles);

        $existing = $driver->table('migrations')->pluck('migration')->all();
        $existingSet = array_fill_keys(array_map('strval', $existing), true);
        $batch = max(1, (int) ($driver->table('migrations')->max('batch') ?? 0));
        $inserted = 0;

        foreach ($migrationFiles as $filePath) {
            $migration = pathinfo($filePath, PATHINFO_FILENAME);
            if (isset($existingSet[$migration])) {
                continue;
            }

            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            if (!preg_match("/Schema::create\\s*\\(\\s*['\"]([a-zA-Z0-9_]+)['\"]/", $content, $matches)) {
                continue;
            }

            if (($matches[1] ?? '') !== $table) {
                continue;
            }

            $driver->table('migrations')->insert([
                'migration' => $migration,
                'batch' => $batch,
            ]);
            $existingSet[$migration] = true;
            $inserted++;
        }

        return $inserted;
    }

    private function normalizeSqlitePath(string $database): string
    {
        $path = trim($database);
        if ($path === '') {
            return $path;
        }

        $path = str_replace(
            ['{base_path}', '{database_path}', '{storage_path}', '%BASE_PATH%', '%DATABASE_PATH%', '%STORAGE_PATH%'],
            [base_path(), database_path(), storage_path(), base_path(), database_path(), storage_path()],
            $path
        );

        if ($this->isAbsolutePath($path)) {
            if (is_file($path)) {
                return $path;
            }

            $basename = basename(str_replace('\\', '/', $path));
            foreach ([database_path($basename), storage_path('app/' . $basename)] as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }

            return $path;
        }

        $relative = ltrim(str_replace('\\', '/', $path), '/');
        foreach ([base_path($relative), database_path($relative), storage_path('app/' . $relative), storage_path($relative)] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return base_path($relative);
    }

    private function ensureSqliteMigrationsTable(): void
    {
        [$connection, $database] = $this->databaseSnapshot();
        if ($connection !== 'sqlite') {
            return;
        }

        $sqlitePath = $this->normalizeSqlitePath($database);
        if (!is_file($sqlitePath)) {
            return;
        }

        $driver = DB::connection('sqlite');
        $hasMigrationsTable = (bool) $driver->selectOne(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'"
        );

        if (!$hasMigrationsTable) {
            return;
        }

        $columns = $driver->select("PRAGMA table_info('migrations')");
        $idColumn = collect($columns)->first(fn ($column) => ($column->name ?? null) === 'id');
        if (!$idColumn) {
            return;
        }

        $idType = strtolower((string) ($idColumn->type ?? ''));
        $isPrimary = (int) ($idColumn->pk ?? 0) === 1;

        if ($isPrimary && $idType === 'integer') {
            return;
        }

        DB::transaction(function () use ($driver) {
            $driver->statement('CREATE TABLE IF NOT EXISTS migrations_tmp (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, migration VARCHAR NOT NULL, batch INTEGER NOT NULL)');
            $driver->statement('DELETE FROM migrations_tmp');
            $driver->statement('INSERT INTO migrations_tmp (migration, batch) SELECT migration, batch FROM migrations ORDER BY id');
            $driver->statement('DROP TABLE migrations');
            $driver->statement('ALTER TABLE migrations_tmp RENAME TO migrations');
            $driver->statement('CREATE INDEX IF NOT EXISTS migrations_migration_index ON migrations (migration)');
        });
    }

    private function resolveEmailPostfix(Request $request): ?string
    {
        $postfix = trim((string) (
            $request->input('email_postfix')
            ?? $request->input('email_domain')
            ?? $request->input('user_email_postfix')
            ?? ''
        ));

        if ($postfix === '') {
            return null;
        }

        return ltrim(strtolower($postfix), '@');
    }

    private function applyUserEmailPostfix(string $postfix): int
    {
        if ($postfix === '' || !DB::getSchemaBuilder()->hasTable('users')) {
            return 0;
        }

        $updatedCount = 0;
        $users = User::query()->whereNotNull('email')->get();
        foreach ($users as $user) {
            $email = (string) ($user->email ?? '');
            if ($email === '' || !str_contains($email, '@')) {
                continue;
            }

            [$localPart] = explode('@', $email, 2);
            $baseLocalPart = preg_replace('/[^a-z0-9._+-]/i', '', strtolower(trim($localPart))) ?: 'user';
            $newEmail = $baseLocalPart . '@' . $postfix;

            if (
                User::query()
                    ->where('email', $newEmail)
                    ->where('id', '!=', $user->id)
                    ->exists()
            ) {
                $suffix = 1;
                do {
                    $candidate = $baseLocalPart . '-' . $user->id . ($suffix > 1 ? '-' . $suffix : '') . '@' . $postfix;
                    $exists = User::query()
                        ->where('email', $candidate)
                        ->where('id', '!=', $user->id)
                        ->exists();
                    $suffix++;
                } while ($exists);

                $newEmail = $candidate;
            }

            if ($newEmail === $email) {
                continue;
            }

            $user->email = $newEmail;
            $user->save();
            $updatedCount++;
        }

        return $updatedCount;
    }

    private function resolveClinicSettingsPayload(Request $request): array
    {
        $nested = $request->input('clinic_settings');
        $payload = is_array($nested) ? $nested : [];

        $name = isset($payload['name']) ? $payload['name'] : $request->input('name');
        $address = isset($payload['address']) ? $payload['address'] : $request->input('address');
        $description = $payload['description']
            ?? $payload['tagline']
            ?? $request->input('description')
            ?? $request->input('tagline');

        $posDescription = $payload['pos_description']
            ?? $payload['posDescription']
            ?? $request->input('pos_description')
            ?? $request->input('posDescription');
        $heroImage = $payload['hero_image_url']
            ?? $payload['heroImageUrl']
            ?? $payload['hero_image']
            ?? $payload['heroImage']
            ?? $request->input('hero_image_url')
            ?? $request->input('heroImageUrl')
            ?? $request->input('hero_image')
            ?? $request->input('heroImage');
        $logo = isset($payload['logo'])
            ? $payload['logo']
            : (isset($payload['logo_url']) ? $payload['logo_url'] : ($request->input('logo') ?? $request->input('logo_url')));

        $data = [];
        if ($name !== null) {
            $data['name'] = trim((string) $name);
        }
        if ($address !== null) {
            $data['address'] = trim((string) $address);
        }
        if ($description !== null) {
            $data['description'] = trim((string) $description);
        }
        if ($posDescription !== null) {
            $data['pos_description'] = trim((string) $posDescription);
        }
        if ($logo !== null) {
            $data['logo_url'] = trim((string) $logo);
        }
        if ($heroImage !== null) {
            $data['hero_image_url'] = trim((string) $heroImage);
        }
        // Add shop_type if present in payload or request
        $shopType = $payload['shop_type'] ?? $request->input('shop_type');
        if ($shopType !== null && $shopType !== '') {
            $data['shop_type'] = trim((string) $shopType);
        }
        return array_filter($data, static fn ($value) => $value !== '');
    }

    private function applyClinicSettings(array $data): void
    {
        if (empty($data) || !DB::getSchemaBuilder()->hasTable('clinic_settings')) {
            return;
        }

        $setting = ClinicSetting::query()->orderBy('id')->first();
        if ($setting) {
            $setting->fill($data);
            $setting->save();
            return;
        }

        ClinicSetting::query()->create($data);
    }

    private function databaseSnapshot(): array
    {
        $connection = (string) DB::getDefaultConnection();
        $database = (string) config("database.connections.{$connection}.database");

        return [$connection, $database];
    }

    private function canConnect(string $connection): array
    {
        try {
            DB::connection($connection)->getPdo();
            return [true, null];
        } catch (\Throwable $throwable) {
            return [false, $throwable->getMessage()];
        }
    }

    private function resolveDomainHint(Request $request): ?string
    {
        $domain = trim((string) (
            $request->header('X-Target-Domain')
            ?? $request->input('domain')
            ?? $request->query('domain')
            ?? $request->header('X-Frontend-Domain')
            ?? ''
        ));

        return $domain !== '' ? strtolower($domain) : null;
    }

    private function authorizeRequest(Request $request): bool
    {
        $configuredKey = trim((string) env('SYSTEM_MAINTENANCE_KEY', ''));
        if ($configuredKey === '') {
            return false;
        }

        $providedKey = trim((string) (
            $request->header('X-Maintenance-Key')
            ?? $request->input('key')
            ?? ''
        ));

        return hash_equals($configuredKey, $providedKey);
    }

    public function migrate(Request $request)
    {
        if (!$this->authorizeRequest($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $domain = $this->resolveDomainHint($request);
        [$connection, $database] = $this->databaseSnapshot();
        $sqlitePath = null;
        if ($connection === 'sqlite') {
            $sqlitePath = base_path($database);
            if (!is_file($sqlitePath)) {
                $sqlitePath = database_path(basename($database));
            }
            if (is_file($sqlitePath)) {
                $size = filesize($sqlitePath);
                $mtime = filemtime($sqlitePath);
                \Log::info("[MIGRATE] SQLite file before migration: $sqlitePath, size=$size, mtime=" . date('c', $mtime));
            } else {
                \Log::warning("[MIGRATE] SQLite file not found before migration: $sqlitePath");
            }
        }
        [$canConnect, $error] = $this->canConnect($connection);

        if (!$canConnect) {
            return response()->json([
                'message' => 'Database connection failed before migration',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'error' => $error,
            ], 422);
        }

        try {
            $this->ensureSqliteMigrationsTable();
            $reconciled = $this->reconcileSqliteCreateTableMigrations();
        } catch (\Throwable $throwable) {
            return response()->json([
                'message' => 'Pre-migration SQLite table check failed',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'error' => $throwable->getMessage(),
            ], 500);
        }

        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = trim((string) Artisan::output());
            if ($connection === 'sqlite' && $sqlitePath && is_file($sqlitePath)) {
                $size = filesize($sqlitePath);
                $mtime = filemtime($sqlitePath);
                \Log::info("[MIGRATE] SQLite file after migration: $sqlitePath, size=$size, mtime=" . date('c', $mtime));
            }
        } catch (\Throwable $throwable) {
            $message = (string) $throwable->getMessage();
            if (
                $connection === 'sqlite'
                && str_contains($message, 'already exists')
                && preg_match('/table\s+"([a-zA-Z0-9_]+)"\s+already exists/i', $message, $matches)
            ) {
                $tableName = (string) ($matches[1] ?? '');
                try {
                    $fixed = $this->markSqliteCreateMigrationAsRanForTable($tableName);
                    if ($fixed > 0) {
                        $exitCode = Artisan::call('migrate', ['--force' => true]);
                        $output = trim((string) Artisan::output());

                        if ($exitCode === 0) {
                            return response()->json([
                                'message' => 'Migration completed',
                                'domain' => $domain,
                                'connection' => $connection,
                                'database' => $database,
                                'sqliteCreateMigrationsReconciled' => ($reconciled['inserted'] ?? 0) + $fixed,
                                'output' => $output,
                            ]);
                        }
                    }
                } catch (\Throwable $recoveryThrowable) {
                    return response()->json([
                        'message' => 'Migration failed during SQLite recovery',
                        'domain' => $domain,
                        'connection' => $connection,
                        'database' => $database,
                        'error' => $recoveryThrowable->getMessage(),
                    ], 500);
                }
            }

            return response()->json([
                'message' => 'Migration failed',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'error' => $message,
            ], 500);
        }

        if ($exitCode !== 0) {
            return response()->json([
                'message' => 'Migration command failed',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'exitCode' => $exitCode,
                'output' => $output,
            ], 500);
        }

        return response()->json([
            'message' => 'Migration completed',
            'domain' => $domain,
            'connection' => $connection,
            'database' => $database,
            'sqliteCreateMigrationsReconciled' => $reconciled['inserted'] ?? 0,
            'output' => $output,
        ]);
    }

    public function seed(Request $request)
    {
        if (!$this->authorizeRequest($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $domain = $this->resolveDomainHint($request);
        $clinicSettingsData = $this->resolveClinicSettingsPayload($request);
        $emailPostfix = $this->resolveEmailPostfix($request);
        [$connection, $database] = $this->databaseSnapshot();
        [$canConnect, $error] = $this->canConnect($connection);

        if (!$canConnect) {
            return response()->json([
                'message' => 'Database connection failed before seeding',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'error' => $error,
            ], 422);
        }

        $shopType = $request->input('shop_type');
        if ($shopType) {
            try {
                $seederClass = null;
                switch ($shopType) {
                    case 'retail':
                        $seederClass = \Database\Seeders\RetailShopSeeder::class;
                        break;
                    case 'pharmacy':
                        $seederClass = \Database\Seeders\PharmacySeeder::class;
                        break;
                    case 'restaurant':
                        $seederClass = \Database\Seeders\RestaurantSeeder::class;
                        break;
                    case 'spares':
                    case 'spareparts':
                        $seederClass = \Database\Seeders\SparePartsSeeder::class;
                        break;
                    default:
                        return response()->json([
                            'message' => 'Invalid shop_type',
                            'domain' => $domain,
                            'connection' => $connection,
                            'database' => $database,
                        ], 422);
                }
                (new $seederClass)->run();
                $exitCode = 0;
                $output = 'Seeded: ' . $seederClass;
            } catch (\Throwable $throwable) {
                return response()->json([
                    'message' => 'Seeding failed',
                    'domain' => $domain,
                    'connection' => $connection,
                    'database' => $database,
                    'error' => $throwable->getMessage(),
                ], 500);
            }
        } else {
            try {
                $exitCode = \Artisan::call('db:seed', ['--force' => true]);
                $output = trim((string) \Artisan::output());
            } catch (\Throwable $throwable) {
                return response()->json([
                    'message' => 'Seeding failed',
                    'domain' => $domain,
                    'connection' => $connection,
                    'database' => $database,
                    'error' => $throwable->getMessage(),
                ], 500);
            }
        }

        try {
            $this->applyClinicSettings($clinicSettingsData);
        } catch (\Throwable $throwable) {
            return response()->json([
                'message' => 'Seeding completed but clinic settings update failed',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'error' => $throwable->getMessage(),
            ], 500);
        }

        try {
            $updatedUsersCount = $emailPostfix ? $this->applyUserEmailPostfix($emailPostfix) : 0;
        } catch (\Throwable $throwable) {
            return response()->json([
                'message' => 'Seeding completed but user email postfix update failed',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'error' => $throwable->getMessage(),
            ], 500);
        }

        if ($exitCode !== 0) {
            return response()->json([
                'message' => 'Seeding command failed',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'exitCode' => $exitCode,
                'output' => $output,
            ], 500);
        }

        return response()->json([
            'message' => 'Seeding completed',
            'domain' => $domain,
            'connection' => $connection,
            'database' => $database,
            'clinicSettingsUpdated' => !empty($clinicSettingsData),
            'emailPostfixApplied' => $emailPostfix,
            'updatedUsersCount' => $updatedUsersCount,
            'output' => $output,
        ]);
    }

    public function checkDatabase(Request $request)
    {
        if (!$this->authorizeRequest($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $domain = $this->resolveDomainHint($request);
        [$connection, $database] = $this->databaseSnapshot();
        [$canConnect, $error] = $this->canConnect($connection);

        return response()->json([
            'message' => 'Database check completed',
            'domain' => $domain,
            'connection' => $connection,
            'database' => $database,
            'canConnect' => $canConnect,
            'error' => $error,
        ]);
    }
}
