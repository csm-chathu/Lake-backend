<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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

    private function resolveSqliteDatabasePath(string $database): string
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
            $candidates = [
                database_path($basename),
                storage_path('app/' . $basename),
            ];

            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }

            return $path;
        }

        $relative = ltrim(str_replace('\\', '/', $path), '/');
        $candidates = [
            base_path($relative),
            database_path($relative),
            storage_path('app/' . $relative),
            storage_path($relative),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return base_path($relative);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $request = request();
        if (!$request) {
            return;
        }

        $frontendHost = strtolower(trim((string) (
            $request->headers->get('X-Target-Domain')
            ?? $request->headers->get('X-Frontend-Domain')
            ?? $request->query('domain')
            ?? $request->input('domain')
            ?? ''
        )));

        if ($frontendHost === '') {
            $originHeader = trim((string) $request->headers->get('Origin', ''));
            if ($originHeader !== '') {
                $frontendHost = (string) parse_url($originHeader, PHP_URL_HOST);
            }
        }

        if ($frontendHost === '') {
            $refererHeader = trim((string) $request->headers->get('Referer', ''));
            if ($refererHeader !== '') {
                $frontendHost = (string) parse_url($refererHeader, PHP_URL_HOST);
            }
        }

        $frontendHost = strtolower(trim($frontendHost));
        if ($frontendHost === '') {
            return;
        }

        $mapPath = env('DOMAIN_DB_MAP_FILE', storage_path('app/domain-database-map.json'));
        if (!is_file($mapPath) || !is_readable($mapPath)) {
            return;
        }

        $raw = file_get_contents($mapPath);
        if ($raw === false || trim($raw) === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return;
        }

        $databasesByDomain = isset($decoded['domains']) && is_array($decoded['domains'])
            ? $decoded['domains']
            : $decoded;

        $domainConfig = $databasesByDomain[$frontendHost] ?? ($databasesByDomain['*'] ?? null);
        if ($domainConfig === null) {
            return;
        }

        $targetConnection = 'mysql';
        $targetDatabase = null;

        if (is_string($domainConfig)) {
            $targetDatabase = trim($domainConfig);
        } elseif (is_array($domainConfig)) {
            $targetConnection = strtolower(trim((string) ($domainConfig['connection'] ?? 'mysql')));
            $targetDatabase = trim((string) ($domainConfig['database'] ?? ''));
        }

        $connectionAliases = [
            'sql' => 'mysql',
            'sqllite' => 'sqlite',
            'sqlliteserver' => 'sqlite',
            'sqlite3' => 'sqlite',
        ];
        $targetConnection = $connectionAliases[$targetConnection] ?? $targetConnection;

        if ($targetDatabase === '') {
            return;
        }

        $availableConnections = array_keys((array) config('database.connections', []));
        if (!in_array($targetConnection, $availableConnections, true)) {
            return;
        }

        if ($targetConnection === 'sqlite') {
            $targetDatabase = $this->resolveSqliteDatabasePath($targetDatabase);
        }

        $currentConnection = (string) config('database.default', 'mysql');
        $currentDatabase = (string) config("database.connections.{$targetConnection}.database");

        if ($currentConnection === $targetConnection && $currentDatabase === $targetDatabase) {
            return;
        }

        config([
            'database.default' => $targetConnection,
            "database.connections.{$targetConnection}.database" => $targetDatabase,
        ]);

        DB::purge($currentConnection);
        if ($targetConnection !== $currentConnection) {
            DB::purge($targetConnection);
        }
        DB::setDefaultConnection($targetConnection);
        DB::reconnect($targetConnection);
    }
}
