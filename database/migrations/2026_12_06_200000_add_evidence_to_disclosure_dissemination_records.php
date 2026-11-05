<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NOM-035: the evidence of the disclosure keeps its file (rediseño v2, board V4-Difusion-Registro; decisión A11). A list of
 * signatures on paper is a means of its own: how many signed and the scanned sheet, both required; the other means may keep
 * a photo or a PDF. The file lives on the private disk; the record keeps its path. Still never changed nor deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disclosure_dissemination_records', function (Blueprint $table) {
            $table->string('medium', 20)->change();
            $table->string('evidence_path')->nullable();
            $table->unsignedInteger('signatures')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE disclosure_dissemination_records
                DROP CONSTRAINT disclosure_dissemination_records_medium_known,
                ADD CONSTRAINT disclosure_dissemination_records_medium_known CHECK (medium IN ('poster', 'bulletin', 'brochure', 'meeting', 'other', 'signed_list')),
                ADD CONSTRAINT disclosure_dissemination_records_signatures_of_a_list CHECK ((medium = 'signed_list') = (signatures IS NOT NULL) AND (signatures IS NULL OR signatures >= 1) AND (medium <> 'signed_list' OR evidence_path IS NOT NULL))
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE disclosure_dissemination_records
                DROP CONSTRAINT disclosure_dissemination_records_signatures_of_a_list,
                DROP CONSTRAINT disclosure_dissemination_records_medium_known,
                ADD CONSTRAINT disclosure_dissemination_records_medium_known CHECK (medium IN ('poster', 'bulletin', 'brochure', 'meeting', 'other'))
        SQL);

        Schema::table('disclosure_dissemination_records', function (Blueprint $table) {
            $table->dropColumn(['evidence_path', 'signatures']);
            $table->string('medium', 10)->change();
        });
    }
};
