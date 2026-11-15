<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who pays whom, and for what: payer accounts (direct, or a consultant's), which company each one pays now and before, one
 * subscription per company and its extra charges. Nothing here is access: Tenancy keeps that. The checks repeat the
 * console's validation so no other path can store what the form would reject. Money is in integer cents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 20);
            $table->string('legal_name', 200);
            $table->string('rfc', 13)->unique('billing_accounts_rfc_unique');
            // What a CFDI will need when invoicing is connected.
            $table->string('tax_regime', 120)->nullable();
            $table->string('postal_code', 5)->nullable();
            $table->string('cfdi_use', 10)->nullable();
            $table->string('contact_name', 120);
            $table->string('contact_email', 255);
            $table->string('contact_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE billing_accounts
                ADD CONSTRAINT billing_accounts_kind_known CHECK (kind IN ('direct', 'consultant')),
                ADD CONSTRAINT billing_accounts_legal_name_filled CHECK (btrim(legal_name) <> ''),
                ADD CONSTRAINT billing_accounts_rfc_format CHECK (rfc ~ '^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$'),
                ADD CONSTRAINT billing_accounts_contact_filled CHECK (btrim(contact_name) <> '' AND btrim(contact_email) <> '')
        SQL);

        Schema::create('billing_account_tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('billing_account_id')->constrained('billing_accounts')->restrictOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestampsTz();
            $table->index(['billing_account_id', 'ends_on']);
        });

        DB::statement('ALTER TABLE billing_account_tenants ADD CONSTRAINT billing_account_tenants_dates_ordered CHECK (ends_on IS NULL OR ends_on >= starts_on)');
        // One current payer per company: moving a company closes one row and opens another, in one transaction.
        DB::statement('CREATE UNIQUE INDEX billing_account_tenants_one_current_payer ON billing_account_tenants (tenant_id) WHERE ends_on IS NULL');

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique('subscriptions_tenant_unique')->constrained('tenants')->cascadeOnDelete();
            $table->string('plan', 20);
            $table->string('profile', 20);
            $table->string('catalog_version', 20);
            $table->string('status', 20);
            $table->unsignedInteger('seats');
            $table->unsignedInteger('centers');
            $table->date('starts_on');
            $table->date('renews_on');
            $table->unsignedBigInteger('negotiated_price_cents')->nullable();
            $table->date('negotiated_valid_until')->nullable();
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_plan_known CHECK (plan IN ('jornada', 'cumplimiento', 'integral')),
                ADD CONSTRAINT subscriptions_profile_known CHECK (profile IN ('direct', 'channel_compliance', 'channel_integral', 'negotiated')),
                ADD CONSTRAINT subscriptions_status_known CHECK (status IN ('trial', 'active', 'past_due', 'cancel_scheduled', 'ended')),
                ADD CONSTRAINT subscriptions_seats_not_negative CHECK (seats >= 0),
                ADD CONSTRAINT subscriptions_centers_at_least_one CHECK (centers >= 1),
                ADD CONSTRAINT subscriptions_negotiated_price_not_negative CHECK (negotiated_price_cents IS NULL OR negotiated_price_cents >= 0),
                ADD CONSTRAINT subscriptions_negotiated_has_price CHECK (profile <> 'negotiated' OR negotiated_price_cents IS NOT NULL),
                ADD CONSTRAINT subscriptions_renews_after_start CHECK (renews_on > starts_on)
        SQL);

        Schema::create('subscription_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('description', 200);
            $table->string('scope', 200);
            $table->unsignedBigInteger('amount_cents');
            // The day a one-time charge is due, or the first day of a recurring one.
            $table->date('charge_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['subscription_id', 'charge_on']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE subscription_charges
                ADD CONSTRAINT subscription_charges_kind_known CHECK (kind IN ('recurring', 'one_time')),
                ADD CONSTRAINT subscription_charges_amount_positive CHECK (amount_cents > 0),
                ADD CONSTRAINT subscription_charges_description_filled CHECK (btrim(description) <> '' AND btrim(scope) <> ''),
                ADD CONSTRAINT subscription_charges_only_recurring_ends CHECK (ends_on IS NULL OR (kind = 'recurring' AND ends_on >= charge_on))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_charges');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('billing_account_tenants');
        Schema::dropIfExists('billing_accounts');
    }
};
