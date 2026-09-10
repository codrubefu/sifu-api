<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->default('')->after('id');
            }

            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->default('')->after('first_name');
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('last_name');
            }

            if (! Schema::hasColumn('users', 'active')) {
                $table->boolean('active')->default(true)->after('phone');
            }
        });

        if (Schema::hasColumn('users', 'name')) {
            DB::statement("UPDATE users SET first_name = name WHERE first_name = ''");

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'name')) {
                $table->string('name')->after('id');
            }
        });

        if (Schema::hasColumn('users', 'first_name') && Schema::hasColumn('users', 'last_name')) {
            DB::statement("UPDATE users SET name = TRIM(CONCAT(first_name, ' ', last_name))");
        }

        Schema::table('users', function (Blueprint $table) {
            $columns = collect(['first_name', 'last_name', 'phone', 'active'])
                ->filter(fn (string $column) => Schema::hasColumn('users', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
