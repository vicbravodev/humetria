<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** A demo's WhatsApp may be of any country: still in E.164, which is what the Mexican ones already were. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE demo_requests DROP CONSTRAINT demo_requests_whatsapp_e164_mx');
        DB::statement('ALTER TABLE demo_requests ALTER COLUMN whatsapp TYPE varchar(16)');
        DB::statement("ALTER TABLE demo_requests ADD CONSTRAINT demo_requests_whatsapp_e164 CHECK (whatsapp ~ '^\\+[1-9][0-9]{6,14}$')");
    }

    /** Fails, before changing anything, while some WhatsApp is not Mexican. */
    public function down(): void
    {
        $abroad = DB::table('demo_requests')->where('whatsapp', 'not like', '+52%')->count();

        if ($abroad > 0) {
            throw new RuntimeException("Cannot go back to Mexican WhatsApp numbers only: {$abroad} demo requests have one of another country. Remove them first.");
        }

        DB::statement('ALTER TABLE demo_requests DROP CONSTRAINT demo_requests_whatsapp_e164');
        DB::statement('ALTER TABLE demo_requests ALTER COLUMN whatsapp TYPE varchar(13)');
        DB::statement("ALTER TABLE demo_requests ADD CONSTRAINT demo_requests_whatsapp_e164_mx CHECK (whatsapp ~ '^\\+52[0-9]{10}$')");
    }
};
