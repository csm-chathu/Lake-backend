<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function rebuildUsersTableForSqlite(array $allowedTypes, bool $mapPosAdminToCashier = false): void
    {
        $allowedList = implode("', '", $allowedTypes);

        DB::beginTransaction();

        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('DROP TABLE IF EXISTS users_tmp');

        DB::statement(
            "CREATE TABLE users_tmp (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR NOT NULL,
                email VARCHAR NOT NULL,
                email_verified_at DATETIME NULL,
                password VARCHAR NOT NULL,
                user_type VARCHAR CHECK (\"user_type\" in ('{$allowedList}')) NOT NULL DEFAULT 'doctor',
                remember_token VARCHAR NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )"
        );

        if ($mapPosAdminToCashier) {
            DB::statement(
                "INSERT INTO users_tmp (id, name, email, email_verified_at, password, user_type, remember_token, created_at, updated_at)
                 SELECT id, name, email, email_verified_at, password,
                    CASE WHEN user_type = 'pos_admin' THEN 'cashier' ELSE user_type END,
                    remember_token, created_at, updated_at
                 FROM users"
            );
        } else {
            DB::statement(
                'INSERT INTO users_tmp (id, name, email, email_verified_at, password, user_type, remember_token, created_at, updated_at)
                 SELECT id, name, email, email_verified_at, password, user_type, remember_token, created_at, updated_at
                 FROM users'
            );
        }

        DB::statement('DROP TABLE users');
        DB::statement('ALTER TABLE users_tmp RENAME TO users');
        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users(email)');
        DB::statement('PRAGMA foreign_keys = ON');

        DB::commit();
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'user_type')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $this->rebuildUsersTableForSqlite(['doctor', 'cashier', 'pos_admin']);
            return;
        }

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('doctor', 'cashier', 'pos_admin') NOT NULL DEFAULT 'doctor'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'user_type')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $this->rebuildUsersTableForSqlite(['doctor', 'cashier'], true);
            return;
        }

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("UPDATE users SET user_type = 'cashier' WHERE user_type = 'pos_admin'");
        DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('doctor', 'cashier') NOT NULL DEFAULT 'doctor'");
    }
};
