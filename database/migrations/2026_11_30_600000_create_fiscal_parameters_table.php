<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The fiscal and legal values of the collective contract calculator. They belong to the platform, not to a company, so
 * there is no tenant_id: Humetria maintains them and no account edits them. A value is value_int / 10^scale (money is
 * whole cents with scale 0; a rate is a fraction with its decimals). Each one carries its vigencia, its source and the
 * date it was consulted; past valid_to the last value is carried and the report flags it (spec, decision 11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_parameters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 80);
            $table->string('state', 3)->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->bigInteger('value_int');
            $table->unsignedSmallInteger('scale');
            $table->text('source');
            $table->date('consulted_on');
            $table->text('note')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE fiscal_parameters ADD CONSTRAINT fiscal_parameters_vigencia CHECK (valid_to IS NULL OR valid_to >= valid_from)');
        DB::statement("ALTER TABLE fiscal_parameters ADD CONSTRAINT fiscal_parameters_sourced CHECK (btrim(source) <> '' AND btrim(key) <> '')");
        DB::statement('ALTER TABLE fiscal_parameters ADD CONSTRAINT fiscal_parameters_scale CHECK (scale BETWEEN 0 AND 9)');
        // NULLS NOT DISTINCT: a value without state is also unique per key and start date.
        DB::statement('CREATE UNIQUE INDEX fiscal_parameters_key_state_from_unique ON fiscal_parameters (key, state, valid_from) NULLS NOT DISTINCT');
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_parameters');
    }
};
