<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statements of account per company and month, and the manual (SPEI) payments applied to them. Money is in integer cents.
 *
 * An issued statement and its lines are immutable: a trigger rejects any change, as audit_entries does. Whether it is paid
 * is never stored on it: it is derived from the allocations, so the snapshot stays as it was issued. A correction is
 * another statement that adjusts it. Payments and allocations are append-only too, apart from the one change a payment
 * allows: being reversed, with a reason. Nothing here is access: Tenancy keeps that.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->statements();
        $this->payments();
        $this->triggers();

        Schema::table('subscription_charges', function (Blueprint $table) {
            // The moment a one-time charge went onto a statement; a charge is billed once.
            $table->timestampTz('billed_at')->nullable();
        });

        DB::statement("ALTER TABLE subscription_charges ADD CONSTRAINT subscription_charges_only_one_time_billed CHECK (billed_at IS NULL OR kind = 'one_time')");
    }

    public function down(): void
    {
        Schema::table('subscription_charges', function (Blueprint $table) {
            $table->dropColumn('billed_at');
        });

        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('statement_lines');
        Schema::dropIfExists('statements');
        DB::unprepared('DROP FUNCTION IF EXISTS statements_immutable_once_issued(), statement_lines_immutable_once_issued(), payments_only_reversible(), payment_allocations_checked()');
    }

    private function statements(): void
    {
        Schema::create('statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('billing_account_id')->constrained('billing_accounts')->restrictOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            // Set on a statement that corrects another one; null on the statement of the period.
            $table->uuid('adjusts_statement_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20);
            $table->char('currency', 3);
            // The catalog version and the VAT rate the amounts were reached with.
            $table->string('catalog_version', 20);
            $table->unsignedSmallInteger('vat_percent');
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('vat_cents');
            $table->bigInteger('total_cents');
            // Everything the amounts came from: catalog version, plan, people, centers, discount basis, VAT, days.
            $table->jsonb('snapshot');
            $table->text('reason')->nullable();
            $table->timestampTz('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['billing_account_id', 'period_start']);
            $table->index('tenant_id');
        });

        Schema::table('statements', function (Blueprint $table) {
            $table->foreign('adjusts_statement_id')->references('id')->on('statements')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE statements
                ADD CONSTRAINT statements_status_known CHECK (status IN ('draft', 'issued')),
                ADD CONSTRAINT statements_period_ordered CHECK (period_end > period_start),
                ADD CONSTRAINT statements_total_adds_up CHECK (total_cents = subtotal_cents + vat_cents),
                ADD CONSTRAINT statements_issued_has_who CHECK (status <> 'issued' OR (issued_at IS NOT NULL AND issued_by IS NOT NULL)),
                ADD CONSTRAINT statements_regular_not_negative CHECK (adjusts_statement_id IS NOT NULL OR total_cents >= 0),
                ADD CONSTRAINT statements_adjustment_has_reason CHECK (adjusts_statement_id IS NULL OR (reason IS NOT NULL AND btrim(reason) <> ''))
        SQL);
        // One statement per company and month; an adjustment shares the month of the one it corrects.
        DB::statement('CREATE UNIQUE INDEX statements_one_per_company_period ON statements (tenant_id, period_start) WHERE adjusts_statement_id IS NULL');

        Schema::create('statement_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('statement_id')->constrained('statements')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('code', 30);
            $table->string('label', 200);
            $table->bigInteger('amount_cents');
            $table->text('explanation');
            $table->foreignUuid('charge_id')->nullable()->constrained('subscription_charges')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['statement_id', 'position']);
        });

        DB::statement("ALTER TABLE statement_lines ADD CONSTRAINT statement_lines_label_filled CHECK (btrim(label) <> '')");
        // A one-time charge goes onto one line, once.
        DB::statement("CREATE UNIQUE INDEX statement_lines_one_time_charge_once ON statement_lines (charge_id) WHERE code = 'charge_one_time'");
    }

    private function payments(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('billing_account_id')->constrained('billing_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('paid_on');
            $table->string('reference', 120);
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            // A wrong payment is reversed, never deleted: when, by whom and why.
            $table->timestampTz('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->index(['billing_account_id', 'paid_on']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payments
                ADD CONSTRAINT payments_amount_positive CHECK (amount_cents > 0),
                ADD CONSTRAINT payments_reference_filled CHECK (btrim(reference) <> ''),
                ADD CONSTRAINT payments_reversal_complete CHECK (
                    (reversed_at IS NULL AND reversed_by IS NULL AND reversal_reason IS NULL)
                    OR (reversed_at IS NOT NULL AND reversed_by IS NOT NULL AND reversal_reason IS NOT NULL AND btrim(reversal_reason) <> '')
                )
        SQL);
        // The same reference twice is a double entry; once reversed, its reference may be recorded again.
        DB::statement('CREATE UNIQUE INDEX payments_reference_unique ON payments (billing_account_id, reference) WHERE reversed_at IS NULL');

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignUuid('statement_id')->constrained('statements')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['payment_id', 'statement_id']);
            $table->index('statement_id');
        });

        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_amount_positive CHECK (amount_cents > 0)');
    }

    private function triggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION statements_immutable_once_issued() RETURNS trigger AS $$
            BEGIN
                IF OLD.status = 'issued' THEN
                    RAISE EXCEPTION 'an issued statement is immutable';
                END IF;
                IF TG_OP = 'UPDATE' AND NEW.status = 'issued'
                    AND COALESCE((SELECT SUM(amount_cents) FROM statement_lines WHERE statement_id = NEW.id), 0) <> NEW.total_cents THEN
                    RAISE EXCEPTION 'the lines of a statement must add up to its total';
                END IF;
                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER statements_immutable_once_issued
                BEFORE UPDATE OR DELETE ON statements
                FOR EACH ROW EXECUTE FUNCTION statements_immutable_once_issued();

            CREATE OR REPLACE FUNCTION statement_lines_immutable_once_issued() RETURNS trigger AS $$
            BEGIN
                IF (TG_OP <> 'INSERT' AND EXISTS (SELECT 1 FROM statements WHERE id = OLD.statement_id AND status = 'issued'))
                    OR (TG_OP <> 'DELETE' AND EXISTS (SELECT 1 FROM statements WHERE id = NEW.statement_id AND status = 'issued')) THEN
                    RAISE EXCEPTION 'the lines of an issued statement are immutable';
                END IF;
                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER statement_lines_immutable_once_issued
                BEFORE INSERT OR UPDATE OR DELETE ON statement_lines
                FOR EACH ROW EXECUTE FUNCTION statement_lines_immutable_once_issued();

            CREATE OR REPLACE FUNCTION payments_only_reversible() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'a payment is never deleted: reverse it';
                END IF;
                IF OLD.reversed_at IS NOT NULL
                    OR (to_jsonb(NEW) - 'reversed_at' - 'reversed_by' - 'reversal_reason') <> (to_jsonb(OLD) - 'reversed_at' - 'reversed_by' - 'reversal_reason') THEN
                    RAISE EXCEPTION 'a payment can only be reversed, once';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER payments_only_reversible
                BEFORE UPDATE OR DELETE ON payments
                FOR EACH ROW EXECUTE FUNCTION payments_only_reversible();

            CREATE OR REPLACE FUNCTION payment_allocations_checked() RETURNS trigger AS $$
            DECLARE
                payment payments%ROWTYPE;
                statement statements%ROWTYPE;
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'an allocation is never changed: reverse the payment';
                END IF;

                SELECT * INTO payment FROM payments WHERE id = NEW.payment_id FOR UPDATE;
                SELECT * INTO statement FROM statements WHERE id = NEW.statement_id FOR UPDATE;

                IF payment.reversed_at IS NOT NULL THEN
                    RAISE EXCEPTION 'a reversed payment cannot be applied';
                END IF;
                IF statement.status <> 'issued' THEN
                    RAISE EXCEPTION 'a payment is applied to an issued statement';
                END IF;
                IF statement.billing_account_id <> payment.billing_account_id THEN
                    RAISE EXCEPTION 'a payment is applied to a statement of its own account';
                END IF;
                IF (SELECT COALESCE(SUM(amount_cents), 0) FROM payment_allocations WHERE payment_id = payment.id) + NEW.amount_cents > payment.amount_cents THEN
                    RAISE EXCEPTION 'the payment is over-allocated';
                END IF;
                IF (SELECT COALESCE(SUM(a.amount_cents), 0) FROM payment_allocations a JOIN payments p ON p.id = a.payment_id
                        WHERE a.statement_id = statement.id AND p.reversed_at IS NULL) + NEW.amount_cents > statement.total_cents THEN
                    RAISE EXCEPTION 'the statement is over-paid';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER payment_allocations_checked
                BEFORE INSERT OR UPDATE OR DELETE ON payment_allocations
                FOR EACH ROW EXECUTE FUNCTION payment_allocations_checked();
        SQL);
    }
};
