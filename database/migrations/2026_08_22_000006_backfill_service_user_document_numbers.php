<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('organizations')
            || ! Schema::hasTable('service_user')
            || ! Schema::hasColumn('service_user', 'invoice_number')
            || ! Schema::hasColumn('service_user', 'bill_number')
        ) {
            return;
        }

        DB::table('organizations')->orderBy('id')->select([
            'id',
            'invoice_code',
            'invoice_number',
            'bill_code',
            'bill_number',
        ])->chunkById(100, function ($organizations): void {
            foreach ($organizations as $organization) {
                $invoiceNumber = (int) $organization->invoice_number;
                $billNumber = (int) $organization->bill_number;
                $invoiceCode = $organization->invoice_code ?: 'INV';
                $billCode = $organization->bill_code ?: 'BILL';

                DB::table('service_user')
                    ->join('services', 'services.id', '=', 'service_user.service_id')
                    ->where('services.organization_id', $organization->id)
                    ->where(function ($query): void {
                        $query->whereNull('service_user.invoice_number')
                            ->orWhereNull('service_user.bill_number');
                    })
                    ->orderBy('service_user.id')
                    ->select('service_user.id', 'service_user.invoice_number', 'service_user.bill_number')
                    ->chunkById(100, function ($assignments) use (&$invoiceNumber, &$billNumber, $invoiceCode, $billCode): void {
                        foreach ($assignments as $assignment) {
                            $updates = [];

                            if ($assignment->invoice_number === null) {
                                $invoiceNumber++;
                                $updates['invoice_number'] = sprintf('%s%06d', $invoiceCode, $invoiceNumber);
                            }

                            if ($assignment->bill_number === null) {
                                $billNumber++;
                                $updates['bill_number'] = sprintf('%s%06d', $billCode, $billNumber);
                            }

                            if ($updates !== []) {
                                DB::table('service_user')->where('id', $assignment->id)->update($updates);
                            }
                        }
                    }, 'service_user.id', 'id');

                DB::table('organizations')->where('id', $organization->id)->update([
                    'invoice_number' => $invoiceNumber,
                    'bill_number' => $billNumber,
                ]);
            }
        });
    }

    public function down(): void
    {
    }
};
