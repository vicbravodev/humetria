<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * NOM-035, complaints channel: who presents a complaint, when they choose to say so. A confidential complaint carries the
 * name (and, if the person wants, a contact) that only its handlers read; an anonymous one carries none. The written notice
 * of a severe traumatic event (6.5) always carries the name of who writes it, the date it happened and, if any, the people
 * involved. The texts are encrypted by the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE complaints
                ADD COLUMN reporter_name text,
                ADD COLUMN reporter_contact text,
                ADD COLUMN involved_text text,
                ADD CONSTRAINT complaints_name_only_when_confidential CHECK ((identity_mode = 'confidential') = (reporter_name IS NOT NULL)),
                ADD CONSTRAINT complaints_contact_only_when_confidential CHECK (reporter_contact IS NULL OR identity_mode = 'confidential'),
                ADD CONSTRAINT complaints_notice_has_name CHECK (type <> 'traumatic_event_notice' OR reporter_name IS NOT NULL),
                ADD CONSTRAINT complaints_notice_has_date CHECK (type <> 'traumatic_event_notice' OR occurred_on IS NOT NULL),
                ADD CONSTRAINT complaints_involved_only_in_notice CHECK (involved_text IS NULL OR type = 'traumatic_event_notice')
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE complaints
                DROP CONSTRAINT complaints_name_only_when_confidential,
                DROP CONSTRAINT complaints_contact_only_when_confidential,
                DROP CONSTRAINT complaints_notice_has_name,
                DROP CONSTRAINT complaints_notice_has_date,
                DROP CONSTRAINT complaints_involved_only_in_notice,
                DROP COLUMN reporter_name,
                DROP COLUMN reporter_contact,
                DROP COLUMN involved_text
        SQL);
    }
};
