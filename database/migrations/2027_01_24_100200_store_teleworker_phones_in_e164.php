<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A teleworker's contact phone stops being free text: it is kept in E.164 (`+524421234567`), of any country. The ones
 * there were Mexican: their last 10 digits get the +52.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE teleworkers SET contact_phone = '+52' || right(regexp_replace(contact_phone, '[^0-9]', '', 'g'), 10) WHERE contact_phone IS NOT NULL");
        DB::statement('ALTER TABLE teleworkers ALTER COLUMN contact_phone TYPE varchar(16)');
        DB::statement("ALTER TABLE teleworkers ADD CONSTRAINT teleworkers_contact_phone_e164 CHECK (contact_phone ~ '^\\+[1-9][0-9]{6,14}$')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE teleworkers DROP CONSTRAINT teleworkers_contact_phone_e164');
        DB::statement('ALTER TABLE teleworkers ALTER COLUMN contact_phone TYPE varchar(30)');
    }
};
