<?php

declare(strict_types=1);

use Database\Seeders\FiscalParametersSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the fiscal parameters of docs/normas/compensation/criterios.md v1.0 (consulted 2026-11-03). The data is versioned
 * with the schema: a later vigencia is another migration, not an edit of this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new FiscalParametersSeeder)->run();
    }

    public function down(): void
    {
        DB::table('fiscal_parameters')->delete();
    }
};
