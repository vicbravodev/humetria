<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The channels a company talks through ("Canales de comunicación"): one row per company and channel. With no row a channel keeps
 * its default (mail on first, WhatsApp off second), so a company that existed before never loses its mail. Turning WhatsApp on
 * keeps who, when and which version of the words the owner accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_channels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('channel', 10);
            $table->boolean('enabled');
            $table->smallInteger('preference');
            $table->integer('monthly_cap')->nullable();
            $table->string('consent_wording_version', 20)->nullable();
            $table->foreignId('enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('enabled_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'channel']);
        });

        DB::statement("ALTER TABLE tenant_channels ADD CONSTRAINT tenant_channels_channel_known CHECK (channel IN ('mail', 'whatsapp'))");
        DB::statement('ALTER TABLE tenant_channels ADD CONSTRAINT tenant_channels_preference_known CHECK (preference BETWEEN 1 AND 2)');
        DB::statement("ALTER TABLE tenant_channels ADD CONSTRAINT tenant_channels_cap_only_whatsapp CHECK (monthly_cap IS NULL OR (channel = 'whatsapp' AND monthly_cap > 0))");
        DB::statement("ALTER TABLE tenant_channels ADD CONSTRAINT tenant_channels_whatsapp_consent CHECK (channel <> 'whatsapp' OR NOT enabled OR (consent_wording_version IS NOT NULL AND enabled_at IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_channels');
    }
};
