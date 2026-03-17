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
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = trim((string) Artisan::output());
        } catch (\Throwable $throwable) {
            return response()->json([
                'message' => 'Migration failed',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'error' => $throwable->getMessage(),
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

        try {
            $exitCode = Artisan::call('db:seed', ['--force' => true]);
            $output = trim((string) Artisan::output());
        } catch (\Throwable $throwable) {
            return response()->json([
                'message' => 'Seeding failed',
                'domain' => $domain,
                'connection' => $connection,
                'database' => $database,
                'error' => $throwable->getMessage(),
            ], 500);
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
