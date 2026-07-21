<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('billing_number_sequences')
            ->whereIn('document_type', ['invoice', 'proforma_invoice', 'tax_invoice'])
            ->get()
            ->groupBy('financial_year');

        foreach ($rows as $financialYear => $sequences) {
            $lastNumber = (int) $sequences->max('last_number');

            DB::table('billing_number_sequences')->updateOrInsert(
                [
                    'document_type' => 'invoice',
                    'financial_year' => $financialYear,
                ],
                [
                    'last_number' => $lastNumber,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        // Keep the consolidated sequence to avoid accidental document number reuse.
    }
};
