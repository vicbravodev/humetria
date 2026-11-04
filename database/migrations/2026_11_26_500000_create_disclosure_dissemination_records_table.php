<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The physical or in-person means by which a company disclosed information to its people (criterio C-52): a poster, a
 * bulletin, a leaflet, a meeting. They are what the norm names as evidence of the disclosure (num. 10.2). A record says
 * where, when and which incisos of 5.7 (a to f) it covered; a trigger refuses to change or delete it (num. 10.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disclosure_dissemination_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->string('medium', 10);
            $table->jsonb('covered_items');
            $table->date('done_on');
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->index(['tenant_id', 'work_center_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE disclosure_dissemination_records
                ADD CONSTRAINT disclosure_dissemination_records_medium_known CHECK (medium IN ('poster', 'bulletin', 'brochure', 'meeting', 'other')),
                ADD CONSTRAINT disclosure_dissemination_records_items_valid CHECK (jsonb_typeof(covered_items) = 'array' AND jsonb_array_length(covered_items) > 0 AND covered_items <@ '["a", "b", "c", "d", "e", "f"]'::jsonb)
        SQL);

        DB::statement("CREATE OR REPLACE FUNCTION disclosure_is_evidence() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'a record of disclosure is evidence and never changes'; END; \$\$");
        DB::statement('CREATE TRIGGER disclosure_dissemination_records_are_evidence BEFORE UPDATE OR DELETE ON disclosure_dissemination_records FOR EACH ROW EXECUTE FUNCTION disclosure_is_evidence()');
    }

    public function down(): void
    {
        Schema::dropIfExists('disclosure_dissemination_records');
        DB::statement('DROP FUNCTION IF EXISTS disclosure_is_evidence()');
    }
};
