<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * NOM-035, complaints channel: the written notice of a traumatic event (6.5) must carry the name of who writes it and the day it
 * happened while the case lives; once its content is purged, the skeleton keeps neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE complaints
                DROP CONSTRAINT complaints_notice_has_name,
                DROP CONSTRAINT complaints_notice_has_date,
                ADD CONSTRAINT complaints_notice_has_name CHECK (type <> 'traumatic_event_notice' OR purged_on IS NOT NULL OR reporter_name IS NOT NULL),
                ADD CONSTRAINT complaints_notice_has_date CHECK (type <> 'traumatic_event_notice' OR purged_on IS NOT NULL OR occurred_on IS NOT NULL)
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE complaints
                DROP CONSTRAINT complaints_notice_has_name,
                DROP CONSTRAINT complaints_notice_has_date,
                ADD CONSTRAINT complaints_notice_has_name CHECK (type <> 'traumatic_event_notice' OR reporter_name IS NOT NULL),
                ADD CONSTRAINT complaints_notice_has_date CHECK (type <> 'traumatic_event_notice' OR occurred_on IS NOT NULL)
        SQL);
    }
};
