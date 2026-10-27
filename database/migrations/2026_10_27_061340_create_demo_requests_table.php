<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Demo requests from the landing. Platform data, so no tenant_id. The checks repeat
 * the form's validation so no other path can store a value the form would reject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('whatsapp', 13);
            $table->string('company', 160);
            $table->string('profile', 20);
            $table->string('workforce', 20);
            $table->text('message')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            // The superadmin console lists the newest first.
            $table->rawIndex('created_at DESC', 'demo_requests_created_at_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE demo_requests
                ADD CONSTRAINT demo_requests_name_filled CHECK (btrim(name) <> ''),
                ADD CONSTRAINT demo_requests_email_filled CHECK (btrim(email) <> ''),
                ADD CONSTRAINT demo_requests_company_filled CHECK (btrim(company) <> ''),
                ADD CONSTRAINT demo_requests_whatsapp_e164_mx CHECK (whatsapp ~ '^\+52[0-9]{10}$'),
                ADD CONSTRAINT demo_requests_profile_valid CHECK (profile IN ('company', 'consultant')),
                ADD CONSTRAINT demo_requests_workforce_valid CHECK (workforce IN ('1_15', '16_50', '51_100', '101_500', '501_plus')),
                ADD CONSTRAINT demo_requests_message_length CHECK (char_length(message) <= 2000)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
