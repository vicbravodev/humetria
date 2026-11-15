<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A demo request can be worked: where it stands, an internal note, the first day someone handled it, and the company it
 * became. The company link has no cascade: if the company goes, the request stays and only the link is cleared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->string('status', 20)->default('new')->index();
            $table->text('note')->nullable();
            $table->timestampTz('handled_at')->nullable();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
        });

        DB::statement("ALTER TABLE demo_requests ADD CONSTRAINT demo_requests_status_valid CHECK (status IN ('new', 'contacted', 'converted', 'discarded'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE demo_requests DROP CONSTRAINT demo_requests_status_valid');

        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['status', 'note', 'handled_at']);
        });
    }
};
