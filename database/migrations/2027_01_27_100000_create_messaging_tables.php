<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Messaging: what each message did (WhatsApp and mail), who asked not to be written to, and the provider events already seen.
 * Never a phone, never a body: only the HMAC of the phone. Every status of the history is kept with its exact time; only
 * aggregates are ever shown. The history is insert-only (a trigger), but the demo company's reset, the retention and a data
 * request (messaging:purge, messaging:forget) may delete.
 */
return new class extends Migration
{
    private const string STATUSES = "'created', 'sent', 'delayed', 'delivered', 'read', 'failed', 'bounced', 'complained', 'unknown', 'paused'";

    private const string CAUSES = "'invalid_token', 'template_not_approved', 'no_whatsapp_account', 'outside_window', 'rate_limited', 'server_error', 'timed_out', 'retries_exhausted', 'spam_limited', 'ecosystem_blocked', 'opted_out', 'invitation_closed', 'paused', 'disabled', 'unresolved', 'unknown_error'";

    public function up(): void
    {
        $this->deliveries();
        $this->deliveryEvents();
        $this->optOuts();
        $this->webhookEvents();
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_webhook_events');
        Schema::dropIfExists('messaging_opt_outs');
        Schema::dropIfExists('messaging_delivery_events');
        Schema::dropIfExists('messaging_deliveries');
        DB::unprepared('DROP FUNCTION IF EXISTS messaging_delivery_events_append_only()');
    }

    private function deliveries(): void
    {
        Schema::create('messaging_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('channel', 10);
            $table->string('template', 64)->nullable();
            $table->string('provider_message_id', 128)->nullable();
            $table->char('recipient_hash', 64)->nullable();
            $table->smallInteger('key_version')->nullable();
            $table->string('invitation_type', 40);
            $table->uuid('invitation_id');
            $table->string('source_type', 40);
            $table->uuid('source_id');
            $table->string('status', 12);
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['channel', 'provider_message_id']);
            $table->index(['tenant_id', 'source_type', 'source_id', 'channel']);
            $table->index('recipient_hash');
            $table->index(['status', 'updated_at']);
            $table->index(['invitation_type', 'invitation_id']);
            $table->index(['tenant_id', 'channel', 'accepted_at']);
            $table->index(['channel', 'accepted_at']);
        });

        $statuses = self::STATUSES;
        DB::statement("ALTER TABLE messaging_deliveries ADD CONSTRAINT messaging_deliveries_channel_check CHECK (channel IN ('mail', 'whatsapp'))");
        DB::statement("ALTER TABLE messaging_deliveries ADD CONSTRAINT messaging_deliveries_template_check CHECK (channel <> 'whatsapp' OR template IS NOT NULL)");
        DB::statement('ALTER TABLE messaging_deliveries ADD CONSTRAINT messaging_deliveries_key_version_check CHECK ((recipient_hash IS NULL) = (key_version IS NULL))');
        DB::statement("ALTER TABLE messaging_deliveries ADD CONSTRAINT messaging_deliveries_recipient_check CHECK (channel <> 'whatsapp' OR recipient_hash IS NOT NULL)");
        DB::statement("ALTER TABLE messaging_deliveries ADD CONSTRAINT messaging_deliveries_invitation_type_check CHECK (invitation_type IN ('campaign_participant', 'engagement_invitation'))");
        DB::statement("ALTER TABLE messaging_deliveries ADD CONSTRAINT messaging_deliveries_source_type_check CHECK (source_type IN ('campaign', 'engagement_survey'))");
        DB::statement("ALTER TABLE messaging_deliveries ADD CONSTRAINT messaging_deliveries_status_check CHECK (status IN ({$statuses}))");
    }

    private function deliveryEvents(): void
    {
        Schema::create('messaging_delivery_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('delivery_id');
            $table->string('status', 12);
            $table->string('failure_cause', 32)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->foreign(['delivery_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messaging_deliveries')->cascadeOnDelete();
            $table->index(['delivery_id', 'occurred_at']);
            $table->index(['status', 'occurred_at']);
        });

        $statuses = self::STATUSES;
        $causes = self::CAUSES;
        DB::statement("ALTER TABLE messaging_delivery_events ADD CONSTRAINT messaging_delivery_events_status_check CHECK (status IN ({$statuses}))");
        DB::statement("ALTER TABLE messaging_delivery_events ADD CONSTRAINT messaging_delivery_events_failure_cause_check CHECK (failure_cause IS NULL OR failure_cause IN ({$causes}))");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION messaging_delivery_events_append_only() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF EXISTS (SELECT 1 FROM tenants WHERE id = OLD.tenant_id AND is_demo) THEN
                        RETURN OLD;
                    END IF;
                    IF current_setting('messaging.purge', true) = 'on' THEN
                        RETURN OLD;
                    END IF;
                END IF;
                RAISE EXCEPTION 'messaging_delivery_events is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER messaging_delivery_events_no_update_delete
                BEFORE UPDATE OR DELETE ON messaging_delivery_events
                FOR EACH ROW EXECUTE FUNCTION messaging_delivery_events_append_only();

            CREATE TRIGGER messaging_delivery_events_no_truncate
                BEFORE TRUNCATE ON messaging_delivery_events
                FOR EACH STATEMENT EXECUTE FUNCTION messaging_delivery_events_append_only();
        SQL);
    }

    private function optOuts(): void
    {
        Schema::create('messaging_opt_outs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Null = a global opt-out, for every company.
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->char('recipient_hash', 64);
            $table->smallInteger('key_version');
            $table->string('source', 10);
            $table->timestampTz('created_at');

            $table->unique(['tenant_id', 'recipient_hash'])->nullsNotDistinct();
            $table->index('recipient_hash');
        });

        DB::statement("ALTER TABLE messaging_opt_outs ADD CONSTRAINT messaging_opt_outs_source_check CHECK (source IN ('stop', 'operator'))");
    }

    private function webhookEvents(): void
    {
        Schema::create('messaging_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 10);
            $table->string('event_id', 191);
            $table->timestampTz('received_at');
            // A status of Meta whose message does not exist yet (never the phone), to reprocess when its id is written.
            $table->jsonb('orphan')->nullable();
            $table->timestampTz('processed_at')->nullable();

            $table->unique(['provider', 'event_id']);
            $table->index('received_at');
        });

        DB::statement("ALTER TABLE messaging_webhook_events ADD CONSTRAINT messaging_webhook_events_provider_check CHECK (provider IN ('meta', 'resend'))");
        DB::statement('CREATE INDEX messaging_webhook_events_orphans ON messaging_webhook_events (received_at) WHERE orphan IS NOT NULL AND processed_at IS NULL');
    }
};
