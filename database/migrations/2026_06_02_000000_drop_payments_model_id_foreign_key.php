<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $foreignKeyName = $this->getModelIdForeignKeyName();

        if ($foreignKeyName === null) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `payments` DROP FOREIGN KEY `%s`', $foreignKeyName));
    }

    public function down(): void
    {
        if ($this->getModelIdForeignKeyName() !== null) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('model_id')->references('id')->on('services')->nullOnDelete();
        });
    }

    private function getModelIdForeignKeyName(): ?string
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return null;
        }

        $databaseName = Schema::getConnection()->getDatabaseName();

        $constraint = DB::selectOne(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$databaseName, 'payments', 'model_id'],
        );

        if ($constraint === null || ! isset($constraint->CONSTRAINT_NAME)) {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_]+$/', $constraint->CONSTRAINT_NAME) === 1
            ? $constraint->CONSTRAINT_NAME
            : null;
    }
};