<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The equipment delivered to a teleworker (numerals 5.7 and 5.1 k, criterio C-22): each item with its date and the
 * person's receipt, or their refusal with the reason. Never edited or deleted once answered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('teleworker_id');
            $table->string('kind', 20);
            $table->text('description');
            $table->date('delivered_on');
            $table->string('status', 10);
            $table->text('refusal_reason')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->string('responded_ip', 45)->nullable();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['teleworker_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('teleworkers')->restrictOnDelete();
            $table->index(['tenant_id', 'teleworker_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE equipment_items
                ADD CONSTRAINT equipment_items_kind_known CHECK (kind IN ('chair', 'supplies', 'ergonomic_accessory', 'computer', 'other')),
                ADD CONSTRAINT equipment_items_status_known CHECK (status IN ('pending', 'received', 'refused')),
                ADD CONSTRAINT equipment_items_description_filled CHECK (btrim(description) <> ''),
                ADD CONSTRAINT equipment_items_refusal_has_reason CHECK ((status = 'refused') = (refusal_reason IS NOT NULL AND btrim(refusal_reason) <> '')),
                ADD CONSTRAINT equipment_items_answered_has_time CHECK ((status = 'pending') = (responded_at IS NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_items');
    }
};
