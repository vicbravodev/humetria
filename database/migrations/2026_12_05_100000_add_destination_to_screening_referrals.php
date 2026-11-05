<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The channeling of a person, as the redesign records it (board V4-N035-Canalizacion, decisión A7): where they were sent from
 * a closed list (or that they did not want care), how they were told, a short note without clinical detail and the day to ask
 * how they are, once. The institution in free text stays for the records written before; new ones leave it empty. Never an
 * answer or a diagnosis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_referrals', function (Blueprint $table) {
            $table->string('destination', 30)->nullable();
            $table->string('told', 20)->nullable();
            $table->string('note', 200)->nullable();
            $table->date('remind_on')->nullable();
            $table->timestampTz('reminded_at')->nullable();
            $table->string('institution', 150)->nullable()->change();
        });

        // What was written before named an institution in free text and was told in person.
        DB::table('screening_referrals')->update(['destination' => 'other', 'told' => 'in_person']);

        Schema::table('screening_referrals', function (Blueprint $table) {
            $table->string('destination', 30)->nullable(false)->change();
            $table->string('told', 20)->nullable(false)->change();
            $table->index(['remind_on', 'reminded_at']);
        });

        DB::statement("ALTER TABLE screening_referrals ADD CONSTRAINT screening_referrals_destination_known CHECK (destination IN ('imss', 'company_medical', 'external_psychological', 'other', 'declined'))");
        DB::statement("ALTER TABLE screening_referrals ADD CONSTRAINT screening_referrals_told_known CHECK (told IN ('in_person', 'portal'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE screening_referrals DROP CONSTRAINT screening_referrals_told_known');
        DB::statement('ALTER TABLE screening_referrals DROP CONSTRAINT screening_referrals_destination_known');
        DB::table('screening_referrals')->whereNull('institution')->update(['institution' => '']);

        Schema::table('screening_referrals', function (Blueprint $table) {
            $table->dropIndex(['remind_on', 'reminded_at']);
            $table->dropColumn(['destination', 'told', 'note', 'remind_on', 'reminded_at']);
            $table->string('institution', 150)->nullable(false)->change();
        });
    }
};
