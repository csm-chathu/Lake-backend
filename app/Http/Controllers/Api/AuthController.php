<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    private function ensureSqlitePersonalAccessTokensTable(): void
    {
        if ((string) DB::getDefaultConnection() !== 'sqlite') {
            return;
        }

        $driver = DB::connection('sqlite');
        $hasTable = (bool) $driver->selectOne(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='personal_access_tokens'"
        );

        if (!$hasTable) {
            return;
        }

        $columns = $driver->select("PRAGMA table_info('personal_access_tokens')");
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
            $driver->statement('CREATE TABLE IF NOT EXISTS personal_access_tokens_tmp (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, tokenable_type VARCHAR NOT NULL, tokenable_id INTEGER NOT NULL, name VARCHAR NOT NULL, token VARCHAR NOT NULL, abilities TEXT, last_used_at DATETIME, expires_at DATETIME, created_at DATETIME, updated_at DATETIME)');
            $driver->statement('DELETE FROM personal_access_tokens_tmp');
            $driver->statement('INSERT INTO personal_access_tokens_tmp (tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) SELECT tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at FROM personal_access_tokens');
            $driver->statement('DROP TABLE personal_access_tokens');
            $driver->statement('ALTER TABLE personal_access_tokens_tmp RENAME TO personal_access_tokens');
            $driver->statement('CREATE UNIQUE INDEX IF NOT EXISTS personal_access_tokens_token_unique ON personal_access_tokens (token)');
            $driver->statement('CREATE INDEX IF NOT EXISTS personal_access_tokens_tokenable_type_tokenable_id_index ON personal_access_tokens (tokenable_type, tokenable_id)');
        });
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required']
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        try {
            $token = $user->createToken('api-token')->plainTextToken;
        } catch (\Throwable $throwable) {
            $message = (string) $throwable->getMessage();
            if (!str_contains($message, 'personal_access_tokens.id') || !str_contains($message, 'NOT NULL')) {
                throw $throwable;
            }

            $this->ensureSqlitePersonalAccessTokensTable();
            $token = $user->createToken('api-token')->plainTextToken;
        }

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            // revoke current token
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }
}
