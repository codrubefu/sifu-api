<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'address' => ['type' => 'text', 'after' => 'description'],
            'email' => ['type' => 'string', 'after' => 'address'],
            'phone' => ['type' => 'string', 'after' => 'email'],
            'web' => ['type' => 'string', 'after' => 'phone'],
            'cui' => ['type' => 'string', 'after' => 'web'],
            'nr_reg_com' => ['type' => 'string', 'after' => 'cui'],
            'capital' => ['type' => 'string', 'after' => 'nr_reg_com'],
            'cont' => ['type' => 'string', 'after' => 'capital'],
            'bank' => ['type' => 'string', 'after' => 'cont'],
        ];

        foreach ($columns as $column => $definition) {
            if (Schema::hasColumn('organizations', $column)) {
                continue;
            }

            Schema::table('organizations', function (Blueprint $table) use ($column, $definition): void {
                if ($definition['type'] === 'text') {
                    $table->text($column)->nullable()->after($definition['after']);

                    return;
                }

                $table->string($column)->nullable()->after($definition['after']);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(['address', 'email', 'phone', 'web', 'cui', 'nr_reg_com', 'capital', 'cont', 'bank']) as $column) {
            if (! Schema::hasColumn('organizations', $column)) {
                continue;
            }

            Schema::table('organizations', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }
};
